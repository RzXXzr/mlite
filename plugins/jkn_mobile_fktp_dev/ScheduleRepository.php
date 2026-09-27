<?php
namespace Plugins\JKN_Mobile_FKTP_Dev;

require_once __DIR__.'/SchedulePolicy.php';
require_once __DIR__.'/ReferenceActivation.php';

/** Persistent schedule approvals. BPJS reference rows are snapshots; this class never calls BPJS. */
class ScheduleRepository
{
    const TABLE = 'mlite_jkn_mobile_fktp_dev_schedule';
    const APPROVAL_TTL = 1800;

    public static function install(\PDO $pdo)
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ".self::TABLE." (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            service_date date NOT NULL,
            candidate_id char(64) NOT NULL,
            kodepoli varchar(20) NOT NULL,
            namapoli varchar(100) NOT NULL DEFAULT '',
            kd_poli varchar(20) NOT NULL DEFAULT '',
            kodedokter varchar(20) NOT NULL,
            namadokter varchar(100) NOT NULL DEFAULT '',
            kd_dokter varchar(20) NOT NULL DEFAULT '',
            shift_name varchar(10) NOT NULL,
            jam_mulai time NOT NULL,
            jam_selesai time NOT NULL,
            quota int unsigned NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 0,
            source_url varchar(255) NOT NULL DEFAULT '',
            source_fingerprint char(64) NOT NULL,
            source_fetched_at datetime NOT NULL,
            saved_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_jkn_fktp_dev_schedule_candidate (service_date, candidate_id),
            KEY idx_jkn_fktp_dev_schedule_active (service_date, kodepoli, is_active),
            KEY idx_jkn_fktp_dev_schedule_doctor (service_date, kodedokter, jam_mulai, jam_selesai)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public static function tableExists(\PDO $pdo)
    {
        try { $pdo->query('SELECT 1 FROM '.self::TABLE.' LIMIT 1'); return true; }
        catch (\Throwable $e) { return false; }
    }

    /** Replace every supplied date in one transaction; a partial week is never committed. */
    public static function replaceBatch(\PDO $pdo, array $snapshots, array $selectedByDate, array $allowed, array $catalog, $fingerprint, $now = null)
    {
        $now = $now ?? time();
        if (!$snapshots || count($snapshots) > 7) { throw new \DomainException('Referensi harus berisi 1 sampai 7 tanggal.'); }
        $policy = SchedulePolicy::validate(array_values($allowed), [], $catalog);
        $destination = array_flip($policy['policies']);
        $dates = [];
        $records = [];
        $activeRules = [];
        foreach ($snapshots as $key => $snapshot) {
            if (!is_array($snapshot) || !SchedulePolicy::validDate((string) ($snapshot['date'] ?? '')) || (string) $key !== $snapshot['date']) {
                throw new \DomainException('Snapshot referensi tanggal tidak valid.');
            }
            if (!ReferenceActivation::fresh($snapshot, $fingerprint, self::APPROVAL_TTL, $now)) {
                throw new \DomainException('Referensi '.$snapshot['date'].' kedaluwarsa atau sumber Antrol berubah. Muat ulang referensi.');
            }
            $date = $snapshot['date'];
            $dates[] = $date;
            $selected = isset($selectedByDate[$date]) && is_array($selectedByDate[$date])
                ? array_values(array_unique(array_map('strval', $selectedByDate[$date]))) : [];
            $candidates = array_column($snapshot['rows'] ?? [], null, 'id');
            foreach ($selected as $id) {
                if (!isset($candidates[$id])) { throw new \DomainException('Pilihan jadwal '.$date.' tidak terdapat pada referensi BPJS.'); }
            }
            foreach ($candidates as $id => $row) {
                if (!isset($destination[$row['kodepoli']])) {
                    if (in_array($id, $selected, true)) { throw new \DomainException('Poli tujuan untuk kode BPJS '.$row['kodepoli'].' belum dipilih.'); }
                    continue;
                }
                $doctor = ReferenceActivation::doctor($row, $catalog);
                $active = in_array($id, $selected, true);
                if ($active && (!$doctor || (int) $row['quota'] < 1)) {
                    throw new \DomainException('Dokter terpilih pada '.$date.' belum mempunyai mapping unik/aktif atau kapasitasnya nol.');
                }
                foreach (['namapoli', 'namadokter'] as $name) {
                    if (mb_strlen((string) $row[$name]) > 100) { throw new \DomainException('Nama poli/dokter BPJS terlalu panjang.'); }
                }
                $record = [
                    'date'=>$date, 'id'=>(string) $id, 'kodepoli'=>(string) $row['kodepoli'], 'namapoli'=>(string) $row['namapoli'],
                    'kd_poli'=>(string) $destination[$row['kodepoli']], 'kodedokter'=>(string) $row['kodedokter'],
                    'namadokter'=>(string) $row['namadokter'], 'kd_dokter'=>$doctor ? (string) $doctor['kd_dokter'] : '',
                    'shift'=>(string) $row['shift'], 'start'=>(string) $row['start'], 'end'=>(string) $row['end'],
                    'quota'=>(int) $row['quota'], 'active'=>$active ? 1 : 0, 'source'=>(string) ($snapshot['source'] ?? ''),
                    'fingerprint'=>(string) $snapshot['fingerprint'], 'fetched'=>(int) $snapshot['fetched_at']
                ];
                $records[] = $record;
                if ($active) {
                    $activeRules[$date][] = ['kd_poli'=>$record['kd_poli'], 'kd_dokter'=>$record['kd_dokter'], 'date'=>$date,
                        'day'=>SchedulePolicy::day($date), 'shift'=>$record['shift'], 'start'=>$record['start'], 'end'=>$record['end'],
                        'quota'=>(string) $record['quota']];
                }
            }
            foreach ($snapshot['closures'] ?? [] as $closure) {
                $remote = (string) ($closure['kodepoli'] ?? '');
                if ($remote === '' || !isset($destination[$remote])) { continue; }
                $reason = trim((string) ($closure['reason'] ?? ''));
                if ($reason === '') { $reason = 'BPJS tidak menyediakan jadwal dokter pada tanggal ini.'; }
                $records[] = [
                    'date'=>$date, 'id'=>hash('sha256', $date.'|'.$remote.'|closed'), 'kodepoli'=>$remote,
                    'namapoli'=>(string) ($closure['namapoli'] ?? ''), 'kd_poli'=>(string) $destination[$remote],
                    'kodedokter'=>'', 'namadokter'=>mb_substr($reason, 0, 100), 'kd_dokter'=>'',
                    'shift'=>'tutup', 'start'=>'00:00', 'end'=>'00:00', 'quota'=>0, 'active'=>0,
                    'source'=>(string) ($snapshot['source'] ?? ''), 'fingerprint'=>(string) $snapshot['fingerprint'],
                    'fetched'=>(int) $snapshot['fetched_at']
                ];
            }
            $dayReason = trim((string) ($snapshot['day_reason'] ?? ''));
            if ($dayReason !== '' && empty($snapshot['rows']) && empty($snapshot['closures'])) {
                $records[] = [
                    'date'=>$date, 'id'=>hash('sha256', $date.'|*|closed'), 'kodepoli'=>'*', 'namapoli'=>'Semua poli',
                    'kd_poli'=>'', 'kodedokter'=>'', 'namadokter'=>mb_substr($dayReason, 0, 100), 'kd_dokter'=>'',
                    'shift'=>'tutup', 'start'=>'00:00', 'end'=>'00:00', 'quota'=>0, 'active'=>0,
                    'source'=>(string) ($snapshot['source'] ?? ''), 'fingerprint'=>(string) $snapshot['fingerprint'],
                    'fetched'=>(int) $snapshot['fetched_at']
                ];
            }
        }
        // Conflicts are date-scoped. Validate each date independently so a valid
        // seven-day batch is not rejected by the legacy 80-row single-form ceiling.
        foreach ($dates as $date) {
            SchedulePolicy::validate(array_values($allowed), $activeRules[$date] ?? [], $catalog);
        }

        $ownTransaction = !$pdo->inTransaction();
        try {
            if ($ownTransaction) { $pdo->beginTransaction(); }
            $marks = implode(',', array_fill(0, count($dates), '?'));
            $delete = $pdo->prepare('DELETE FROM '.self::TABLE.' WHERE service_date IN ('.$marks.')');
            $delete->execute($dates);
            $insert = $pdo->prepare('INSERT INTO '.self::TABLE.' (service_date,candidate_id,kodepoli,namapoli,kd_poli,kodedokter,namadokter,kd_dokter,shift_name,jam_mulai,jam_selesai,quota,is_active,source_url,source_fingerprint,source_fetched_at,saved_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $savedAt = date('Y-m-d H:i:s', $now);
            foreach ($records as $r) {
                $insert->execute([$r['date'],$r['id'],$r['kodepoli'],$r['namapoli'],$r['kd_poli'],$r['kodedokter'],$r['namadokter'],$r['kd_dokter'],$r['shift'],$r['start'].':00',$r['end'].':00',$r['quota'],$r['active'],$r['source'],$r['fingerprint'],date('Y-m-d H:i:s',$r['fetched']),$savedAt]);
            }
            if ($ownTransaction) { $pdo->commit(); }
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
        return ['dates'=>count($dates), 'candidates'=>count($records),
            'active'=>array_sum(array_map('count', $activeRules))];
    }

    public static function rowsForDate(\PDO $pdo, $date)
    {
        $stmt = $pdo->prepare('SELECT id,service_date,candidate_id,kodepoli,namapoli,kd_poli,kodedokter,namadokter,kd_dokter,shift_name,jam_mulai,jam_selesai,quota,is_active,source_url,source_fetched_at,saved_at FROM '.self::TABLE.' WHERE service_date=? ORDER BY kodepoli,jam_mulai,kodedokter');
        $stmt->execute([$date]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Update only local quotas for already-approved rows; never contacts or rewrites BPJS reference identity. */
    public static function updateCapacities(\PDO $pdo, $startDate, $endDate, array $input, $now = null)
    {
        if (!SchedulePolicy::validDate((string) $startDate) || !SchedulePolicy::validDate((string) $endDate)
            || $endDate < $startDate || count($input) < 1 || count($input) > 200) {
            throw new \DomainException('Rentang atau jumlah kapasitas jadwal tidak valid.');
        }
        $quotas = [];
        foreach ($input as $id => $quota) {
            $id = (string) $id;
            $quota = is_scalar($quota) ? trim((string) $quota) : '';
            if (!ctype_digit($id) || (int) $id < 1 || !ctype_digit($quota) || (int) $quota < 1 || (int) $quota > 999) {
                throw new \DomainException('Kapasitas harus berupa angka 1–999 untuk seluruh jadwal aktif.');
            }
            $quotas[(int) $id] = (int) $quota;
        }
        $ids = array_keys($quotas);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $select = $pdo->prepare('SELECT id FROM '.self::TABLE.' WHERE id IN ('.$marks.') AND service_date BETWEEN ? AND ? AND is_active=1 AND shift_name IN (?,?)');
        $select->execute(array_merge($ids, [$startDate, $endDate, 'pagi', 'sore']));
        $found = array_map('intval', $select->fetchAll(\PDO::FETCH_COLUMN));
        sort($found);
        $expected = $ids;
        sort($expected);
        if ($found !== $expected) {
            throw new \DomainException('Ada jadwal yang tidak aktif, berada di luar minggu, atau sudah berubah. Muat ulang halaman.');
        }
        $ownTransaction = !$pdo->inTransaction();
        try {
            if ($ownTransaction) { $pdo->beginTransaction(); }
            $update = $pdo->prepare('UPDATE '.self::TABLE.' SET quota=?, saved_at=? WHERE id=? AND service_date BETWEEN ? AND ? AND is_active=1');
            $savedAt = date('Y-m-d H:i:s', $now ?? time());
            foreach ($quotas as $id => $quota) {
                $update->execute([$quota, $savedAt, $id, $startDate, $endDate]);
                if ($update->rowCount() > 1) { throw new \RuntimeException('Pembaruan kapasitas menyentuh lebih dari satu jadwal.'); }
            }
            if ($ownTransaction) { $pdo->commit(); }
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
        return count($quotas);
    }

    /**
     * Switch an approved day/shift to another candidate from the stored BPJS snapshot and update quota.
     * This never fetches BPJS and never moves existing bookings to the replacement doctor.
     */
    public static function updateAssignments(\PDO $pdo, $startDate, $endDate, array $assignments, array $quotaInput, array $catalog, $now = null)
    {
        if (!SchedulePolicy::validDate((string) $startDate) || !SchedulePolicy::validDate((string) $endDate)
            || $endDate < $startDate || !$assignments || count($assignments) > 200
            || count($assignments) !== count($quotaInput)) {
            throw new \DomainException('Pilihan dokter atau rentang jadwal mingguan tidak valid.');
        }
        $requested = [];
        foreach ($assignments as $sourceId => $targetId) {
            $sourceId = is_scalar($sourceId) ? trim((string) $sourceId) : '';
            $targetId = is_scalar($targetId) ? trim((string) $targetId) : '';
            $quota = $quotaInput[$sourceId] ?? null;
            $quota = is_scalar($quota) ? trim((string) $quota) : '';
            if (!ctype_digit($sourceId) || (int) $sourceId < 1 || !ctype_digit($targetId) || (int) $targetId < 1
                || !ctype_digit($quota) || (int) $quota < 1 || (int) $quota > 999) {
                throw new \DomainException('Dokter harus berasal dari pilihan yang tersedia dan kapasitas harus 1–999.');
            }
            $requested[(int) $sourceId] = ['target'=>(int) $targetId, 'quota'=>(int) $quota];
        }

        $ownTransaction = !$pdo->inTransaction();
        try {
            if ($ownTransaction) { $pdo->beginTransaction(); }
            $lock = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $pdo->prepare('SELECT id,service_date,kodepoli,kd_poli,kodedokter,namadokter,kd_dokter,shift_name,jam_mulai,jam_selesai,quota,is_active FROM '.self::TABLE.' WHERE service_date BETWEEN ? AND ? ORDER BY service_date,id'.$lock);
            $stmt->execute([$startDate, $endDate]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $byId = [];
            foreach ($rows as $row) { $byId[(int) $row['id']] = $row; }

            $groups = [];
            $targets = [];
            foreach ($requested as $sourceId => $choice) {
                $source = $byId[$sourceId] ?? null;
                $target = $byId[$choice['target']] ?? null;
                if (!$source || !(int) $source['is_active'] || !in_array($source['shift_name'], ['pagi', 'sore'], true)) {
                    throw new \DomainException('Jadwal aktif sudah berubah. Muat ulang halaman sebelum menyimpan.');
                }
                if (!$target || $target['service_date'] !== $source['service_date']
                    || $target['kodepoli'] !== $source['kodepoli'] || $target['kd_poli'] !== $source['kd_poli']
                    || $target['shift_name'] !== $source['shift_name'] || $target['kodedokter'] === ''
                    || $target['kd_dokter'] === '') {
                    throw new \DomainException('Dokter pengganti bukan kandidat BPJS pada poli, tanggal, dan shift yang sama.');
                }
                $mapping = array_values(array_filter($catalog['doctors'] ?? [], function ($doctor) use ($target) {
                    return (string) ($doctor['kd_dokter'] ?? '') === (string) $target['kd_dokter']
                        && (string) ($doctor['kd_dokter_pcare'] ?? '') === (string) $target['kodedokter']
                        && (string) ($doctor['status'] ?? '') === '1';
                }));
                $remoteMappings = array_values(array_filter($catalog['doctors'] ?? [], function ($doctor) use ($target) {
                    return (string) ($doctor['kd_dokter_pcare'] ?? '') === (string) $target['kodedokter']
                        && (string) ($doctor['status'] ?? '') === '1';
                }));
                if (count($mapping) !== 1 || count($remoteMappings) !== 1) {
                    throw new \DomainException('Mapping dokter pengganti tidak lagi unik atau aktif. Perbarui Mapping Dokter terlebih dahulu.');
                }
                $group = self::assignmentGroup($source);
                if (isset($groups[$group])) { throw new \DomainException('Satu shift dikirim lebih dari sekali. Muat ulang halaman.'); }
                $groups[$group] = true;
                $target['quota'] = $choice['quota'];
                $target['is_active'] = 1;
                $targets[$group] = $target;
            }

            $final = [];
            foreach ($rows as $row) {
                if (!(int) $row['is_active'] || !in_array($row['shift_name'], ['pagi', 'sore'], true)) { continue; }
                $group = self::assignmentGroup($row);
                if (!isset($groups[$group])) { $final[] = $row; }
            }
            foreach ($targets as $target) { $final[] = $target; }
            self::assertNoAssignmentConflicts($final);

            $disable = $pdo->prepare('UPDATE '.self::TABLE.' SET is_active=0,saved_at=? WHERE service_date=? AND kodepoli=? AND kd_poli=? AND shift_name=?');
            $enable = $pdo->prepare('UPDATE '.self::TABLE.' SET is_active=1,quota=?,saved_at=? WHERE id=? AND service_date BETWEEN ? AND ?');
            $savedAt = date('Y-m-d H:i:s', $now ?? time());
            foreach ($targets as $target) {
                $disable->execute([$savedAt, $target['service_date'], $target['kodepoli'], $target['kd_poli'], $target['shift_name']]);
                $enable->execute([(int) $target['quota'], $savedAt, (int) $target['id'], $startDate, $endDate]);
            }
            if ($ownTransaction) { $pdo->commit(); }
            return count($targets);
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
    }

    private static function assignmentGroup(array $row)
    {
        return $row['service_date'].'|'.$row['kodepoli'].'|'.$row['kd_poli'].'|'.$row['shift_name'];
    }

    private static function assertNoAssignmentConflicts(array $rows)
    {
        $count = count($rows);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $left = $rows[$i]; $right = $rows[$j];
                if ($left['service_date'] !== $right['service_date']) { continue; }
                $overlap = substr($left['jam_mulai'], 0, 5) < substr($right['jam_selesai'], 0, 5)
                    && substr($right['jam_mulai'], 0, 5) < substr($left['jam_selesai'], 0, 5);
                if (!$overlap) { continue; }
                if ($left['kodepoli'] === $right['kodepoli'] && $left['kd_poli'] === $right['kd_poli']) {
                    throw new \DomainException('Pilihan dokter membuat jadwal beririsan pada poli yang sama.');
                }
                if ($left['kd_dokter'] !== '' && $left['kd_dokter'] === $right['kd_dokter']) {
                    throw new \DomainException('Dokter pengganti bertugas bersamaan pada poli lain.');
                }
            }
        }
    }

    public static function activeDates(\PDO $pdo, $fromDate)
    {
        $stmt = $pdo->prepare('SELECT service_date, SUM(is_active) active_count, COUNT(*) candidate_count, MAX(saved_at) saved_at FROM '.self::TABLE.' WHERE service_date>=? GROUP BY service_date ORDER BY service_date LIMIT 90');
        $stmt->execute([$fromDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function acceptedPolis(\PDO $pdo, $fromDate)
    {
        $stmt = $pdo->prepare('SELECT DISTINCT kd_poli FROM '.self::TABLE.' WHERE service_date>=? AND is_active=1 AND kd_poli<>?');
        $stmt->execute([$fromDate, '']);
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    public static function schedules(\PDO $pdo, array $catalog, $date, $bpjs = null)
    {
        $rows = self::rowsForDate($pdo, $date);
        $result = [];
        foreach ($rows as $row) {
            if (!(int) $row['is_active'] || ($bpjs !== null && (string) $row['kodepoli'] !== (string) $bpjs)) { continue; }
            $polis = array_values(array_filter($catalog['polis'], function ($p) use ($row) {
                return (string) $p['kd_poli'] === $row['kd_poli'] && (string) $p['kd_poli_pcare'] === $row['kodepoli']
                    && (string) $p['status'] === '1' && !SchedulePolicy::referral($p['kd_poli'], $p['nm_poli']);
            }));
            $doctors = array_values(array_filter($catalog['doctors'], function ($d) use ($row) {
                return (string) $d['kd_dokter'] === $row['kd_dokter'] && (string) $d['kd_dokter_pcare'] === $row['kodedokter'] && (string) $d['status'] === '1';
            }));
            $sameRemote = array_filter($catalog['doctors'], function ($d) use ($row) {
                return (string) $d['kd_dokter_pcare'] === $row['kodedokter'] && (string) $d['status'] === '1';
            });
            if (count($polis) !== 1 || count($doctors) !== 1 || count($sameRemote) !== 1) { continue; }
            $result[] = ['kd_poli'=>$row['kd_poli'], 'kd_dokter'=>$row['kd_dokter'], 'kd_poli_pcare'=>$row['kodepoli'],
                'kd_dokter_pcare'=>$row['kodedokter'], 'nm_poli'=>$polis[0]['nm_poli'], 'nm_dokter'=>$doctors[0]['nm_dokter'],
                'hari_kerja'=>SchedulePolicy::day($date), 'jam_mulai'=>$row['jam_mulai'], 'jam_selesai'=>$row['jam_selesai'],
                'jampraktek'=>substr($row['jam_mulai'],0,5).'-'.substr($row['jam_selesai'],0,5),
                'kuota'=>(int) $row['quota'], 'shift'=>$row['shift_name'], 'tanggal'=>$date];
        }
        return $result;
    }

    public static function replacementMessage(\PDO $pdo, $date, $kodepoli, $requestedDoctor, $practice)
    {
        $start = substr((string) $practice, 0, 5);
        $end = substr((string) $practice, 6, 5);
        $shift = $start < '12:00' ? 'pagi' : 'sore';
        $closed = $pdo->prepare('SELECT namadokter FROM '.self::TABLE.' WHERE service_date=? AND (kodepoli=? OR kodepoli=?) AND shift_name=? ORDER BY (kodepoli=?) DESC LIMIT 1');
        $closed->execute([$date, $kodepoli, '*', 'tutup', $kodepoli]);
        $closedReason = trim((string) $closed->fetchColumn());
        if ($closedReason !== '') {
            return 'Pendaftaran online ditutup pada tanggal '.$date.'. BPJS tidak menyediakan jadwal dokter; kemungkinan hari libur/tanggal merah atau jadwal belum tersedia. Keterangan BPJS: '.$closedReason;
        }
        $requested = $pdo->prepare('SELECT is_active FROM '.self::TABLE.' WHERE service_date=? AND kodepoli=? AND kodedokter=? AND jam_mulai=? AND jam_selesai=? LIMIT 1');
        $requested->execute([$date, $kodepoli, $requestedDoctor, $start.':00', $end.':00']);
        $requestedActive = $requested->fetchColumn();
        if ($requestedActive === false) {
            return 'Dokter atau jam praktik tidak terdapat pada referensi BPJS untuk tanggal yang dipilih.';
        }
        $stmt = $pdo->prepare('SELECT namadokter,kodedokter,jam_mulai,jam_selesai FROM '.self::TABLE.' WHERE service_date=? AND kodepoli=? AND shift_name=? AND is_active=1 ORDER BY jam_mulai,kodedokter LIMIT 1');
        $stmt->execute([$date, $kodepoli, $shift]);
        $active = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$active) { return 'Dokter dan jadwal tersebut tidak menerima pendaftaran online pada tanggal yang dipilih.'; }
        $name = trim((string) $active['namadokter']) ?: 'dokter kode '.$active['kodedokter'];
        $time = substr($active['jam_mulai'], 0, 5).'-'.substr($active['jam_selesai'], 0, 5);
        if ((string) $active['kodedokter'] === (string) $requestedDoctor && $time === $practice) {
            return 'Jadwal terpilih aktif tetapi mapping dokter/poli lokal tidak lagi valid. Silakan hubungi administrator.';
        }
        return 'Jadwal dokter yang dipilih tidak diaktifkan. Pendaftaran online pada shift ini dilayani oleh '.$name.' ('.$time.'). Silakan pilih dokter tersebut di Mobile JKN.';
    }
}
