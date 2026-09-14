// sembunyikan form dan notif
$("#form_rincian").hide();
$("#form_soap").hide();
$("#form_sep").hide();
$("#histori_pelayanan").hide();
$("#notif").hide();
$('#provider').hide();
$('#aturan_pakai').hide();
$('#daftar_racikan').hide();
$("#info_tambahan").hide();
$("#form_kontrol").hide();
$("#form_alergi_container").hide();

$("#display").on("click",".riwayat_perawatan", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var no_rkm_medis = $(this).attr("data-no_rkm_medis");
  window.open(baseURL + '/pasien/riwayatperawatan/' + no_rkm_medis + '?t=' + mlite.token);
});

$('#manage').on('click', '#submit_periode_rawat_jalan', function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url    = baseURL + '/pemeriksaan_ralan/display?t=' + mlite.token;
  var periode_rawat_jalan  = $('input:text[name=periode_rawat_jalan]').val();
  var periode_rawat_jalan_akhir  = $('input:text[name=periode_rawat_jalan_akhir]').val();
  var status_periksa = 'semua';

  if(periode_rawat_jalan == '') {
    alert('Tanggal awal masih kosong!')
  }
  if(periode_rawat_jalan_akhir == '') {
    alert('Tanggal akhir masih kosong!')
  }

  $.post(url, {periode_rawat_jalan: periode_rawat_jalan, periode_rawat_jalan_akhir: periode_rawat_jalan_akhir} ,function(data) {
  // tampilkan data
    $("#form").show();
    $("#display").html(data).show();
    $("#form_rincian").hide();
    $("#form_soap").hide();
    $("#form_sep").hide();
    $("#notif").hide();
    $("#rincian").show();
    $("#sep").hide();
    $("#soap").hide();
    $("#form_alergi_container").hide();
    $('.periode_rawat_jalan').datetimepicker('remove');
  });

});

$('#manage').on('click', '#belum_periode_rawat_jalan', function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url    = baseURL + '/pemeriksaan_ralan/display?t=' + mlite.token;
  var periode_rawat_jalan  = $('input:text[name=periode_rawat_jalan]').val();
  var periode_rawat_jalan_akhir  = $('input:text[name=periode_rawat_jalan_akhir]').val();
  var status_periksa = 'belum';

  if(periode_rawat_jalan == '') {
    alert('Tanggal awal masih kosong!')
  }
  if(periode_rawat_jalan_akhir == '') {
    alert('Tanggal akhir masih kosong!')
  }

  $.post(url, {periode_rawat_jalan: periode_rawat_jalan, periode_rawat_jalan_akhir: periode_rawat_jalan_akhir, status_periksa: status_periksa} ,function(data) {
  // tampilkan data
    $("#form").show();
    $("#display").html(data).show();
    $("#form_rincian").hide();
    $("#form_soap").hide();
    $("#form_sep").hide();
    $("#notif").hide();
    $("#rincian").hide();
    $("#sep").hide();
    $("#soap").hide();
    $("#form_alergi_container").hide();
    $('.periode_rawat_jalan').datetimepicker('remove');
  });

});

$('#manage').on('click', '#selesai_periode_rawat_jalan', function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url    = baseURL + '/pemeriksaan_ralan/display?t=' + mlite.token;
  var periode_rawat_jalan  = $('input:text[name=periode_rawat_jalan]').val();
  var periode_rawat_jalan_akhir  = $('input:text[name=periode_rawat_jalan_akhir]').val();
  var status_periksa = 'selesai';

  if(periode_rawat_jalan == '') {
    alert('Tanggal awal masih kosong!')
  }
  if(periode_rawat_jalan_akhir == '') {
    alert('Tanggal akhir masih kosong!')
  }

  $.post(url, {periode_rawat_jalan: periode_rawat_jalan, periode_rawat_jalan_akhir: periode_rawat_jalan_akhir, status_periksa: status_periksa} ,function(data) {
  // tampilkan data
    $("#form").show();
    $("#display").html(data).show();
    $("#form_rincian").hide();
    $("#form_soap").hide();
    $("#form_sep").hide();
    $("#notif").hide();
    $("#rincian").hide();
    $("#sep").hide();
    $("#soap").hide();
    $("#form_alergi_container").hide();
    $('.periode_rawat_jalan').datetimepicker('remove');
  });

});

$('#manage').on('click', '#lunas_periode_rawat_jalan', function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url    = baseURL + '/pemeriksaan_ralan/display?t=' + mlite.token;
  var periode_rawat_jalan  = $('input:text[name=periode_rawat_jalan]').val();
  var periode_rawat_jalan_akhir  = $('input:text[name=periode_rawat_jalan_akhir]').val();
  var status_periksa = 'lunas';

  if(periode_rawat_jalan == '') {
    alert('Tanggal awal masih kosong!')
  }
  if(periode_rawat_jalan_akhir == '') {
    alert('Tanggal akhir masih kosong!')
  }

  $.post(url, {periode_rawat_jalan: periode_rawat_jalan, periode_rawat_jalan_akhir: periode_rawat_jalan_akhir, status_periksa: status_periksa} ,function(data) {
  // tampilkan data
    $("#form").show();
    $("#display").html(data).show();
    $("#form_rincian").hide();
    $("#form_soap").hide();
    $("#form_sep").hide();
    $("#notif").hide();
    $("#rincian").hide();
    $("#sep").hide();
    $("#soap").hide();
    $("#form_alergi_container").hide();
    $('.periode_rawat_jalan').datetimepicker('remove');
  });

});

// ketika tombol simpan diklik
$("#form_soap").on("click", "#simpan_soap", function(event){
  {if: !$cek_role}
    bootbox.alert({
        title: "Pemberitahuan penggunaan!",
        message: "Silahkan login dengan akun non administrator (akun yang berelasi dengan modul kepegawaian)!"
    });
  {else}
    var baseURL = mlite.url + '/' + mlite.admin;
    event.preventDefault();

    var no_rawat        = $('input:text[name=no_rawat]').val();
    var tgl_perawatan   = $('input:text[name=tgl_perawatan]').val();
    var jam_rawat       = $('input:text[name=jam_rawat]').val();
    var suhu_tubuh      = $('input:text[name=suhu_tubuh]').val();
    var tensi           = $('input:text[name=tensi]').val();
    var nadi            = $('input:text[name=nadi]').val();
    var respirasi       = $('input:text[name=respirasi]').val();
    var tinggi          = $('input:text[name=tinggi]').val();
    var berat           = $('input:text[name=berat]').val();
    var gcs             = $('input:text[name=gcs]').val();
    var kesadaran       = $('input:text[name=kesadaran]').val();
    var alergi          = $('input:text[name=alergi]').val();
    var alergi          = $('input:text[name=alergi]').val();
    var lingkar_perut   = $('input:text[name=lingkar_perut]').val();
    var keluhan         = $('textarea[name=keluhan]').val();
    var pemeriksaan     = $('textarea[name=pemeriksaan]').val();
    var penilaian       = $('textarea[name=penilaian]').val();
    var rtl             = $('textarea[name=rtl]').val();
    var instruksi       = $('textarea[name=instruksi]').val();
    var evaluasi        = $('textarea[name=evaluasi]').val();
    var spo2            = $('input:text[name=spo2]').val();

    // Fungsi validasi field wajib (tidak boleh kosong)
    function validasiWajib(nilai, namaField) {
      var nilaiTrim = nilai ? nilai.trim() : '';
      if(nilaiTrim == '' || nilaiTrim == '-') {
        return { valid: false, error: namaField + ' tidak boleh kosong' };
      }
      return { valid: true, error: '' };
    }
    
    // Validasi field TTV & Anamnesa wajib diisi
    var fieldErrors = [];
    var wajibFields = [
      { nama: 'Tensi (mmHg)', nilai: tensi },
      { nama: 'Suhu (C)', nilai: suhu_tubuh },
      { nama: 'Nadi (/mnt)', nilai: nadi },
      { nama: 'RR (/mnt)', nilai: respirasi },
      { nama: 'Tinggi (cm)', nilai: tinggi },
      { nama: 'Berat (kg)', nilai: berat },
      { nama: 'Kesadaran', nilai: kesadaran },
      { nama: 'SPO2', nilai: spo2 },
      { nama: 'GCS (E,V,M)', nilai: gcs },
      { nama: 'Alergi', nilai: alergi },
      { nama: 'Lingkar Perut', nilai: lingkar_perut },
      { nama: 'Subyektif (Keluhan)', nilai: keluhan }
    ];
    
    for(var i = 0; i < wajibFields.length; i++) {
      var field = wajibFields[i];
      var hasil = validasiWajib(field.nilai, field.nama);
      if(!hasil.valid) {
        fieldErrors.push('<strong>' + field.nama + '</strong>');
      }
    }

    if(fieldErrors.length > 0) {
      bootbox.alert({
        title: "<i class='fa fa-warning text-danger'></i> Data Belum Lengkap!",
        message: "<p>Silahkan lengkapi field berikut:</p><ul><li>" + fieldErrors.join('</li><li>') + "</li></ul>",
        size: 'large',
        backdrop: true
      });
      return false;
    }

    var url = baseURL + '/pemeriksaan_ralan/savesoap?t=' + mlite.token;
    $.post(url, {no_rawat : no_rawat,
    tgl_perawatan: tgl_perawatan,
    jam_rawat: jam_rawat,
    suhu_tubuh : suhu_tubuh,
    tensi : tensi,
    nadi : nadi,
    respirasi : respirasi,
    tinggi : tinggi,
    berat : berat,
    gcs : gcs,
    kesadaran : kesadaran,
    alergi : alergi,
    lingkar_perut: lingkar_perut,
    keluhan : keluhan,
    pemeriksaan : pemeriksaan,
    penilaian : penilaian,
    rtl : rtl,
    instruksi : instruksi,
    evaluasi : evaluasi,
    spo2 : spo2
    }, function(data) {
      // Update status periksa menjadi "Berkas Dikirim"
      var urlUpdateStatus = baseURL + '/pemeriksaan_ralan/setberkasterkirim?t=' + mlite.token;
      $.post(urlUpdateStatus, {no_rawat: no_rawat}, function(response) {
        console.log("Status diubah ke Berkas Dikirim:", response);
        
        // Kirim notifikasi WebSocket untuk update anjungan display
        if (typeof ws !== 'undefined' && ws.readyState === 1) {
          var payload = {
            'action': 'update_status',
            'modul': 'pemeriksaan_ralan',
            'data': {
              'no_rawat': no_rawat,
              'new_status': 'Berkas Dikirim'
            }
          };
          ws.send(JSON.stringify(payload));
          console.log("📤 Status update sent via WebSocket:", payload);
        }
      });
      
      // tampilkan data
      $("#display").hide();
      var url = baseURL + '/pemeriksaan_ralan/soap?t=' + mlite.token;
      $.post(url, {no_rawat : no_rawat,
      }, function(data) {
        // tampilkan data
        $("#soap").html(data).show();
      });
      $('input:text[name=suhu_tubuh]').val("");
      $('input:text[name=tensi]').val("");
      $('input:text[name=nadi]').val("");
      $('input:text[name=respirasi]').val("");
      $('input:text[name=tinggi]').val("");
      $('input:text[name=berat]').val("");
      $('input:text[name=gcs]').val("");
      $('input:text[name=kesadaran]').val("");
      $('input:text[name=alergi]').val(window._pcareAlergiText || '');
      $('input:text[name=lingkar_perut]').val("");
      $('textarea[name=keluhan]').val("");
      $('textarea[name=pemeriksaan]').val("");
      $('textarea[name=penilaian]').val("");
      $('textarea[name=rtl]').val("");
      $('textarea[name=instruksi]').val("");
      $('textarea[name=evaluasi]').val("");
      $('input:text[name=spo2]').val("");
      $('input:text[name=tgl_perawatan]').val("{?=date('Y-m-d')?}");
      $('input:text[name=tgl_registrasi]').val("{?=date('Y-m-d')?}");
      //$('input:text[name=jam_rawat]').val("{?=date('H:i:s')?}");
      $.post(baseURL + '/pemeriksaan_ralan/cekwaktu?t=' + mlite.token, {
      } ,function(data) {
        $("#form_soap #jam_rawat").val(data);
      });
      $('#notif').html("<div class=\"alert alert-success alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
      "Data soap telah disimpan!"+
      "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
      "</div>").show();
    });
  {/if}
});

// ketika tombol hapus ditekan
$("#soap").on("click",".copy_soap", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var suhu_tubuh      = $(this).attr("data-suhu_tubuh");
  var tensi           = $(this).attr("data-tensi");
  var nadi            = $(this).attr("data-nadi");
  var respirasi       = $(this).attr("data-respirasi");
  var tinggi          = $(this).attr("data-tinggi");
  var berat           = $(this).attr("data-berat");
  var gcs             = $(this).attr("data-gcs");
  var kesadaran       = $(this).attr("data-kesadaran");
  var alergi          = $(this).attr("data-alergi");
  var lingkar_perut   = $(this).attr("data-lingkar_perut");
  var keluhan         = $(this).attr("data-keluhan");
  var pemeriksaan     = $(this).attr("data-pemeriksaan");
  var penilaian       = $(this).attr("data-penilaian");
  var rtl             = $(this).attr("data-rtl");
  var instruksi       = $(this).attr("data-instruksi");
  var evaluasi        = $(this).attr("data-evaluasi");
  var spo2            = $(this).attr("data-spo2");

  // Salin ke Form: hanya isi field yang masih kosong atau hanya berisi "-"
  function fillIfEmpty(selector, value) {
    var $el = $(selector);
    var cur = $el.val().trim();
    if (cur === '' || cur === '-') $el.val(value);
  }
  fillIfEmpty('input:text[name=suhu_tubuh]', suhu_tubuh);
  fillIfEmpty('input:text[name=tensi]', tensi);
  fillIfEmpty('input:text[name=nadi]', nadi);
  fillIfEmpty('input:text[name=respirasi]', respirasi);
  fillIfEmpty('input:text[name=tinggi]', tinggi);
  fillIfEmpty('input:text[name=berat]', berat);
  fillIfEmpty('input:text[name=gcs]', gcs);
  fillIfEmpty('input:text[name=kesadaran]', kesadaran);
  // alergi selalu dari PCare — baca window._pcareAlergiText, fallback baca dropdown langsung
  var _pcareText = window._pcareAlergiText;
  if (!_pcareText && typeof buildAlergiTextFromPcare === 'function') {
    _pcareText = buildAlergiTextFromPcare(
      $('#alergi_makanan').val() || '00',
      $('#alergi_makanan_lainnya').val() || '',
      $('#alergi_udara').val() || '00',
      $('#alergi_udara_lainnya').val() || '',
      $('#alergi_obat').val() || '00',
      $('#alergi_obat_lainnya').val() || ''
    );
    window._pcareAlergiText = _pcareText; // cache untuk berikutnya
  }
  $('input:text[name=alergi]').val(_pcareText || '');
  fillIfEmpty('input:text[name=lingkar_perut]', lingkar_perut);
  fillIfEmpty('textarea[name=keluhan]', keluhan);
  fillIfEmpty('textarea[name=pemeriksaan]', pemeriksaan);
  fillIfEmpty('textarea[name=penilaian]', penilaian);
  fillIfEmpty('textarea[name=rtl]', rtl);
  fillIfEmpty('textarea[name=instruksi]', instruksi);
  fillIfEmpty('textarea[name=evaluasi]', evaluasi);
  fillIfEmpty('input:text[name=spo2]', spo2);

});

// ketika tombol hapus ditekan
$("#soap").on("click",".edit_soap", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var no_rawat        = $(this).attr("data-no_rawat");
  var tgl_perawatan   = $(this).attr("data-tgl_perawatan");
  var jam_rawat       = $(this).attr("data-jam_rawat");
  var suhu_tubuh      = $(this).attr("data-suhu_tubuh");
  var tensi           = $(this).attr("data-tensi");
  var nadi            = $(this).attr("data-nadi");
  var respirasi       = $(this).attr("data-respirasi");
  var tinggi          = $(this).attr("data-tinggi");
  var berat           = $(this).attr("data-berat");
  var gcs             = $(this).attr("data-gcs");
  var kesadaran       = $(this).attr("data-kesadaran");
  var alergi          = $(this).attr("data-alergi");
  var lingkar_perut   = $(this).attr("data-lingkar_perut");
  var keluhan         = $(this).attr("data-keluhan");
  var pemeriksaan     = $(this).attr("data-pemeriksaan");
  var penilaian       = $(this).attr("data-penilaian");
  var rtl             = $(this).attr("data-rtl");
  var instruksi       = $(this).attr("data-instruksi");
  var evaluasi        = $(this).attr("data-evaluasi");
  var spo2            = $(this).attr("data-spo2");

  $('input:text[name=tgl_perawatan]').val(tgl_perawatan);
  $('input:text[name=jam_rawat]').val(jam_rawat);
  $('input:text[name=suhu_tubuh]').val(suhu_tubuh);
  $('input:text[name=tensi]').val(tensi);
  $('input:text[name=nadi]').val(nadi);
  $('input:text[name=respirasi]').val(respirasi);
  $('input:text[name=tinggi]').val(tinggi);
  $('input:text[name=berat]').val(berat);
  $('input:text[name=gcs]').val(gcs);
  $('input:text[name=kesadaran]').val(kesadaran);
  $('input:text[name=alergi]').val(alergi);
  $('input:text[name=lingkar_perut]').val(lingkar_perut);
  $('textarea[name=keluhan]').val(keluhan);
  $('textarea[name=pemeriksaan]').val(pemeriksaan);
  $('textarea[name=penilaian]').val(penilaian);
  $('textarea[name=rtl]').val(rtl);
  $('textarea[name=instruksi]').val(instruksi);
  $('textarea[name=evaluasi]').val(evaluasi);
  $('input:text[name=spo2]').val(spo2);

});

// ketika tombol hapus ditekan
$("#soap").on("click",".hapus_soap", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/pemeriksaan_ralan/hapussoap?t=' + mlite.token;
  var no_rawat = $(this).attr("data-no_rawat");
  var tgl_perawatan = $(this).attr("data-tgl_perawatan");
  var jam_rawat = $(this).attr("data-jam_rawat");

  // tampilkan dialog konfirmasi
  bootbox.confirm("Apakah Anda yakin ingin menghapus data ini?", function(result){
    // ketika ditekan tombol ok
    if (result){
      // mengirimkan perintah penghapusan
      $.post(url, {
        no_rawat: no_rawat,
        tgl_perawatan: tgl_perawatan,
        jam_rawat: jam_rawat
      } ,function(data) {
        var url = baseURL + '/pemeriksaan_ralan/soap?t=' + mlite.token;
        $.post(url, {no_rawat : no_rawat,
        }, function(data) {
          // tampilkan data
          $("#soap").html(data).show();
        });
        $('input:text[name=suhu_tubuh]').val("");
        $('input:text[name=tensi]').val("");
        $('input:text[name=nadi]').val("");
        $('input:text[name=respirasi]').val("");
        $('input:text[name=tinggi]').val("");
        $('input:text[name=berat]').val("");
        $('input:text[name=gcs]').val("");
        $('input:text[name=kesadaran]').val("");
        $('input:text[name=alergi]').val(window._pcareAlergiText || '');
        $('input:text[name=lingkar_perut]').val("");
        $('textarea[name=keluhan]').val("");
        $('textarea[name=pemeriksaan]').val("");
        $('textarea[name=penilaian]').val("");
        $('textarea[name=rtl]').val("");
        $('textarea[name=instruksi]').val("");
        $('textarea[name=evaluasi]').val("");
        $('input:text[name=spo2]').val("");
        $('input:text[name=tgl_perawatan]').val("{?=date('Y-m-d')?}");
        $('input:text[name=tgl_registrasi]').val("{?=date('Y-m-d')?}");
        $('input:text[name=jam_rawat]').val("{?=date('H:i:s')?}");
        $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
        "Data rincian riwayat telah dihapus!"+
        "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
        "</div>").show();
      });
    }
  });
});

// ketika tombol simpan diklik
$("#form_kontrol").on("click", "#simpan_kontrol", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var no_rkm_medis    = $('input:text[name=no_rkm_medis]').val();
  var no_rawat        = $('input:text[name=no_rawat]').val();
  var tanggal_rujukan = $('input:text[name=tanggal_rujukan]').val();
  var tanggal_datang  = $('input:text[name=tanggal_datang]').val();
  var diagnosa        = $('input:text[name=diagnosa]').val();
  var terapi          = $('input:text[name=terapi]').val();
  var alasan1         = $('textarea[name=alasan1]').val();
  var rtl1            = $('textarea[name=rtl1]').val();

  var url = baseURL + '/pemeriksaan_ralan/savekontrol?t=' + mlite.token;
  $.post(url, {no_rawat : no_rawat,
  no_rkm_medis   : no_rkm_medis,
  tanggal_rujukan       : tanggal_rujukan,
  tanggal_datang  : tanggal_datang,
  diagnosa : diagnosa,
  terapi  : terapi,
  alasan1      : alasan1,
  rtl1          : rtl1
  }, function(data) {
    // tampilkan data
    $("#display").hide();
    var url = baseURL + '/pemeriksaan_ralan/kontrol?t=' + mlite.token;
    $.post(url, {no_rkm_medis : no_rkm_medis,
    }, function(data) {
      // tampilkan data
      $("#surat_kontrol").html(data).show();
    });
    $('input:text[name=nm_perawatan]').val("");
    $('input:text[name=biaya]').val("");
    $('input:text[name=diagnosa_klinis]').val("");
    $('input:text[name=nama_provider]').val("");
    $('input:text[name=nama_provider2]').val("");
    $('input:text[name=kode_provider]').val("");
    $('input:text[name=kode_provider2]').val("");
    $('input:text[name=racikan]').val("");
    $('input:text[name=nama_racik]').val("");
    $('#notif').html("<div class=\"alert alert-success alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
    "Data surat kontrol telah disimpan!"+
    "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
    "</div>").show();
  });
});

// tombol batal diklik
$("#form_rincian").on("click", "#selesai", function(event){
  bersih();
  $("#form_rincian").hide();
  $("#form_soap").hide();
  $("#form").show();
  $("#display").show();
  $("#rincian").hide();
  $("#soap").hide();
  $('#aturan_pakai').hide();
  $('#daftar_racikan').hide();
  $("#info_tambahan").hide();
  $("#form_kontrol").hide();
  $("#surat_kontrol").hide();
  $("#form_alergi_container").hide();
});

// tombol selesai diklik - dengan validasi kelengkapan
$("#form_soap").on("click", "#selesai_soap", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  var no_rawat = $('input:text[name=no_rawat]').val();
  
  // Cek kelengkapan SOAP dan ICD10 via AJAX
  $.post(baseURL + '/pemeriksaan_ralan/cekKelengkapan?t=' + mlite.token, {
    no_rawat: no_rawat
  }, function(data) {
    var result = JSON.parse(data);
    var pesanError = [];
    
    // Cek apakah SOAP sudah disimpan
    if(result.soap_count == 0) {
      pesanError.push('- Data SOAP belum disimpan. Silahkan isi SOAP (Subyektif, Obyektif, Assesment, Plan) dan klik tombol Simpan terlebih dahulu.');
    }
    
    if(pesanError.length > 0) {
      bootbox.alert({
        title: "<i class='fa fa-warning text-danger'></i> Tidak Dapat Menyelesaikan Pemeriksaan!",
        message: "<p>Data pemeriksaan belum lengkap:</p>" + pesanError.join('<br><br>'),
        size: 'large',
        backdrop: true
      });
      return false;
    } else {
      // Jika semua validasi terpenuhi, lanjutkan proses selesai
      bersih();
      $("#form_rincian").hide();
      $("#form_soap").hide();
      $("#form").show();
      $("#display").show();
      $("#rincian").hide();
      $("#soap").hide();
      $('#aturan_pakai').hide();
      $('#daftar_racikan').hide();
      $("#info_tambahan").hide();
      $("#form_kontrol").hide();
      $("#surat_kontrol").hide();
      $("#form_alergi_container").hide();
    }
  });
});

// tombol batal diklik
$("#form_kontrol").on("click", "#selesai_kontrol", function(event){
  bersih();
  $("#form_rincian").hide();
  $("#form_soap").hide();
  $("#form").show();
  $("#display").show();
  $("#rincian").hide();
  $("#soap").hide();
  $('#aturan_pakai').hide();
  $('#daftar_racikan').hide();
  $("#info_tambahan").hide();
  $("#form_kontrol").hide();
  $("#surat_kontrol").hide();
  $("#form_alergi_container").hide();
});

// ketika inputbox pencarian diisi
$('input:text[name=layanan]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/pemeriksaan_ralan/layanan?t=' + mlite.token;
  var layanan = $('input:text[name=layanan]').val();

  if(layanan!="") {
      $.post(url, {layanan: layanan} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#layanan").html(data).show();
        $("#obat").hide();
        $("#racikan").hide();
      });
  }

});
// end pencarian

// ketika inputbox pencarian diisi
$('input:text[name=obat]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/pemeriksaan_ralan/obat?t=' + mlite.token;
  var obat = $('input:text[name=obat]').val();

  if(obat!="") {
      $.post(url, {obat: obat} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#obat").html(data).show();
        $("#layanan").hide();
        $("#racikan").hide();
        $("#radiologi").hide();
        $("#laboratorium").hide();
      });
  }

});
// end pencarian

// ketika inputbox pencarian diisi
$('input:text[name=racikan]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/pemeriksaan_ralan/racikan?t=' + mlite.token;
  var racikan = $('input:text[name=racikan]').val();

  if(racikan!="") {
      $.post(url, {racikan: racikan} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#racikan").html(data).show();
        $("#layanan").hide();
        $("#obat").hide();
        $("#radiologi").hide();
        $("#laboratorium").hide();
      });
  }

});
// end pencarian

// ketika inputbox pencarian diisi
$('.nama_brng').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/pemeriksaan_ralan/obatracikan?t=' + mlite.token;
  var obat = $('.nama_brng').val();

  if(obat!="") {
      $.post(url, {obat: obat} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#obat_racikan").html(data).show();
        $("#layanan").hide();
        $("#racikan").hide();
        $("#radiologi").hide();
        $("#laboratorium").hide();
      });
  }

});
// end pencarian

// ketika inputbox pencarian diisi
$('input:text[name=laboratorium]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/pemeriksaan_ralan/laboratorium?t=' + mlite.token;
  var laboratorium = $('input:text[name=laboratorium]').val();

  if(laboratorium!="") {
      $.post(url, {laboratorium: laboratorium} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#laboratorium").html(data).show();
        $("#layanan").hide();
        $("#obat").hide();
        $("#racikan").hide();
        $("#radiologi").hide();
      });
  }

});
// end pencarian

// ketika inputbox pencarian diisi
$('input:text[name=radiologi]').on('input',function(e){
  var baseURL = mlite.url + '/' + mlite.admin;
  var url    = baseURL + '/pemeriksaan_ralan/radiologi?t=' + mlite.token;
  var radiologi = $('input:text[name=radiologi]').val();

  if(radiologi!="") {
      $.post(url, {radiologi: radiologi} ,function(data) {
      // tampilkan data yang sudah di perbaharui
        $("#radiologi").html(data).show();
        $("#layanan").hide();
        $("#obat").hide();
        $("#laboratorium").hide();
        $("#racikan").hide();
      });
  }

});
// end pencarian

// ketika baris data diklik
$("#layanan").on("click", ".pilih_layanan", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var kd_jenis_prw = $(this).attr("data-kd_jenis_prw");
  var nm_perawatan = $(this).attr("data-nm_perawatan");
  var biaya = $(this).attr("data-biaya");
  var kat = $(this).attr("data-kat");

  $('input:hidden[name=kd_jenis_prw]').val(kd_jenis_prw);
  $('input:text[name=nm_perawatan]').val(nm_perawatan);
  $('input:text[name=biaya]').val(biaya);
  $('input:hidden[name=kat]').val(kat);

  $("#layanan").hide();
  $('#provider').show();
  $('#aturan_pakai').hide();
  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  $('#daftar_racikan').hide();
  $("#info_tambahan").hide();
});

// ketika baris data diklik
$("#obat").on("click", ".pilih_obat", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var kode_brng = $(this).attr("data-kode_brng");
  var nama_brng = $(this).attr("data-nama_brng");
  var biaya = $(this).attr("data-dasar");
  var stok = $(this).attr("data-stok");
  var stokminimal = $(this).attr("data-stokminimal");
  var kat = $(this).attr("data-kat");

  if(stok < stokminimal) {
    alert('Stok obat ' + nama_brng + ' tidak mencukupi.');
    $('input:hidden[name=kd_jenis_prw]').val();
    $('input:text[name=nm_perawatan]').val();
    $('input:text[name=biaya]').val();
    $('input:hidden[name=kat]').val();
  } else {
    $('input:hidden[name=kd_jenis_prw]').val(kode_brng);
    $('input:text[name=nm_perawatan]').val(nama_brng);
    $('input:text[name=biaya]').val(biaya);
    $('input:hidden[name=kat]').val(kat);
  }

  $('#obat').hide();
  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  $('#daftar_racikan').hide();
  $('#aturan_pakai').show();
  $("#info_tambahan").hide();
});

// ketika baris data diklik
$("#racikan").on("click", ".pilih_racikan", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var kd_racik = $(this).attr("data-kd_racik");
  var nm_racik = $(this).attr("data-nm_racik");
  var kat = $(this).attr("data-kat");

  $('input:hidden[name=kd_jenis_prw]').val(kd_racik);
  $('input:text[name=nm_perawatan]').val(nm_racik);
  $('input:text[name=biaya]').val('');
  $('input:hidden[name=kat]').val(kat);

  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  $('#aturan_pakai').show();
  $('#daftar_racikan').show();
  $("#info_tambahan").hide();
});

// ketika baris data diklik
$("#obat_racikan").on("click", ".pilih_obat_racikan", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var kode_brng = $(this).attr("data-kode_brng");
  var nama_brng = $(this).attr("data-nama_brng");
  var biaya = $(this).attr("data-dasar");
  var stok = $(this).attr("data-stok");

  if(stok < 1) {
    alert('Stok obat ' + nama_brng + ' tidak mencukupi.');
    $('input:hidden[name=kode_brng]').val();
    $('input:text[name=nama_brng]').val();
    $('input:text[name=biaya]').val();
  } else {
    $('input:hidden[name=kode_brng]').val(kode_brng);
    $('input:text[name=nama_brng]').val(nama_brng);
    $('input:text[name=biaya]').val(biaya);
  }

  $('#obat').hide();
  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  //$('#daftar_racikan').hide();
  $('#aturan_pakai').show();
  $("#info_tambahan").hide();
});

// ketika baris data diklik
$("#laboratorium").on("click", ".pilih_laboratorium", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var kd_jenis_prw = $(this).attr("data-kd_jenis_prw");
  var nm_perawatan = $(this).attr("data-nm_perawatan");
  var biaya = $(this).attr("data-biaya");
  var kat = $(this).attr("data-kat");

  $('input:hidden[name=kd_jenis_prw]').val(kd_jenis_prw);
  $('input:text[name=nm_perawatan]').val(nm_perawatan);
  $('input:text[name=biaya]').val(biaya);
  $('input:hidden[name=kat]').val(kat);

  $("#layanan").hide();
  $('#provider').show();
  $('#aturan_pakai').hide();
  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  $('#daftar_racikan').hide();
  $("#info_tambahan").show();
});

// ketika baris data diklik
$("#radiologi").on("click", ".pilih_radiologi", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var kd_jenis_prw = $(this).attr("data-kd_jenis_prw");
  var nm_perawatan = $(this).attr("data-nm_perawatan");
  var biaya = $(this).attr("data-biaya");
  var kat = $(this).attr("data-kat");

  $('input:hidden[name=kd_jenis_prw]').val(kd_jenis_prw);
  $('input:text[name=nm_perawatan]').val(nm_perawatan);
  $('input:text[name=biaya]').val(biaya);
  $('input:hidden[name=kat]').val(kat);

  $("#layanan").hide();
  $('#provider').show();
  $('#aturan_pakai').hide();
  $('#racikan').hide();
  $("#laboratorium").hide();
  $("#radiologi").hide();
  $('#daftar_racikan').hide();
  $("#info_tambahan").show();
});

// ketika tombol panggil ditekan - unified handler (WebSocket + ACK + TTS fallback)
$("#display").on("click",".panggil", function(event){
  event.preventDefault();

  var nm_pasien = $(this).attr("data-nm_pasien");
  var nm_poli = $(this).attr("data-nm_poli");
  var no_reg = $(this).attr("data-no_reg");
  var button = $(this);

  // Generate unique message ID untuk korelasi ACK
  var msgId = 'panggil_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
  var ackReceived = false;
  var ttsTimeout = null;

  function playNativeTTS() {
    if (ackReceived) {
      console.log('ACK sudah diterima dari anjungan, skip TTS lokal');
      return;
    }
    // Cek apakah browser mendukung Web Speech API
    if ('speechSynthesis' in window) {
      
      // Hentikan speech yang mungkin masih berjalan
      speechSynthesis.cancel();
      
      // Buat text yang akan diucapkan
      var textToSpeak = nm_pasien + ", nomor antrian " + no_reg + ", silakan menuju pemeriksaan awal";
      
      // Buat utterance object
      var utterance = new SpeechSynthesisUtterance(textToSpeak);
      
      // Set properti suara
      utterance.lang = 'id-ID';
      utterance.rate = 0.8;
      utterance.pitch = 1;
      utterance.volume = 1;
      
      // Cari voice Indonesia jika tersedia
      var voices = speechSynthesis.getVoices();
      var indonesianVoice = voices.find(function(voice) {
        return voice.lang.includes('id') || 
               voice.name.toLowerCase().includes('indonesia') ||
               voice.name.toLowerCase().includes('damayanti');
      });
      
      if (indonesianVoice) {
        utterance.voice = indonesianVoice;
      }
      
      // Event handlers untuk feedback UI
      utterance.onstart = function() {
        console.log('TTS dimulai: ' + textToSpeak);
        button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Memanggil (lokal)...');
      };
      
      utterance.onend = function() {
        console.log('TTS selesai');
        button.prop('disabled', false).html('<i class="fa fa-bullhorn"></i><span class="hidden-xs hidden-sm"> Panggil</span>');
      };
      
      utterance.onerror = function(event) {
        console.error('TTS Error:', event.error);
        button.prop('disabled', false).html('<i class="fa fa-bullhorn"></i><span class="hidden-xs hidden-sm"> Panggil</span>');
        
        // Tampilkan pesan error yang user-friendly
        switch(event.error) {
          case 'network':
            bootbox.alert('Gagal memutar suara: Tidak ada koneksi internet');
            break;
          case 'not-allowed':
            bootbox.alert('Gagal memutar suara: Browser memblokir autoplay audio. Silakan klik halaman terlebih dahulu.');
            break;
          case 'audio-busy':
            bootbox.alert('Gagal memutar suara: Audio sedang digunakan aplikasi lain');
            break;
          default:
            bootbox.alert('Gagal memutar suara: ' + event.error);
        }
      };
      
      // Putar TTS
      speechSynthesis.speak(utterance);
      
    } else {
      // Browser tidak mendukung Web Speech API
      bootbox.alert('Browser Anda tidak mendukung Text-to-Speech.<br>Silakan gunakan Chrome, Firefox, atau Edge terbaru.');
      console.error('Web Speech API tidak didukung di browser ini');
    }
  }

  // Cek apakah WebSocket tersedia dan terhubung
  var wsConnected = (typeof ws != 'undefined' && typeof ws.readyState != 'undefined' && ws.readyState == 1);

  if (wsConnected) {
    // Kirim panggilan via WebSocket dengan ACK protocol
    var payload = {
      'action': 'panggil',
      'modul': 'pemeriksaan_awal',
      'msgId': msgId,
      'data': {
        'nm_pasien': nm_pasien,
        'nm_poli': nm_poli,
        'no_reg': no_reg,
        'nm_pemanggil': (typeof nama_pemanggil != 'undefined') ? nama_pemanggil : ''
      }
    };
    ws.send(JSON.stringify(payload));
    console.log('📢 Panggilan dikirim via WebSocket:', payload);
    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menunggu anjungan...');

    // Listener untuk ACK dari anjungan
    var onAckMessage = function(response) {
      try {
        var output = JSON.parse(response.data);
        if (output.action === 'panggil_ack' && output.msgId === msgId) {
          ackReceived = true;
          clearTimeout(ttsTimeout);
          ws.removeEventListener('message', onAckMessage);
          console.log('✅ ACK diterima dari anjungan untuk msgId:', msgId);
          button.prop('disabled', true).html('<i class="fa fa-check"></i> Dipanggil via anjungan');
          setTimeout(function() {
            button.prop('disabled', false).html('<i class="fa fa-bullhorn"></i><span class="hidden-xs hidden-sm"> Panggil</span>');
          }, 5000);
        }
      } catch(e) {}
    };
    ws.addEventListener('message', onAckMessage);

    // Timeout 3 detik - jika belum ada ACK, fallback ke TTS lokal
    ttsTimeout = setTimeout(function() {
      if (!ackReceived) {
        ws.removeEventListener('message', onAckMessage);
        console.log('⏱️ Timeout ACK, fallback ke TTS lokal');
        // Fallback ke local TTS
        if (speechSynthesis.getVoices().length === 0) {
          speechSynthesis.addEventListener('voiceschanged', function() {
            playNativeTTS();
          }, { once: true });
          setTimeout(playNativeTTS, 100);
        } else {
          playNativeTTS();
        }
      }
    }, 3000);

  } else {
    // Tidak ada WebSocket - langsung gunakan TTS lokal
    if (speechSynthesis.getVoices().length === 0) {
      speechSynthesis.addEventListener('voiceschanged', function() {
        playNativeTTS();
      }, { once: true });
      setTimeout(playNativeTTS, 100);
    } else {
      playNativeTTS();
    }
  }

});
// akhir kode panggil

// ketika tombol simpan diklik
$("#form_rincian").on("click", "#simpan_rincian", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();

  var no_rawat        = $('input:text[name=no_rawat]').val();
  var kd_jenis_prw 	  = $('input:hidden[name=kd_jenis_prw]').val();
  var provider        = $('select[name=provider]').val();
  var kode_provider   = $('input:text[name=kode_provider]').val();
  var kode_provider2   = $('input:text[name=kode_provider2]').val();
  var tgl_perawatan   = $('input:text[name=tgl_perawatan]').val();
  var jam_rawat       = $('input:text[name=jam_reg]').val();
  var biaya           = $('input:text[name=biaya]').val();
  var aturan_pakai    = $('input:text[name=aturan_pakai]').val();
  var kat             = $('input:hidden[name=kat]').val();
  var jml             = $('input:text[name=jml]').val();
  var nama_racik      = $('input:text[name=nama_racik]').val();
  var keterangan      = $('textarea[name=keterangan]').val();
  var kode_brng       = JSON.stringify($('select[name=kode_brng]').serializeArray());
  var kandungan       = JSON.stringify($('input:text[name=kandungan]').serializeArray());
  var diagnosa_klinis = $('input:text[name=diagnosa_klinis]').val();
  var informasi_tambahan = $('textarea[name=informasi_tambahan]').val();

  var url = baseURL + '/pemeriksaan_ralan/savedetail?t=' + mlite.token;
  $.post(url, {no_rawat : no_rawat,
  kd_jenis_prw   : kd_jenis_prw,
  provider       : provider,
  kode_provider  : kode_provider,
  kode_provider2 : kode_provider2,
  tgl_perawatan  : tgl_perawatan,
  jam_rawat      : jam_rawat,
  biaya          : biaya,
  aturan_pakai   : aturan_pakai,
  kat            : kat,
  jml            : jml,
  nama_racik     : nama_racik,
  keterangan     : keterangan,
  kode_brng      : kode_brng,
  kandungan      : kandungan,
  informasi_tambahan : informasi_tambahan,
  diagnosa_klinis      : diagnosa_klinis
  }, function(data) {
    console.log(data);
    if(typeof ws != 'undefined' && typeof ws.readyState != 'undefined' && ws.readyState == 1){
      if(kat == 'obat' || kat == 'racikan') {
        let payload = {
            'action' : 'permintaan_resep',
            'modul' : 'pemeriksaan_ralan'
        }
        ws.send(JSON.stringify(payload));
      }
      if(kat == 'laboratorium') {
        let payload = {
            'action' : 'permintaan_laboratorium',
            'modul' : 'pemeriksaan_ralan'
        }
        ws.send(JSON.stringify(payload));
      }
      if(kat == 'radiologi') {
        let payload = {
            'action' : 'permintaan_radiologi',
            'modul' : 'pemeriksaan_ralan'
        }
        ws.send(JSON.stringify(payload));
      }
    }
    // tampilkan data
    $("#display").hide();
    var url = baseURL + '/pemeriksaan_ralan/rincian?t=' + mlite.token;
    $.post(url, {no_rawat : no_rawat,
    }, function(data) {
      // tampilkan data
      $("#rincian").html(data).show();
    });
    $('input:hidden[name=kd_jenis_prw]').val("");
    $('input:text[name=nm_perawatan]').val("");
    $.post(baseURL + '/pemeriksaan_ralan/cekwaktu?t=' + mlite.token, {
    } ,function(data) {
      $("#form_rincian #jam_reg").val(data);
    });
    $('input:hidden[name=kat]').val("");
    $('input:text[name=biaya]').val("");
    $('input:text[name=diagnosa_klinis]').val("");
    $('#informasi_tambahan').val("");
    $('input:text[name=nama_provider]').val("");
    $('input:text[name=nama_provider2]').val("");
    $('input:text[name=kode_provider]').val("");
    $('input:text[name=kode_provider2]').val("");
    $('input:text[name=racikan]').val("");
    $('input:text[name=nama_racik]').val("");
    $('#kode_brng').val("");
    $('#keterangan').val("");
    $('input:text[name=kandungan]').val("");
    $('.row_racikan').remove();
    $('#notif').html("<div class=\"alert alert-success alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
    "Data pasien telah disimpan!"+
    "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
    "</div>").show();
  });
});

// ketika tombol hapus ditekan
$("#rincian").on("click",".hapus_detail", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/pemeriksaan_ralan/hapusdetail?t=' + mlite.token;
  var no_rawat = $(this).attr("data-no_rawat");
  var kd_jenis_prw = $(this).attr("data-kd_jenis_prw");
  var tgl_perawatan = $(this).attr("data-tgl_perawatan");
  var jam_rawat = $(this).attr("data-jam_rawat");
  var provider = $(this).attr("data-provider");

  // tampilkan dialog konfirmasi
  bootbox.confirm("Apakah Anda yakin ingin menghapus data ini?", function(result){
    // ketika ditekan tombol ok
    if (result){
      // mengirimkan perintah penghapusan
      $.post(url, {
        no_rawat: no_rawat,
        kd_jenis_prw: kd_jenis_prw,
        tgl_perawatan: tgl_perawatan,
        jam_rawat: jam_rawat,
        provider: provider
      } ,function(data) {
        var url = baseURL + '/pemeriksaan_ralan/rincian?t=' + mlite.token;
        $.post(url, {no_rawat : no_rawat,
        }, function(data) {
          // tampilkan data
          $("#rincian").html(data).show();
        });
        $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
        "Data rincian rawat jalan telah dihapus!"+
        "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
        "</div>").show();
      });
    }
  });
});

// ketika tombol hapus ditekan
$("#rincian").on("click",".hapus_permintaan_lab", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/pemeriksaan_ralan/hapuspermintaanlab?t=' + mlite.token;
  var noorder = $(this).attr("data-noorder");
  var no_rawat = $(this).attr("data-no_rawat");

  // tampilkan dialog konfirmasi
  bootbox.confirm("Apakah Anda yakin ingin menghapus data ini?", function(result){
    // ketika ditekan tombol ok
    if (result){
      // mengirimkan perintah penghapusan
      $.post(url, {
        noorder: noorder,
        no_rawat: no_rawat
      } ,function(data) {
        var url = baseURL + '/pemeriksaan_ralan/rincian?t=' + mlite.token;
        $.post(url, {no_rawat : no_rawat,
        }, function(data) {
          // tampilkan data
          $("#rincian").html(data).show();
        });
        $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
        "Data rincian rawat jalan telah dihapus!"+
        "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
        "</div>").show();
      });
    }
  });
});

// ketika tombol hapus ditekan
$("#rincian").on("click",".hapus_permintaan_rad", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/pemeriksaan_ralan/hapuspermintaanrad?t=' + mlite.token;
  var noorder = $(this).attr("data-noorder");
  var no_rawat = $(this).attr("data-no_rawat");

  // tampilkan dialog konfirmasi
  bootbox.confirm("Apakah Anda yakin ingin menghapus data ini?", function(result){
    // ketika ditekan tombol ok
    if (result){
      // mengirimkan perintah penghapusan
      $.post(url, {
        noorder: noorder,
        no_rawat: no_rawat
      } ,function(data) {
        var url = baseURL + '/pemeriksaan_ralan/rincian?t=' + mlite.token;
        $.post(url, {no_rawat : no_rawat,
        }, function(data) {
          // tampilkan data
          $("#rincian").html(data).show();
        });
        $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
        "Data rincian rawat jalan telah dihapus!"+
        "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
        "</div>").show();
      });
    }
  });
});

// ketika tombol hapus ditekan
$("#rincian").on("click",".hapus_resep_obat", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/pemeriksaan_ralan/hapusresep?t=' + mlite.token;
  var no_resep = $(this).attr("data-no_resep");
  var no_rawat = $(this).attr("data-no_rawat");
  var tgl_peresepan = $(this).attr("data-tgl_peresepan");
  var jam_peresepan = $(this).attr("data-jam_peresepan");

  // tampilkan dialog konfirmasi
  bootbox.confirm("Apakah Anda yakin ingin menghapus data ini?", function(result){
    // ketika ditekan tombol ok
    if (result){
      // mengirimkan perintah penghapusan
      $.post(url, {
        no_resep: no_resep,
        no_rawat: no_rawat,
        tgl_peresepan: tgl_peresepan,
        jam_peresepan: jam_peresepan
      } ,function(data) {
        var url = baseURL + '/pemeriksaan_ralan/rincian?t=' + mlite.token;
        $.post(url, {no_rawat : no_rawat,
        }, function(data) {
          // tampilkan data
          $("#rincian").html(data).show();
        });
        $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
        "Data rincian rawat jalan telah dihapus!"+
        "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
        "</div>").show();
      });
    }
  });
});

// ketika tombol hapus ditekan
$("#rincian").on("click",".hapus_resep_dokter", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/pemeriksaan_ralan/hapusresep?t=' + mlite.token;
  var no_resep = $(this).attr("data-no_resep");
  var no_rawat = $(this).attr("data-no_rawat");
  var kd_jenis_prw = $(this).attr("data-kd_jenis_prw");

  // tampilkan dialog konfirmasi
  bootbox.confirm("Apakah Anda yakin ingin menghapus data ini?", function(result){
    // ketika ditekan tombol ok
    if (result){
      // mengirimkan perintah penghapusan
      $.post(url, {
        no_resep: no_resep,
        no_rawat: no_rawat,
        kd_jenis_prw: kd_jenis_prw
      } ,function(data) {
        var url = baseURL + '/pemeriksaan_ralan/rincian?t=' + mlite.token;
        $.post(url, {no_rawat : no_rawat,
        }, function(data) {
          // tampilkan data
          $("#rincian").html(data).show();
        });
        $('#notif').html("<div class=\"alert alert-danger alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
        "Data rincian rawat jalan telah dihapus!"+
        "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
        "</div>").show();
      });
    }
  });
});

// ketika tombol hapus ditekan
$("#rincian").on("click",".copy_resep", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/pemeriksaan_ralan/copyresep?t=' + mlite.token;
  var no_resep  = $(this).attr("data-no_resep");

  $.post(url, {no_resep: no_resep} ,function(data) {
    // tampilkan data
    $("#display_copy_resep").html(data).show();
  });

});

$("#resep").on("click",".copy_resep", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url = baseURL + '/pemeriksaan_ralan/copyresep?t=' + mlite.token;
  var no_resep  = $(this).attr("data-no_resep");

  $.post(url, {no_resep: no_resep} ,function(data) {
    // tampilkan data
    $("#display_copy_resep").html(data).show();
  });

});

// ketika tombol hapus ditekan
$("#rincian").on("click","#simpan_copy_resep", function(event){
//$('form').on('submit', function(event){
  //alert('submit copy');
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var url_save = baseURL + '/pemeriksaan_ralan/savecopyresep?t=' + mlite.token;
  var url = baseURL + '/pemeriksaan_ralan/rincian?t=' + mlite.token;
  var no_rawat = $('input:text[name=no_rawat]').val();
  var tgl_perawatan   = $('input:text[name=tgl_perawatan]').val();
  var jam_rawat       = $('input:text[name=jam_reg]').val();
  var kode_brng       = JSON.stringify($('input:hidden[name=kode_brng_copyresep]').serializeArray());
  var jml       = JSON.stringify($('input:text[name=jml_copyresep]').serializeArray());
  var aturan_pakai       = JSON.stringify($('input:text[name=aturan_copyresep]').serializeArray());

  $.post(url_save, {no_rawat : no_rawat,
    tgl_perawatan : tgl_perawatan,
    jam_rawat : jam_rawat,
    kode_brng : kode_brng,
    jml : jml,
    aturan_pakai : aturan_pakai
  }, function(data) {
    //alert(data);
    //if(data == 'ErrorError') {
    //  alert('Stok tidak mencukupi pada satu atau lebih obat.');
    //} else {
      $.post(url, {no_rawat : no_rawat,
      }, function(data) {
        // tampilkan data
        $("#rincian").html(data).show();
      });
      $('#notif').html("<div class=\"alert alert-success alert-dismissible fade in\" role=\"alert\" style=\"border-radius:0px;margin-top:-15px;\">"+
      "Data pasien telah disimpan!"+
      "<button type=\"button\" class=\"close\" data-dismiss=\"alert\" aria-label=\"Close\">&times;</button>"+
      "</div>").show();

    //}
  });

});

function bersih(){
  $('input:text[name=no_rawat]').val("");
  $('input:text[name=no_rkm_medis]').val("");
  $('input:text[name=nm_pasien]').val("");
  $('input:text[name=tgl_perawatan]').val("{?=date('Y-m-d')?}");
  $('input:text[name=tgl_registrasi]').val("{?=date('Y-m-d')?}");
  $('input:text[name=tgl_lahir]').val("");
  $('input:text[name=jenis_kelamin]').val("");
  $('input:text[name=alamat]').val("");
  $('input:text[name=telepon]').val("");
  $('input:text[name=pekerjaan]').val("");
  $('input:text[name=layanan]').val("");
  $('input:text[name=obat]').val("");
  $('input:text[name=nama_jenis]').val("");
  $('input:text[name=jumlah_jual]').attr("disabled", true);
  $('input:text[name=potongan]').attr("disabled", true);
  $('input:text[name=harga_jual]').val("");
  $('input:text[name=total]').val("");
  $('input:text[name=no_reg]').val("");
  $('input:text[name=racikan]').val("");
  $('input:text[name=nama_racik]').val("");
  $('#kode_brng').val("");
  $('#keterangan').val("");
  $('input:text[name=kandungan]').val("");
  $('input:text[name=nm_perawatan]').val("");
}

$(document).click(function (event) {
    $('.dropdown-menu[data-parent]').hide();
});
$(document).on('click', '.table-responsive [data-toggle="dropdown"]', function () {
    if ($('body').hasClass('modal-open')) {
        throw new Error("This solution is not working inside a responsive table inside a modal, you need to find out a way to calculate the modal Z-index and add it to the element")
        return true;
    }

    $buttonGroup = $(this).parent();
    if (!$buttonGroup.attr('data-attachedUl')) {
        var ts = +new Date;
        $ul = $(this).siblings('ul');
        $ul.attr('data-parent', ts);
        $buttonGroup.attr('data-attachedUl', ts);
        $(window).resize(function () {
            $ul.css('display', 'none').data('top');
        });
    } else {
        $ul = $('[data-parent=' + $buttonGroup.attr('data-attachedUl') + ']');
    }
    if (!$buttonGroup.hasClass('open')) {
        $ul.css('display', 'none');
        return;
    }
    dropDownFixPosition($(this).parent(), $ul);
    function dropDownFixPosition(button, dropdown) {
        var dropDownTop = button.offset().top + button.outerHeight();
        dropdown.css('top', dropDownTop-60 + "px");
        dropdown.css('left', button.offset().left+7 + "px");
        dropdown.css('position', "absolute");

        dropdown.css('width', dropdown.width());
        dropdown.css('heigt', dropdown.height());
        dropdown.css('display', 'block');
        dropdown.appendTo('body');
    }
});

$('body').on('hidden.bs.modal', '.modal', function () {
    $(this).removeData('bs.modal');
});

$(document).ready(function () {
  var strip_tags = function(str) {
    return (str + '').replace(/<\/?[^>]+(>|$)/g, '')
  };
  var truncate_string = function(str, chars) {
    if ($.trim(str).length <= chars) {
      return str;
    } else {
      return $.trim(str.substr(0, chars)) + '...';
    }
  };
  $('select').selectator('destroy');
  $('.databarang_ajax').selectator({
    labels: {
      search: 'Cari obat...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/pemeriksaan_ralan/ajax?show=databarang&nama_brng=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'kode_brng',
    textField: 'nama_brng'
  });
  $('.master_aturan_pakai').selectator({
    labels: {
      search: 'Cari aturan pakai...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/pemeriksaan_ralan/ajax?show=aturan_pakai&aturan=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'aturan',
    textField: 'aturan'
  });
  $('.jns_perawatan').selectator({
    labels: {
      search: 'Cari perawatan...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/pemeriksaan_ralan/ajax?show=jns_perawatan&nm_perawatan=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'kd_jenis_prw',
    textField: 'nm_perawatan'
  });
  $('.jns_perawatan_lab_ajax').selectator({
    labels: {
      search: 'Cari perawatan lab...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/pemeriksaan_ralan/ajax?show=jns_perawatan_lab&nm_perawatan=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'kd_jenis_prw',
    textField: 'nm_perawatan'
  });
  $('.jns_perawatan_rad_ajax').selectator({
    labels: {
      search: 'Cari perawatan radiologi...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/pemeriksaan_ralan/ajax?show=jns_perawatan_radiologi&nm_perawatan=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'kd_jenis_prw',
    textField: 'nm_perawatan'
  });
  $('.icd10_').selectator({
    labels: {
      search: 'Cari ICD-10...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/pemeriksaan_ralan/ajax?show=icd10&s=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'kd_penyakit',
    textField: 'nm_penyakit'
  });
  $('.icd9_').selectator({
    labels: {
      search: 'Cari ICD-9...'
    },
    load: function (search, callback) {
      if (search.length < this.minSearchLength) return callback();
      $.ajax({
        url: '{?=url()?}/{?=ADMIN?}/pemeriksaan_ralan/ajax?show=icd9&s=' + encodeURIComponent(search) + '&t={?=$_SESSION['token']?}',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
          callback(data.slice(0, 100));
          console.log(data);
        },
        error: function() {
          callback();
        }
      });
    },
    delay: 300,
    minSearchLength: 1,
    valueField: 'kode',
    textField: 'deskripsi_panjang'
  });
  $('select').selectator();
});

$("#form_soap").on("click","#odontogram", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var id_pasien = $('input:text[name=no_rkm_medis]').val();
  var loadURL =  baseURL + '/pemeriksaan_ralan/odontogram/' + id_pasien + '?t=' + mlite.token;

  var modal = $('#odontogramModal');
  var modalContent = $('#odontogramModal .modal-content');

  modal.off('show.bs.modal');
  modal.on('show.bs.modal', function () {
      modalContent.load(loadURL);
  }).modal();
  return false;
});

$("#form_soap").on("click","#jam_rawat", function(event){
    var baseURL = mlite.url + '/' + mlite.admin;
    var url = baseURL + '/pemeriksaan_ralan/cekwaktu?t=' + mlite.token;
    $.post(url, {
    } ,function(data) {
      $("#form_soap #jam_rawat").val(data);
    });
});

$("#form_rincian").on("click","#jam_reg", function(event){
    var baseURL = mlite.url + '/' + mlite.admin;
    var url = baseURL + '/pemeriksaan_ralan/cekwaktu?t=' + mlite.token;
    $.post(url, {
    } ,function(data) {
      $("#form_rincian #jam_reg").val(data);
    });
});

$("#form_soap").on("click",".resume", function(event){
  var baseURL = mlite.url + '/' + mlite.admin;
  event.preventDefault();
  var no_rawat = $('input:text[name=no_rawat]').val().replace(/\//g, '');

  var loadURL =  baseURL + '/pemeriksaan_ralan/resume/' + no_rawat + '?t=' + mlite.token;

  var modal = $('#eresepModal');
  var modalContent = $('#eresepModal .modal-content');

  modal.off('show.bs.modal');
  modal.on('show.bs.modal', function () {
      modalContent.load(loadURL);
  }).modal();
  return false;
});

{if: $mlite.websocket == 'ya'}

  {if: $mlite.websocket_proxy != ''}
    var URL_WEBSOCKET = "{$mlite.websocket_proxy}".replace('http://', 'ws://').replace('https://', 'wss://');
  {else}
    var URL_WEBSOCKET = "ws://<?php echo $_SERVER['HTTP_HOST'] ?>:3892";
  {/if}

  var ws = new WebSocket(URL_WEBSOCKET);
  var baseURL = mlite.url + '/' + mlite.admin;
  
  ws.onopen = function() {
    console.log("✅ WebSocket terhubung untuk panggilan");
  };
  
  ws.onmessage = function(response){
    try{
      output = JSON.parse(response.data);
      if(output['action'] == 'simpan'){
        if(output['modul'] == 'rawat_jalan' || output['modul'] == 'igd'){
          // Jangan reload display jika form SOAP sedang terbuka (mencegah form rusak)
          // #form_soap adalah sibling dari #pemeriksaan_ralan, bukan child
          if (!$('#form_soap').is(':visible')) {
            // Load ke parent container agar tidak ada nested #display duplikat
            $("#pemeriksaan_ralan").load(baseURL + '/pemeriksaan_ralan/display?t=' + mlite.token);
          }
        }
      }
      
    }catch(e){
      console.log(e);
    }
  }
  
  
  ws.onclose = function(){
    // Jika terputus dari websocket server, maka mencoba terhubung kembali.
    var interval_reconnect_ws = setInterval(function(){
      if(ws.readyState != 0){
        if(ws.readyState == 1){ // readyState = 1 (Open) , berarti sudah terhubung dengan websocket. Maka gak perlu interval lagi.
          clearInterval(interval_reconnect_ws);
        }else{
          ws = new WebSocket(URL_WEBSOCKET);	
        }
      }
      
    },5000);
  }
  
  // Variabel nama pemanggil untuk digunakan oleh unified panggil handler
  var nama_pemanggil = "{$mlite.fullname}";

{/if}