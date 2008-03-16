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
    	$file = "images/$file";
		$thumb = preg_replace('/\.([a-z]+)$/', '-thumb.\1', $file);
		if(!file_exists($thumb)) $thumb = $file;

    	printf('<a href="%s" title="%s" class="thickbox"><img src="%s" width="%d" height="%d" alt="%s" title="%s"></a>',
    		$file, $caption, $thumb, $width, $height, $caption, $caption);
    }
?>
<head>
    <meta charset="UTF-8">
    <title>Gifford-Sachs Wedding</title>
    <link rel="shortcut icon" href="favicon.ico">
    <link rel="stylesheet" href="thickbox.css" type="text/css" media="screen">
    <link rel="stylesheet" href="style.css" type="text/css" media="screen">
    <script src="jquery-1.2.3.min.js" type="text/javascript"></script>
    <script src="thickbox-compressed.js" type="text/javascript"></script>
    <script src="header.js" type="text/javascript"></script>
</head>
<body>
<div id="page">

<div id="page-header">
<h1 class="all-header-text"><a href="index.php">Elizabeth and Matthew</a></h1>
<ul id="nav">
<? emitNavItem("info.php", "Info"); ?>
<? emitNavItem("about.php", "About Us"); ?>
<? emitNavItem("photos.php", "Photos"); ?>
<li id="countdown" class="rightmost"><!-- Countdown text gets inserted here --></li>
</ul>
</div>

<div id="page-content">
