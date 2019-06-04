var WPURLS = WPURLS;
document.addEventListener("DOMContentLoaded", ()=> {
    $=jQuery;
    $(".payUsingIOTA_QRDATA_COPY").click(function() {
        copyToClipboard($(this).parent().find("input")[0]);
    });
    $(".payUsingIOTA_QR_showdata").click(function() {
        $("#payUsingIOTA_QRDATA").slideToggle();
        $(this).slideToggle();
    });
    $("#payUsingIOTA_loading .spinner").css("background","url('"+WPURLS.adminurl+"/images/wpspin_light.gif') no-repeat");
    $.ajax({
        url: WPURLS.siteurl+'/wp-json/payUsingIOTA/v1/getNodeInfo',
        cache: false,
        success: function(response,status,xhr) {
            if(
                !response ||
                Math.abs(response.latestMilestoneIndex - response.latestSolidSubtangleMilestoneIndex) > 3
            ){
                // Do not proceed
                $("#payUsingIOTA_loading").css("display","none");
                $("#payUsingIOTA_error").css("display","block");
                console.error('node problem: ',response,status);
            }else{
                // proceed
                $("#payUsingIOTA_loading").css("display","none");
                $(".spinner").css("display","none");
                $("#payUsingIOTA_restrictedArea").css("display","block");
                proceed($);
            }
        },
        error: function(xhr,status,error) {
            // Do not proceed
            console.error('node problem: ',error,status);
            $("#payUsingIOTA_loading").css("display","none");
            $("#payUsingIOTA_error").css("display","block");
        },
        complete: function(xhr,status) {
            //
        },
        timeout: 60000
    });
});


function proceed($){
    var address = $("#payUsingIOTA_QRCanvas").attr("data-address");
    var price = parseInt($("#payUsingIOTA_QRCanvas").attr("data-price"));
    var postId = parseInt($("#payUsingIOTA_QRCanvas").attr("data-postId"));
    var code = $("#payUsingIOTA_QRCanvas").attr("data-code");
    $("#iota-deep-link").attr("href","iota://"+address+"/?amount="+price+"&message="+code);
    const object = {
        address,
        amount: price,
        message: JSON.stringify({postId,code})
    }
    if(!document.getElementById('payUsingIOTA_QRCanvas'))
        return;
    QRCode.toCanvas(document.getElementById('payUsingIOTA_QRCanvas'), JSON.stringify(object), function (error) {
        if (error) console.error(error)
        $("#payUsingIOTA_QRCanvas").css("height","auto");
    });
    $("#payUsingIOTA_VerificationArea button").click((event)=>{
        $(event.currentTarget).hide();
        $(".spinner").css("display","inline-block");
        $("#payUsingIOTA_feedback").text("");
        $.ajax({
            url: WPURLS.siteurl+'/wp-json/payUsingIOTA/v1/estado',
            type: "POST",
            cache: false,
            data: {postId,code},
            success: function(response,status,xhr) {
                if(response && !response.result) {
                    if(response.reason == "unconfirmed") {
                        $("#payUsingIOTA_VerificationArea button").show();
                        $("#payUsingIOTA_feedback").text("Unconfirmed transaction. Try later.");
                        return;
                    }
                    $("#payUsingIOTA_VerificationArea button").show();
                    $("#payUsingIOTA_feedback").text("The verification has failed. Try later or contact us.");
                    return;
                }
                $("#payUsingIOTA_feedback").text("The verification has been completed.");
                $("#payUsingIOTA_feedback").append('<br><a href=".">REFRESH</a>')
                location.reload();
            },
            error: function(xhr,status,error) {
                console.error('Error with API request',status,error);
                $(event.currentTarget).show();
                $("#payUsingIOTA_feedback").text("The verification has failed. Try later or contact us.")
            },
            complete: function(xhr,status) {
                $(".spinner").css("display","none");
            },
            timeout: 60000
        });
    });
}

function copyToClipboard(elem) {
	
    // Create hidden text element, if it doesn't already exist
    var targetId = "_hiddenCopyText_";
    var isInput = elem.tagName === "INPUT" || elem.tagName === "TEXTAREA";
    var origSelectionStart, origSelectionEnd;
    if (isInput) {
        // can just use the original source element for the selection and copy
        target = elem;
        origSelectionStart = elem.selectionStart;
        origSelectionEnd = elem.selectionEnd;
    } else {
        // must use a temporary form element for the selection and copy
        target = document.getElementById(targetId);
        if (!target) {
            var target = document.createElement("textarea");
            target.style.position = "absolute";
            target.style.left = "-9999px";
            target.style.top = "0";
            target.id = targetId;
            document.body.appendChild(target);
        }
        target.textContent = elem.textContent;
    }
    
    // select the content
    var currentFocus = document.activeElement;
    target.focus();
    target.setSelectionRange(0, target.value.length);
    
    // copy the selection
    var succeed;
    try {
    	  succeed = document.execCommand("copy");
    } catch(e) {
        succeed = false;
    }
    // restore original focus
    if (currentFocus && typeof currentFocus.focus === "function") {
        currentFocus.focus();
    }
    
    if (isInput) {
        // restore prior selection
        elem.setSelectionRange(origSelectionStart, origSelectionEnd);
    } else {
        // clear temporary content
        target.textContent = "";
    }
    return succeed;
}