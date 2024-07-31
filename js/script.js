jQuery(document).ready(function($) {

    window.sendChatGptMessage = function(message) {
        sendMessage();
    }

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

    async function sendMessage() {
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

        try {
            const response = await $.ajax({
                url: tgi_chatgpt.ajax_url,
                method: 'POST',
                data: {
                    action: 'tgi_chatgpt_send',
                    message: message
                },
                dataType: 'json'
            });

            $('.typing-indicator').remove();
            console.log('Response received:', response);
            if (response.success) {
                if (typeof marked !== 'undefined') {
                    var htmlResponse = marked.parse(response.data);
                    console.log('Converted HTML:', htmlResponse);
                    $('.tgi-chatgpt-messages').append('<div class="bot-response">' + htmlResponse + '</div>');
                    
                     // Replace HTML tags with commas, to ensure there is pause
                    let speakingText = htmlResponse.replace(/<[^>]+>/g, ',').trim();
                    window.tgiSpeak(speakingText);

                } else {
                    console.error('marked is not defined');
                    $('.tgi-chatgpt-messages').append('<div class="bot-response">' + response.data + '</div>');
                }
            } else {
                console.log('Error response:', response.data);
                $('.tgi-chatgpt-messages').append('<div class="bot-response bot-error">' + response.data + '</div>');
            }
        } catch (error) {
            $('.typing-indicator').remove();
            console.log('AJAX error:', error);
            $('.tgi-chatgpt-messages').append('<div class="bot-response">Error sending message.</div>');
        }
        scrollToBottom();
    }

    async function clearMessage() {
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

        try {
            const response = await $.ajax({
                url: tgi_chatgpt.ajax_url,
                method: 'POST',
                data: {
                    action: 'tgi_chatgpt_reset'
                },
                dataType: 'json'
            });

            $('.typing-indicator').remove();
            console.log('Response received:', response);
            if (response.success) {
                messagesContainer.empty();
            } else {
                console.log('Error response:', response.data);
                messagesContainer.append('<div class="bot-response bot-error">' + response.data + '</div>');
            }
        } catch (error) {
            console.log('AJAX error:', error);
            messagesContainer.append('<div class="bot-response">Error clearing message.</div>');
        }
        scrollToBottom();
    }

    function scrollToBottom() {
        $('.tgi-chatgpt-messages').scrollTop($('.tgi-chatgpt-messages')[0].scrollHeight);
    }

    async function loadSession() {
        if (window.load_previous_chat == undefined || window.load_previous_chat == 0) {
            return;
        }

        console.log(document.cookie);
        if (document.cookie.indexOf('tgi_chatgpt_session_id=') === -1) {
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
            console.log("Load previous chat session");

            // Add animation for generating response
            $('.tgi-chatgpt-messages').append('<div class="bot-response typing-indicator">...</div>');

            try {
                const response = await $.ajax({
                    url: tgi_chatgpt.ajax_url,
                    method: 'POST',
                    data: {
                        action: 'tgi_load_session'
                    },
                    dataType: 'json'
                });

                $('.typing-indicator').remove();
                console.log('Response received:', response);
                if (response.success) {
                    response.data.forEach(function(item) {
                        $('.tgi-chatgpt-messages').append('<div class="user-message">' + item.user_message + '</div>');
                        if (typeof marked !== 'undefined') {
                            var htmlResponse = marked.parse(item.bot_response);
                            $('.tgi-chatgpt-messages').append('<div class="bot-response">' + htmlResponse + '</div>');
                        } else {
                            console.error('marked is not defined');
                            $('.tgi-chatgpt-messages').append('<div class="bot-response">' + item.response + '</div>');
                        }
                    });
                } else {
                    console.log('Error response:', response.data);
                    $('.tgi-chatgpt-messages').append('<div class="bot-response bot-error">' + response.data + '</div>');
                }
            } catch (error) {
                $('.typing-indicator').remove();
                console.log('AJAX error:', error);
                $('.tgi-chatgpt-messages').append('<div class="bot-response">Error loading session.</div>');
            }
            scrollToBottom();
        }
    }


});

