/* Resend Mailer — admin JS */
(function ($) {
  'use strict';

  /* ── Color pickers ────────────────────────────────────────────── */
  $('.rm-color-picker').wpColorPicker();

  /* ── Reveal/hide API key ──────────────────────────────────────── */
  $('.rm-reveal-btn').on('click', function () {
    var $input = $(this).siblings('.rm-input');
    var isPass = $input.attr('type') === 'password';
    $input.attr('type', isPass ? 'text' : 'password');
  });

  /* ── Font preview ─────────────────────────────────────────────── */
  function updateFontPreview($select, $preview) {
    var font = $select.val();
    $preview.css('font-family', font);
  }

  var $headingSelect  = $('#rm_heading_font');
  var $headingPreview = $('#rm-heading-preview');
  var $bodySelect     = $('#rm_body_font');
  var $bodyPreview    = $('#rm-body-preview');

  $headingSelect.on('change', function () { updateFontPreview($headingSelect, $headingPreview); });
  $bodySelect.on('change',    function () { updateFontPreview($bodySelect, $bodyPreview); });

  /* ── Media uploader (logo) ────────────────────────────────────── */
  var mediaFrame;

  $('#rm-upload-logo').on('click', function () {
    if (mediaFrame) {
      mediaFrame.open();
      return;
    }
    mediaFrame = wp.media({
      title:    rmAdmin.mediaTitle,
      button:   { text: rmAdmin.mediaButton },
      multiple: false,
      library:  { type: 'image' },
    });
    mediaFrame.on('select', function () {
      var att = mediaFrame.state().get('selection').first().toJSON();
      $('#rm_logo_id').val(att.id);
      $('#rm-logo-preview')
        .html('<img src="' + att.url + '" alt="Logo">')
        .addClass('is-visible');
      $('#rm-upload-logo').text('Change logo');
      $('#rm-remove-logo').removeClass('is-hidden');
    });
    mediaFrame.open();
  });

  $('#rm-remove-logo').on('click', function () {
    $('#rm_logo_id').val('');
    $('#rm-logo-preview').removeClass('is-visible').html('');
    $('#rm-upload-logo').text('Upload logo');
    $(this).addClass('is-hidden');
  });

  /* ── Send test email ──────────────────────────────────────────── */
  $('#rm-send-test').on('click', function () {
    var $btn    = $(this);
    var $result = $('#rm-test-result');

    $btn.prop('disabled', true).text('Sending…');
    $result.text('').removeClass('is-success is-error');

    $.post(rmAdmin.ajaxUrl, { action: 'rm_send_test', nonce: rmAdmin.nonce })
      .done(function (res) {
        if (res.success) {
          $result.text('✓ ' + res.data).addClass('is-success');
        } else {
          $result.text('✗ ' + res.data).addClass('is-error');
        }
      })
      .fail(function () {
        $result.text('✗ Request failed — check your browser console.').addClass('is-error');
      })
      .always(function () {
        $btn.prop('disabled', false).text('Send test email');
      });
  });

  /* ── Preview email ────────────────────────────────────────────── */
  $('#rm-preview-email').on('click', function (e) {
    e.preventDefault();
    var url = rmAdmin.ajaxUrl + '?action=rm_preview_email&nonce=' + encodeURIComponent(rmAdmin.nonce);
    window.open(url, '_blank', 'noopener');
  });

  /* ── Reset template ───────────────────────────────────────────── */
  $('#rm-reset-template').on('click', function () {
    if (!window.confirm(rmAdmin.resetConfirm)) return;

    var $btn    = $(this);
    var $result = $('#rm-reset-result');

    $btn.prop('disabled', true);

    $.post(rmAdmin.ajaxUrl, { action: 'rm_reset_template', nonce: rmAdmin.nonce })
      .done(function (res) {
        if (res.success) {
          $('#rm_template').val(res.data);
          $result.text('Reset to default.');
          setTimeout(function () { $result.text(''); }, 3000);
        }
      })
      .always(function () { $btn.prop('disabled', false); });
  });

}(jQuery));
