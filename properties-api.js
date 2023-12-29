jQuery(document).ready(function ($) {
    // Delete API key form
    $('form[name="delete_api_key_form"]').on('submit', function (event) {
        event.preventDefault();

        if (confirm('Are you sure you want to delete this API key?')) {
            var apiKeyId = $(this).find('input[name="api_key_id"]').val();

            $.ajax({
                url: propertiesApi.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_api_key',
                    security: propertiesApi.nonce,
                    api_key_id: apiKeyId
                },
                success: function (response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                }
            });
        }
    });
});
