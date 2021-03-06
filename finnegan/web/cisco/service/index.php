<?

$cisco = 1;
require "../../include/finnegan.inc";

// Destroy any session that might exist in case we have one sitting around.
// This can happen if the user bails out while in mkwake.php
@unlink("/tmp/sess_$extension");

?>
<CiscoIPPhoneMenu>
<Title>Wake-up Calls</Title>
<Prompt>Select an option</Prompt>

<MenuItem>
<Name>View/Edit Alarms</Name>
<URL><?echo $FinneganCiscoConfig->url_base?>/service/wakes.php</URL>
</MenuItem>

<MenuItem>
<Name>New Alarm</Name>
<URL><?echo $FinneganCiscoConfig->url_base?>/service/mkwake.php</URL>
</MenuItem>

<MenuItem>
<Name>Set Web Access PIN</Name>
<URL><?echo $FinneganCiscoConfig->url_base?>/service/setpin.php</URL>
</MenuItem>

<MenuItem>
<Name>About</Name>
<URL><?echo $FinneganCiscoConfig->url_base?>/service/about.php</URL>
</MenuItem>

<SoftKeyItem>
<Name>Select</Name>
<URL>SoftKey:Select</URL>
<Position>1</Position>
</SoftKeyItem>

<?

//Disabled, because this needs Expire: -1 header, which breaks 7940/60.  c.f. include/cisco-funcs.php
/*<SoftKeyItem>
<Name>Exit</Name>
<URL>SoftKey:Exit</URL>
<Position>3</Position>
</SoftKeyItem>*/

?>

</CiscoIPPhoneMenu>
