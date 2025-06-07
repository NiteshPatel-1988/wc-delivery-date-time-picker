jQuery(document).ready(function($) {
    $('#leadgen-form').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.post(leadgen_obj.ajaxurl, formData + '&action=leadgen_submit&nonce=' + leadgen_obj.nonce, function(response) {
            $('#leadgen-response').html(response.data);
            $('#leadgen-form')[0].reset();
        });
    });
});
