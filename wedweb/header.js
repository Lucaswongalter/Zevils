$(document).ready(function() {
    //Email address obfuscation adapted from http://w2.syronex.com/jmr/safemailto/
    var v2 = 'BVQH3ARCMT6YBV8';
    var v7 = unescape('7%25%11%3BR%22%3A0+5%5Bw-%24_');
    var v5 = v2.length;
    var v1 = '';
    for(var v4=0; v4 < v5; v4++) {
	v1 += String.fromCharCode(v2.charCodeAt(v4)^v7.charCodeAt(v4));
    }
    $("#footer-email").text("");
    $("#footer-email").append('<a href="javascript:void(0)">us -AT- sachsfam -DOT- org</a>');
    $("#footer-email > a").click(function() {
	window.location="mail\u0074o\u003a" + v1;
    });

    $("dl.glidey > dd:not(:first)").hide();
    $("dl.glidey > dt").click(function(){
        $("dd:visible").slideUp();
        $(this).next().slideDown();
        return false;
    });


    var today = new Date();
    var todayMS = Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate());
    var targetMS = Date.UTC(2008, 9, 12);
    // 86400000 milliseconds per day
    var daysLeft = Math.floor((targetMS - todayMS) / 86400000);
    var daysLeftText;
    if(daysLeftText < 0) {
        daysLeftText = "This has allegedly happened already...";
    } else if(daysLeftText == 0) {
        daysLeftText = "Yes, it's today";
    } else if(daysLeft == 1) {
        daysLeftText = "T-1 day";
    } else {
        daysLeftText = "T-"+daysLeft+" days";
    }
    $("#countdown").text(daysLeftText);
});
