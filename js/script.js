jQuery(document).ready(function($) {
    $('#tgi-chatgpt-icon').click(function() {
        $('#tgi-chatgpt-modal').show();
    });

    $('.tgi-chatgpt-close').click(function() {
        $('#tgi-chatgpt-modal').hide();
    });

    $('#tgi-chatgpt-send').click(function() {
        sendMessage();
    });

    $('#tgi-chatgpt-input').keypress(function(e) {
        if (e.which == 13) {
            e.preventDefault();
            sendMessage();
        }
    });

    function sendMessage() {
        var message = $('#tgi-chatgpt-input').val();
        if (message.trim() === '') {
            return;
        }

        $('.tgi-chatgpt-messages').append('<div class="user-message">' + message + '</div>');
        $('#tgi-chatgpt-input').val('');
        scrollToBottom();

        $.ajax({
            url: tgi_chatgpt.ajax_url,
            method: 'POST',
            data: {
                action: 'tgi_chatgpt_send',
                message: message
            },
            success: function(response) {
                if (response.success) {
                    $('.tgi-chatgpt-messages').append('<div class="bot-response">' + response.data + '</div>');
                } else {
                    $('.tgi-chatgpt-messages').append('<div class="bot-response">Error: ' + response.data + '</div>');
                }
                scrollToBottom();
            },
            error: function() {
                $('.tgi-chatgpt-messages').append('<div class="bot-response">Error sending message.</div>');
                scrollToBottom();
            }
        });
    }

    function scrollToBottom() {
        $('.tgi-chatgpt-messages').scrollTop($('.tgi-chatgpt-messages')[0].scrollHeight);
    }
});
