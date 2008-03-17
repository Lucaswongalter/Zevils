function emailLinkify(ciphertext, pad, elt_id) {
    //Email address obfuscation adapted from http://w2.syronex.com/jmr/safemailto/
    var elt = $(elt_id);
    if(!elt) return;

    pad = unescape(pad);
    var cipher_length = ciphertext.length;
    var plaintext = '';
    for(var i = 0; i < cipher_length; i++) {
	   plaintext += String.fromCharCode(ciphertext.charCodeAt(i) ^ pad.charCodeAt(i));
    }
    elt.wrapInner('<a href="#">');
    $(elt_id + " > a").click(function() {
	   window.location="mail\u0074o\u003a" + plaintext;
    });
}

$(document).ready(function() {
    emailLinkify('BVQH3ARCMT6YBV8', '7%25%11%3BR%22%3A0+5%5Bw-%24_', '#footer-email');
    emailLinkify('3R8G8FDYXAIRPDRT3', '_%3BB%07Z*%25%3A35%2809j1%3B%5E', '#liz-email');
    emailLinkify('4Q66XVBS45KDVES5V2M', 'Y0BB0354tO.2%3F%29%20%1B5%5D%20', '#matthew-email');
    emailLinkify('2Y483RQYXAFFZQPDSKITC2J', 'U0R%5E%5C%2053%1823%22%3E4%3E%28%3A%25%22z-W%3E', '#jean-email');
    emailLinkify('M3DTHDYIW3C4EQVWK845KQN', '*Z%222%276%3D-%17@6P%2148%3B%22V_%1B%254%3A', '#dave-email');
    emailLinkify('YNP4S88TTHVVX2R8IH3E3', '+/%3EP%3ALK57%20%25%16%3F_3Q%25fP*%5E', '#randi-email');
    emailLinkify('EJUM4UIBCRJT54C2AG', '%21+%3B%3EU6%211%03%22+%3A%5CLmQ.*', '#dan-email');

    var startGlideeID = window.location.hash;
    if(startGlideeID && $(startGlideeID)) {
        $("dl.glidey > dd").hide();
        $(startGlideeID).next().show();
    } else {
        $("dl.glidey > dd:not(:first)").hide();
    }
    $("dl.glidey > dt").each(function() {
        var glideID = $(this).attr("id");
        if(!glideID) glideID = "";
        $(this).wrapInner("<a href=\"#" + glideID + "\">");

        var glidee = $(this).next();
        $(this).children("a").click(function(){
            if(!glidee.is(":visible")) {
                $("dl.glidey > dd:visible").slideUp();
                glidee.slideDown();
            }
            return true;
        });
    });
});
