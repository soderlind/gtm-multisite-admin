
//IE fix for console.log
var console=console||{"log":function(){}};

/**
 * gtm multisie admin ajax tag change handling
 *
 *
 * Uses ajax_change_portfolio_status() in multisite-portfolio.php
 *
 * @author Per Soderlind <per@soderlind.no>
 */

jQuery(document).ready(function($){


    // (only a potential issue if script is loaded through AJAX)
    $(document.body).off('keypress', 'div.gtm-multisite :text');

    // Bind to keyup events on the $selector.
    $(document).on('keypress', 'div.gtm-multisite :text', function(event) {
        if(event.keyCode === 13) { // 13 = Enter Key
            $(this).blur(); // trigger blur event below
            event.preventDefault();
            return false; //don't submit the form
        }

    });

    $(document).on("click", ".gtm-multisite-tag",  function( event ){

		event.preventDefault();

        var a = $(this);
        var txt = $(this).next();
        txt.val(a.text()).show();
        txt.focus();
        a.css('display','none'); //addClass("edit");
        return false;
    });


    $(document).on("blur", "div.gtm-multisite :text", function( event ){

        var parent = $(this).parent();
        var txt = $(this);
        var a = $(this).prev();
        var gtm_tag = txt.val();
        a.show().text(gtm_tag);
        txt.hide();

		var orgBackgroundColor = a.css('background-color');
		var data = {
			action:     "gtm_multisite_admin_change_tag",
			site_id:    a.data('siteid'),
            gtm_tag:    gtm_tag,
            security:   gtm_multisite_options.nonce
		};

		$.ajax({
			url:          gtm_multisite_options.ajaxurl + '?now='  +escape(new Date().getTime().toString())
			, type:       'post'
			, dataType:   'json'
			, cache:      false
			, data:       data
			, beforeSend: function() {
				//self.css('box-shadow','none');
				a.animate({backgroundColor: '#0073aa'}, 'slow');
			}
			, complete: function(){
				a.animate({backgroundColor: orgBackgroundColor}, 'fast');
	        }
			, success: function(data) {
				if( 'success' === data.response ) {
                    a.text(data.text);
                    console.log(data);
                    //self.data('changeto',data.change_to);
				} else if( 'failed' === data.response ) {
					console.log(data);
				}
			}
            , error: function(e, x, settings, exception) {
                // Generic debugging
                var errorMessage;
                var statusErrorMap = {
                    '400' : "Server understood request but request content was invalid.",
                    '401' : "Unauthorized access.",
                    '403' : "Forbidden resource can't be accessed.",
                    '500' : "Internal Server Error",
                    '503' : "Service Unavailable"
                };
                if (x.status) {
                    errorMessage = statusErrorMap[x.status];
                    if (!errorMessage) {
                        errorMessage = "Unknown Error.";
                    } else if (exception === 'parsererror') {
                        errorMessage = "Error. Parsing JSON request failed.";
                    } else if (exception === 'timeout') {
                        errorMessage = "Request timed out.";
                    } else if (exception === 'abort') {
                        errorMessage = "Request was aborted by server.";
                    } else {
                        errorMessage = "Unknown Error.";
                    }
                    $this.parent().html(errorMessage);
                    console.log("Error message is: " + errorMessage);
                } else {
                    console.log("ERROR!!");
                    console.log(e);
            	}
            }
		});
	});
});
