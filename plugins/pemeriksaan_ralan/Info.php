<?php

return [
    'name'          =>  'Pemeriksaan Paramedis',
    'description'   =>  'Modul pemeriksaan awal oleh paramedis/perawat untuk mLITE (TTV & Anamnesa)',
    'author'        =>  'Basoro',
    'category'      =>  'layanan', 
    'version'       =>  '1.0',
    'compatibility' =>  '6.*.*',
    'icon'          =>  'stethoscope',
    'install'       =>  function () use ($core) {
      $core->db()->pdo()->exec("INSERT INTO `mlite_settings` (`module`, `field`, `value`) VALUES ('pemeriksaan_ralan', 'set_sudah', 'tidak')");
    },
    'uninstall'     =>  function() use($core)
    {
    }
];
