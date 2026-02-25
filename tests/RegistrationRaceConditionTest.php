<?php
/**
 * Unit tests for race condition fixes in registration.
 *
 * Tests cover:
 * 1. setNoRawatSafe() generates unique no_rawat under concurrent access
 * 2. setNoRegSafe() generates unique no_reg under concurrent access
 * 3. Transaction isolation prevents duplicate registration
 * 4. FOR UPDATE locking prevents SELECT MAX race condition
 *
 * These tests use REAL database transactions to verify locking behavior.
 * They create and clean up test data in `reg_periksa`.
 */

use PHPUnit\Framework\TestCase;

class RegistrationRaceConditionTest extends TestCase
{
    /** @var \PDO */
    private static $pdo;

    /** @var string Test date used for all test registrations */
    private static $testDate = '2099-12-31';

    /** @var string Test poli code */
    private static $testPoli = 'P001';

    /** @var string Test doctor code */
    private static $testDokter = 'DR001';

    public static function setUpBeforeClass(): void
    {
        // Connect to MySQL
        $dsn = 'mysql:host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4';
        self::$pdo = new \PDO($dsn, DBUSER, DBPASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);

        // Clean up any leftover test data
        self::cleanTestData();
    }

    public static function tearDownAfterClass(): void
    {
        // Reconnect if connection was lost (e.g. after pcntl_fork)
        try {
            self::$pdo->query("SELECT 1");
        } catch (\Exception $e) {
            $dsn = 'mysql:host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4';
            self::$pdo = new \PDO($dsn, DBUSER, DBPASS, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]);
        }
        self::cleanTestData();
        self::$pdo = null;
    }

    protected function setUp(): void
    {
        self::cleanTestData();
    }

    protected function tearDown(): void
    {
        // Rollback any lingering transactions
        if (self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
        self::cleanTestData();
    }

    private static function cleanTestData(): void
    {
        self::$pdo->exec("DELETE FROM reg_periksa WHERE tgl_registrasi = '" . self::$testDate . "'");
    }

    // =========================================================================
    // Helper: mimics Main::setNoRawatSafe()
    // =========================================================================
    private function setNoRawatSafe(\PDO $pdo, string $date): string
    {
        $stmt = $pdo->prepare(
            "SELECT IFNULL(MAX(CONVERT(RIGHT(no_rawat, 6), SIGNED)), 0) AS max_no
             FROM reg_periksa WHERE tgl_registrasi = ? FOR UPDATE"
        );
        $stmt->execute([$date]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $urut = ((int) $row['max_no']) + 1;
        return str_replace('-', '/', $date) . '/' . sprintf('%06d', $urut);
    }

    // =========================================================================
    // Helper: mimics Main::setNoRegSafe()
    // =========================================================================
    private function setNoRegSafe(\PDO $pdo, string $kd_dokter, string $kd_poli, string $date): string
    {
        $stmt = $pdo->prepare(
            "SELECT IFNULL(MAX(CONVERT(RIGHT(no_reg, 3), SIGNED)), 0) AS max_no
             FROM reg_periksa WHERE kd_poli = ? AND tgl_registrasi = ? FOR UPDATE"
        );
        $stmt->execute([$kd_poli, $date]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $urut = ((int) $row['max_no']) + 1;
        return sprintf('%03d', $urut);
    }

    // =========================================================================
    // Helper: Insert a test registration row
    // =========================================================================
    private function insertRegPeriksa(\PDO $pdo, string $no_rawat, string $no_reg, string $no_rkm_medis = '000001'): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO reg_periksa
             (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis,
              kd_poli, p_jawab, almt_pj, hubunganpj, biaya_reg, stts,
              stts_daftar, status_lanjut, kd_pj, umurdaftar, sttsumur,
              status_bayar, status_poli)
             VALUES (?, ?, ?, ?, ?, ?, ?, '-', '-', '-', 0, 'Belum', 'Baru',
                     'Ralan', 'BPJ', 30, 'Th', 'Belum Bayar', 'Baru')"
        );
        $stmt->execute([
            $no_reg,
            $no_rawat,
            self::$testDate,
            date('H:i:s'),
            self::$testDokter,
            $no_rkm_medis,
            self::$testPoli
        ]);
    }

    // =========================================================================
    // TEST 1: setNoRawatSafe generates sequential numbers
    // =========================================================================
    public function testSetNoRawatSafeSequential(): void
    {
        $pdo = self::$pdo;

        // First registration
        $pdo->beginTransaction();
        $no_rawat_1 = $this->setNoRawatSafe($pdo, self::$testDate);
        $no_reg_1 = $this->setNoRegSafe($pdo, self::$testDokter, self::$testPoli, self::$testDate);
        $this->insertRegPeriksa($pdo, $no_rawat_1, $no_reg_1, '000001');
        $pdo->commit();

        // Second registration
        $pdo->beginTransaction();
        $no_rawat_2 = $this->setNoRawatSafe($pdo, self::$testDate);
        $no_reg_2 = $this->setNoRegSafe($pdo, self::$testDokter, self::$testPoli, self::$testDate);
        $this->insertRegPeriksa($pdo, $no_rawat_2, $no_reg_2, '000002');
        $pdo->commit();

        // Third registration
        $pdo->beginTransaction();
        $no_rawat_3 = $this->setNoRawatSafe($pdo, self::$testDate);
        $no_reg_3 = $this->setNoRegSafe($pdo, self::$testDokter, self::$testPoli, self::$testDate);
        $this->insertRegPeriksa($pdo, $no_rawat_3, $no_reg_3, '000003');
        $pdo->commit();

        // Verify all are unique and sequential
        $expected_date = str_replace('-', '/', self::$testDate);
        $this->assertEquals($expected_date . '/000001', $no_rawat_1);
        $this->assertEquals($expected_date . '/000002', $no_rawat_2);
        $this->assertEquals($expected_date . '/000003', $no_rawat_3);

        $this->assertEquals('001', $no_reg_1);
        $this->assertEquals('002', $no_reg_2);
        $this->assertEquals('003', $no_reg_3);
    }

    // =========================================================================
    // TEST 2: setNoRawatSafe starts at 1 when no data exists
    // =========================================================================
    public function testSetNoRawatSafeStartsAtOne(): void
    {
        $pdo = self::$pdo;
        $pdo->beginTransaction();

        $no_rawat = $this->setNoRawatSafe($pdo, self::$testDate);

        $pdo->rollBack();

        $expected = str_replace('-', '/', self::$testDate) . '/000001';
        $this->assertEquals($expected, $no_rawat);
    }

    // =========================================================================
    // TEST 3: setNoRegSafe starts at 1 when no data exists
    // =========================================================================
    public function testSetNoRegSafeStartsAtOne(): void
    {
        $pdo = self::$pdo;
        $pdo->beginTransaction();

        $no_reg = $this->setNoRegSafe($pdo, self::$testDokter, self::$testPoli, self::$testDate);

        $pdo->rollBack();

        $this->assertEquals('001', $no_reg);
    }

    // =========================================================================
    // TEST 4: FOR UPDATE blocks concurrent reads (simulated two-connection test)
    // =========================================================================
    public function testForUpdateBlocksConcurrentReads(): void
    {
        // Create two separate PDO connections to simulate two concurrent users
        $dsn = 'mysql:host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4';

        $pdo1 = new \PDO($dsn, DBUSER, DBPASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);

        $pdo2 = new \PDO($dsn, DBUSER, DBPASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);

        // Set a short lock wait timeout on connection 2 (1 second)
        $pdo2->exec("SET SESSION innodb_lock_wait_timeout = 1");

        // Connection 1 starts transaction and acquires FOR UPDATE lock
        $pdo1->beginTransaction();
        $no_rawat_1 = $this->setNoRawatSafe($pdo1, self::$testDate);
        // Don't commit yet — hold the lock

        // Connection 2 should be BLOCKED by the FOR UPDATE lock
        $pdo2->beginTransaction();
        $blocked = false;
        try {
            // This should timeout because connection 1 holds the lock
            $no_rawat_2 = $this->setNoRawatSafe($pdo2, self::$testDate);
        } catch (\PDOException $e) {
            $blocked = true;
            // Expected: Lock wait timeout exceeded
            $this->assertStringContainsString('lock wait timeout', strtolower($e->getMessage()));
        }

        // Clean up connection 1
        $pdo1->rollBack();
        if ($pdo2->inTransaction()) {
            $pdo2->rollBack();
        }

        // The second connection MUST be blocked
        $this->assertTrue($blocked, 'FOR UPDATE must block concurrent access to the same rows');
    }

    // =========================================================================
    // TEST 5: Full registration in transaction produces unique no_rawat
    // =========================================================================
    public function testFullRegistrationTransactionProducesUniqueNumbers(): void
    {
        $dsn = 'mysql:host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4';

        $pdo1 = new \PDO($dsn, DBUSER, DBPASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
        $pdo2 = new \PDO($dsn, DBUSER, DBPASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);

        // Increase lock wait timeout so connection 2 waits instead of failing
        $pdo2->exec("SET SESSION innodb_lock_wait_timeout = 10");

        // Connection 1: register patient 1
        $pdo1->beginTransaction();
        $no_rawat_1 = $this->setNoRawatSafe($pdo1, self::$testDate);
        $no_reg_1 = $this->setNoRegSafe($pdo1, self::$testDokter, self::$testPoli, self::$testDate);
        $this->insertRegPeriksa($pdo1, $no_rawat_1, $no_reg_1, '000001');
        $pdo1->commit();  // Release lock

        // Connection 2: register patient 2 (should see patient 1's data)
        $pdo2->beginTransaction();
        $no_rawat_2 = $this->setNoRawatSafe($pdo2, self::$testDate);
        $no_reg_2 = $this->setNoRegSafe($pdo2, self::$testDokter, self::$testPoli, self::$testDate);
        $this->insertRegPeriksa($pdo2, $no_rawat_2, $no_reg_2, '000002');
        $pdo2->commit();

        // Verify uniqueness
        $this->assertNotEquals($no_rawat_1, $no_rawat_2, 'no_rawat must be unique');
        $this->assertNotEquals($no_reg_1, $no_reg_2, 'no_reg must be unique');

        $expected_date = str_replace('-', '/', self::$testDate);
        $this->assertEquals($expected_date . '/000001', $no_rawat_1);
        $this->assertEquals($expected_date . '/000002', $no_rawat_2);
    }

    // =========================================================================
    // TEST 6: Duplicate patient registration is rejected in transaction
    // =========================================================================
    public function testDuplicateRegistrationRejectedInTransaction(): void
    {
        $dsn = 'mysql:host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4';

        $pdo1 = new \PDO($dsn, DBUSER, DBPASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
        $pdo2 = new \PDO($dsn, DBUSER, DBPASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
        $pdo2->exec("SET SESSION innodb_lock_wait_timeout = 10");

        $same_rm = '999999';

        // Connection 1: register patient
        $pdo1->beginTransaction();
        $no_rawat_1 = $this->setNoRawatSafe($pdo1, self::$testDate);
        $no_reg_1 = $this->setNoRegSafe($pdo1, self::$testDokter, self::$testPoli, self::$testDate);

        // Check duplicate
        $stmt = $pdo1->prepare("SELECT COUNT(*) as cnt FROM reg_periksa WHERE no_rkm_medis = ? AND tgl_registrasi = ? FOR UPDATE");
        $stmt->execute([$same_rm, self::$testDate]);
        $cek = $stmt->fetch();
        $this->assertEquals(0, $cek['cnt']);

        $this->insertRegPeriksa($pdo1, $no_rawat_1, $no_reg_1, $same_rm);
        $pdo1->commit();

        // Connection 2: try to register same patient — should find duplicate
        $pdo2->beginTransaction();
        $stmt2 = $pdo2->prepare("SELECT COUNT(*) as cnt FROM reg_periksa WHERE no_rkm_medis = ? AND tgl_registrasi = ? FOR UPDATE");
        $stmt2->execute([$same_rm, self::$testDate]);
        $cek2 = $stmt2->fetch();

        // Should detect the duplicate
        $this->assertGreaterThan(0, $cek2['cnt'], 'Duplicate registration must be detected');
        $pdo2->rollBack();
    }

    // =========================================================================
    // TEST 7: Transaction rollback does not leave partial data
    // =========================================================================
    public function testTransactionRollbackLeavesNoData(): void
    {
        $pdo = self::$pdo;

        $pdo->beginTransaction();
        $no_rawat = $this->setNoRawatSafe($pdo, self::$testDate);
        $no_reg = $this->setNoRegSafe($pdo, self::$testDokter, self::$testPoli, self::$testDate);
        $this->insertRegPeriksa($pdo, $no_rawat, $no_reg, '000099');
        $pdo->rollBack();

        // Verify no data was saved
        $stmt = self::$pdo->prepare("SELECT COUNT(*) as cnt FROM reg_periksa WHERE tgl_registrasi = ?");
        $stmt->execute([self::$testDate]);
        $row = $stmt->fetch();
        $this->assertEquals(0, $row['cnt'], 'Rollback must not leave any data');
    }

    // =========================================================================
    // TEST 8: Multiple sequential registrations maintain correct numbering
    // =========================================================================
    public function testMultipleRegistrationsMaintainSequence(): void
    {
        $pdo = self::$pdo;
        $expected_date = str_replace('-', '/', self::$testDate);

        for ($i = 1; $i <= 10; $i++) {
            $pdo->beginTransaction();
            $no_rawat = $this->setNoRawatSafe($pdo, self::$testDate);
            $no_reg = $this->setNoRegSafe($pdo, self::$testDokter, self::$testPoli, self::$testDate);
            $this->insertRegPeriksa($pdo, $no_rawat, $no_reg, sprintf('%06d', $i));
            $pdo->commit();

            $this->assertEquals($expected_date . '/' . sprintf('%06d', $i), $no_rawat, "no_rawat #{$i}");
            $this->assertEquals(sprintf('%03d', $i), $no_reg, "no_reg #{$i}");
        }

        // Verify total count
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM reg_periksa WHERE tgl_registrasi = ?");
        $stmt->execute([self::$testDate]);
        $row = $stmt->fetch();
        $this->assertEquals(10, $row['cnt']);
    }

    // =========================================================================
    // TEST 9: Verify no_rawat format is correct
    // =========================================================================
    public function testNoRawatFormat(): void
    {
        $pdo = self::$pdo;
        $pdo->beginTransaction();

        $no_rawat = $this->setNoRawatSafe($pdo, self::$testDate);

        $pdo->rollBack();

        // Must match format: YYYY/MM/DD/NNNNNN
        $this->assertMatchesRegularExpression('/^\d{4}\/\d{2}\/\d{2}\/\d{6}$/', $no_rawat);
        $expected_date = str_replace('-', '/', self::$testDate);
        $this->assertEquals($expected_date . '/000001', $no_rawat);
    }

    // =========================================================================
    // TEST 10: Concurrent registration simulation (fork-based, if pcntl available)
    // =========================================================================
    public function testConcurrentRegistrationWithProcesses(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension not available — skipping process-based concurrency test');
        }

        $numWorkers = 5;
        $pids = [];

        for ($i = 0; $i < $numWorkers; $i++) {
            $pid = pcntl_fork();
            if ($pid == -1) {
                $this->fail('Failed to fork');
            } elseif ($pid == 0) {
                // Child process
                try {
                    $dsn = 'mysql:host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4';
                    $childPdo = new \PDO($dsn, DBUSER, DBPASS, [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                    ]);
                    $childPdo->exec("SET SESSION innodb_lock_wait_timeout = 30");

                    $childPdo->beginTransaction();

                    $stmt = $childPdo->prepare(
                        "SELECT IFNULL(MAX(CONVERT(RIGHT(no_rawat, 6), SIGNED)), 0) AS max_no
                         FROM reg_periksa WHERE tgl_registrasi = ? FOR UPDATE"
                    );
                    $stmt->execute([self::$testDate]);
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $urut = ((int) $row['max_no']) + 1;
                    $no_rawat = str_replace('-', '/', self::$testDate) . '/' . sprintf('%06d', $urut);
                    $no_reg = sprintf('%03d', $urut);

                    $stmtIns = $childPdo->prepare(
                        "INSERT INTO reg_periksa
                         (no_reg, no_rawat, tgl_registrasi, jam_reg, kd_dokter, no_rkm_medis,
                          kd_poli, p_jawab, almt_pj, hubunganpj, biaya_reg, stts,
                          stts_daftar, status_lanjut, kd_pj, umurdaftar, sttsumur,
                          status_bayar, status_poli)
                         VALUES (?, ?, ?, ?, ?, ?, ?, '-', '-', '-', 0, 'Belum', 'Baru',
                                 'Ralan', 'BPJ', 30, 'Th', 'Belum Bayar', 'Baru')"
                    );
                    $stmtIns->execute([
                        $no_reg,
                        $no_rawat,
                        self::$testDate,
                        date('H:i:s'),
                        self::$testDokter,
                        sprintf('%06d', $i + 100),
                        self::$testPoli
                    ]);

                    $childPdo->commit();
                    exit(0); // success
                } catch (\Exception $e) {
                    exit(1); // failure
                }
            } else {
                $pids[] = $pid;
            }
        }

        // Wait for all children
        $allSuccess = true;
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            if (pcntl_wexitstatus($status) !== 0) {
                $allSuccess = false;
            }
        }

        $this->assertTrue($allSuccess, 'All concurrent registrations must succeed');

        // Reconnect PDO (parent connection may be invalid after fork)
        $dsn = 'mysql:host=' . DBHOST . ';port=' . DBPORT . ';dbname=' . DBNAME . ';charset=utf8mb4';
        self::$pdo = new \PDO($dsn, DBUSER, DBPASS, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);

        // Check uniqueness: all no_rawat values must be different
        $stmt = self::$pdo->prepare("SELECT no_rawat, COUNT(*) as cnt FROM reg_periksa WHERE tgl_registrasi = ? GROUP BY no_rawat HAVING cnt > 1");
        $stmt->execute([self::$testDate]);
        $duplicates = $stmt->fetchAll();

        $this->assertEmpty($duplicates, 'No duplicate no_rawat should exist after concurrent registration');

        // Verify exactly numWorkers registrations
        $stmt = self::$pdo->prepare("SELECT COUNT(*) as cnt FROM reg_periksa WHERE tgl_registrasi = ?");
        $stmt->execute([self::$testDate]);
        $row = $stmt->fetch();
        $this->assertEquals($numWorkers, $row['cnt'], "Expected {$numWorkers} registrations");
    }
}
