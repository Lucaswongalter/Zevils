<?

$cisco = 1;
require "../../include/finnegan.inc";

$id = preg_replace("/[^0-9]/", "", $_REQUEST["id"]);
$date = $_REQUEST["date"];

if($date) {
	$result = mysql_query("DELETE FROM wakes WHERE wake_id=$id AND extension='$extension'");
} else {
	$result = mysql_query("UPDATE wakes SET this_trigger_time=NULL, next_trigger_time=NULL WHERE extension='$extension' AND wake_id=$id");
}
if(!$result) db_error();
mysql_query("UPDATE log_wake SET end_time=NOW, result='success' WHERE ISNULL(end_time) AND wake_id=$id AND extension='$extension' AND event='activate'");

header("Refresh: 0; url=SoftKey:Exit");
?>

<CiscoIPPhoneText>
<Title>Done</Title>
<Text>Exiting...</Text>
</CiscoIPPhoneText>

