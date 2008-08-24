<?

$SESSION = array();
$RET = array();

function get_home_dir() {
  $uid = getmyuid();
  $user_info = posix_getpwuid($uid);
  $home = $user_info["dir"];
  return $home;
}

function get_config_data($key) {
  $home = get_home_dir();
  $data = json_decode(file_get_contents("$home/.wrsvp"), true);
  return $data[$key];
}

function db_bool_to_php($val) {
  if(!isset($val))
    return NULL;
  else if(0+$val == 0)
    return "0";
  else if(0+$val == 1)
    return "1";
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
    $attending = db_bool_to_php($guest["attending"]);
    $attending_dessert = db_bool_to_php($guest["attending_dessert"]);

    if($meal_names and $guest["meal"]) {
      $meal = $meal_map[$guest["meal"]];
    } else {
      $meal = $guest["meal"];
    }
    $data = array("id" => $guest["person_id"],
                  "name" => $guest["name"],
                  "meal" => $meal);
    if(!is_null($attending))
      $data["attending"] = $attending;
    else
      $data["attending"] = "";
    if(!is_null($attending_dessert))
      $data["attending_dessert"] = $attending_dessert;
    else
      $data["attending_dessert"] = "";

    $ret_guests[] = $data;
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
  header("Pragma: no-cache");
  header("Expires: -1");
  header("Cache-Control: no-cache, must-revalidate");
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

  if($destroy) {
    sql_do("DELETE FROM sessions WHERE session_id = '" .
           mysql_real_escape_string($SESSION_ID) .
           "'");
    setcookie("wrsvp_session", "", time() - 42000, "/");
    $SESSION = array();
    return;
  }
  
  if($id) {
    $SESSION_ID = $id;
    $data = sql_fetch_one("SELECT session_data FROM sessions WHERE session_id = '" .
                          mysql_real_escape_string($id) .
                          "'");
    if($data) {
      $SESSION = unserialize($data);
      return;
    }
  } else {
    $SESSION_ID = md5(uniqid(rand(), true));
    setcookie("wrsvp_session", $SESSION_ID, time() + 60*60*24*365, "/");
  }

  $SESSION = array();
  sql_do("INSERT INTO sessions(session_id, session_data) VALUES('" .
         mysql_real_escape_string($SESSION_ID) .
         "', '" .
         mysql_real_escape_string(serialize($SESSION)) .
         "')");
}

function check_session() {
  global $SESSION_ID;

  if($_COOKIE["wrsvp_session"]) {
    init_session($_COOKIE["wrsvp_session"]);
  }
}

function set_session($key, $value) {
  global $SESSION;
  global $SESSION_ID;

  if(count($SESSION) == 0) init_session($SESSION_ID);
  
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
    set_ret("is_admin", True);
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
    $norm_street_name = preg_replace("/ *\\(.*?\\) */", "", $norm_street_name);
    $norm_street_name = preg_replace("/,.*/", "", $norm_street_name);
    $norm_street_name = preg_replace("/ +(APARTMENT|APT\\.) */", "", $norm_street_name);
    $norm_street_name = preg_replace("/(?!BOX) +#[0-9]+ */", "", $norm_street_name);
    $norm_street_name = preg_replace("/ +[A-Z][0-9]*\$/", "", $norm_street_name);
    $norm_street_name = preg_replace(array("/\\bNORTH\\b/",
                                           "/\\bSOUTH\\b/",
                                           "/\\bEAST\\b/",
                                           "/\\bWEST\\b/",
                                           "/\\bNORTHWEST\\b/",
                                           "/\\bSOUTHWEST\\b/",
                                           "/\\bSOUTHEAST\\b/"),
                                     array("N", "S", "E", "W", "NW", "SW", "SE"),
                                     $norm_street_name);
    $norm_street_name = preg_replace("/^([0-9]+) (.*) (N|S|E|W|NW|SW|SE)$/",
                                     "\\1 \\3 \\2",
                                     $norm_street_name);
    $norm_street_name = preg_replace("/\\b(P\\.O\\. )?BOX\\b ?/", "",
                                     $norm_street_name);
    $norm_street_name = preg_replace("/ (STREET|ST|PLACE|PL|PLACE|AVE|AVENUE|DRIVE|DR|COURT|CT|EXPRESSWAY|EXPY|PARKWAY|PKWY|TURNPIKE|TPKE|BLVD|BOULEVARD|BOLEVARD|CIR|CIRCLE|LN|LANE|RD|ROAD|TER|TERR|TERRACE|WAY)\\.?$/", "", $norm_street_name);
    $norm_street_name = preg_replace(array("/\\bTHIRD\\b/",
                                           "/\\bFOURTH\\b/",
                                           "/\\b7309\\b/",
                                           "/\\b7450\\b/"),
                                     array("3RD", "4TH", "434", "WASHINGTON"),
                                     $norm_street_name);
    $norm_street_name = preg_replace("/([0-9])(ST|ND|RD|TH)\\b/", "\\1",
                                     $norm_street_name);

    $sql = sprintf("SELECT * FROM groups WHERE (street_name='%s' OR street_name LIKE '%% %s')",
                   mysql_real_escape_string($norm_street_name),
                   mysql_real_escape_string($norm_street_name));
    
    if($group_id) {
      $sql .= sprintf(" AND group_id=%d", $group_id);
    }
    $candidates = sql_fetch_all_hash($sql);

    if(is_admin()) {
      set_ret("norm_street", $norm_street_name);
      set_ret("norm_name", $norm_last_name);
      if($candidates)
        set_ret("found_street", true);
      else
        set_ret("found_street", false);
    }
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
  $dessert_invite = false;
  if(0+$group["rehearsal_dessert_invite"]) $dessert_invite = true;
  set_ret("dessert_invite", $dessert_invite);
  set_ret("wants_share", $group["wants_share"]);
  set_ret("share_details", $group["share_details"]);
  set_ret("comments", $group["comments"]);
  $group_id = 0 + $group["group_id"];

  $people = sql_fetch_all_hash(sprintf("SELECT * FROM people WHERE group_id=%d ORDER BY name", $group_id));
  $meals = sql_fetch_all_hash("SELECT * FROM meals ORDER BY meal_order");

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

  $response_total = 0;
  $yes_total = 0;
  $total = 0;
  $dessert_response_total = 0;
  $dessert_yes_total = 0;
  $dessert_total = 0;
  $ret_groups = array();
  $groups = sql_fetch_all_hash("SELECT * FROM groups ORDER BY street_name, group_id");

  $meal_counts = array();
  $meals = sql_fetch_all_hash("SELECT * FROM meals ORDER BY meal_order");
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
    $dessert_invite = false;
    if(0 + $group["rehearsal_dessert_invite"]) $dessert_invite = true;
    $ret_groups[] = array("id" => $group["group_id"],
                          "street" => $group["street_name"],
                          "dessert_invite" => $dessert_invite,
                          "guests" => transform_guests_for_ret($guests, true),
                          "wants_share" => $group["wants_share"],
                          "share_details" => $group["share_details"],
                          "comments" => $group["comments"]);
    foreach($guests as $guest) {
      $total++;
      $attending = db_bool_to_php($guest["attending"]);
      $attending_dessert = db_bool_to_php($guest["attending_dessert"]);
      if(!is_null($attending)) {
        $response_total++;
        if($attending == "1") {
          $yes_total++;

          if($guest["meal"]) {
            $meal_name = $meal_map[$guest["meal"]];
          } else {
            $meal_name = $meals_ret[0];
          }
          $meal_counts[$meal_name]++;
        }
      }

      if($dessert_invite) {
        $dessert_total++;
        if(!is_null($attending_dessert)) {
          $dessert_response_total++;
          if($attending_dessert == "1") $dessert_yes_total++;
        }
      }
    }
  }
  set_ret("groups", $ret_groups);
  set_ret("response_count", $response_total);
  set_ret("attendee_count", $yes_total);
  set_ret("invitee_count", $total);
  set_ret("dessert_response_count", $dessert_response_total);
  set_ret("dessert_attendee_count", $dessert_yes_total);
  set_ret("dessert_invitee_count", $dessert_total);
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

  
  $group_changed_cols = array();

  $wants_share_bool = 0;
  if($data["wants_share"]) $wants_share_bool = 1;
  if($wants_share_bool != 0+$group["wants_share"]) {
    $group_changed_cols[] = "wants_share=$wants_share_bool";
    $deltas[] = sprintf("<li>Wants share: %d &rarr; %d</li>",
                        $group["wants_share"],
                        $wants_share_bool);
  }

  if($data["share_details"] != $group["share_details"]) {
    $group_changed_cols[] = sprintf("share_details='%s'",
                                    mysql_real_escape_string($data["share_details"]));
    $deltas[] = sprintf("<li>Share details: '%s' &rarr; '%s'</li>",
                        htmlspecialchars($group["share_details"]),
                        htmlspecialchars($data["share_details"]));
  }

  if($data["comments"] != $group["comments"]) {
    $group_changed_cols[] = sprintf("comments='%s'",
                                    mysql_real_escape_string($data["comments"]));
    $deltas[] = sprintf("<li>Comments: '%s' &rarr; '%s'</li>",
                        htmlspecialchars($group["comments"]),
                        htmlspecialchars($data["comments"]));
  }

  if(count($group_changed_cols)) {
      sql_do(sprintf("UPDATE groups SET %s WHERE group_id=%d",
                     implode(", ", $group_changed_cols),
                     $group_id));
  }

  
  $people = sql_fetch_all_hash(sprintf("SELECT * FROM people WHERE group_id=%d", $group_id));
  $people_map = array();
  foreach($people as $person) {
      $people_map[$person["person_id"]] = $person;
  }

  foreach($data["guests"] as $guest) {
      $guest_attending = db_bool_to_php($guest["attending"]);
      $guest_attending_dessert = db_bool_to_php($guest["attending_dessert"]);
      if(is_null($guest_attending))
        $guest_attending_raw = "NULL";
      else
        $guest_attending_raw = $guest_attending;
      if(is_null($guest_attending_dessert))
        $guest_attending_dessert_raw = "NULL";
      else
        $guest_attending_dessert_raw = $guest_attending_dessert;
      $attending_dessert_bool = 0;
      $meal_raw = "NULL";
      $guest_id = 0 + $guest["id"];
      $person = $people_map[$guest_id];
      $data_attending = db_bool_to_php($person["attending"]);
      $data_attending_dessert = db_bool_to_php($person["attending_dessert"]);
      if($guest["meal"]) $meal_raw = sprintf("%d", 0 + $guest["meal"]);

      sql_do("UPDATE people SET attending=$guest_attending_raw, attending_dessert=$guest_attending_dessert_raw, meal=$meal_raw WHERE person_id=$guest_id AND group_id=$group_id");

      if($guest_attending != $data_attending) {
          $deltas[] = sprintf("<li>%s attending: %s &rarr; %s</li>",
                              htmlspecialchars($person["name"]),
                              $data_attending,
                              $guest_attending);
      }
      if($guest_attending_dessert != $data_attending_dessert) {
          $deltas[] = sprintf("<li>%s attending dessert: %s &rarr; %s</li>",
                              htmlspecialchars($person["name"]),
                              $data_attending_dessert,
                              $guest_attending_dessert);
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

  if(count($deltas)) {
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
  }
  
  display_group($path);
}

function export_csv($path) {
    header("Content-disposition: attachment; filename=wedding-rsvp.csv");
    header("Content-type: text/csv; name=wedding-rsvp.csv");

    $meal_map = meal_map();
    $people = sql_fetch_all_hash("SELECT groups.group_id AS 'group_id', groups.street_name AS 'street_name', groups.rehearsal_dessert_invite, people.* FROM people left outer join groups on people.group_id=groups.group_id ORDER BY groups.group_id, name");
    print("Group,Street,Name,Attending?,Meal,Attending Dessert?\n");
    foreach($people as $person) {
        if($person["meal"]) {
            $meal = $meal_map[$person["meal"]];
        } else {
            $meal = "";
        }

        if(!isset($person["attending"])) {
          $attending = "?";
        } else if($person["attending"] == "0") {
          $attending = "N";
        } else {
          $attending = "Y";
        }

        if(!$person["rehearsal_dessert_invite"]) {
          $attending_dessert = "U";
        } else if(!isset($person["attending_dessert"])) {
          $attending_dessert = "?";
        } else if($person["attending_dessert"] == "0") {
          $attending_dessert = "N";
        } else {
          $attending_dessert = "Y";
        }
        
        printf("%s,%s,%s,%s,%s,%s\n",
               $person["group_id"],
               $person["street_name"],
               $person["name"],
               $attending,
               $meal,
               $attending_dessert);
    }
}

function get_dessert_info($path) {
  $has_privs = false;
  if(is_admin()) {
    $has_privs = true;
  } else if(get_session("view_group")) {
    $dessert = sql_fetch_one(sprintf("SELECT rehearsal_dessert_invite FROM groups WHERE group_id=%d",
                                     0+get_session("view_group")));
    if($dessert) $has_privs = true;
  }

  header("Content-type: text/html");
  if(!$has_privs) {
    print('<p class="wrsvp_error">Dessert info fetch error.</p>');
  } else {
    print(file_get_contents(get_home_dir() . "/wrsvp-dessert-info.html"));
  }
  exit();
}

function get_wedding_info($path) {
  header("Content-type: text/html");
  if(is_admin() or get_session("view_group")) {
    print(file_get_contents(get_home_dir() . "/wrsvp-wedding-info.html"));
  } else {
    print('<p class="wrsvp_error">Wedding info fetch error.</p>');
  }
  exit();
}

function get_web_changelist($path) {
  set_ret("action", "web_changelist");
  if(!is_admin()) {
    set_ret("success", false);
    set_ret("error", "noauth");
    return;
  }

  $ret_changes = array();
  $changes = sql_fetch_all_hash("SELECT change_id, change_time, change_text FROM changes ORDER BY change_time DESC LIMIT 200");
  foreach($changes as $change) {
      
    $ret_changes[] = array("id" => $change["change_id"],
                           "text" => $change["change_text"],
                           "time" => $change["change_time"]);
  }
  set_ret("changes", $ret_changes);
}

function export_rss($path) {
  $rsspass = $_REQUEST["rssauth"];
  if(!is_admin() and (!$rsspass or sha1("wrsvp:::$rsspass") != rss_pass())) {
    header("Status: 403 Access Denied");
    header("Content-type: text/plain");
    print("Access restricted.\n");
    return;
  }

  $atom_tag = "tag:matthewg@zevils.com,2008-08-09:wrsvp2";
  include_once('atombuilder/class.AtomBuilder.inc.php');
  $atom = new AtomBuilder("Wedding Responses", "http://w.sachsfam.org/rsvp_server.php/rss2", $atom_tag);

  $changes = sql_fetch_all_hash("SELECT change_id, change_time, change_text FROM changes ORDER BY change_time DESC LIMIT 200");

  $atom->setUpdated($changes[0]["change_time"]);
  $atom->setEncoding("UTF-8");
  $atom->setLanguage("en");
  $atom->setSubtitle("Responses to our wedding invitations");
  $atom->setIcon("http://w.sachsfam.org/favicon.ico");
  $atom->setAuthor('Liz and Matthew', 'us@sachsfam.org', 'http://sachsfam.org/');
  $atom->addContributor('Liz and Matthew', 'us@sachsfam.org');
  $atom->addLink("http://w.sachsfam.org/rsvp.php", "Homepage", "alternate", "text/html", "en");

  foreach($changes as $change) {
    $entry = $atom->newEntry("Response #" . $change["change_id"], "http://w.sachsfam.org/rsvp.php/rss2/" . $change["change_id"], "$atom_tag/" . $change["change_id"]);
    $entry->setUpdated($change["change_time"]);
    $entry->setContent($change["change_text"], "html");
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

  if($obj == "admin_logout") {
    set_ret("is_admin", False);
    set_ret("is_guest", False);
    set_ret("action", "logout");
    set_ret("success", True);
    init_session(Null, True);
  } else if($obj == "guest_logout") {
    set_ret("is_guest", False);
    set_ret("action", "logout");
    set_ret("success", True);
    if(!is_admin()) init_session(Null, True);
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
  } else if($obj == "rss2") {
    export_rss($path);
    exit();
  } else if($obj == "web_changelist") {
    get_web_changelist($path);
  } else if($obj == "dessert_info") {
    get_dessert_info($path);
  } else if($obj == "wedding_info") {
    get_wedding_info($path);
  } else if($obj) {
    set_ret("action", "UNKNOWN");
    set_ret("error", $obj);
  }

  echo json_encode($RET);
}

main();

?>
