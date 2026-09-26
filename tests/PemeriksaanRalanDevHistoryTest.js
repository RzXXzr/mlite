/* Run with Node: node tests/PemeriksaanRalanDevHistoryTest.js
 * No packages or browser required; exercises copy and async patient isolation. */
function testHistoryClient(source) {
  'use strict';
  var elements = {};
  var requests = [];
  var hidden = [];
  var scrolls = 0;
  function element(key) {
    if (elements[key]) { return elements[key]; }
    var value = '', content = '', data = {}, classes = {};
    var el = {
      0: {reset: function () {}},
      val: function (next) { if (!arguments.length) { return value; } value = next; return el; },
      text: function (next) { if (!arguments.length) { return content; } content = next; return el; },
      html: function (next) { if (!arguments.length) { return content; } content = next; return el; },
      empty: function () { content = ''; return el; },
      data: function (name, next) { if (arguments.length === 1) { return data[name]; } data[name] = next; return el; },
      addClass: function (name) { classes[name] = true; return el; },
      removeClass: function (name) { delete classes[name]; return el; },
      hasClass: function (name) { return !!classes[name]; },
      find: function (selector) {
        if (selector.indexOf(',') !== -1) {
          return {val: function (next) { selector.split(',').forEach(function (part) { el.find(part.trim()).val(next); }); }};
        }
        var name = selector.match(/\[name=["']?([^\]"']+)/);
        return element(name ? 'field:' + name[1] : selector);
      },
      offset: function () { return {top: 0}; }
    };
    ['on', 'show', 'hide', 'prop', 'fadeOut', 'removeAttr', 'toggle'].forEach(function (method) { el[method] = function () { return el; }; });
    el.hide = function () { hidden.push(key); return el; };
    elements[key] = el;
    return el;
  }
  function $(selector) {
    if (typeof selector === 'string' && selector.indexOf(',') !== -1) {
      var group = {};
      ['text', 'prop', 'show', 'hide'].forEach(function (method) {
        group[method] = function () {
          var args = arguments;
          selector.split(',').forEach(function (part) { var item = element(part.trim()); item[method].apply(item, args); });
          return group;
        };
      });
      return group;
    }
    return element(typeof selector === 'string' ? selector : 'window');
  }
  $.trim = function (value) { return value.trim(); };
  $.ajax = function (options) {
    var callbacks = {options: options};
    var request = {};
    ['done', 'fail', 'always'].forEach(function (name) { request[name] = function (callback) { callbacks[name] = callback; return request; }; });
    requests.push(callbacks);
    return request;
  };
  source = source.replace(/\{if: \$mlite.websocket == 'ya'\}true\{else\}false\{\/if\}/, 'false')
    .replace('{$mlite.websocket_proxy}', '').replace('{$mlite.fullname}', 'Synthetic')
    .replace('  connectSocket();\n})(jQuery);', '  return {copy: copyHistoryRecord, allergy: loadAllergy, history: loadHistory, visits: loadPatientHistory, info: loadPatientInfo, bpjs: loadBpjsMembership, flag: participantFlag, advance: function () { patientSession += 1; }};\n})(jQuery);');
  var client = new Function('jQuery', 'mlite', 'window', 'setTimeout', 'clearTimeout', 'return ' + source)(
    $, {url: '', admin: 'admin', token: 'synthetic'}, {scrollTo: function () { scrolls += 1; }}, function () { return 1; }, function () {}
  );
  function check(condition, message) { if (!condition) { throw new Error(message); } }
  function field(name, value) { var el = element('field:' + name); if (arguments.length > 1) { el.val(value); } return el; }
  field('no_rawat', 'ACTIVE');
  field('original_tgl_perawatan', '2026-01-01');
  field('original_jam_rawat', '09:00:00');
  field('tensi', '135/85');
  field('nadi', '-');
  field('spo2', '0');
  field('alergi', 'Profil alergi terbaru');
  var record = {tensi: '120/80', nadi: '80', spo2: '99', suhu_tubuh: '', respirasi: '-', kesadaran: 'Somnolence', keluhan: 'Kutip " <script>literal</script>\nBaris dua', alergi: 'Alergi lama', penilaian: 'Tidak disalin', tgl_perawatan: '2025-12-01', jam_rawat: '10:00:00'};
  var beforeSource = JSON.stringify(record);
  var button = element('button').data('record', record).data('visit', {no_rawat: 'SOURCE'}).data('session', 0);
  client.copy(button);
  check(field('tensi').val() === '135/85' && field('spo2').val() === '0', 'Existing values, including zero, must be preserved');
  check(field('nadi').val() === '80' && field('kesadaran').val() === 'Somnolence', 'Empty/dash fields must receive source values');
  check(field('keluhan').val() === record.keluhan && field('keluhan').hasClass('dev-copied-field'), 'Text must be preserved and copied fields marked');
  check(field('suhu_tubuh').val() === '' && field('respirasi').val() === '', 'Blank/dash source fields must be skipped');
  check(field('no_rawat').val() === 'ACTIVE' && field('original_tgl_perawatan').val() === '' && field('original_jam_rawat').val() === '', 'Copy must create a draft on active encounter');
  check(field('alergi').val() === 'Profil alergi terbaru' && field('penilaian').val() === '', 'Allergy and doctor fields must not be copied');
  check(JSON.stringify(record) === beforeSource && requests.length === 0, 'Copy must not mutate source or send a save request');
  check(element('#dev-copy-origin').text().indexOf('SOURCE') !== -1, 'Draft must show source encounter');
  check(hidden.indexOf('#dev-patient-history') === -1 && hidden.indexOf('.dev-exam-body') === -1 && scrolls === 0, 'Copy must keep form and history visible without navigating or jumping');
  check(element('#dev-form-mode').text() === 'Draf dari riwayat', 'Draft mode must be visible after copying');
  field('original_tgl_perawatan', 'existing-edit');
  client.copy(button);
  check(field('original_tgl_perawatan').val() === 'existing-edit', 'No-op copy must preserve edit identity');
  client.advance();
  field('nadi', '');
  client.copy(button);
  check(field('nadi').val() === '', 'Stale source from previous patient must not be copied');
  client.allergy();
  var allergyResponse = requests[requests.length - 1];
  client.advance();
  allergyResponse.done({data: {}});
  check(field('alergi').val() === 'Profil alergi terbaru', 'Stale allergy response must be ignored');
  client.history();
  var historyResponse = requests[requests.length - 1];
  element('#dev-history').html('Current patient');
  client.advance();
  historyResponse.done('Previous patient');
  check(element('#dev-history').html() === 'Current patient', 'Stale current-visit history must be ignored');
  client.visits(1);
  var oldPage = requests[requests.length - 1];
  client.visits(2);
  // Invalid payload would throw if the stale callback were processed.
  oldPage.done({});
  client.advance();
  requests[requests.length - 1].done({});
  check(client.flag(null, 'Terdaftar', 'Tidak terdaftar') === 'Tidak ada informasi', 'Null program status must remain unknown');
  check(client.flag('', 'Terdaftar', 'Tidak terdaftar') === 'Tidak ada informasi', 'Empty program status must remain unknown');
  check(client.flag(false, 'Terdaftar', 'Tidak terdaftar') === 'Tidak terdaftar', 'Explicit negative flag must be shown');
  check(client.flag(true, 'Terdaftar', 'Tidak terdaftar') === 'Terdaftar', 'Explicit positive flag must be shown');
  check(client.flag('DM', 'Terdaftar', 'Tidak terdaftar') === 'DM', 'Program descriptions must be preserved');
  function startLookup(isBpjs) {
    client.info();
    requests[requests.length - 1].done({data: {name: 'Synthetic', no_rkm_medis: 'RM_A', age: '26 th', birth_date: '29-02-2000', blood_group: 'AB', payer: 'BPJS', card_number: '0000000000001', is_bpjs: isBpjs, pcare: {url: '/admin/pcare/byjeniskartu/noka/0000000000001', type: 'noka'}}});
    return requests[requests.length - 1];
  }
  var before = requests.length;
  startLookup(false);
  check(requests.length === before + 1, 'Non-BPJS must only fetch local identity');
  var membership = startLookup(true);
  check(membership.options.type === 'GET' && membership.options.url.indexOf('/pcare/byjeniskartu/noka/') !== -1, 'Use existing read-only PCare route');
  before = requests.length;
  client.bpjs();
  check(requests.length === before, 'Concurrent repeated membership clicks must not duplicate requests');
  membership.done({metaData: {code: 200}, response: {noKartu: '0000000000001', kdProviderPst: {nmProvider: 'Klinik sintetis', kdProvider: 'TEST'}, jnsPeserta: {nama: 'Pekerja'}, aktif: false, pstPrb: null, pstProl: 'DM'}});
  membership.always();
  check(element('#dev-bpjs-provider').text() === 'Klinik sintetis' && element('#dev-bpjs-active').text() === 'Tidak aktif', 'Provider and membership status must be mapped');
  check(element('#dev-bpjs-prb').text() === 'Tidak ada informasi' && element('#dev-bpjs-prolanis').text() === 'DM', 'PRB and Prolanis must retain their distinct meanings');
  client.bpjs();
  membership = requests[requests.length - 1];
  membership.fail({status: 403});
  membership.always();
  check(element('#dev-bpjs-provider').text() === 'Belum terverifikasi', 'Failed recheck must clear previous verified data');
  membership = startLookup(true);
  membership.done({metaData: {code: 200}, response: {noKartu: '0000000000002'}});
  membership.always();
  check(element('#dev-bpjs-state').text() === 'Belum terverifikasi', 'Mismatched card must be rejected');
  membership = startLookup(true);
  client.advance();
  element('#dev-bpjs-provider').text('Patient B');
  membership.done({});
  membership.always();
  check(element('#dev-bpjs-provider').text() === 'Patient B', 'Stale membership response must not affect a different patient');
  client.info();
  var infoResponse = requests[requests.length - 1];
  client.advance();
  infoResponse.done({});
  return 'PASS: copy, single-page flow, patient isolation, BPJS mapping, unknown/negative states, duplicate requests, and stale responses.';
}

if (typeof module !== 'undefined' && require.main === module) {
  console.log(testHistoryClient(require('fs').readFileSync(__dirname + '/../plugins/pemeriksaan_ralan_dev/js/admin/pemeriksaan_ralan_dev.js', 'utf8')));
}
