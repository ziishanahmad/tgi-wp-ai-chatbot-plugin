jQuery(document).ready(function($) {
    $('#tgi-chatgpt-icon').draggable({
        containment: 'window'
    });

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

        // Add animation for generating response
        $('.tgi-chatgpt-messages').append('<div class="bot-response typing-indicator">...</div>');
        scrollToBottom();

        $.ajax({
            url: tgi_chatgpt.ajax_url,
            method: 'POST',
            data: {
                action: 'tgi_chatgpt_send',
                message: message
            },
            success: function(response) {
                $('.typing-indicator').remove(); // Remove the typing indicator
                console.log('Response received:', response); // Debugging log
                if (response.success) {
                    if (typeof marked !== 'undefined') {
                        var htmlResponse = marked.parse(response.data); // Use marked.parse to convert Markdown to HTML
                        console.log('Converted HTML:', htmlResponse); // Debugging log
                        $('.tgi-chatgpt-messages').append('<div class="bot-response">' + htmlResponse + '</div>');
                    } else {
                        console.error('marked is not defined'); // Debugging log
                        $('.tgi-chatgpt-messages').append('<div class="bot-response">' + response.data + '</div>');
                    }
                } else {
                    console.log('Error response:', response.data); // Debugging log
                    $('.tgi-chatgpt-messages').append('<div class="bot-response">Error: ' + response.data + '</div>');
                }
                scrollToBottom();
            },
            error: function() {
                $('.typing-indicator').remove(); // Remove the typing indicator
                console.log('AJAX error'); // Debugging log
                $('.tgi-chatgpt-messages').append('<div class="bot-response">Error sending message.</div>');
                scrollToBottom();
            }
        });
    }

    function scrollToBottom() {
        $('.tgi-chatgpt-messages').scrollTop($('.tgi-chatgpt-messages')[0].scrollHeight);
    }
});
