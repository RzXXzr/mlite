(function ($) {
  'use strict';

  var baseUrl = mlite.url + '/' + mlite.admin + '/pemeriksaan_ralan_dev';
  var $root = $('#pemeriksaan_ralan_dev');
  var $form = $('#dev-exam-form');
  var $notice = $('#pemeriksaan-dev-notice');
  var socket = null;
  var noticeTimer = null;
  var reconnectTimer = null;
  var reconnectDelay = 1000;
  var pendingCalls = {};
  var websocketEnabled = {if: $mlite.websocket == 'ya'}true{else}false{/if};
  var websocketProxy = {?=json_encode($mlite.websocket_proxy)?};
  var callerName = {?=json_encode($mlite.fullname)?};
  var initialDateStart = $('#dev-date-start').val();
  var initialDateEnd = $('#dev-date-end').val();
  var patientSession = 0;
  var historyPage = 1;
  var historyPages = 1;
  var historyRequest = 0;
  var patientInfoRequest = 0;
  var bpjsRequest = 0;
  var patientInfo = null;
  var bpjsPending = false;
  var copyFields = ['tensi', 'suhu_tubuh', 'nadi', 'respirasi', 'spo2', 'gcs', 'kesadaran', 'tinggi', 'berat', 'lingkar_perut', 'keluhan', 'pemeriksaan'];
  var showExamined = false;
  var kandidatData = null;
  var prolanisStatus = null;
  var pendingArrivalButton = null;
  var currentVisitStatus = '';
  var formWritable = false;
  var lastPatientTrigger = null;

  function endpoint(name) {
    return baseUrl + '/' + name + '?t=' + encodeURIComponent(mlite.token);
  }

  function notify(type, message) {
    if (noticeTimer) { clearTimeout(noticeTimer); }
    $notice.removeClass('alert alert-success alert-danger alert-warning alert-info')
      .addClass('alert alert-' + type).text(message).show();
    noticeTimer = setTimeout(function () { $notice.fadeOut(180); }, type === 'danger' ? 8000 : 5000);
  }

  function api(name, data) {
    return $.ajax({url: endpoint(name), type: 'POST', dataType: 'json', timeout: 30000, data: data || {}});
  }

  function requestError(xhr, fallback) {
    var response = xhr && xhr.responseJSON;
    return response && response.message ? response.message : fallback;
  }

  function clearFormErrors() {
    $('#dev-form-error').hide().empty();
    $form.find('[aria-invalid="true"]').removeAttr('aria-invalid');
    $form.find('.dev-field-error').remove();
  }

  function showFormError(xhr, fallback) {
    var response = xhr && xhr.responseJSON;
    var message = requestError(xhr, fallback);
    var field = response && response.data && response.data.field;
    $('#dev-form-error').text(message).show().focus();
    if (!field) { return; }
    var $field = $form.find('[name="' + field + '"]').first();
    if (!$field.length) { return; }
    $field.attr('aria-invalid', 'true');
    $('<small>', {'class': 'help-block text-danger dev-field-error'}).text(message).appendTo($field.closest('.form-group'));
    $field.focus();
  }

  function setFormWritable(writable, message) {
    formWritable = writable;
    $form.find('input:not([type=hidden]), textarea, select, button').prop('disabled', !writable);
    $('#dev-form-lock').toggle(!writable).find('span').text(message || 'Kunjungan ini hanya dapat dilihat.');
    $('#dev-form-mode').toggleClass('is-readonly', !writable);
  }

  function setButtonBusy(button, busy, loadingHtml) {
    if (busy) {
      if (!button.data('dev-idle-html')) {
        button.data('dev-idle-html', button.html());
      }
      button.prop('disabled', true).html(loadingHtml);
      return;
    }
    button.prop('disabled', false).html(button.data('dev-idle-html'));
  }

  function applyPatientSearch() { applyFilters(); }

  function applyFilters() {
    var query = $.trim($('#dev-patient-search').val()).toLowerCase();
    var visible = 0;
    var $rows = $('#dev-list-container .dev-patient-row');
    $rows.each(function () {
      var $row = $(this);
      var isExamined = $row.attr('data-examined') === '1';
      var matchesSearch = !query || ($row.attr('data-search') || '').toLowerCase().indexOf(query) !== -1;
      var show = matchesSearch && (!isExamined || showExamined);
      $row.toggle(show);
      if (show) { visible += 1; }
    });
    var allExaminedHidden = !showExamined && visible === 0 && $rows.filter('[data-examined="1"]').length > 0;
    if (allExaminedHidden) {
      $('#dev-search-empty-title').text('Tidak ada pasien berstatus Belum');
      $('#dev-search-empty-hint').html('Klik <strong>Tampilkan semua status</strong> untuk melihat daftar lengkap.');
    } else {
      $('#dev-search-empty-title').text('Pasien tidak ditemukan');
      $('#dev-search-empty-hint').text('Coba ubah filter pencarian atau klik Tampilkan semua status.');
    }
    $('#dev-search-empty').toggle($rows.length > 0 && visible === 0);
    $('#dev-toggle-examined .fa').removeClass('fa-eye fa-eye-slash').addClass(showExamined ? 'fa-eye-slash' : 'fa-eye');
    $('#dev-toggle-examined .dev-toggle-label').text(showExamined ? ' Tampilkan hanya Belum' : ' Tampilkan semua status');
    $('#dev-toggle-examined').toggleClass('is-showing-all', showExamined);
  }

  function refreshList() {
    var $button = $('#dev-refresh-list');
    setButtonBusy($button, true, '<i class="fa fa-spinner fa-spin"></i> Memuat...');
    $('#dev-list-container').attr('aria-busy', 'true');
    $.ajax({
      url: endpoint('display'),
      type: 'POST',
      data: {
        periode_rawat_jalan: $('#dev-date-start').val(),
        periode_rawat_jalan_akhir: $('#dev-date-end').val(),
        status_periksa: $('#dev-status').val()
      }
    }).done(function (html) {
      $('#dev-list-container').html(html);
      if ($('#dev-status').val()) { showExamined = true; }
      applyPatientSearch();
    }).fail(function () {
      notify('danger', 'Daftar pasien tidak dapat dimuat.');
    }).always(function () {
      setButtonBusy($button, false);
      $('#dev-list-container').removeAttr('aria-busy');
    });
  }

  function resetExamForm() {
    $form[0].reset();
    $form.find('[name=original_tgl_perawatan], [name=original_jam_rawat]').val('');
    $('#dev-allergy-summary').text('Memuat data alergi...');
    $('#dev-copy-origin').empty().hide();
    $form.find('.dev-copied-field').removeClass('dev-copied-field');
    $('#dev-allergy-banner').removeClass('is-clear is-present').addClass('is-loading');
    $('#dev-allergy-editor').prop('open', false);
    $('#dev-form-mode').removeClass('is-edit is-copy is-saved is-error').text('Catatan baru');
    $('#dev-save-state').removeClass('is-saved is-edit is-copy is-error').html('<i class="fa fa-pencil-square-o"></i> Draf belum disimpan');
    clearFormErrors();
  }

  function allergySummary(data) {
    var food = {'00': '', '01': 'Seafood', '02': 'Gandum', '03': 'Susu Sapi', '04': 'Kacang-Kacangan', '05': data.alergi_makanan_lainnya || 'Makanan lain'};
    var air = {'00': '', '01': 'Udara Panas', '02': 'Udara Dingin', '03': 'Udara Kotor'};
    var medicine = {'00': '', '01': 'Antibiotik', '02': 'Antiinflamasi', '03': 'Non Steroid', '04': 'Aspirin', '05': 'Kortikosteroid', '06': 'Insulin', '07': data.alergi_obat_lainnya || 'Obat lain'};
    var parts = [food[data.alergi_makanan] || '', air[data.alergi_udara] || '', medicine[data.alergi_obat] || ''].filter(Boolean);
    return parts.length ? parts.join(', ') : 'Tidak Ada';
  }

  function toggleAllergyOtherFields() {
    $('.dev-other-food').toggle($form.find('[name=alergi_makanan]').val() === '05');
    $('.dev-other-drug').toggle($form.find('[name=alergi_obat]').val() === '07');
  }

  function fillAllergy(data) {
    data = data || {};
    $form.find('[name=alergi_makanan]').val(data.alergi_makanan || '00');
    $form.find('[name=alergi_makanan_lainnya]').val(data.alergi_makanan_lainnya || '');
    $form.find('[name=alergi_udara]').val(data.alergi_udara || '00');
    $form.find('[name=alergi_udara_lainnya]').val(data.alergi_udara_lainnya || '');
    $form.find('[name=alergi_obat]').val(data.alergi_obat || '00');
    $form.find('[name=alergi_obat_lainnya]').val(data.alergi_obat_lainnya || '');
    toggleAllergyOtherFields();
    var summary = allergySummary(data);
    showAllergySummary(summary);
  }

  function showAllergySummary(summary) {
    $('#dev-allergy-summary').text(summary);
    $form.find('[name=alergi]').val(summary.length > 50 ? 'Lihat profil alergi' : summary);
    $('#dev-allergy-banner').removeClass('is-loading is-clear is-present')
      .addClass(summary.toLowerCase() === 'tidak ada' ? 'is-clear' : 'is-present');
  }

  function loadAllergy() {
    var session = patientSession;
    api('getalergi', {no_rawat: $form.find('[name=no_rawat]').val()}).done(function (response) {
      if (session !== patientSession) { return; }
      fillAllergy(response.data);
    }).fail(function (xhr) {
      if (session !== patientSession) { return; }
      $('#dev-allergy-summary').text('Data alergi tidak dapat dimuat.');
      notify('warning', requestError(xhr, 'Data alergi tidak dapat dimuat.'));
    });
  }

  function loadHistory() {
    var session = patientSession;
    $('#dev-history').attr('aria-busy', 'true');
    $.ajax({
      url: endpoint('riwayatpemeriksaan'),
      type: 'POST',
      data: {no_rawat: $form.find('[name=no_rawat]').val()}
    }).done(function (html) {
      if (session !== patientSession) { return; }
      $('#dev-history').html(html);
      if (currentVisitStatus !== 'Belum') {
        var $latest = $('#dev-history .dev-history-card').first();
        if ($latest.length) {
          populateExamRecord($latest);
          $('#dev-form-mode').removeClass('is-edit is-copy is-saved is-error').addClass('is-readonly').text('Lihat catatan');
          $('#dev-save-state').removeClass('is-saved is-edit is-copy is-error').html('<i class="fa fa-eye"></i> Catatan ' + $latest.attr('data-date') + ' ' + $latest.attr('data-time'));
        }
      }
    }).fail(function (xhr) {
      if (session !== patientSession) { return; }
      $('#dev-history').text(requestError(xhr, 'Riwayat pemeriksaan tidak dapat dimuat.'));
    }).always(function () { if (session === patientSession) { $('#dev-history').removeAttr('aria-busy'); } });
  }

  function populateExamRecord(source) {
    $form.find('[name=original_tgl_perawatan]').val(source.attr('data-date'));
    $form.find('[name=original_jam_rawat]').val(source.attr('data-time'));
    $form.find('[name=tensi]').val(source.attr('data-tensi'));
    $form.find('[name=suhu_tubuh]').val(source.attr('data-suhu'));
    $form.find('[name=nadi]').val(source.attr('data-nadi'));
    $form.find('[name=respirasi]').val(source.attr('data-respirasi'));
    $form.find('[name=spo2]').val(source.attr('data-spo2'));
    $form.find('[name=gcs]').val(source.attr('data-gcs'));
    $form.find('[name=kesadaran]').val(source.attr('data-kesadaran'));
    $form.find('[name=tinggi]').val(source.attr('data-tinggi'));
    $form.find('[name=berat]').val(source.attr('data-berat'));
    $form.find('[name=lingkar_perut]').val(source.attr('data-lingkar'));
    $form.find('[name=alergi]').val(source.attr('data-alergi'));
    $form.find('[name=keluhan]').val(source.attr('data-keluhan'));
    $form.find('[name=pemeriksaan]').val(source.attr('data-pemeriksaan'));
  }

  function displayValue(value) {
    if (value == null || typeof value === 'object') { return 'Tidak ada informasi'; }
    var text = $.trim(String(value));
    return !text || text === '-' || text.toLowerCase() === 'null' ? 'Tidak ada informasi' : text;
  }

  function participantFlag(value, yes, no) {
    var text = displayValue(value);
    if (text === 'Tidak ada informasi') { return text; }
    var normalized = text.toLowerCase();
    if (['true', '1', 'ya', 'y', 'yes'].indexOf(normalized) !== -1) { return yes; }
    if (['false', '0', 'tidak', 'n', 'no'].indexOf(normalized) !== -1) { return no; }
    // Preserve PCare descriptions/codes such as DM or HT; do not guess their meaning.
    return text;
  }

  function clearBpjsFields(label) {
    $('#dev-bpjs-provider, #dev-bpjs-type, #dev-bpjs-active, #dev-bpjs-prb, #dev-bpjs-prolanis').text(label);
    $('#dev-bpjs-provider-code').empty();
  }

  function resetPatientInfo() {
    patientInfoRequest += 1;
    bpjsRequest += 1;
    bpjsPending = false;
    patientInfo = null;
    kandidatData = null;
    prolanisStatus = null;
    $('#dev-prolanis-candidate').hide();
    $('#dev-patient-age, #dev-patient-birth, #dev-patient-blood, #dev-patient-payer, #dev-patient-card').text('Memuat...');
    $('#dev-patient-info-error, #dev-bpjs-panel').hide();
    clearBpjsFields('Belum diperiksa');
    $('#dev-bpjs-message').empty();
    $('#dev-bpjs-state').removeClass('is-success is-error').text('Belum diperiksa');
    $('#dev-refresh-bpjs').prop('disabled', true);
  }

  function loadPatientInfo() {
    resetPatientInfo();
    var request = patientInfoRequest;
    var session = patientSession;
    api('informasipasien', {no_rawat: $form.find('[name=no_rawat]').val()}).done(function (response) {
      if (session !== patientSession || request !== patientInfoRequest) { return; }
      patientInfo = response.data;
      $('#dev-patient-name').text(patientInfo.name);
      $('#dev-patient-rm').text('No. RM: ' + patientInfo.no_rkm_medis);
      $('#dev-patient-age').text(patientInfo.age);
      $('#dev-patient-birth').text(patientInfo.birth_date);
      $('#dev-patient-blood').text(patientInfo.blood_group);
      $('#dev-patient-payer').text(patientInfo.payer);
      $('#dev-patient-card').text(patientInfo.card_number);
      $('#dev-bpjs-panel').toggle(patientInfo.is_bpjs);
      if (patientInfo.is_bpjs) {
        if (patientInfo.pcare.url) { loadBpjsMembership(); }
        else { $('#dev-bpjs-message').text(patientInfo.pcare.message); }
      }
    }).fail(function (xhr) {
      if (session !== patientSession || request !== patientInfoRequest) { return; }
      $('#dev-patient-age, #dev-patient-birth, #dev-patient-blood, #dev-patient-payer, #dev-patient-card').text('Gagal dimuat');
      $('#dev-patient-info-error').show().find('span').text(requestError(xhr, 'Identitas pasien tidak dapat dimuat.'));
    });
  }

  function loadBpjsMembership() {
    if (!patientInfo || !patientInfo.is_bpjs || !patientInfo.pcare.url || bpjsPending) { return; }
    var session = patientSession;
    var request = ++bpjsRequest;
    var info = patientInfo;
    bpjsPending = true;
    clearBpjsFields('Memeriksa...');
    $('#dev-patient-card').text(info.card_number);
    $('#dev-refresh-bpjs').prop('disabled', true);
    $('#dev-bpjs-state').removeClass('is-success is-error').text('Memeriksa PCare...');
    $('#dev-bpjs-message').text('Form pemeriksaan tetap dapat diisi selama pengecekan.');
    function failed(message) {
      clearBpjsFields('Belum terverifikasi');
      $('#dev-bpjs-state').removeClass('is-success').addClass('is-error').text('Belum terverifikasi');
      $('#dev-bpjs-message').text(message);
    }
    $.ajax({url: info.pcare.url, type: 'GET', dataType: 'json', timeout: 35000, cache: false}).done(function (response) {
      if (session !== patientSession || request !== bpjsRequest) { return; }
      var member = response && response.response;
      if (!response || !response.metaData || String(response.metaData.code) !== '200' || !member || typeof member !== 'object' || !/^\d{13}$/.test(String(member.noKartu || ''))) {
        failed('PCare belum memberikan data peserta yang valid. Coba cek ulang atau periksa nomor kartu pada data pasien.');
        return;
      }
      if (info.pcare.type === 'noka' && String(member.noKartu) !== info.card_number) {
        failed('Nomor kartu pada respons PCare tidak sesuai dengan pasien terpilih. Hasil tidak ditampilkan.');
        return;
      }
      var provider = member.kdProviderPst || {};
      $('#dev-bpjs-provider').text(displayValue(provider.nmProvider));
      $('#dev-bpjs-provider-code').text(provider.kdProvider ? 'Kode: ' + provider.kdProvider : '');
      $('#dev-bpjs-type').text(displayValue((member.jnsPeserta || {}).nama));
      $('#dev-bpjs-active').text(participantFlag(member.aktif, 'Aktif', 'Tidak aktif'));
      $('#dev-bpjs-prb').text(participantFlag(member.pstPrb, 'Terdaftar', 'Tidak terdaftar'));
      $('#dev-bpjs-prolanis').text(participantFlag(member.pstProl, 'Terdaftar', 'Tidak terdaftar'));
      var prolResult = participantFlag(member.pstProl, '__Y__', '__N__');
      // Non-boolean codes like "DM,HT" are also positive Prolanis membership.
      prolanisStatus = (prolResult === '__N__') ? 'tidak' : (prolResult === 'Tidak ada informasi') ? null : 'terdaftar';
      evaluateProlanisCandidate();
      $('#dev-patient-card').text(member.noKartu);
      $('#dev-bpjs-state').addClass('is-success').text('Data diterima');
      $('#dev-bpjs-message').text('Sumber: PCare · Diperiksa ' + new Date().toLocaleString('id-ID') + '. Nilai kosong berarti informasi tidak diberikan oleh PCare.');
    }).fail(function (xhr) {
      if (session !== patientSession || request !== bpjsRequest) { return; }
      failed(xhr.status === 401 || xhr.status === 403 ? 'Akses PCare tidak tersedia untuk sesi ini. Periksa sesi atau izin modul PCare.' : 'Pengecekan PCare gagal atau melewati batas waktu. Klik Cek ulang untuk mencoba lagi.');
    }).always(function () {
      if (session !== patientSession || request !== bpjsRequest) { return; }
      bpjsPending = false;
      $('#dev-refresh-bpjs').prop('disabled', false);
    });
  }

  function loadKandidatProlanis() {
    var session = patientSession;
    api('kandidatprolanis', {no_rawat: $form.find('[name=no_rawat]').val()}).done(function (response) {
      if (session !== patientSession) { return; }
      kandidatData = response.data;
      evaluateProlanisCandidate();
    });
  }

  function evaluateProlanisCandidate() {
    if (!kandidatData) { return; }
    var alreadyRegistered = prolanisStatus === 'terdaftar';
    var showHt = kandidatData.has_ht && !alreadyRegistered;
    var showDm = kandidatData.has_dm && !alreadyRegistered;
    $('#dev-candidate-ht-codes').text((kandidatData.ht_codes || []).join(', '));
    $('#dev-candidate-dm-codes').text((kandidatData.dm_codes || []).join(', '));
    $('#dev-candidate-ht').toggle(showHt);
    $('#dev-candidate-dm').toggle(showDm);
    $('#dev-prolanis-candidate').toggle(showHt || showDm);
  }

  function openPatient(button, visitState) {
    patientSession += 1;
    lastPatientTrigger = button && button.length ? button : lastPatientTrigger;
    currentVisitStatus = visitState && visitState.visit_status ? visitState.visit_status : button.attr('data-status');
    resetExamForm();
    resetPatientHistory();
    $('#dev-history').html('<div class="dev-history-loading"><i class="fa fa-spinner fa-spin"></i> Memuat riwayat pemeriksaan...</div>');
    $form.find('[name=no_rawat]').val(button.attr('data-no-rawat'));
    $('#dev-patient-name').text(button.attr('data-patient'));
    $('#dev-patient-rm').text('No. RM: ' + button.attr('data-no-rm'));
    $('#dev-patient-poli').text(button.attr('data-poli'));
    $root.find('.dev-list-workspace').hide();
    $('#dev-exam-container').show();
    if (currentVisitStatus === 'Belum') {
      setFormWritable(true);
    } else {
      setFormWritable(false, currentVisitStatus === 'Berkas Dikirim'
        ? 'Berkas telah dikirim ke poli. Pilih Edit pada catatan tersimpan untuk melakukan koreksi.'
        : 'Status kunjungan ' + currentVisitStatus + '; form ditampilkan hanya untuk referensi.');
    }
    loadPatientInfo();
    loadAllergy();
    loadHistory();
    loadPatientHistory(1);
    loadKandidatProlanis();
    window.scrollTo(0, $('#dev-exam-container').offset().top - 20);
    setTimeout(function () { $('#dev-exam-title').focus(); }, 50);
  }

  function requestOpenPatient(button) {
    lastPatientTrigger = button;
    api('statuskunjungan', {no_rawat: button.attr('data-no-rawat')}).done(function (response) {
      if (response.data.needs_arrival_decision) {
        pendingArrivalButton = button;
        $('#dev-arrival-patient').text(button.attr('data-patient'));
        $('#dev-cancel-reason').val('');
        $('#dev-cancel-reason-wrap, #dev-confirm-cancel').hide();
        $('#dev-show-cancel-reason, #dev-continue-exam').show();
        $('#dev-arrival-modal').modal('show');
        return;
      }
      openPatient(button, response.data);
    }).fail(function (xhr) {
      notify('danger', requestError(xhr, 'Status kunjungan tidak dapat diperiksa.'));
    });
  }

  function closeExam() {
    patientSession += 1;
    resetPatientInfo();
    resetPatientHistory();
    $('#dev-exam-container').hide();
    $root.find('.dev-list-workspace').show();
    $('#dev-history').empty();
    resetExamForm();
    window.scrollTo(0, $root.offset().top - 20);
    if (lastPatientTrigger && lastPatientTrigger.length && $.contains(document, lastPatientTrigger[0])) {
      lastPatientTrigger.focus();
    }
  }

  function resetPatientHistory() {
    historyRequest += 1;
    historyPage = 1;
    historyPages = 1;
    $('#dev-all-visits').empty();
    $('#dev-full-erm').removeAttr('href').hide();
    $root.find('.dev-current-records').prop('open', true);
  }

  function historyError(container, message, retry) {
    container.empty().append($('<p>', {'class': 'text-danger', role: 'alert'}).text(message));
    $('<button>', {type: 'button', 'class': 'btn btn-default'}).text('Coba lagi').on('click', retry).appendTo(container);
  }

  function loadPatientHistory(page) {
    var session = patientSession;
    var request = ++historyRequest;
    var noRawat = $form.find('[name=no_rawat]').val();
    var $list = $('#dev-all-visits');
    $('#dev-history-prev, #dev-history-next').prop('disabled', true);
    $('#dev-history-page').text('Memuat kunjungan...');
    $list.attr('aria-busy', 'true');
    $list.html('<div class="dev-history-loading"><i class="fa fa-spinner fa-spin"></i> Memuat semua kunjungan...</div>');
    api('riwayatpasien', {no_rawat: noRawat, page: page}).done(function (response) {
      if (session !== patientSession || request !== historyRequest) { return; }
      var data = response.data;
      historyPage = data.page;
      historyPages = data.pages;
      $('#dev-full-erm').attr('href', data.erm_url).show();
      $('#dev-history-page').text(data.page + ' / ' + data.pages + ' · ' + data.total + ' kunjungan');
      $('#dev-history-prev').prop('disabled', data.page <= 1);
      $('#dev-history-next').prop('disabled', data.page >= data.pages);
      $list.empty();
      if (!data.visits.length) { $list.append($('<p>').text('Belum ada kunjungan.')); }
      var firstPrevious = false;
      data.visits.forEach(function (visit, index) {
        var $card = $('<article>', {'class': 'dev-visit-card'});
        var detailId = 'dev-visit-detail-' + request + '-' + index;
        var $toggle = $('<button>', {type: 'button', 'class': 'dev-visit-toggle', 'aria-expanded': 'false', 'aria-controls': detailId});
        $('<i>', {'class': 'fa fa-angle-down dev-visit-chevron', 'aria-hidden': 'true'}).appendTo($toggle);
        $('<strong>').text(visit.tgl_registrasi + ' · ' + visit.jam_reg + (visit.no_rawat === noRawat ? ' — Kunjungan aktif' : '')).appendTo($toggle);
        $('<span>').text(visit.no_rawat + ' · ' + (visit.status_lanjut || '-') + ' · ' + (visit.nm_poli || '-') + ' · ' + (visit.stts || '-')).appendTo($toggle);
        $('<span>').text((visit.nm_dokter || '-') + ' · ' + (visit.png_jawab || '-')).appendTo($toggle);
        var $detail = $('<div>', {id: detailId, 'class': 'dev-visit-detail'}).hide();
        $toggle.on('click', function () {
          var expanded = $toggle.attr('aria-expanded') === 'true';
          $toggle.attr('aria-expanded', String(!expanded));
          $detail.toggle(!expanded);
          if (!expanded && !$detail.data('loaded') && !$detail.data('loading')) {
            loadVisitDetail(visit, $detail, session, request);
          }
        });
        $card.append($toggle, $detail).appendTo($list);
        // Show the most recent earlier visit beside the form without another click.
        if (!firstPrevious && visit.no_rawat !== noRawat) {
          firstPrevious = true;
          $toggle.attr('aria-expanded', 'true');
          $detail.show();
          loadVisitDetail(visit, $detail, session, request);
        }
      });
    }).fail(function (xhr) {
      if (session !== patientSession || request !== historyRequest) { return; }
      $('#dev-history-page').text('Riwayat belum dimuat');
      historyError($list, requestError(xhr, 'Riwayat pasien tidak dapat dimuat.'), function () { loadPatientHistory(page); });
    }).always(function () { if (session === patientSession && request === historyRequest) { $list.removeAttr('aria-busy'); } });
  }

  function loadVisitDetail(visit, container, session, request) {
    container.data('loading', true).html('<p><i class="fa fa-spinner fa-spin"></i> Memuat rincian kunjungan...</p>');
    api('detailriwayat', {no_rawat: $form.find('[name=no_rawat]').val(), source_no_rawat: visit.no_rawat}).done(function (response) {
      if (session !== patientSession || request !== historyRequest) { return; }
      container.empty().data('loaded', true);
      renderVisitDetail(container, response.data, visit, session);
    }).fail(function (xhr) {
      if (session !== patientSession || request !== historyRequest) { return; }
      historyError(container, requestError(xhr, 'Rincian kunjungan tidak dapat dimuat.'), function () { loadVisitDetail(visit, container, session, request); });
    }).always(function () { container.data('loading', false); });
  }

  function renderVisitDetail(container, data, visit, session) {
    $('<h4>').text('Pemeriksaan / SOAP').appendTo(container);
    if (!data.records.length) { $('<p>').text('Belum ada catatan pemeriksaan pada kunjungan ini.').appendTo(container); }
    var vitals = {tensi: 'TD', suhu_tubuh: 'Suhu (°C)', nadi: 'Nadi (/mnt)', respirasi: 'RR (/mnt)', spo2: 'SpO2 (%)', gcs: 'GCS', kesadaran: 'Kesadaran', tinggi: 'TB (cm)', berat: 'BB (kg)', lingkar_perut: 'LP (cm)', alergi: 'Alergi saat dicatat'};
    var notes = {keluhan: 'Keluhan / anamnesa', pemeriksaan: 'Temuan obyektif', penilaian: 'Penilaian', rtl: 'Rencana tindak lanjut', instruksi: 'Instruksi', evaluasi: 'Evaluasi'};
    data.records.forEach(function (record) {
      var $card = $('<article>', {'class': 'dev-long-record'});
      var $header = $('<header>').appendTo($card);
      $('<strong>').text(record.tgl_perawatan + ' ' + record.jam_rawat + ' · ' + (record.nama_petugas || record.nip || '-') + ' · ' + record.jenis).appendTo($header);
      if (record.can_copy) {
        $('<button>', {type: 'button', 'class': 'btn btn-primary dev-copy-history', title: 'Isi kolom kosong sebagai catatan baru; periksa ulang hasil pengukuran'}).text('Salin ke form').data('record', record).data('visit', visit).data('session', session).appendTo($header);
      }
      var $vitals = $('<div>', {'class': 'dev-vital-pills'}).appendTo($card);
      Object.keys(vitals).forEach(function (field) {
        if (record[field] == null || record[field] === '' || record[field] === '-') { return; }
        $('<span>').text(vitals[field] + ': ' + record[field]).appendTo($vitals);
      });
      var $notes = $('<div>', {'class': 'dev-soap-notes'}).appendTo($card);
      Object.keys(notes).forEach(function (field) {
        if (field !== 'keluhan' && field !== 'pemeriksaan' && (!record[field] || record[field] === '-')) { return; }
        var $note = $('<div>').appendTo($notes);
        $('<strong>').text(notes[field]).appendTo($note);
        $('<p>').text(record[field] || '—').appendTo($note);
      });
      container.append($card);
    });
    function renderList(title, rows, format) {
      if (!rows.length) { return; }
      $('<h4>').text(title).appendTo(container);
      var $list = $('<ul>').appendTo(container);
      rows.forEach(function (row) { $('<li>').text(format(row)).appendTo($list); });
    }
    renderList('Diagnosis', data.diagnoses, function (row) { return row.kd_penyakit + ' — ' + (row.nm_penyakit || '-'); });
    renderList('Prosedur', data.procedures, function (row) { return row.kode + ' — ' + (row.deskripsi_panjang || '-'); });
    renderList('Obat resep reguler', data.medicines, function (row) { return (row.nama_brng || '-') + ' · Jumlah: ' + row.jml + ' · ' + (row.aturan_pakai || '-') + ' · Resep ' + row.no_resep; });
  }

  function copyHistoryRecord(button) {
    if (currentVisitStatus !== 'Belum' || !formWritable) {
      notify('warning', 'Riwayat hanya dapat disalin saat kunjungan masih berstatus Belum.');
      return;
    }
    if (button.data('session') !== patientSession) { return; }
    var record = button.data('record');
    var visit = button.data('visit');
    var copied = 0;
    copyFields.forEach(function (field) {
      var $field = $form.find('[name="' + field + '"]');
      var current = $.trim($field.val() || '');
      var value = record[field] == null ? '' : $.trim(String(record[field]));
      if ((current === '' || current === '-') && value !== '' && value !== '-') {
        if (field === 'kesadaran' && ['Compos Mentis', 'Somnolence', 'Sopor', 'Coma'].indexOf(value) === -1) { return; }
        $field.val(value).addClass('dev-copied-field');
        copied += 1;
      }
    });
    if (!copied) { notify('info', 'Tidak ada kolom kosong yang dapat diisi dari catatan ini. Isian form tetap dipertahankan.'); return; }
    // Copy creates a draft for the active encounter; it never edits the source.
    $form.find('[name=original_tgl_perawatan], [name=original_jam_rawat]').val('');
    $('#dev-copy-origin').empty()
      .append($('<strong>').text(copied + ' kolom disalin'))
      .append(document.createTextNode(' dari ' + visit.no_rawat + ' · ' + record.tgl_perawatan + ' ' + record.jam_rawat + '.'))
      .append($('<span class="dev-copy-meta">').text('Verifikasi TTV sesuai pengukuran saat ini. Isian terisi & alergi terbaru tidak ditimpa. Belum tersimpan.'))
      .show();
    $('#dev-form-mode').removeClass('is-edit is-saved is-error').addClass('is-copy').text('Draf dari riwayat');
    $('#dev-save-state').removeClass('is-saved is-edit is-error').addClass('is-copy').html('<i class="fa fa-copy"></i> Hasil salinan belum disimpan');
    button.text('Tersalin ke form');
    notify('info', 'Data riwayat disalin ke kolom kosong. Verifikasi sebelum menyimpan pemeriksaan baru.');
  }

  function speakLocally(patient, number, button, reason) {
    if (!('speechSynthesis' in window)) {
      notify('warning', reason + ' Browser tidak mendukung Text-to-Speech.');
      button.prop('disabled', false).html('<i class="fa fa-bullhorn"></i> Panggil');
      return;
    }
    window.speechSynthesis.cancel();
    var speech = new SpeechSynthesisUtterance(patient + ', nomor antrian ' + number + ', silakan menuju pemeriksaan awal');
    speech.lang = 'id-ID';
    speech.rate = 0.8;
    speech.onstart = function () { button.html('<i class="fa fa-volume-up"></i> Memanggil lokal...'); };
    speech.onend = function () { button.prop('disabled', false).html('<i class="fa fa-bullhorn"></i> Panggil'); };
    speech.onerror = function () { button.prop('disabled', false).html('<i class="fa fa-bullhorn"></i> Panggil'); };
    window.speechSynthesis.speak(speech);
    notify('warning', reason + ' Panggilan diputar melalui perangkat ini.');
  }

  function websocketUrl() {
    if (websocketProxy) {
      return websocketProxy.replace('http://', 'ws://').replace('https://', 'wss://');
    }
    return 'ws://' + window.location.hostname + ':3892';
  }

  function setSocketStatus(message, good) {
    var $status = $('#dev-ws-status');
    $status.removeClass('is-pending is-online is-offline').addClass(good ? 'is-online' : 'is-offline');
    $status.empty().append($('<span>', {'class': 'dev-connection-dot'})).append($('<span>').text(message));
  }

  function handleSocketMessage(event) {
    var message;
    try {
      message = JSON.parse(event.data);
    } catch (ignore) {
      return;
    }
    if (message.action === 'panggil_ack' && (!message.modul || message.modul === 'pemeriksaan_awal') && message.msgId && pendingCalls[message.msgId]) {
      var pending = pendingCalls[message.msgId];
      clearTimeout(pending.timer);
      delete pendingCalls[message.msgId];
      pending.button.prop('disabled', true).html('<i class="fa fa-check"></i> Dipanggil via anjungan');
      notify('success', 'Panggilan diterima oleh anjungan.');
      setTimeout(function () { pending.button.prop('disabled', false).html('<i class="fa fa-bullhorn"></i> Panggil'); }, 5000);
      return;
    }
    if (message.action === 'simpan' && (message.modul === 'rawat_jalan' || message.modul === 'igd') && !$('#dev-exam-container').is(':visible')) {
      refreshList();
    }
  }

  function connectSocket() {
    if (!websocketEnabled || !window.WebSocket) {
      setSocketStatus('Anjungan tidak terhubung; TTS lokal tersedia.', false);
      return;
    }
    try {
      socket = new WebSocket(websocketUrl());
    } catch (ignore) {
      setSocketStatus('Anjungan tidak dapat dihubungkan; mencoba kembali.', false);
      scheduleReconnect();
      return;
    }
    socket.onopen = function () {
      reconnectDelay = 1000;
      setSocketStatus('Anjungan terhubung.', true);
    };
    socket.onmessage = handleSocketMessage;
    socket.onerror = function () { setSocketStatus('Koneksi anjungan bermasalah.', false); };
    socket.onclose = function () {
      setSocketStatus('Anjungan terputus; mencoba kembali.', false);
      scheduleReconnect();
    };
  }

  function scheduleReconnect() {
    if (reconnectTimer || !websocketEnabled) { return; }
    var delay = reconnectDelay;
    reconnectDelay = Math.min(reconnectDelay * 2, 30000);
    reconnectTimer = setTimeout(function () { reconnectTimer = null; connectSocket(); }, delay);
  }

  function callQueue(button) {
    var patient = button.attr('data-patient');
    var number = button.attr('data-no-reg');
    var messageId = 'panggil_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
    if (button.attr('data-fktp-registered') === '1') {
      api('antreanpanggil', {no_rawat: button.attr('data-no-rawat'), message_id: messageId}).done(function () {
        notify('success', 'Panggilan juga dikirim ke Antrean FKTP.');
      }).fail(function (xhr) {
        notify('warning', requestError(xhr, 'Panggilan lokal berjalan, tetapi sinkronisasi FKTP gagal.'));
      });
    }
    if (!socket || socket.readyState !== WebSocket.OPEN) {
      button.prop('disabled', true);
      speakLocally(patient, number, button, 'Anjungan tidak terhubung.');
      return;
    }
    button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menunggu anjungan...');
    pendingCalls[messageId] = {
      button: button,
      timer: setTimeout(function () {
        var pending = pendingCalls[messageId];
        if (!pending) { return; }
        delete pendingCalls[messageId];
        speakLocally(patient, number, pending.button, 'Anjungan belum mengonfirmasi dalam 3 detik.');
      }, 3000)
    };
    socket.send(JSON.stringify({
      action: 'panggil',
      modul: 'pemeriksaan_awal',
      msgId: messageId,
      data: {nm_pasien: patient, nm_poli: button.attr('data-poli'), no_reg: number, nm_pemanggil: callerName}
    }));
  }

  function sendStatusUpdate(noRawat, status) {
    if (!socket || socket.readyState !== WebSocket.OPEN) { return; }
    socket.send(JSON.stringify({
      action: 'update_status', modul: 'pemeriksaan_ralan_dev',
      msgId: 'status_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8),
      data: {no_rawat: noRawat, new_status: status, nm_pemanggil: callerName}
    }));
  }

  function runFktpAction(button, endpointName, loadingText) {
    setButtonBusy(button, true, '<i class="fa fa-spinner fa-spin"></i> ' + loadingText);
    api(endpointName, {no_rawat: button.attr('data-no-rawat')}).done(function (response) {
      notify('success', response.message);
      refreshList();
    }).fail(function (xhr) {
      notify('danger', requestError(xhr, 'Sinkronisasi Antrean FKTP gagal.'));
    }).always(function () { setButtonBusy(button, false); });
  }

  $root.on('click', '#dev-toggle-examined', function () { showExamined = !showExamined; applyFilters(); });
  $root.on('click', '#dev-refresh-list', function () { refreshList(); });
  $root.on('input', '#dev-patient-search', applyPatientSearch);
  $root.on('click', '#dev-reset-filter', function () {
    $('#dev-date-start').val(initialDateStart);
    $('#dev-date-end').val(initialDateEnd);
    $('#dev-status').val('');
    $('#dev-patient-search').val('');
    showExamined = false;
    refreshList();
  });
  $root.on('click', '.dev-select-patient', function () { requestOpenPatient($(this)); });
  $root.on('click', '#dev-continue-exam', function () {
    var button = pendingArrivalButton;
    if (!button) { return; }
    $('#dev-arrival-modal').modal('hide');
    openPatient(button, {visit_status: 'Belum'});
    pendingArrivalButton = null;
  });
  $root.on('click', '#dev-show-cancel-reason', function () {
    $('#dev-cancel-reason-wrap, #dev-confirm-cancel').show();
    $('#dev-show-cancel-reason, #dev-continue-exam').hide();
    $('#dev-cancel-reason').focus();
  });
  $root.on('click', '#dev-confirm-cancel', function () {
    var $button = $(this);
    var patientButton = pendingArrivalButton;
    if (!patientButton) { return; }
    var reason = $.trim($('#dev-cancel-reason').val());
    if (reason.length < 5) {
      $('#dev-cancel-reason').attr('aria-invalid', 'true').focus();
      notify('warning', 'Alasan batal minimal 5 karakter.');
      return;
    }
    setButtonBusy($button, true, '<i class="fa fa-spinner fa-spin"></i> Membatalkan...');
    api('batalperiksa', {no_rawat: patientButton.attr('data-no-rawat'), alasan: reason}).done(function (response) {
      var fktp = response.data && response.data.fktp;
      var needsAttention = fktp && ['failed', 'needs_review'].indexOf(fktp.status) !== -1;
      sendStatusUpdate(patientButton.attr('data-no-rawat'), 'Batal');
      $('#dev-arrival-modal').modal('hide');
      pendingArrivalButton = null;
      notify(needsAttention ? 'warning' : 'success', response.message + (fktp && fktp.message ? ' ' + fktp.message : ''));
      refreshList();
    }).fail(function (xhr) {
      notify('danger', requestError(xhr, 'Kunjungan gagal dibatalkan.'));
    }).always(function () { setButtonBusy($button, false); });
  });
  $('#dev-arrival-modal').on('hidden.bs.modal', function () {
    $('#dev-cancel-reason').removeAttr('aria-invalid').val('');
    pendingArrivalButton = null;
    if (!$('#dev-exam-container').is(':visible') && lastPatientTrigger && lastPatientTrigger.length) {
      lastPatientTrigger.focus();
    }
  });
  $root.on('click', '#dev-retry-patient-info', loadPatientInfo);
  $root.on('click', '#dev-refresh-bpjs', loadBpjsMembership);
  $root.on('click', '.dev-close-exam', closeExam);
  $root.on('click', '#dev-toggle-allergy', function () {
    var $editor = $('#dev-allergy-editor');
    $editor.prop('open', !$editor.prop('open'));
    $(this).attr('aria-expanded', String($editor.prop('open')));
    if ($editor.prop('open')) { $editor[0].scrollIntoView({behavior: 'smooth', block: 'nearest'}); }
  });
  $('#dev-allergy-editor').on('toggle', function () {
    $('#dev-toggle-allergy').attr('aria-expanded', String(this.open));
  });
  $form.on('input change', 'input:not([type=hidden]), textarea, select', function () {
    $(this).removeAttr('aria-invalid').closest('.form-group').find('.dev-field-error').remove();
    if (!$form.find('[aria-invalid="true"]').length) { $('#dev-form-error').hide().empty(); }
    if (!$('#dev-save-state').hasClass('is-error')) {
      $('#dev-save-state').removeClass('is-saved is-edit is-copy is-error').html('<i class="fa fa-pencil-square-o"></i> Ada perubahan belum disimpan');
    }
  });
  $root.on('click', '#dev-history-prev', function () { if (historyPage > 1) { loadPatientHistory(historyPage - 1); } });
  $root.on('click', '#dev-history-next', function () { if (historyPage < historyPages) { loadPatientHistory(historyPage + 1); } });
  $root.on('click', '.dev-copy-history', function () { copyHistoryRecord($(this)); });
  $root.on('click', '.dev-call-queue', function () { callQueue($(this)); });
  $root.on('click', '.dev-fktp-add', function () { runFktpAction($(this), 'antreantambah', 'Mengirim...'); });
  $root.on('click', '.dev-fktp-retry-cancel', function () { runFktpAction($(this), 'antreanbatalulang', 'Mengulang...'); });
  $root.on('change', '.dev-allergy-input', toggleAllergyOtherFields);

  $root.on('click', '#dev-save-allergy', function () {
    var $button = $(this);
    var session = patientSession;
    setButtonBusy($button, true, '<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
    api('savealergi', {
      no_rawat: $form.find('[name=no_rawat]').val(),
      alergi_makanan: $form.find('[name=alergi_makanan]').val(),
      alergi_makanan_lainnya: $form.find('[name=alergi_makanan_lainnya]').val(),
      alergi_udara: $form.find('[name=alergi_udara]').val(),
      alergi_udara_lainnya: $form.find('[name=alergi_udara_lainnya]').val(),
      alergi_obat: $form.find('[name=alergi_obat]').val(),
      alergi_obat_lainnya: $form.find('[name=alergi_obat_lainnya]').val()
    }).done(function (response) {
      if (session !== patientSession) { return; }
      showAllergySummary(response.summary);
      $('#dev-allergy-editor').prop('open', false);
      notify('success', response.message);
    }).fail(function (xhr) {
      notify('danger', requestError(xhr, 'Data alergi gagal disimpan.'));
    }).always(function () {
      setButtonBusy($button, false);
    });
  });

  $form.on('submit', function (event) {
    event.preventDefault();
    if (!formWritable) {
      notify('warning', 'Form ini sedang dalam mode lihat saja.');
      return;
    }
    clearFormErrors();
    var $button = $form.find('[type=submit]');
    var session = patientSession;
    $('#dev-save-state').removeClass('is-saved is-edit is-copy is-error').html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
    setButtonBusy($button, true, '<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
    api('savepemeriksaan', $form.serialize()).done(function (response) {
      if (session !== patientSession) { return; }
      notify('success', response.message);
      $form.find('[name=original_tgl_perawatan]').val(response.data.tgl_perawatan);
      $form.find('[name=original_jam_rawat]').val(response.data.jam_rawat);
      $('#dev-copy-origin').empty().hide();
      $form.find('.dev-copied-field').removeClass('dev-copied-field');
      $('#dev-form-mode').removeClass('is-edit is-copy is-error').addClass('is-saved').text('Tersimpan');
      $('#dev-save-state').removeClass('is-edit is-copy is-error').addClass('is-saved').html('<i class="fa fa-check-circle"></i> Tersimpan · ' + response.data.jam_rawat);
      currentVisitStatus = response.data.visit_status || 'Berkas Dikirim';
      setFormWritable(false, 'Berkas telah dikirim ke poli. Pilih Edit pada catatan tersimpan untuk melakukan koreksi.');
      sendStatusUpdate($form.find('[name=no_rawat]').val(), currentVisitStatus);
      loadHistory();
      loadPatientHistory(historyPage);
      refreshList();
    }).fail(function (xhr) {
      if (session !== patientSession) { return; }
      $('#dev-save-state').removeClass('is-saved is-edit is-copy').addClass('is-error').html('<i class="fa fa-warning"></i> Belum tersimpan — periksa isian dan coba lagi');
      notify('danger', requestError(xhr, 'Pemeriksaan awal gagal disimpan.'));
      showFormError(xhr, 'Pemeriksaan awal gagal disimpan.');
    }).always(function () {
      setButtonBusy($button, false);
      if (!formWritable) { $button.prop('disabled', true); }
    });
  });

  $root.on('click', '.dev-edit-exam', function () {
    var button = $(this);
    var record = button.closest('.dev-history-card');
    if (['Belum', 'Berkas Dikirim'].indexOf(currentVisitStatus) === -1) {
      notify('warning', 'Status kunjungan ini tidak mengizinkan koreksi pemeriksaan.');
      return;
    }
    setFormWritable(true);
    clearFormErrors();
    $('#dev-copy-origin').empty().hide();
    $form.find('.dev-copied-field').removeClass('dev-copied-field');
    $('#dev-form-mode').removeClass('is-copy is-saved is-error').addClass('is-edit').text('Edit · ' + record.attr('data-time'));
    $('#dev-save-state').removeClass('is-saved is-copy is-error').addClass('is-edit').html('<i class="fa fa-pencil"></i> Perubahan belum disimpan');
    populateExamRecord(record);
    notify('info', 'Anda sedang mengubah catatan ' + record.attr('data-date') + ' ' + record.attr('data-time') + '.');
    window.scrollTo(0, $form.offset().top - 20);
  });

  $root.on('click', '.dev-delete-exam', function () {
    var button = $(this);
    if (!window.confirm('Hapus catatan pemeriksaan ini?')) { return; }
    api('hapuspemeriksaan', {no_rawat: $form.find('[name=no_rawat]').val(), tgl_perawatan: button.attr('data-date'), jam_rawat: button.attr('data-time')})
      .done(function (response) { notify('success', response.message); loadHistory(); loadPatientHistory(historyPage); refreshList(); })
      .fail(function (xhr) { notify('danger', requestError(xhr, 'Catatan gagal dihapus.')); });
  });

  $(window).on('beforeunload', function () {
    Object.keys(pendingCalls).forEach(function (key) { clearTimeout(pendingCalls[key].timer); });
    if (reconnectTimer) { clearTimeout(reconnectTimer); }
    if (noticeTimer) { clearTimeout(noticeTimer); }
  });

  connectSocket();
  // Apply initial hide-examined filter on the server-rendered list (AJAX refresh is not called on first load)
  applyFilters();
})(jQuery);
