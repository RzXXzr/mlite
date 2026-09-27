<?php
return [
    'name' => 'JKN Mobile FKTP (Dev)',
    'description' => 'Endpoint Antrean FKTP V2 terisolasi dengan editor dokter/kapasitas mingguan, panggilan Antrol Dev di Rawat Jalan, rekonsiliasi booking, dan audit transaksi.',
    'author' => 'Basoro',
    'category' => 'bridging',
    'version' => '2.4.0-dev',
    'compatibility' => '6.*.*',
    'icon' => 'tasks',
    'pages' => ['JKN Mobile FKTP Dev' => 'jknmobilefktpdev'],
    'install' => function () use ($core) {
        $pdo = $core->db()->pdo();
        $secret = bin2hex(random_bytes(32));
        $defaults = [
            'username' => '', 'password' => '', 'token_secret' => $secret,
            'token_ttl_seconds' => '300', 'payer_code' => 'BPJ',
            'booking_open_days' => '30', 'queue_format' => '{kodepoli}-{nomor}',
            'allowed_origin' => '', 'perusahaan_pasien' => '-',
            'suku_bangsa' => '1', 'bahasa_pasien' => '1', 'cacat_fisik' => '1',
            'antrol_url' => '', 'antrol_enabled' => '0'
        ];
        $insert = $pdo->prepare('INSERT IGNORE INTO mlite_settings (module, field, value) VALUES (?, ?, ?)');
        foreach ($defaults as $field => $value) {
            $insert->execute(['jkn_mobile_fktp_dev', $field, $value]);
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS mlite_jkn_mobile_fktp_dev_log (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            request_id varchar(32) NOT NULL DEFAULT '',
            action varchar(30) NOT NULL,
            endpoint varchar(64) NOT NULL DEFAULT '',
            method varchar(8) NOT NULL DEFAULT '',
            no_rawat varchar(25) NOT NULL DEFAULT '',
            metadata_code smallint NOT NULL DEFAULT 0,
            http_code smallint NOT NULL DEFAULT 0,
            outcome varchar(12) NOT NULL DEFAULT '',
            message varchar(255) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_jkn_fktp_dev_log_visit (no_rawat),
            KEY idx_jkn_fktp_dev_log_created (created_at),
            KEY idx_jkn_fktp_dev_log_outcome (outcome, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mlite_jkn_mobile_fktp_dev_booking (
            no_rawat varchar(25) NOT NULL,
            kodepoli varchar(20) NOT NULL,
            kodedokter varchar(20) NOT NULL,
            jampraktek varchar(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (no_rawat),
            KEY idx_jkn_fktp_dev_booking_slot (kodepoli, kodedokter, jampraktek)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $pdo->exec("CREATE TABLE IF NOT EXISTS mlite_jkn_mobile_fktp_dev_schedule (
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
    },
    'uninstall' => function () use ($core) {
        $core->db()->pdo()->exec("DELETE FROM mlite_settings WHERE module = 'jkn_mobile_fktp_dev'");
    }
];
