<? 
  $RSRC_BASE = "/wedding/";
  include("header.inc");
?>

<div id="admin_text" style="display: none">
<p><b>You are an administrator. You can <a id="admin_logout" href="rsvp.php/admin_logout">log out</a> 
or <a id="admin_show_grouplist" href="rsvp.php/grouplist">view the guest list</a>.</b></p>
</div>

<div id="admin_auth" <? if(!$_REQUEST["do_admin"]) { ?>style="display: none" <? } ?>class="wrsvp_action">
<p class="wrsvp_error"></p>
<p>Papers, please.</p>
<form method="POST" action="rsvp.php/admin_auth" id="admin_auth_form">
<p><input type="password" name="admin_pass" id="admin_pass" length="25"> <input type="submit" name="submit" value="Authenticate"></p>
</form>
</div>

<div id="guest_auth" class="wrsvp_action">
<p class="wrsvp_error"></p>
<p>To respond to your invitation, please provide your <b>last name</b> and the
   <b>name of the street</b> to which your invitation was sent (for instance, if your
   invitation was sent to <i>1600 Pennsylvania Ave.</i>, enter <i>Pennsylvania</i>.)</p>
<form method="POST" action="rsvp.php/guest_auth" id="guest_auth_form"><table class="wrsvp_form">
  <tr><td>Last Name:</td><td><input type="text" name="last_name" id="guest_auth_last_name" length="30" value="<? echo urlencode($_POST["last_name"]); ?>"></td></tr>
  <tr><td>Street Name:</td><td><input type="text" name="street_name" id="guest_auth_street_name" length="30" value="<? echo urlencode($_POST["street_name"]); ?>"></td></tr>
<tr><td></td><td><input type="submit" name="submit" id="guest_auth_submit" value="View Response"></td></tr>
</table></form>
</div>

<div id="guest_multi" style="display: none" class="wrsvp_action">
</div>
<textarea id="guest_multi_template" style="display: none">
<p>Please select one of the following parties, or <a id="guest_multi_retry" href="rsvp.php/guest_auth">enter different information</a>:</p>
<ul>{for group in RSVP_DATA["candidates"]}
  <li><a class="wrsvp_auth_guest_multi" href="rsvp.php/auth_guest/${group["id"]}">This party</a>, consisting of:
      <ul>{for name in group["names"]}
        <li>${name}</li>{/for}
      </ul>
  </li>{/for}
</ul>
</textarea>

<div id="group" style="display: none" class="wrsvp_action">
</div>
<textarea id="group_template" style="display: none">
  <p class="wrsvp_error"></p>
  <p>If this is not the party that you'd like to RSVP for, <a href="rsvp.php/logout" id="guest_auth_logout">find a different reservation</a>.</p>
  <form method="POST" action="rsvp.php/edit_group" id="group_edit_form">
  {if IS_ADMIN}<p>Street Name: <span id="group_street">${RSVP_DATA["group_street"]}</span></p>
  <p>Guests:</p>{/if}
  <table class="wrsvp_form">
  <tr><th>Name</th><th>Attending?</th><th>Meal</th></tr>{for guest in RSVP_DATA["guests"]}
    <tr class="guest" id="guest_${guest["id"]}">
    <td class="guest_name">${guest["name"]}</td>
    <td><select class="guest_attending">
      <option value=""{if !guest["attending"]} selected{/if}>Not Attending</option>
      <option value="true"{if guest["attending"]} selected{/if}>Attending</option>
    </select></td>
    <td><select class="guest_meal">
      <option value=""{if !guest["meal"]} selected{/if}>No Selection</option>{for meal in RSVP_DATA["meal_options"]}
      <option value="${meal[0]}"{if guest["meal"] == meal[0]} selected{/if}>${meal[1]}</option>{/for}
    </select></td>
    </tr>{/for}
  </table>
  <p><input type="submit" name="group_edit_submit" id="group_edit_submit" value="Submit Changes"></p>
</textarea>

<div id="grouplist" style="display: none" class="wrsvp_action">
</div>
<textarea id="grouplist_template" style="display: none">
  <p>Number attending: ${RSVP_DATA["attendee_count"]} / ${RSVP_DATA["invitee_count"]}</p>
  <ul>{for group in RSVP_DATA["groups"]}
    <li><a href="rsvp.php/group/edit" class="grouplist_multi" id="grouplist_multi_${group["id"]}">${group["street"]}</a>: <ul>{for guest in group["guests"]}
      <li>${guest["name"]},&nbsp;{if !guest["attending"]}<b>NOT</b>&nbsp;{/if}attending,&nbsp;${guest["meal"]|default:"no meal selection"}</li>{/for}
    </ul></li>{/for}
  </ul>
</textarea>

<? include("footer.inc"); ?>
