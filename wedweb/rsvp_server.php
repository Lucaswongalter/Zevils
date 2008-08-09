<?

$SESSION = array();
$RET = array();

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

function transform_guests_for_ret($people, $meal_names = false) {
  if($meal_names) {
    $meals = sql_fetch_all_hash("SELECT * FROM meals");
    $meal_map = array();
    foreach($meals as $meal) {
      $meal_map[$meal["meal_id"]] = $meal["name"];
    }
  }

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
    $ret_guests[] = array("id" => $guest["guest_id"],
                          "name" => $guest["name"],
                          "attending" => $attending_bool,
                          "meal" => $meal);
  }
  return $ret_guests;
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
  
  if($pass and $pass == "foo") {
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
  $group_id = $path[0];
  global $SESSION;
  
  set_ret("action", "group");
  
  if(!$group_id) $group_id = get_session("view_group");
  if(!$group_id) {
    set_ret("success", False);
    set_ret("error", "noinput");
    return;
  } else if(!is_admin() and $group_id != get_session("view_group")) {
    set_ret("success", False);
    set_ret("error", "nopriv");
    return;
  }

  $group_id = 0 + $group_id;
  $group = sql_fetch_hash(sprintf("SELECT * FROM groups WHERE group_id=%d", $group_id));
  if(!$group) {
    set_ret("success", False);
    set_ret("error", "nogroup");
    set_ret("sql", sprintf("SELECT * FROM groups WHERE group_id=%d", $group_id));
    return;
  }

  set_ret("success", True);
  set_ret("group_street", $group["street_name"]);

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
  } else {
    set_ret("success", True);

    $yes_total = 0;
    $total = 0;
    $ret_groups = array();
    $groups = sql_fetch_all_hash("SELECT * FROM groups ORDER BY group_id");
    foreach($groups as $group) {
      $group_id = 0 + $group["group_id"];
      $guests = sql_fetch_all_hash(sprintf("SELECT * FROM people WHERE group_id=%d ORDER BY name", $group_id));
      $ret_groups[] = array("id" => $group["group_id"],
                            "street" => $group["street_name"],
                            "guests" => transform_guests_for_ret($guests, true));
      foreach($guests as $guest) {
          $total++;
          if($guest["attending"]) $yes_total++;
      }
    }
    set_ret("groups", $ret_groups);
    set_ret("attendee_count", $yes_total);
    set_ret("invitee_count", $total);
  }
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
  } else if($obj) {
    set_ret("action", "UNKNOWN");
    set_ret("error", $obj);
  }

  echo json_encode($RET);
}

main();

?>
