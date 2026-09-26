<?php

return [
    'name'          => 'Pemeriksaan Paramedis (Dev)',
    'description'   => 'Versi pengembangan modul pemeriksaan awal paramedis: TTV, anamnesa, alergi, dan panggilan antrean.',
    'author'        => 'Basoro',
    'category'      => 'layanan',
    'version'       => '2.0.0-dev',
    'compatibility' => '6.*.*',
    'icon'          => 'stethoscope',
    'install'       => function () use ($core) {
        $core->db()->pdo()->exec("INSERT IGNORE INTO `mlite_settings` (`module`, `field`, `value`) VALUES ('pemeriksaan_ralan_dev', 'set_sudah', 'tidak')");
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
    },
    'uninstall'      => function () use ($core) {
    }
];
