<!DOCTYPE html>
<html lang="en">
<?
    $WEBPAGE_FILE = basename($_SERVER["REQUEST_URI"]);

    function emitNavItem($filename, $title) {
        global $WEBPAGE_FILE;
        echo '<li';
        if($WEBPAGE_FILE == $filename) {
            echo ' id="nav-selected">' . $title;
        } else {
            echo '><a href="' . $filename . '">' . $title . '</a>';
        }
        echo '</li>';
    }
    
    function fancyImage($file, $caption, $width, $height) {
    	$full = "images/$file";
    	$thumb = "images/thumbnails/$file";
		$thumb = preg_replace('/\.([a-z]+)$/', '_thumb.\1', $thumb);
		if(!file_exists($thumb)) $thumb = $full;

    	printf('<a href="%s" title="%s" class="thickbox"><img src="%s" width="%d" height="%d" alt="%s" title="%s"></a>',
    		$full, $caption, $thumb, $width, $height, $caption, $caption);
    }
    
    function emitCountdownText() {
        $now = gmmktime();
        $then = gmmktime(20, 0, 0, 10, 12, 108);
        $secDiff = $then - $now;
        $daysLeft = floor($secDiff / 86400);
        if($daysLeft < 0) {
            echo "This has allegedly happened already...";
        } elseif($daysLeft == 0) {
            echo "Yes, it's today";
        } elseif($daysLeft == 1) {
            echo "T-1 day";
        } else {
            echo "T-" . $daysLeft . " days";
        }
    }
?>
<head>
    <meta charset="UTF-8">
    <title>Gifford-Sachs Wedding</title>
    <link rel="shortcut icon" href="favicon.ico">
    <link rel="stylesheet" href="thickbox.css" type="text/css" media="screen">
    <link rel="stylesheet" href="style.css" type="text/css" media="screen">
    <script type="text/javascript"><!--
        var WEBPAGE_FILE = "<? echo $WEBPAGE_FILE; ?>";
    //--></script>
    <script src="jquery-1.2.3.min.js" type="text/javascript"></script>
    <script src="thickbox-compressed.js" type="text/javascript"></script>
    <script src="header.js" type="text/javascript"></script>
</head>
<body>
<div id="page">

<div id="page-header">
<h1 class="all-header-text"><a href="index.php"><img src="images/banner.png"></a></h1>
<ul id="nav">
<? emitNavItem("info.php", "Info"); ?>
<? emitNavItem("about.php", "About Us"); ?>
<? emitNavItem("photos.php", "Photos"); ?>
<? emitNavItem("contact.php", "Contact"); ?>
<li id="countdown" class="rightmost"><? emitCountdownText(); ?></li>
</ul>
</div>

<div id="page-content">
