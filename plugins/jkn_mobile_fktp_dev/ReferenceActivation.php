<?php
namespace Plugins\JKN_Mobile_FKTP_Dev;

require_once __DIR__.'/SchedulePolicy.php';

class ReferenceActivation
{
    const APPROVAL_TTL = 900;
    const ACTIVE_TTL = 86400;

    public static function fresh(array $snapshot, $fingerprint, $ttl, $now = null)
    {
        $now = $now ?? time();
        return is_int($snapshot['fetched_at'] ?? null) && $snapshot['fetched_at'] <= $now
            && $now - $snapshot['fetched_at'] < $ttl && is_string($snapshot['fingerprint'] ?? null)
            && hash_equals($snapshot['fingerprint'], $fingerprint);
    }

    public static function doctor(array $row, array $catalog)
    {
        $matches = array_values(array_filter($catalog['doctors'], function ($doctor) use ($row) {
            return (string) $doctor['kd_dokter_pcare'] === $row['kodedokter'] && (string) $doctor['status'] === '1';
        }));
        if (count($matches) !== 1) { return null; }
        $local = $matches[0]['kd_dokter'];
        $localCount = count(array_filter($catalog['doctors'], function ($d) use ($local) { return $d['kd_dokter'] === $local; }));
        return $localCount === 1 ? $matches[0] : null;
    }

    /** Build rules from trusted session snapshot, never client-supplied times or quotas. */
    public static function approve(array $current, array $snapshot, array $selected, array $allowed, array $catalog, $fingerprint, $now = null)
    {
        $now = $now ?? time();
        if (!self::fresh($snapshot, $fingerprint, self::APPROVAL_TTL, $now)) {
            throw new \DomainException('Referensi kedaluwarsa atau pengaturan PCare berubah. Ambil ulang referensi BPJS.');
        }
        if (count($selected) > 80) { throw new \DomainException('Maksimal 80 jadwal aktif.'); }
        $policy = SchedulePolicy::validate($allowed, [], $catalog);
        $destinations = array_flip($policy['policies']);
        $candidates = array_column($snapshot['rows'], null, 'id');
        $rules = [];
        $dates = [];
        if (($current['source_mode'] ?? '') === 'bpjs_reference') {
            foreach ($current['bpjs_dates'] ?? [] as $date => $saved) {
                if ($date !== $snapshot['date'] && $date >= date('Y-m-d', $now)) { $dates[$date] = $saved; }
            }
            foreach ($current['rules'] as $rule) {
                if (isset($dates[$rule['date']])) {
                    $mapped = self::doctor(['kodedokter'=>$rule['kodedokter']], $catalog);
                    if (!$mapped || $mapped['kd_dokter'] !== $rule['kd_dokter']) {
                        throw new \DomainException('Mapping dokter pada tanggal aktif lain berubah. Perbarui/tutup tanggal tersebut lebih dahulu.');
                    }
                    $rules[] = $rule;
                }
            }
        }
        foreach ($selected as $id) {
            if (!is_string($id) || !isset($candidates[$id])) { throw new \DomainException('Pilihan jadwal tidak ada pada referensi BPJS.'); }
            $r = $candidates[$id];
            $doctor = self::doctor($r, $catalog);
            if (!$doctor || !isset($destinations[$r['kodepoli']]) || $r['quota'] < 1) {
                throw new \DomainException('Pilih poli tujuan yang diizinkan dan pastikan dokter terpetakan unik/aktif serta kapasitas tersedia.');
            }
            $rules[] = ['kd_poli'=>(string) $destinations[$r['kodepoli']], 'kd_dokter'=>(string) $doctor['kd_dokter'],
                'date'=>$snapshot['date'], 'day'=>SchedulePolicy::day($snapshot['date']), 'shift'=>$r['shift'],
                'start'=>$r['start'], 'end'=>$r['end'], 'quota'=>(string) $r['quota']];
        }
        $validated = SchedulePolicy::validate($allowed, $rules, $catalog);
        // Saved rules on other dates must not silently inherit changed mappings.
        foreach ($current['rules'] as $old) {
            if (isset($dates[$old['date']]) && (($validated['policies'][$old['kd_poli']] ?? '') !== $old['kodepoli'])) {
                throw new \DomainException('Tujuan poli masih digunakan pada tanggal lain. Tutup tanggal tersebut sebelum mengubah tujuan.');
            }
        }
        $snapshot['selected'] = array_values($selected);
        unset($snapshot['nonce']);
        $dates[$snapshot['date']] = $snapshot;
        $validated['source_mode'] = 'bpjs_reference';
        $validated['bpjs_dates'] = $dates;
        return $validated;
    }
}
