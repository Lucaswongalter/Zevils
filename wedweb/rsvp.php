<?

global $RSRC_BASE;

$SESSION = array();
$RSRC_BASE = "/wedding/";

function sql_do($query) {
  $result = mysql_query($query);
  if(!$result) {
    die("SQL error: " . mysql_error());
  }
  return $result;
}

function sql_fetch_one($query) {
  $result = sql_do($query);
  $row = mysql_fetch_row($result);
  if($row) {
    $ret = $row[0];
  } else {
    $ret = Null;
  }
  mysql_free_result($result);
  return $ret;
}

function sql_fetch_hash($query) {
  $result = sql_do($query);
  $row = mysql_fetch_assoc($result);
  mysql_free_result($result);
  return $ret;
}

function sql_fetch_all_one($query) {
  $result = sql_do($query);
  $ret = array();
  while(($row = mysql_fetch_row($result))) {
    $ret[] = $row[0];
  }
  mysql_free_result($result);
  return $ret;
}

function sql_fetch_all_hash($query) {
  $result = sql_do($query);
  $ret = array();
  while(($row = mysql_fetch_assoc($result))) {
    $ret[] = $row;
  }
  mysql_free_result($result);
  return $ret;
}


function init_db() {
  global $DBH;
  $DBH = mysql_connect("localhost", "root");
  if(!$DBH) {
    die("Couldn't connect: " . mysql_error());
  }
  if(!mysql_select_db("wrsvp")) {
    die("Couldn't select DB: " . mysql_error());
  }
}

function normalize_name($name) {
  return preg_replace("/[^a-zA-Z ]/", "", $name);
}

function main() {
  ob_start("ob_gzhandler");
  init_db();
  check_session();
  include("header.inc");
  if(is_admin()) {
    ?><p><b>You are an administrator.  Click <a href="<? echo $RSRC_BASE; ?>rsvp.php/unadmin">here</a> to log out.</b></p><?
  }
  dispatch_request();
  include("footer.inc");
}

function init_session($id = Null, $destroy = Null) {
  global $SESSION;
  global $SESSION_ID;

  if($SESSION_ID and !$destroy) return;
  
  if($SESSION_ID) sql_do("DELETE FROM sessions WHERE session_id = '" .
                         mysql_real_escape_string($SESSION_ID) .
                         "'");
  if($destroy) {
    setcookie("wrsvp_session", "", time() - 3600);
    $SESSION = array();
    return;
  }
  
  if($id) {
    $data = sql_fetch_one("SELECT session_data FROM sessions WHERE session_id = '" .
                          mysql_real_escape_string($id) .
                          "'");
    if($data) {
      $SESSION = unserialize($data);
      $SESSION_ID = $id;
    } else {
      $id = Null;
    }
  }

  if(!$id) {
    $SESSION_ID = md5(uniqid(rand(), true));
    $SESSION = array();
    sql_do("INSERT INTO sessions(session_id, session_data) VALUES('" .
           mysql_real_escape_string($SESSION_ID) .
           "', '" .
           mysql_real_escape_string(serialize($SESSION)) .
           "')");
    setcookie("wrsvp_session", $SESSION_ID, time() + 60*60*24*365);
  }
}

function check_session() {
  if($_COOKIE["wrsvp_session"]) {
    init_session($_COOKIE["wrsvp_session"]);
  }
}

function set_session($key, $value) {
  global $SESSION;
  global $SESSION_ID;
  
  $SESSION[$key] = $value;
  sql_do("UPDATE sessions SET session_data='" .
         mysql_real_escape_string(serialize($SESSION)) .
         "' WHERE session_id='" .
         mysql_real_escape_string($SESSION_ID) .
         "'");
}

function get_session($key) {
  global $SESSION;
  return $SESSION[$key];
}

function is_admin() {
  if(get_session("is_admin") == 1) {
    return True;
  } else {
    return False;
  }
}


function authenticate_admin($path = array()) {
  global $RSRC_BASE;
  
  $pass = $_POST["admin_pass"];
  if($pass and $pass == "foo") {
    init_session();
    set_session("is_admin", 1);
    authenticate_guest();
  } else {
    if($pass) echo "<p class=\"wrsvp_error\">Invalid password.</p>\n";
?>
<p>Papers, please.</p>
<form method="POST" action="<? echo $RSRC_BASE; ?>rsvp.php/admin">
<p><input type="password" name="admin_pass" length="25"></p>
</form>
<?
  }
}

function authenticate_guest($path = array()) {
  global $RSRC_BASE;
    
  $street_name = $_REQUEST["street_name"];
  $last_name = $_REQUEST["last_name"];

  $error = "";

  if(!$group_id and $street_name and $last_name) {
    $norm_last_name = normalize_name($last_name);
    $norm_street_name = strtoupper($street_name);
    $candidates = sql_fetch_all_hash("SELECT * FROM groups WHERE street_name='" .
                                     mysql_real_escape_string($norm_street_name) .
                                     "'");
    $groups = array();
    $group_people = array();

    foreach($candidates as $candidate) {
      $people = sql_fetch_all_one(sprintf("SELECT name FROM people WHERE group_id=%d",
                                          $candidate["group_id"]));
      $group_people[$candidate["group_id"]] = $people;
      foreach($people as $person) {
        if(preg_match("/ $norm_last_name\$/i", normalize_name($person))) {
          $groups[] = $candidate;
          break;
        }
      }
    }

    if(sizeof($groups) == 0) {
      $error = "We could not find an invitation with that information.";
    } else if(sizeof($groups) == 1) {
      $group = $groups[0];
    } else {
      echo "<p>Please select one of the following parties:</p>\n<ol>\n";
      foreach($groups as $candidate) {
        $group_id = $candidate["group_id"];
        echo "  <li><a href=\"<? echo $RSRC_BASE; ?>rsvp.php/group/$group_id\">This</a> party, consisting of:\n  <ul>";
        foreach($group_people[$group_id] as $person) {
          echo "    <li>" . htmlspecialchars($person) . "</li>\n";
        }
        echo "  </ul></li>\n";
      }
      echo "</ol>\n";
      return;
    }
  }

  if($group) {
    init_session();
    set_session("view_group", $group["group_id"]);
    header("Location: ".$RSRC_BASE."rsvp.php/group/".$group["group_id"]);
    return;
  } else {
    if($last_name) {
      $esc_last_name = urlencode($last_name);
    } else {
      $esc_last_name = "";
    }
    if($street_name) {
      $esc_street_name = urlencode($street_name);
    } else {
      $esc_street_name = "";
    }

    if($error) echo "<p class=\"wrsvp_error\">$error</p>\n";
    ?>
<p>To respond to your invitation, please provide your <b>last name</b> and the
<b>name of the street</b> to which your invitation was sent (for instance, if your
invitation was sent to <i>1600 Pennsylvania Ave.</i>, enter <i>Pennsylvania</i>.)</p>

<form method="POST" action="<? echo $RSRC_BASE; ?>rsvp.php"><table class="wrsvp_form">
<tr><td>Last Name:</td><td><input type="text" name="last_name" length="30" value="<? echo $esc_last_name ?>"></td></tr>
<tr><td>Street Name:</td><td><input type="text" name="street_name" length="30" value="<? echo $esc_street_name ?>"></td></tr>
<tr><td></td><td><input type="submit" name="submit" value="View Response"></td></tr>
</table></form>
    <?
  }
}


function edit_if_admin($name, $val) {
  if(is_admin()) {
    printf('<input type="text" length="20" value="%s">', urlencode($val));
  } else {
    echo htmlspecialchars($val);
  }
}

function display_guest_for_edit($guest) {
  $guest_id = $guest["person_id"];
  $guest_attending = $guest["attending"];
  $guest_meal = $guest["meal_id"];

  ?>
  <tr>
    <td><? edit_if_admin("guest_name[$guest_id]", $guest["name"]); ?></td>
    <td><select name="guest_attending[<? echo $guest_id; ?>]">
      <option value=""<? if(!$guest_attending) echo " selected"; ?>>Not Attending</option>
      <option value="true"<? if($guest_attending) echo " selected"; ?>>Attending</option>
    </select></td>
    <td><select name="guest_meal[<? echo $guest_id; ?>]">
      <option value=""<? if(!$guest_meal) echo " selected"; ?>>No Selection</option>
      <? foreach($meals as $meal) {
           if($guest_meal and $meal["meal_id"] == $guest_meal) {
             $seltext = " selected";
           } else {
             $seltext = "";
           }
           printf("<option value=\"%d\"%s>%s</option>\n",
                  $meal["meal_id"],
                  $seltext,
                  $meal["name"]);
      ?>
    </select></td>
  </tr><?
  }
}

function display_group($path) {
  $group_id = $path[0];

  if(!$group_id) return authenticate_guest();
  if(!is_admin() and (!get_session("view_group") or get_session("view_group") != $group_id)) {
    echo "<p class=\"wrsvp_error\">Please enter your reservation info.</p><\n>";
    return authenticate_guest();
  }

  if(!$group_street) {
    $group = sql_fetch_hash(sprintf("SELECT * FROM groups WHERE group_id=%d", $group_id));
    if(!$group) {
      printf("<p class=\"wrsvp_error\">That party (%d) does not exist.</p>\n", $group_id);
      return authenticate_guest();
    }
    $group_street = $group["street_name"];
  }

  $people = sql_fetch_all_hash(sprintf("SELECT * FROM people WHERE group_id=%d ORDER BY name", $group_id));
  $meals = sql_fetch_all_hash("SELECT * FROM meals ORDER BY name");
  
  ?>
  <form method="POST" action="<? echo $RSRC_BASE; ?>rsvp.php/editguests/<? printf("%d", $group_id); ?>">
  <? if(is_admin()) { ?>
    <p>Street Name: <? edit_if_admin("group_street", $group_street); ?></p>
  <? } ?>
  <p>Guests:</p>
  <table class="wrsvp_form">
  <tr><th>Name</th><th>Attending?</th><th>Meal</th></tr>
  <? foreach($people as $guest) display_guest_for_edit($guest); ?>
  </table>
  <?
}


function dispatch_request() {
  $path = explode("/", $_SERVER["PATH_INFO"]);
  if($path and sizeof($path) > 1) {
    array_shift($path);
    $obj = array_shift($path);
  } else {
    $obj = "";
  }

  if($obj == "unadmin") {
    init_session(Null, True);
    $obj = "";
  }

  if(!$obj) {
    authenticate_guest($path);
  } else if($obj == "group") {
    display_group($path);
  } else if($obj == "admin") {
    authenticate_admin($path);
  } else if($obj == "editguests") {
    edit_guest($path);
  } else {
    die("Unknown obj $obj");
  }
}


main();

?>