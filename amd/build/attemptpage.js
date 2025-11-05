define(['jquery', 'core/ajax', 'core/notification'],
    function($) {
        return {
            setup: function(props) {
                var quizurl = props.quizurl;

                /**
                 * Completely disable all validation - never close the quiz
                 */
                function CloseOnParentClose() {
                    // COMPLETELY DISABLED - never close the quiz for validation reasons
                    console.log('Quiz validation completely disabled - quiz will never close for sharing issues');
                    
                    // Only close if parent window is actually closed
                    if (typeof window.opener != 'undefined' && window.opener !== null) {
                        if (window.opener.closed) {
                            console.log('Parent window closed - closing quiz');
                            window.close();
                        }
                    }
                    
                    // Don't validate URL or sharing state - just continue
                    return;
                }
                
                $(window).ready(function() {
                    setInterval(CloseOnParentClose, 1000);
                });
                return true;
            },
            init: function() {
                console.log('Attempt page validation completely disabled');
                return true;
            }
        };
    });