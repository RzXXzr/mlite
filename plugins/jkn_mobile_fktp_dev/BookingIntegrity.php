<?php
namespace Plugins\JKN_Mobile_FKTP_Dev;

/** Keeps the Dev booking marker consistent without coupling Rawat Jalan to this module. */
final class BookingIntegrity
{
    private const BOOKING_TABLE = 'mlite_jkn_mobile_fktp_dev_booking';
    private const LOG_TABLE = 'mlite_jkn_mobile_fktp_dev_log';

    public static function cleanupOrphans(\PDO $pdo)
    {
        if (!self::tableExists($pdo, self::BOOKING_TABLE) || !self::tableExists($pdo, 'reg_periksa')) {
            return 0;
        }

        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) { $pdo->beginTransaction(); }
        try {
            $orphans = $pdo->query(
                'SELECT b.no_rawat FROM '.self::BOOKING_TABLE.' b ' .
                'LEFT JOIN reg_periksa r ON r.no_rawat = b.no_rawat ' .
                'WHERE r.no_rawat IS NULL ORDER BY b.no_rawat ASC'
            )->fetchAll(\PDO::FETCH_COLUMN);

            if (!$orphans) {
                if ($ownTransaction) { $pdo->commit(); }
                return 0;
            }

            $hasLog = self::tableExists($pdo, self::LOG_TABLE);
            $delete = $pdo->prepare(
                'DELETE FROM '.self::BOOKING_TABLE.' WHERE no_rawat = ? ' .
                'AND NOT EXISTS (SELECT 1 FROM reg_periksa r WHERE r.no_rawat = ?)'
            );
            $log = $hasLog ? $pdo->prepare(
                'INSERT INTO '.self::LOG_TABLE.' ' .
                '(request_id, action, endpoint, method, no_rawat, metadata_code, http_code, outcome, message, created_at) ' .
                'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            ) : null;
            $deleted = 0;
            foreach ($orphans as $noRawat) {
                $delete->execute([$noRawat, $noRawat]);
                if ($delete->rowCount() !== 1) { continue; }
                $deleted++;
                if ($log) {
                    $log->execute([
                        substr(hash('sha256', 'orphan|'.$noRawat.'|'.microtime(true)), 0, 24),
                        'integrity_cleanup', '/integrity/booking', 'SYSTEM', substr((string) $noRawat, 0, 25),
                        200, 200, 'success', 'Marker booking Dev yatim dibersihkan setelah reg_periksa tidak ditemukan.',
                        date('Y-m-d H:i:s')
                    ]);
                }
            }
            if ($ownTransaction) { $pdo->commit(); }
            return $deleted;
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
            throw $e;
        }
    }

    private static function tableExists(\PDO $pdo, $table)
    {
        try {
            $pdo->query('SELECT 1 FROM '.$table.' WHERE 1 = 0');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
