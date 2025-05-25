jQuery(document).ready(function ($) {
  $('#ktfparts-add-form').on('submit', function (e) {
    e.preventDefault();

    var formData = $(this).serialize() + '&action=ktfparts_add_part&nonce=' + KTFPartsAjax.nonce;

    $.post(KTFPartsAjax.ajaxurl, formData, function (response) {
      if (response.success) {
        $('#ktfparts-form-result').html('<p style="color:green;">' + response.data + '</p>');
        $('#ktfparts-add-form')[0].reset();
      } else {
        $('#ktfparts-form-result').html('<p style="color:red;">' + response.data + '</p>');
      }
    });
  });
});
