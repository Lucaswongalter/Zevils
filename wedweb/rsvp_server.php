<?

$SESSION = array();
$RET = array();

function get_config_data($key) {
  $uid = getmyuid();
  $user_info = posix_getpwuid($uid);
  $home = $user_info["dir"];
  $data = json_decode(file_get_contents("$home/.wrsvp"), true);
  return $data[$key];
}

function sql_do($query) {
  //global $RET;
  //if(!$RET["sql"]) $RET["sql"] = "";
  //$RET["sql"] .= "$query\n";

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
  return $row;
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

  $dbconfig = get_config_data("dbconfig");
  $DBH = mysql_connect($dbconfig["host"], $dbconfig["user"], $dbconfig["pass"]);
  if(!$DBH) {
    die("Couldn't connect: " . mysql_error());
  }
  if(!mysql_select_db($dbconfig["db"])) {
    die("Couldn't select DB: " . mysql_error());
  }
}

function normalize_name($name) {
  return preg_replace("/[^a-zA-Z ]/", "", $name);
}

function meal_map() {
    $meals = sql_fetch_all_hash("SELECT * FROM meals");
    $meal_map = array();
    foreach($meals as $meal) {
      $meal_map[$meal["meal_id"]] = $meal["name"];
    }
    return $meal_map;
}

function transform_guests_for_ret($people, $meal_names = false) {
  if($meal_names) $meal_map = meal_map();

  $ret_guests = array();
  foreach($people as $guest) {
    if($guest["attending"]) {
      $attending_bool = True;
    } else {
      $attending_bool = False;
    }

    if($meal_names and $guest["meal"]) {
      $meal = $meal_map[$guest["meal"]];
    } else {
      $meal = $guest["meal"];
    }
    $ret_guests[] = array("id" => $guest["person_id"],
                          "name" => $guest["name"],
                          "attending" => $attending_bool,
                          "meal" => $meal);
  }
  return $ret_guests;
}

function group_checking_perms($group_id = Null) {
  if(!$group_id) $group_id = get_session("view_group");
  if(!$group_id) {
    set_ret("success", False);
    set_ret("error", "noinput");
    return false;
  } else if(!is_admin() and $group_id != get_session("view_group")) {
    set_ret("success", False);
    set_ret("error", "nopriv");
    return false;
  }

  $group = sql_fetch_hash(sprintf("SELECT * FROM groups WHERE group_id=%d", $group_id));
  if(!$group) {
    set_ret("success", False);
    set_ret("error", "nogroup");
    return false;
  }

  return $group;
}

function main() {
  ob_start("ob_gzhandler");
  init_db();
  check_session();
  if(is_admin()) {
    set_ret("is_admin", True);
  } else {
    set_ret("is_admin", False);
  }
  if(get_session("view_group")) {
    set_ret("is_guest", True);
  } else {
    set_ret("is_guest", False);
  }
  dispatch_request();
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

  if(!$SESSION_ID) init_session();
  
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

function admin_pass() {
  return get_config_data("admin_pass");
}

function rss_pass() {
  return get_config_data("rss_pass");
}

function is_admin() {
  if(get_session("is_admin") == 1) {
    return True;
  } else {
    return False;
  }
}

function set_ret($key, $value) {
   global $RET;
   $RET[$key] = $value;
}


function authenticate_admin($path = array()) {
  global $RSRC_BASE;

  set_ret("action", "admin_auth");
  $pass = $_POST["admin_pass"];
  
  if($pass and sha1("wrsvp:::$pass") == admin_pass()) {
    set_session("is_admin", 1);
    set_ret("success", True);
    authenticate_guest();
  } else {
    set_ret("success", False);
    if($pass) {
      set_ret("error", "badpass");
    } else {
      set_ret("error", "nopass");
    }
  }
}

function authenticate_guest($path = array()) {
  global $RSRC_BASE;

  set_ret("action", "guest_auth");
  $street_name = $_REQUEST["street_name"];
  $last_name = $_REQUEST["last_name"];
  $group_id = 0 + $path[0];

  $error = "";

  if(!$street_name or !$last_name) {
    set_ret("error", "nocreds");
  } else {
    $norm_last_name = normalize_name($last_name);
    $norm_street_name = strtoupper($street_name);
    $sql = sprintf("SELECT * FROM groups WHERE street_name='%s'",
                   mysql_real_escape_string($norm_street_name));
    if($group_id) {
      $sql .= sprintf(" AND group_id=%d", $group_id);
    }
    $candidates = sql_fetch_all_hash($sql);
    $groups = array();
    $group_people = array();

    foreach($candidates as $candidate) {
      $people = sql_fetch_all_one(sprintf("SELECT name FROM people WHERE group_id=%d",
                                          0 + $candidate["group_id"]));
      $group_people[$candidate["group_id"]] = $people;
      foreach($people as $person) {
        if(preg_match("/ $norm_last_name\$/i", normalize_name($person))) {
          $groups[] = $candidate;
          break;
        }
      }
    }

    if(sizeof($groups) == 0) {
      set_ret("success", False);
      set_ret("error", "notfound");
    } else if(sizeof($groups) == 1) {
      set_ret("success", True);
      set_ret("gotmulti", False);
      set_session("view_group", $groups[0]["group_id"]);
    } else {
      set_ret("success", True);
      set_ret("gotmulti", True);
      $ret_candidates = array();
      foreach($groups as $candidate) {
        $group_id = $candidate["group_id"];
        $ret_candidates[] = array("id" => $group_id,
                                  "names" => $group_people[$group_id]);
      }
      set_ret("candidates", $ret_candidates);
    }
  }
}


function display_group($path) {
  $group_id = 0 + $path[0];
  global $SESSION;
  
  set_ret("action", "group");
  $group = group_checking_perms($group_id);
  if(!$group) return;  

  set_ret("success", True);
  set_ret("group_street", $group["street_name"]);
  $group_id = 0 + $group["group_id"];

  $people = sql_fetch_all_hash(sprintf("SELECT * FROM people WHERE group_id=%d ORDER BY name", $group_id));
  $meals = sql_fetch_all_hash("SELECT * FROM meals ORDER BY name");

  $ret_meals = array();
  foreach($meals as $meal) {
    $ret_meals[] = array($meal["meal_id"], $meal["name"]);
  }
  set_ret("meal_options", $ret_meals);
  set_ret("guests", transform_guests_for_ret($people));
}

function list_groups($path) {
  set_ret("action", "grouplist");
  if(!is_admin()) {
    set_ret("success", False);
    set_ret("error", "nopriv");
    return;
  }

  set_ret("success", True);

  $yes_total = 0;
  $total = 0;
  $ret_groups = array();
  $groups = sql_fetch_all_hash("SELECT * FROM groups ORDER BY group_id");

  $meal_counts = array();
  $meals = sql_fetch_all_hash("SELECT * FROM meals ORDER BY name");
  $meals_ret = array();
  $meal_map = meal_map();
  $meals_ret[] = "No Selection";
  $meal_counts[$meals_ret[0]] = 0;
  foreach($meals as $meal) {
    $meals_ret[] = $meal["name"];
    $meal_counts[$meal["name"]] = 0;
  }
  
  foreach($groups as $group) {
    $group_id = 0 + $group["group_id"];
    $guests = sql_fetch_all_hash(sprintf("SELECT * FROM people WHERE group_id=%d ORDER BY name", $group_id));
    $ret_groups[] = array("id" => $group["group_id"],
                          "street" => $group["street_name"],
                          "guests" => transform_guests_for_ret($guests, true));
    foreach($guests as $guest) {
      $total++;
      if($guest["attending"]) $yes_total++;

      if($guest["meal"]) {
        $meal_name = $meal_map[$guest["meal"]];
      } else {
        $meal_name = $meals_ret[0];
      }
      $meal_counts[$meal_name]++;
    }
  }
  set_ret("groups", $ret_groups);
  set_ret("attendee_count", $yes_total);
  set_ret("invitee_count", $total);
  set_ret("meals", $meals_ret);
  set_ret("meal_counts", $meal_counts);
}

function edit_group($path) {
  $group_id = 0 + $path[0];

  set_ret("action", "group_edit");
  $group = group_checking_perms($group_id);
  if(!$group) return;

  set_ret("success", "true");
  $group_id = 0 + $group["group_id"];

  $meal_map = meal_map();
  $deltas = array();
  
  $post_data = file_get_contents("php://input");
  $data = json_decode($post_data, true);

  if(is_admin() and $data["street"] and $group["street_name"] != $data["street"]) {
      $deltas[] = sprintf("<li>Street name: %s &rarr; %s</li>",
                          htmlspecialchars($group["street_name"]),
                          htmlspecialchars($data["street"]));
      sql_do(sprintf("UPDATE groups SET street_name=upper('%s') WHERE group_id=%d",
                     mysql_real_escape_string($data["street"]),
                     $group_id));
  }

  $people = sql_fetch_all_hash(sprintf("SELECT * FROM people WHERE group_id=%d", $group_id));
  $people_map = array();
  foreach($people as $person) {
      $people_map[$person["person_id"]] = $person;
  }

  foreach($data["guests"] as $guest) {
      $attending_bool = 0;
      $meal_raw = "NULL";
      $guest_id = 0 + $guest["id"];
      $person = $people_map[$guest_id];
      if($guest["attending"]) $attending_bool = 1;
      if($guest["meal"]) $meal_raw = sprintf("%d", 0 + $guest["meal"]);
      
      sql_do("UPDATE people SET attending=$attending_bool, meal=$meal_raw WHERE person_id=$guest_id AND group_id=$group_id");

      if($attending_bool != 0+$person["attending"]) {
          $deltas[] = sprintf("<li>%s attending: %s &rarr; %s</li>",
                              htmlspecialchars($person["name"]),
                              $person["attending"],
                              $attending_bool);
      }
      if(($guest["meal"] and !$person["meal"]) or
         (!$guest["meal"] and $person["meal"]) or
         (0+$guest["meal"] != 0+$person["meal"])) {

        $old_meal = "No Selection";
        if($person["meal"]) $old_meal = $meal_map[$person["meal"]];
        $new_meal = "No Selection";
        if($guest["meal"]) $new_meal = $meal_map[$guest["meal"]];

        $deltas[] = sprintf("<li>%s meal selection: %s &rarr; %s</li>",
                            htmlspecialchars($person["name"]),
                            $old_meal,
                            $new_meal);
      }
  }

  if(is_admin()) {
    $changetext = "<p>An admin";
  } else {
    $changetext = "<p>A user";
  }
  $changetext .= " at IP " . $_SERVER["REMOTE_ADDR"] . " changed group $group_id as follows:</p>\n";
  $changetext .= "<ul>\n" . implode("\n", $deltas) . "\n</ul>\n";

  sql_do("INSERT INTO changes(change_time, change_text) VALUES ('" .
         mysql_real_escape_string(gmstrftime("%Y-%m-%dT%H:%M:%SZ")) .
         "', '" .
         mysql_real_escape_string($changetext) .
         "')");
  
  display_group($path);
}

function export_csv($path) {
    header("Content-disposition: attachment; filename=wedding-rsvp.xls");
    header("Content-type: excel/ms-excel; name=wedding-rsvp.xls");

    $meal_map = meal_map();
    $people = sql_fetch_all_hash("SELECT * FROM people ORDER BY group_id, name");
    print("\"Group\"\t\"Name\"\t\"Attending?\"\t\"Meal\"\n");
    foreach($people as $person) {
        if($person["meal"]) {
            $meal = $meal_map[$person["meal"]];
        } else {
            $meal = "";
        }

        if($person["attending"]) {
            $attending = "Y";
        } else {
            $attending = "";
        }
        
        printf("\"%s\"\t\"%s\"\t\"%s\"\t\"%s\"\n",
               $person["group_id"],
               $person["name"],
               $attending,
               $meal);
    }
}

function export_rss($path) {
  $rsspass = $_REQUEST["rssauth"];
  if(!is_admin() and (!$rsspass or sha1("wrsvp:::$rsspass") != rss_pass())) {
    header("Status: 403 Access Denied");
    header("Content-type: text/plain");
    print("Access restricted.\n");
    return;
  }

  $atom_tag = "tag:matthewg@zevils.com,2008-08-09:wrsvp";
  include_once('atombuilder/class.AtomBuilder.inc.php');
  $atom = new AtomBuilder("Wedding Responses", "http://w.sachsfam.org/rsvp.php", $atom_tag);

  $changes = sql_fetch_all_hash("SELECT change_time, change_text FROM changes ORDER BY change_time DESC LIMIT 200");

  $atom->setUpdated($changes[0]["change_time"]);
  $atom->setEncoding("UTF-8");
  $atom->setLanguage("en");
  $atom->setSubtitle("Responses to our wedding invitations");
  $atom->setIcon("http://w.sachsfam.org/favicon.ico");

  foreach($changes as $change) {
    $entry = $atom->newEntry("Response", "http://w.sachsfam.org/rsvp.php", "$atom_tag/" . $change["change_id"]);
    $entry->setUpdated($change["change_time"]);
    $entry->setContent($change["change_text"]);
    $atom->addEntry($entry);
  }

  $atom->outputAtom("1.0.0");
}


function dispatch_request() {
  global $RET;

  header("Content-type: text/plain");
  
  $path = explode("/", $_SERVER["PATH_INFO"]);
  if($path and sizeof($path) > 1) {
    array_shift($path);
    $obj = array_shift($path);
  } else {
    $obj = "";
  }

  if(!$obj) {
    if(is_admin()) {
      $obj = "grouplist";
    } else if(get_session("view_group")) {
      $obj = "group";
    }
  }

  if($obj == "logout") {
    set_ret("is_admin", False);
    set_ret("is_guest", False);
    set_ret("action", "logout");
    set_ret("success", True);
    init_session(Null, True);
  } else if($obj == "admin_auth") {
    authenticate_admin($path);
  } else if($obj == "guest_auth") {
    authenticate_guest($path);
  } else if($obj == "group") {
    display_group($path);
  } else if($obj == "group_edit") {
    edit_group($path);
  } else if($obj == "grouplist") {
    list_groups($path);
  } else if($obj == "csv") {
    export_csv($path);
    exit();
  } else if($obj == "rss") {
    export_rss($path);
    exit();
  } else if($obj) {
    set_ret("action", "UNKNOWN");
    set_ret("error", $obj);
  }

  echo json_encode($RET);
}

main();

?>
