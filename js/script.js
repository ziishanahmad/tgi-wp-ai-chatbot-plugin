jQuery(document).ready(function($) {
    $('#tgi-chatgpt-icon').draggable({
        containment: 'window'
    });

    $("#tgi-chatgpt-modal").draggable({
        handle: ".tgi-chat-header",
    });

    $('#tgi-chatgpt-icon').click(function() {
        $('#tgi-chatgpt-modal').toggle();
        loadSession();
    });

    $('.tgi-chatgpt-close').click(function() {
        $('#tgi-chatgpt-modal').hide();
    });

    $('#tgi-chatgpt-send').click(function() {
        sendMessage();
    });

    $('#tgi-chatgpt-reset').click(function() {
        if(window.reset_message_confirm == undefined || window.reset_message_confirm == false) {
            clearMessage();
        }
        else{
            if(confirm(window.reset_message_confirm)) {
                clearMessage();
            }
        }
    });

    $('#tgi-chatgpt-input').keypress(function(e) {
        if (e.which == 13) {
            e.preventDefault();
            sendMessage();
        }
    });

    $("#tgi-chatgpt-questions").change(function() {
        var selectedQuestion = $(this).val();
        $('#tgi-chatgpt-input').val(selectedQuestion);
        sendMessage();
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
                    $('.tgi-chatgpt-messages').append('<div class="bot-response bot-error">' + response.data + '</div>');
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

    function clearMessage() {
        var messagesContainer = $('.tgi-chatgpt-messages');
    
        if (messagesContainer.children().length == 0) {
            return;
        }
    
        if (messagesContainer.children().length == 1) {
            var firstChild = messagesContainer.children().eq(0);
            if (!firstChild.hasClass('user-message') && !firstChild.hasClass('bot-response')) {
                return;
            }
        }

        $.ajax({
            url: tgi_chatgpt.ajax_url,
            method: 'POST',
            data: {
                action: 'tgi_chatgpt_reset'
            },
            success: function(response) {
                $('.typing-indicator').remove(); // Remove the typing indicator
                console.log('Response received:', response); // Debugging log
                if (response.success) {
                    messagesContainer.empty();
                } else {
                    console.log('Error response:', response.data); // Debugging log
                    messagesContainer.append('<div class="bot-response bot-error">' + response.data + '</div>');
                }
                scrollToBottom();
            },
            error: function() {
                console.log('AJAX error'); // Debugging log
                messagesContainer.append('<div class="bot-response">Error clearing message.</div>');
                scrollToBottom();
            }
        });
    }

    function scrollToBottom() {
        $('.tgi-chatgpt-messages').scrollTop($('.tgi-chatgpt-messages')[0].scrollHeight);
    }
    
    function loadSession() {
        if(window.window.load_previous_chat == undefined || window.load_previous_chat == 0) {
            return;
        }

        // if not has cookie tgi_chatgpt_session_id, return
        if (document.cookie.split(';').filter((item) => item.trim().startsWith('tgi_chatgpt_session_id=')).length) {
            return;
        }

        var messagesContainer = $('.tgi-chatgpt-messages');
    
        var $needLoadSession = false;
        if (messagesContainer.children().length == 0) {
            $needLoadSession = true;
        }
    
        if (messagesContainer.children().length == 1) {
            var firstChild = messagesContainer.children().eq(0);
            if (!firstChild.hasClass('user-message') && !firstChild.hasClass('bot-response')) {
                $needLoadSession = true;
            }
        }

        if ($needLoadSession) {
            // Add animation for generating response
            $('.tgi-chatgpt-messages').append('<div class="bot-response typing-indicator">...</div>');

            $.ajax({
                url: tgi_chatgpt.ajax_url,
                method: 'POST',
                data: {
                    action: 'tgi_load_session'
                },
                success: function(response) {
                    $('.typing-indicator').remove(); // Remove the typing indicator
                    console.log('Response received:', response); // Debugging log
                    if (response.success) {
                        response.data.forEach(function(item) {
                            $('.tgi-chatgpt-messages').append('<div class="user-message">' + item.user_message + '</div>');
                            if (typeof marked !== 'undefined') {
                                var htmlResponse = marked.parse(item.bot_response); // Use marked.parse to convert Markdown to HTML
                                $('.tgi-chatgpt-messages').append('<div class="bot-response">' + htmlResponse + '</div>');
                            } else {
                                console.error('marked is not defined'); // Debugging log
                                $('.tgi-chatgpt-messages').append('<div class="bot-response">' + item.response + '</div>');
                            }
                        });
                    } else {
                        console.log('Error response:', response.data); // Debugging log
                        $('.tgi-chatgpt-messages').append('<div class="bot-response bot-error">' + response.data + '</div>');
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
    }

});

