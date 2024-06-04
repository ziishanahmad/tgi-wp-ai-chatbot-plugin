jQuery(document).ready(function($) {
    $('#clear-logs').click(function() {
        if (!confirm('Are you sure you want to clear all chat and error logs?')) {
            return;
        }

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'clear_tgi_chat_logs',
                nonce: tgi_chatgpt_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#clear-logs-message').html('<div class="updated"><p>All logs cleared successfully.</p></div>');
                    location.reload(); // Refresh the page
                } else {
                    $('#clear-logs-message').html('<div class="error"><p>Error clearing logs: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                $('#clear-logs-message').html('<div class="error"><p>Error clearing logs.</p></div>');
            }
        });
    });
});
