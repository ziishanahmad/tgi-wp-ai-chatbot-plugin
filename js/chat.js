// Referenced from https://github.com/Azure-Samples/cognitive-services-speech-sdk/blob/master/samples/js/browser/avatar/js/chat.js

// Global objects
var speechRecognizer = null;
var avatarSynthesizer;
var peerConnection;
var isSpeaking = false;
var spokenTextQueue = [];
var sessionActive = false;
var lastSpeakTime;
var connectingAvatar = false;

jQuery(document).ready(function($) {
    async function getAzureToken() {
        try {
            const response = await $.ajax({
                url: tgi_chatgpt.ajax_url,
                method: 'POST',
                data: {
                    action: 'generate_azure_token',
                    messages: null
                },
                dataType: 'json'
            });

            if (response.success) {
                return response.data.token;
            } else {
                throw new Error(response.data.message);
            }
        } catch (error) {
            console.error('Error fetching token:', error);
            throw error;
        }
    }

    // Connect to avatar service
    async function connectAvatar() {
        if (sessionActive || connectingAvatar) {
            return
        }
        connectingAvatar = true;

        let cogSvcRegion = window.azure_speech_region;

        let token = await getAzureToken();

        let privateEndpoint = window.azure_speech_private_endpoint;

        let speechSynthesisConfig
        if (privateEndpoint) {
            speechSynthesisConfig = SpeechSDK.SpeechConfig.fromEndpoint(new URL(`wss://${privateEndpoint}/tts/cognitiveservices/websocket/v1?enableTalkingAvatar=true`), '') 
            speechSynthesisConfig.authorizationToken = token;
        } else {
            speechSynthesisConfig = SpeechSDK.SpeechConfig.fromAuthorizationToken(token, cogSvcRegion)
        }

        if(window.azure_speech_custom_voice_endpoint_id) {
            speechSynthesisConfig.endpointId = window.azure_speech_custom_voice_endpoint_id;
        }

        let talkingAvatarCharacter = window.azure_speech_character;
        let talkingAvatarStyle = window.azure_speech_avatar_style;
        let avatarConfig = new SpeechSDK.AvatarConfig(talkingAvatarCharacter, talkingAvatarStyle)
        
        avatarConfig.customized = window.azure_speech_avatar_customized;
        avatarSynthesizer = new SpeechSDK.AvatarSynthesizer(speechSynthesisConfig, avatarConfig);
        avatarSynthesizer.avatarEventReceived = function (s, e) {
            var offsetMessage = ", offset from session start: " + e.offset / 10000 + "ms."
            if (e.offset === 0) {
                offsetMessage = ""
            }

            console.log("Event received: " + e.description + offsetMessage)
        }

        if (speechRecognizer == null) {
            const speechRecognitionConfig = SpeechSDK.SpeechConfig.fromEndpoint(new URL(`wss://${cogSvcRegion}.stt.speech.microsoft.com/speech/universal/v2`), '');
            speechRecognitionConfig.authorizationToken = token;

            speechRecognitionConfig.setProperty(SpeechSDK.PropertyId.SpeechServiceConnection_LanguageIdMode, "Continuous");
            var sttLocales = window.azure_speech_locale.split(',');
            var autoDetectSourceLanguageConfig = SpeechSDK.AutoDetectSourceLanguageConfig.fromLanguages(sttLocales);
            speechRecognizer = SpeechSDK.SpeechRecognizer.FromConfig(speechRecognitionConfig, autoDetectSourceLanguageConfig, SpeechSDK.AudioConfig.fromDefaultMicrophoneInput());

            console.log("Speech recognizer created.");
        }

        const xhr = new XMLHttpRequest()
        if (privateEndpoint) {
            xhr.open("GET", `https://${privateEndpoint}/tts/cognitiveservices/avatar/relay/token/v1`)
        } else {
            xhr.open("GET", `https://${cogSvcRegion}.tts.speech.microsoft.com/cognitiveservices/avatar/relay/token/v1`)
        }
        xhr.setRequestHeader("Authorization", `Bearer ${token}`)
        xhr.addEventListener("readystatechange", function() {
            if (this.readyState === 4) {
                const responseData = JSON.parse(this.responseText)
                const iceServerUrl = responseData.Urls[0]
                const iceServerUsername = responseData.Username
                const iceServerCredential = responseData.Password
                setupWebRTC(iceServerUrl, iceServerUsername, iceServerCredential)
            }
        })
        xhr.send()
    }

    // Disconnect from avatar service
    function disconnectAvatar() {
        if (avatarSynthesizer !== undefined) {
            avatarSynthesizer.close();
        }

        sessionActive = false
    }

    // Setup WebRTC
    function setupWebRTC(iceServerUrl, iceServerUsername, iceServerCredential) {
        // Create WebRTC peer connection
        peerConnection = new RTCPeerConnection({
            iceServers: [{
                urls: [ iceServerUrl ],
                username: iceServerUsername,
                credential: iceServerCredential
            }]
        })

        // Fetch WebRTC video stream and mount it to an HTML video element
        peerConnection.ontrack = function (event) {
            if (event.track.kind === 'audio') {
                let audioElement = document.createElement('audio')
                audioElement.id = 'audioPlayer'
                audioElement.srcObject = event.streams[0]
                audioElement.autoplay = true

                audioElement.onplaying = () => {
                    console.log(`WebRTC ${event.track.kind} channel connected.`)
                }

                document.getElementById('remoteVideo').appendChild(audioElement)
            }

            if (event.track.kind === 'video') {
                let videoElement = document.createElement('video')
                videoElement.id = 'videoPlayer'
                videoElement.srcObject = event.streams[0]
                videoElement.autoplay = true
                videoElement.playsInline = true

                videoElement.onplaying = () => {
                    // Clean up existing video element if there is any
                    remoteVideoDiv = document.getElementById('remoteVideo')
                    for (var i = 0; i < remoteVideoDiv.childNodes.length; i++) {
                        if (remoteVideoDiv.childNodes[i].localName === event.track.kind) {
                            remoteVideoDiv.removeChild(remoteVideoDiv.childNodes[i])
                        }
                    }

                    // Append the new video element
                    document.getElementById('remoteVideo').appendChild(videoElement)

                    console.log(`WebRTC ${event.track.kind} channel connected.`)
                    document.getElementById('stopSession').disabled = false
                    document.getElementById('remoteVideo').style.width = '960px'

                    document.getElementById('localVideo').hidden = true
                    if (lastSpeakTime === undefined) {
                        lastSpeakTime = new Date()
                    }

                    //setTimeout(() => { sessionActive = true }, 5000)
                }
            }
        }
        
        // Listen to data channel, to get the event from the server
        peerConnection.addEventListener("datachannel", event => {
            const dataChannel = event.channel
            dataChannel.onmessage = e => {
                console.log("[" + (new Date()).toISOString() + "] WebRTC event received: " + e.data)
            }
        })

        // This is a workaround to make sure the data channel listening is working by creating a data channel from the client side
        c = peerConnection.createDataChannel("eventChannel")

        // Make necessary update to the web page when the connection state changes
        peerConnection.oniceconnectionstatechange = e => {
            console.log("WebRTC status: " + peerConnection.iceConnectionState)
            if (peerConnection.iceConnectionState === 'disconnected') {
                document.getElementById('localVideo').hidden = false
                document.getElementById('remoteVideo').style.width = '0.1px'
            }
        }

        // Offer to receive 1 audio, and 1 video track
        peerConnection.addTransceiver('video', { direction: 'sendrecv' })
        peerConnection.addTransceiver('audio', { direction: 'sendrecv' })

        // start avatar, establish WebRTC connection
        avatarSynthesizer.startAvatarAsync(peerConnection).then((r) => {
            if (r.reason === SpeechSDK.ResultReason.SynthesizingAudioCompleted) {
                console.log("[" + (new Date()).toISOString() + "] Avatar started. Result ID: " + r.resultId)
                connectingAvatar = false
                sessionActive = true
            } else {
                console.log("[" + (new Date()).toISOString() + "] Unable to start avatar. Result ID: " + r.resultId)
                if (r.reason === SpeechSDK.ResultReason.Canceled) {
                    let cancellationDetails = SpeechSDK.CancellationDetails.fromResult(r)
                    if (cancellationDetails.reason === SpeechSDK.CancellationReason.Error) {
                        console.log(cancellationDetails.errorDetails)
                    };

                    console.log("Unable to start avatar: " + cancellationDetails.errorDetails);
                }
                document.getElementById('startSession').disabled = false;
            }
        }).catch(
            (error) => {
                console.log("[" + (new Date()).toISOString() + "] Avatar failed to start. Error: " + error)
                document.getElementById('startSession').disabled = false
            }
        )
    }

    // Do HTML encoding on given text
    function htmlEncode(text) {
        const entityMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '/': '&#x2F;'
        };

        return String(text).replace(/[&<>"'\/]/g, (match) => entityMap[match])
    }

    window.tgiSpeak = (text, endingSilenceMs = 0) => {
        if(!window.azure_speech_region) {
            return;
        }

        if($('#startSession').prop('disabled') != true) {
            return;
        }

        if(!sessionActive) {
            connectAvatar();
            setTimeout(() => {
                speak(text, endingSilenceMs);
            }, 5000);
        }
        else{
            speak(text, endingSilenceMs);
        }
    }

    // Speak the given text
    function speak(text, endingSilenceMs = 0) {
        if (isSpeaking) {
            spokenTextQueue.push(text)
            return
        }

        speakNext(text, endingSilenceMs)
    }

    function speakNext(text, endingSilenceMs = 0) {
        let ttsVoice = window.azure_speech_voice;
        let personalVoiceSpeakerProfileID = window.azure_speech_personal_voice_speaker_profile_id;
        let ssml = `<speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xmlns:mstts='http://www.w3.org/2001/mstts' xml:lang='en-US'><voice name='${ttsVoice}'><mstts:ttsembedding speakerProfileId='${personalVoiceSpeakerProfileID}'><mstts:leadingsilence-exact value='0'/>${htmlEncode(text)}</mstts:ttsembedding></voice></speak>`
        if (endingSilenceMs > 0) {
            ssml = `<speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xmlns:mstts='http://www.w3.org/2001/mstts' xml:lang='en-US'><voice name='${ttsVoice}'><mstts:ttsembedding speakerProfileId='${personalVoiceSpeakerProfileID}'><mstts:leadingsilence-exact value='0'/>${htmlEncode(text)}<break time='${endingSilenceMs}ms' /></mstts:ttsembedding></voice></speak>`
        }

        lastSpeakTime = new Date()
        isSpeaking = true
        avatarSynthesizer.speakSsmlAsync(ssml).then(
            (result) => {
                if (result.reason === SpeechSDK.ResultReason.SynthesizingAudioCompleted) {
                    console.log(`Speech synthesized to speaker for text [ ${text} ]. Result ID: ${result.resultId}`)
                    lastSpeakTime = new Date()
                } else {
                    console.log(`Error occurred while speaking the SSML. Result ID: ${result.resultId}`)
                }

                if (spokenTextQueue.length > 0) {
                    speakNext(spokenTextQueue.shift())
                } else {
                    isSpeaking = false
                }
            }).catch(
                (error) => {
                    console.log(`Error occurred while speaking the SSML: [ ${error} ]`)

                    if (spokenTextQueue.length > 0) {
                        speakNext(spokenTextQueue.shift())
                    } else {
                        isSpeaking = false
                    }
                }
            )
    }

    window.stopSpeaking = () => {
        spokenTextQueue = []
        avatarSynthesizer.stopSpeakingAsync().then(
            () => {
                isSpeaking = false
                console.log("[" + (new Date()).toISOString() + "] Stop speaking request sent.")
            }
        ).catch(
            (error) => {
                console.log("Error occurred while stopping speaking: " + error)
            }
        )
    }

    function checkHung() {
        // Check whether the avatar video stream is hung, by checking whether the video time is advancing
        let videoElement = document.getElementById('videoPlayer');
        if (videoElement !== null && videoElement !== undefined && sessionActive) {
            let videoTime = videoElement.currentTime
            setTimeout(() => {
                // Check whether the video time is advancing
                if (videoElement.currentTime === videoTime) {
                    if (sessionActive) {
                        sessionActive = false;
                    }
                }
            }, 2000);
        }
    }

    function checkLastSpeak() {
        if (lastSpeakTime === undefined) {
            return
        }

        let currentTime = new Date()
        if (currentTime - lastSpeakTime > 60000) { // 1 minute
            if (sessionActive && !isSpeaking) {
                console.log(`[${currentTime.toISOString()}] No speaking detected for 1 minute, disconnecting the avatar.`)
                disconnectAvatar()
                document.getElementById('localVideo').hidden = false
                document.getElementById('remoteVideo').style.width = '0.1px'
                sessionActive = false
            }
        }
    }

    window.onload = () => {
        setInterval(() => {
            checkHung();
            checkLastSpeak();
        }, 5000); // Check session activity every 5 seconds
    };

    window.addEventListener("beforeunload", function (event) {
        window.stopSession();
    });

    window.startSession = () => {
        $('#startSession').prop('disabled', true);
        $('#stopSession').prop('disabled', false);
        $('#localVideo').prop('hidden', false);
        $('#remoteVideo').css('width', '0.1px');
        $('#tgi-chatgpt-avatar').prop('hidden', false);

        connectAvatar();

        setTimeout(() => {
            window.microphone();
        }, 5000)
    }

    window.stopSession = () => {
        if (speechRecognizer) {
            speechRecognizer.stopContinuousRecognitionAsync();
            speechRecognizer.close();
            speechRecognizer.dispose();
            speechRecognizer = null;
            console.log("Speech recognizer stopped.");
        }

        disconnectAvatar();

        $('#startSession').prop('disabled', false);
        $('#stopSession').prop('disabled', true);
        $('#localVideo').prop('hidden', true);
        $('#tgi-chatgpt-avatar').prop('hidden', true);
        $('#tgi-chatgpt-modal').css('width', '');
    }

    window.microphone = () => {
        speechRecognizer.recognized = async (s, e) => {
            if (e.result.reason === SpeechSDK.ResultReason.RecognizedSpeech) {
                console.log("Recognized: " + e.result.text);
                let userQuery = e.result.text.trim()
                if (userQuery === '') {
                    return
                }

                $('#tgi-chatgpt-input').val(userQuery);
                window.sendChatGptMessage();
            }
        }

        speechRecognizer.startContinuousRecognitionAsync(
            () => {
                console.log("Started continuous recognition:");
            }, (err) => {
                console.log("Failed to start continuous recognition:", err);
            })
    }

});