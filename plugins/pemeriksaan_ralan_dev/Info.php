<?php

return [
    'name'          => 'Pemeriksaan Paramedis (Dev)',
    'description'   => 'Versi pengembangan modul pemeriksaan awal paramedis: TTV, anamnesa, alergi, dan panggilan antrean.',
    'author'        => 'Basoro',
    'category'      => 'layanan',
    'version'       => '2.2.0-dev',
    'compatibility' => '6.*.*',
    'icon'          => 'stethoscope',
    'install'       => function () use ($core) {
        $core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `alergi_pasien` (
            `no_rkm_medis` varchar(15) NOT NULL,
            `alergi_makanan` varchar(5) DEFAULT '00',
            `alergi_makanan_lainnya` varchar(255) DEFAULT NULL,
            `alergi_udara` varchar(5) DEFAULT '00',
            `alergi_udara_lainnya` varchar(255) DEFAULT NULL,
            `alergi_obat` varchar(5) DEFAULT '00',
            `alergi_obat_lainnya` varchar(255) DEFAULT NULL,
            `tgl_input` datetime DEFAULT NULL,
            `tgl_update` datetime DEFAULT NULL,
            `nip_input` varchar(20) DEFAULT NULL,
            `nip_update` varchar(20) DEFAULT NULL,
            PRIMARY KEY (`no_rkm_medis`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `mlite_pemeriksaan_ralan_dev_status_log` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `no_rawat` varchar(17) NOT NULL,
            `from_status` varchar(40) NOT NULL,
            `to_status` varchar(40) NOT NULL,
            `reason` varchar(255) NOT NULL DEFAULT '',
            `actor_nip` varchar(20) NOT NULL,
            `event_key` varchar(100) NOT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_prd_status_event` (`event_key`),
            KEY `idx_prd_status_visit` (`no_rawat`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $core->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS `mlite_pemeriksaan_ralan_dev_antrean` (
            `id` bigint unsigned NOT NULL AUTO_INCREMENT,
            `no_rawat` varchar(17) NOT NULL,
            `operation` varchar(20) NOT NULL,
            `event_key` varchar(100) NOT NULL,
            `event_at_ms` bigint DEFAULT NULL,
            `reason` varchar(255) NOT NULL DEFAULT '',
            `requested_at` datetime NOT NULL,
            `completed_at` datetime DEFAULT NULL,
            `status` varchar(20) NOT NULL,
            `attempt_count` int unsigned NOT NULL DEFAULT 1,
            `response_code` varchar(20) NOT NULL DEFAULT '',
            `response_message` varchar(255) NOT NULL DEFAULT '',
            `actor_nip` varchar(20) NOT NULL,
            `payload_hash` char(64) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_prd_antrean_event` (`event_key`),
            KEY `idx_prd_antrean_visit` (`no_rawat`, `operation`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    },
    'uninstall'      => function () use ($core) {
    }
];
