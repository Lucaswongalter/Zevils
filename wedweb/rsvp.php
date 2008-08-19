<? 

  header("Pragma: no-cache");
  header("Expires: -1");
  header("Cache-Control: no-cache, must-revalidate");

  if($_SERVER["SERVER_NAME"] == "localhost" or $_SERVER["SERVER_NAME"] == "192.168.1.193" or $_SERVER["SERVER_NAME"] == "aardvark.local") {
    $RSRC_BASE = "/wedding/";
  } else {
    $RSRC_BASE = "/";
  }

  include("header.inc");
?>

<div id="modal-overlay" style="display: none"></div>
<div id="modal-loading" style="display: none" class="modal-dialog">
  <p><img src="<? echo $RSRC_BASE; ?>ajax-loader.gif" width="16" height="16"> Loading...</p>
</div>
<div id="modal-thanks" style="display: none" class="modal-dialog">
  <p class="modal-thanks-text"></p>
  <p class="modal-thanks-subtext">If you need to change your
  responses, simply do so and click the &ldquo;Update Responses&rdquo;
  button again.</p>
  <p class="modal-thanks-button"><input id="modal-thanks-close-button" type="button" value="OK"></p>
</div>

<div id="admin_text" style="display: none" class="wrsvp_text">
<p><b>You are an administrator. You can <a id="admin_logout" href="rsvp.php/admin_logout">log out</a>,
<a id="admin_show_grouplist" href="rsvp.php/grouplist">view the guest list</a>, or <a id="admin_show_search" href="rsvp.php/groupsearch">simulate guest login.</a></b></p>
</div>

<div id="admin_auth" <? if(!$_REQUEST["do_admin"]) { ?>style="display: none" <? } ?>class="wrsvp_action wrsvp_text">
<p class="wrsvp_error"></p>
<p>Papers, please.</p>
<form method="POST" action="rsvp.php/admin_auth" id="admin_auth_form">
<p><input type="password" name="admin_pass" id="admin_pass" length="25"> <input type="submit" name="submit" value="Authenticate"></p>
</form>
</div>

<div id="guest_auth" class="wrsvp_action wrsvp_text"<? if($_REQUEST["do_admin"]) { ?> style="display: none"<? } ?>>
<p class="wrsvp_response_deadline">Please respond by September 25<sup>th</sup>!</p>
<p class="wrsvp_error"></p>
<p>To respond to your invitation, please provide your <b>last name</b> and the
   <b>name of the street</b> (or P.O. box number) to which your invitation was sent (for instance, if your
   invitation was sent to <i>1600 West Pennsylvania Ave.</i>, enter <i>Pennsylvania</i>.)</p>
<form method="POST" action="rsvp.php/guest_auth" id="guest_auth_form"><table class="wrsvp_form">
  <tr><td>Last Name:</td><td><input type="text" name="last_name" id="guest_auth_last_name" length="30" value="<? echo urlencode($_POST["last_name"]); ?>"></td></tr>
  <tr><td>Street Name or P.O. Box Number:</td><td><input type="text" name="street_name" id="guest_auth_street_name" length="30" value="<? echo urlencode($_POST["street_name"]); ?>"></td></tr>
<tr><td></td><td><input type="submit" name="submit" id="guest_auth_submit" value="View Response"></td></tr>
</table></form>
</div>

<div id="guest_multi" style="display: none" class="wrsvp_action wrsvp_text">
</div>
<textarea id="guest_multi_template" style="display: none">
<p>Please select one of the following parties, or <a id="guest_multi_retry" href="rsvp.php/guest_auth">enter different information</a>:</p>
<ul>{for group in RSVP_DATA["candidates"]}
  <li><a class="guest_auth_multi" id="guest_auth_multi_${group["id"]}" href="rsvp.php/auth_guest/${group["id"]}">This party</a>, consisting of:
      <ul>{for name in group["names"]}
        <li>${name}</li>{/for}
      </ul>
  </li>{/for}
</ul>
</textarea>

<div id="group" style="display: none" class="wrsvp_action wrsvp_text">
  <p class="wrsvp_error"></p>
  <p>If this isn&rsquo;t the party that you&rsquo;d like to respond for, <a href="rsvp.php/logout" id="guest_auth_logout">find a different reservation</a>.</p>
  <p class="wrsvp_response_deadline">Please respond by September 25<sup>th</sup>!</p>
  <form method="POST" action="rsvp.php/edit_group" id="group_edit_form">
  </form>
</div>
<textarea id="group_template" style="display: none">
  {if IS_ADMIN}<p>Street Name: <input type="text" length="20" id="group_street" value="${RSVP_DATA["group_street"]}"></p>
  <p>Guests:</p>{/if}
  <table class="wrsvp_form">
  <tr><th>Name</th>{if RSVP_DATA["dessert_invite"]}<th>Attending Rehearsal Dessert Party?</th>{/if}<th>Attending Wedding?</th><th>Entr&eacute;e</th></tr>{for guest in RSVP_DATA["guests"]}
    <tr class="guest" id="guest_${guest["id"]}">
    <td class="guest_name">${guest["name"]}</td>
    {if RSVP_DATA["dessert_invite"]}
    <td><select class="guest_attending_dessert">
      <option value=""{if guest["attending_dessert"] == ""} selected{/if}>No Response for Dessert Party</option>
      <option value="0"{if guest["attending_dessert"] == "0"} selected{/if}>Not Attending Dessert Party</option>
      <option value="1"{if guest["attending_dessert"] == "1"} selected{/if}>Attending Dessert Party</option>
    </select></td>
    {/if}
    <td><select class="guest_attending">
      <option value=""{if guest["attending"] == ""} selected{/if}>No Response for Wedding</option>
      <option value="0"{if guest["attending"] == "0"} selected{/if}>Not Attending Wedding</option>
      <option value="1"{if guest["attending"] == "1"} selected{/if}>Attending Wedding</option>
    </select></td>
    <td><select class="guest_meal">
      <option value=""{if !guest["meal"]} selected{/if}>No Selection</option>{for meal in RSVP_DATA["meal_options"]}
      <option value="${meal[0]}"{if guest["meal"] == meal[0]} selected{/if}>${meal[1]}</option>{/for}
    </select></td>
    <td class="wrsvp_guest_error" style="display: none"></td>
    </tr>{/for}
<!--
    {if RSVP_DATA["guests"].length > 1}
    <tr>
        <td></td>
        {if RSVP_DATA["dessert_invite"]}<td><input type="button" id="group_all_not_attending_dessert" value="Nobody&rsquo;s Attending"></td>{/if}
        <td><input type="button" id="group_all_not_attending" value="Nobody&rsquo;s Attending"></td>
    </tr>
    <tr>
        <td></td>
        {if RSVP_DATA["dessert_invite"]}<td><input type="button" id="group_all_attending_dessert" value="Everybody&rsquo;s Attending"></td>{/if}
        <td><input type="button" id="group_all_attending" value="Everybody&rsquo;s Attending"></td>
    </tr>
    {/if}
-->
  </table>
  <p>Are you interested in sharing transportation or lodging with
  other guests? <select id="group_wants_share">
    <option value=""{if RSVP_DATA["wants_share"] == "0"} selected{/if}>No</option>
    <option value="true"{if RSVP_DATA["wants_share"] == "1"} selected{/if}>Yes</option>
  </select></p>
  <div id="group_share_details" style="display: none">
    <p>Please give us details such as where you&rsquo;re coming from, your travel dates, and what you&rsquo;re looking for / offering; we&rsquo;ll do what we can.  Also, please provide an email address where we, and other guests we may want to put in contact with you, can reach you.</p>
    <div id="group_share_textarea"></div>
  </div>
  <div id="group_comments">
    <p>If you have any comments or questions, you
    can <a href="mailto:us@sachsfam.org">email us</a> or provide them
    here.  If you&rsquo;d like a response, please let us know how to contact you.</p>
    <div id="group_comments_textarea"></div>
  </div>
  
  <p class="wrsvp_success"></p>
  <p><input type="submit" name="group_edit_submit" id="group_edit_submit" value="Update Responses"></p>

  <h3><span class="subheader-text all-header-text">Information for Guests</span></h3>
  <div id="wrsvp_tabstrip" class="tabstrip">
    <ul>
      <li><a href="#rsvp-tab-entree-info"><span>Entr&eacute;e Selections</span></a></li>
      {if RSVP_DATA["dessert_invite"]}<li><a href="<? echo $RSRC_BASE; ?>rsvp_server.php/dessert_info"><span>Rehearsal Dessert Party</span></a></li>{/if}
      <li><a href="<? echo $RSRC_BASE; ?>rsvp_server.php/wedding_info"><span>Ceremony and Reception</span></a></li>
    </ul>
    <div id="rsvp-tab-entree-info">
      <dl class="wrsvp-dl">
        <dt>Basil Sea Bass:</dt>
        <dd>Basil and pine nut-crusted sea bass atop saffron orzo risotto,
          roasted tomato sauce.</dd>

        <dt>Leek Strudel:</dt>
        <dd>Leek, wild mushroom, and goat cheese strudel with fire-roasted pepper sauce and organic olive oil.</dd>
        
        <dt>New York Sirloin:</dt>
        <dd>Grilled New York sirloin steak served with caramelized cipollini, red wine jus.</dd>

        <dt>Tomato-Stuffed Zucchini:</dt>
        <dd>Roasted vine-ripened red and yellow tomato-stuffed zucchini with pearl mozzarella.</dd>
      </dl>
      <p>If you would like a kid&rsquo;s meal, or have any special dietary needs or other requests, please <a href="mailto:us@sachsfam.org">contact us</a>.
    </div>
  </div>
</textarea>

<div id="grouplist" style="display: none" class="wrsvp_action wrsvp_text">
</div>
<textarea id="grouplist_template" style="display: none">
  <ul>
    <li>Number responded: ${RSVP_DATA["response_count"]} / ${RSVP_DATA["invitee_count"]}</li>
    <li>Number attending: ${RSVP_DATA["attendee_count"]} / ${RSVP_DATA["invitee_count"]}</li>
    <li>Number responded for rehearsal dessert: ${RSVP_DATA["dessert_response_count"]} / ${RSVP_DATA["dessert_invitee_count"]}</li>
    <li>Number attending rehearsal dessert: ${RSVP_DATA["dessert_attendee_count"]} / ${RSVP_DATA["dessert_invitee_count"]}</li>
    <li>Meal counts:
      <ul>{for meal in RSVP_DATA["meals"]}
        <li>${meal}: ${RSVP_DATA["meal_counts"][meal]}</li>{/for}
      </ul></li>
    <li><a href="rsvp_server.php/csv" id="csv_export">Download spreadsheet</a></li>
    <li><a href="rsvp_server.php/rss" id="rss_link">Get RSS link</a></li>
  </ul>
  <div id="wrsvp_grouplist_tabstrip" class="tabstrip">
    <ul>
      <li><a href="#grouplist_groups"><span>Guest List</span></a></li>
      <li><a href="#grouplist_share"><span>Sharing Requests</span></a></li>
      <li><a href="#grouplist_comments"><span>Comments</span></a></li>
      <li><a href="#grouplist_changes"><span>Recent Responses</span></a></li>
    </ul>
    <div id="grouplist_groups">
      <h4>Guest List</h4>
      <ul>{for group in RSVP_DATA["groups"]}
        <li><a href="rsvp.php/group/edit" class="grouplist_multi" id="grouplist_multi_${group["id"]}">${group["street"]}</a>:
          ({if !group["dessert_invite"]}<b>NOT&nbsp;</b>{/if}invited to dessert):
          <ul>{for guest in group["guests"]}
            <li>${guest["name"]},&nbsp;{if guest["attending"] == ""}<b>???</b>&nbsp;{elseif guest["attending"] == "0"}<b>NOT</b>&nbsp;{/if}attending,&nbsp;
              {if group["dessert_invite"]}
                {if guest["attending_dessert"] == ""}<b>???</b>&nbsp;{elseif guest["attending_dessert"] == "0"}<b>NOT</b>&nbsp;{/if}attending dessert,&nbsp;
              {/if}
              ${guest["meal"]|default:"no meal selection"}</li>{/for}
        </ul></li>{/for}
      </ul>
    </div>
    {macro prettyJoinList(theList)}
      {if theList.length == 1}
        ${theList[0]["name"]}:
      {elseif theList.length == 2}
        ${theList[0]["name"]} and ${theList[1]["name"]}:
      {else}
        {for name in theList}
          {if name_index >= theList.length - 1}
            and&nbsp;${name["name"]}:
          {else}
            ${name["name"]},&nbsp;
          {/if}
        {/for}
      {/if}
    {/macro}
    <div id="grouplist_share">
      <h4>Sharing Requests</h4>
      <ul>{for group in RSVP_DATA["groups"]}
        {if group["wants_share"] == "1"}
          <li>${prettyJoinList(group["guests"])} <pre id="grouplist_group_${group["id"]}_share_details"></pre></li>
        {/if}
      {/for}</ul>
    </div>
    <div id="grouplist_comments">
      <h4>Comments</h4>
      <ul>{for group in RSVP_DATA["groups"]}
        {if group["comments"] != ""}
          <li>${prettyJoinList(group["guests"])} <pre id="grouplist_group_${group["id"]}_comments"></pre></li>
        {/if}
      {/for}</ul>
    </div>
    <div id="grouplist_changes">
      <h4>Recent Responses</h4>
      <div id="grouplist_changes_content">Loading changes...</div>
    </div>
  </div>
</textarea>

<? include("footer.inc"); ?>
