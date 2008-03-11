$(document).ready(function() {
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
