define(['jquery', 'core/ajax', 'core/notification'],
    function($, Ajax, Notification) {
        return {
            setup: function(props) {
                window.invigilatorShareState = document.getElementById('invigilator_share_state');
                window.invigilatorWindowSurface = document.getElementById('invigilator_window_surface');
                window.invigilatorScreenoff = document.getElementById('invigilator_screen_off_flag');

                const videoElem = document.getElementById("invigilator-video-screen");
                const logElem = document.getElementById("invigilator-log-screen");
                const screensharemsg = props.screensharemsg;
                const restartattemptcommand = props.restartattemptcommand;
                const somethingwentwrong = props.somethingwentwrong;

                var displayMediaOptions = {
                    monitorTypeSurfaces: "include",
                    displaySurface: "monitor",
                    video: {
                        cursor: "always",
                        mediaSource: "screen"
                    },
                    audio: false
                };

                $("#invigilator-share-screen-btn").click(function() {
                    startCapture();
                });

                /**
                 * Start screen capture.
                 */
                async function startCapture() {
                    logElem.innerHTML = "";
                    try {
                        // Console.log("vid found success");
                        videoElem.srcObject = await navigator.mediaDevices.getDisplayMedia(displayMediaOptions);
                        // Console.log('videoElem.srcObject', videoElem.srcObject);

                        $('#id_invigilator').css("display", 'block');
                        $("label[for='id_invigilator']").css("display", 'block');
                    } catch (err) {
                        // Console.log("Error: " + err.toString());
                        let errString = err.toString();
                        if (errString == "NotAllowedError: Permission denied") {
                            Notification.addNotification({
                                message: screensharemsg,
                                type: 'error'
                            });
                            return false;
                        }
                    }
                    return true;
                }

                var updateWindowStatus = function() {
                    if (videoElem.srcObject !== null) {
                        // Console.log(videoElem);
                        const videoTrack = videoElem.srcObject.getVideoTracks()[0];
                        var currentStream = videoElem.srcObject;
                        var active = currentStream.active;
                        var readyState = videoTrack.readyState;
                        
                        // Console.log('displaySurface - updateWindow : ', displaySurface);
                        document.getElementById('invigilator_window_surface').value = readyState;
                        document.getElementById('invigilator_share_state').value = active;
                        var screenoff = document.getElementById('invigilator_screen_off_flag').value;
                        
                        if (screenoff == "1") {
                            let tracks =  currentStream.getTracks();
                            tracks.forEach(track => track.stop());
                            // Console.log('video stopped');
                            clearInterval(windowState);
                            location.reload();
                        }
                    }
                };

                var takeScreenshot = function() {
                    var screenoff = document.getElementById('invigilator_screen_off_flag').value;
                    if (videoElem.srcObject !== null) {
                        const videoTrack = videoElem.srcObject.getVideoTracks()[0];
                        var currentStream = videoElem.srcObject;
                        var active = currentStream.active;

                        var settings = videoTrack.getSettings();
                        var displaySurface = settings.displaySurface;

                        if (screenoff == "0") {
                            if (!active) {
                                Notification.addNotification({
                                    message: restartattemptcommand,
                                    type: 'error'
                                });
                                clearInterval(screenShotInterval);
                                window.close();
                                return false;
                            }
                            // Console.log('displaySurface', displaySurface);
                            if (displaySurface !== "live") {
                                Notification.addNotification({
                                    message: screensharemsg,
                                    type: 'error'
                                });
                                clearInterval(screenShotInterval);
                                window.close();
                                return false;
                            }

                        }
                        // Console.log(displaySurface);
                        // console.log(quizurl);

                        // Capture Screen
                        var videoScreen = document.getElementById('invigilator-video-screen');
                        var canvasScreen = document.getElementById('invigilator-canvas-screen');
                        var screenContext = canvasScreen.getContext('2d');
                        // Var photo_screen = document.getElementById('photo_screen');
                        var widthConfig = props.screenshotwidth;
                        var heightConfig = findHeight(props.screenshotwidth);
                        canvasScreen.width = widthConfig;
                        canvasScreen.height = heightConfig;
                        screenContext.drawImage(videoScreen, 0, 0, widthConfig, heightConfig);
                        var screenData = canvasScreen.toDataURL('image/png');
                        // Photo_screen.setAttribute('src', screenData);
                        // console.log(screenData);

                        // API Call
                        var wsfunction = 'quizaccess_invigilator_send_screenshot';
                        var params = {
                            'courseid': props.courseid,
                            'cmid': props.cmid,
                            'quizid': props.quizid,
                            'screenshot': screenData
                        };

                        var request = {
                            methodname: wsfunction,
                            args: params
                        };

                        // Console.log('params', params);
                        if (screenoff == "0") {
                            Ajax.call([request])[0].done(function(data) {
                                if (data.warnings.length < 1) {
                                    // NO; pictureCounter++;
                                } else {
                                    if (videoScreen) {
                                        Notification.addNotification({
                                            message: somethingwentwrong,
                                            type: 'error'
                                        });
                                        clearInterval(screenShotInterval);
                                    }
                                }
                            }).fail(Notification.exception);
                        }
                    }
                    return true;
                };

                /**
                 * Calculate height from width and screen aspect ratio.
                 * @param {number} width
                 * @returns {number}
                 */
                function findHeight(width) {
                    var currentAspectRatio = screen.width / screen.height;
                    var newHeight = width / currentAspectRatio;
                    return newHeight;
                }

                var windowState = setInterval(updateWindowStatus, 1000);
                var screenShotInterval = setInterval(takeScreenshot, props.screenshotdelay * 1000);
            },
            init: function(props) {
                $('#id_submitbutton').prop("disabled", true);
                $('#id_invigilator').css("display", 'none');
                $("label[for='id_invigilator']").css("display", 'none');

                const screensharemsg = props.screensharemsg;
                $('#id_invigilator').click(function() {
                    if (!$(this).is(':checked')) {
                        hideButtons();
                    } else {
                        var screensharestatus = document.getElementById('invigilator_share_state').value;
                        var screensharemode = document.getElementById('invigilator_window_surface').value;
                        if ((screensharemode == 'live') && (screensharestatus == "true")) {
                            showButtons();
                        } else {
                            Notification.addNotification({
                                message: screensharemsg,
                                type: 'error'
                            });
                        }
                    }
                });

                /**
                 * HideButtons
                 */
                function hideButtons() {
                    $('#id_submitbutton').prop("disabled", true);
                }

                /**
                 * ShowButtons
                 */
                function showButtons() {
                    $('#id_submitbutton').prop("disabled", false);
                }
                return true;
            }
        };
    });
