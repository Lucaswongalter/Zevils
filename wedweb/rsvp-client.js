var RSVP_DATA = false;
var IS_READY = false;
var IS_ADMIN = false;
var IS_GUEST = false;
var CURRENT_EDIT_GROUP;

function show_progress() {
    if($("#modal-window").is(":hidden")) {
        $("#modal-window").show("fast");
        $("#modal-overlay").show("fast");
    }
}
function hide_progress() {
    $("#modal-window").hide("fast");
    $("#modal-overlay").hide("fast");
}

function error_message(data) {
    var msgid = data["error"];
    var msgstrs = {
        "badpass": "Incorrect password.",
        "nopass": "Please enter a password.",
        "notfound": "No matching reservation found.",
        "nocreds": "Please enter a last name and a street name.",
        "noinput": "Please provide all information.",
        "nopriv": "You don't have access to that.",
        "nogroup": "Group not found."
    };

    var msgstr = msgstrs[msgid];
    if(!msgstr) msgstr = msgid;
    return msgstr;
}

function extract_id(id) {
    return /^[-a-zA_Z_]+_([0-9]+)/(id)[1];
}

function check_rsvp_data() {
    if(!RSVP_DATA || !IS_READY) return;

    IS_ADMIN = RSVP_DATA["is_admin"];
    IS_GUEST = RSVP_DATA["is_guest"];
    if(IS_ADMIN) {
        show_progress();
        $("#admin_text").show();
        $("#guest_auth").hide();
        get_grouplist();
    } else {
        $("#admin_text").hide();
        if(IS_GUEST) {
            $("#guest_auth").hide();
            show_progress();
            get_group();
        } else {
            hide_progress();
        }
    }
}

function get_grouplist() {
    $.getJSON(RSRC_BASE + "rsvp_server.php/grouplist", function(data, status) {
        RSVP_DATA = data;
        var result = TrimPath.processDOMTemplate("grouplist_template", data);
        $("#grouplist").html(result);

        $("#rss_link").click(function() {
            var pass = prompt("Please enter the RSS password");
            if(pass) {
                $(this).unbind("click");
                var url = RSRC_BASE + "rsvp_server.php/rss?rssauth=" + pass;
                $(this).attr("href", url);
                $(this).text("http://w.sachsfam.org" + url);
            }
            return false;
        });

        $(".grouplist_multi").click(function() {
            $("#grouplist").hide();
            show_progress();

            var attr_id = $(this).attr("id");
            var id = extract_id(attr_id);
            get_group(id);

            return false;
        });

        $("#grouplist").show();
        hide_progress();
    });
}

function got_group(data) {
    RSVP_DATA = data;
    var result = TrimPath.processDOMTemplate("group_template", data);
    $("#group_edit_form").html(result);
    $("#group .wrsvp_error").text("");
    $("#group").show();
    hide_progress();
}

function find_group(group_id) {
    var url;
    if(group_id) {
        url = "rsvp_server.php/guest_auth/" + group_id;
    } else {
        url = "rsvp_server.php/guest_auth";
    }

    $.getJSON(RSRC_BASE + url, {"street_name": $("#guest_auth_street_name").val(),
                                "last_name": $("#guest_auth_last_name").val()},
              function(data, status) {
                  if(!data["success"]){ 
                      $("#guest_auth .wrsvp_error").text(error_message(data));
                      hide_progress();
                  } else if(data["gotmulti"]) {
                      $("#guest_auth").hide();
                      RSVP_DATA = data;
                      var result = TrimPath.processDOMTemplate("guest_multi_template", data);
                      $("#guest_multi").html(result);

                      $("#guest_multi_retry").click(function() {
                          $("#guest_multi").hide();
                          $("#guest_auth .wrsvp_error").text("");
                          $("#guest_auth").show();
                          return false;
                      });

                      $(".guest_auth_multi").click(function() {
                          var attr_id = $(this).attr("id");
                          var id = extract_id(attr_id);

                          show_progress();
                          $("#guest_multi").hide();
                          find_group(id);

                          return false;
                      });

                      $("#guest_multi").show();
                      hide_progress();
                  } else {
                      $("#guest_auth").hide();
                      get_group();
                  }
              });
}

function get_group(group_id) {
    if(group_id) {
        path = "rsvp_server.php/group/" + group_id;
    } else {
        path = "rsvp_server.php/group";
    }

    $.getJSON(RSRC_BASE + path, function(data, status) {
        got_group(data);
        CURRENT_EDIT_GROUP = group_id;
    });
}

$.getJSON(RSRC_BASE + "rsvp_server.php", function(data, status) {
    RSVP_DATA = data;
    check_rsvp_data();
});

$(document).ready(function() {
    IS_READY = true;

    show_progress();
    $("#admin_logout").click(function() {
        show_progress();
        $.getJSON(RSRC_BASE + "rsvp_server.php/logout", function(data, status) {
            RSVP_DATA = data;
            check_rsvp_data();
            $(".wrsvp_action").hide();
            $("#guest_auth .wrsvp_error").text("");
            $("#guest_auth").show();
            hide_progress();
        });
        return false;
    });

    $("#guest_auth_logout").click(function() {
        show_progress();
        $.getJSON(RSRC_BASE + "rsvp_server.php/logout", function(data, status) {
            RSVP_DATA = data;
            check_rsvp_data();
            $(".wrsvp_action").hide();
            $("#guest_auth .wrsvp_error").text("");
            $("#guest_auth").show();
            hide_progress();
        });
        return false;
    });

    $("#admin_show_grouplist").click(function() {
        show_progress();
        $(".wrsvp_action").hide();
        get_grouplist();
        return false;
    });

    $("#admin_auth_form").submit(function() {
        show_progress();
        $.post(RSRC_BASE + "rsvp_server.php/admin_auth",
               {"admin_pass": $("#admin_pass").val()},
               function(data, status) {
                   RSVP_DATA = data;
                   check_rsvp_data();
                   if(data["success"]) {
                       document.location = RSRC_BASE + "rsvp.php";
                   } else {
                       $("#admin_auth .wrsvp_error").text(error_message(data));
                   }
               },
              "json");

        return false;
    });

    $("#guest_auth_form").submit(function() {
        if($("#guest_auth_street_name").val() == "" || $("#guest_auth_last_name").val() == "") {
            $("#guest_auth .wrsvp_error").text("Please enter a street name and a last name.");
            return false;
        }

        show_progress();
        find_group();

        return false;
    });

    $("#group_edit_form").submit(function() {
        var data = {};
        show_progress();
        if(IS_ADMIN) data["street"] = $("#group_street").val();
        data["guests"] = [];
        $(".guest").each(function() {
            var attr_id = $(this).attr("id");
            var id = extract_id(attr_id);
            var attending = $("#" + attr_id + " >> .guest_attending > option:selected").val();
            var meal = $("#" + attr_id + " >> .guest_meal > option:selected").val();
            data["guests"][data["guests"].length++] = {"id": id,
                                                       "attending": attending,
                                                       "meal": meal};
        });

        var editURL;
        if(CURRENT_EDIT_GROUP) {
            editURL = "rsvp_server.php/group_edit/" + CURRENT_EDIT_GROUP;
        } else {
            editURL = "rsvp_server.php/group_edit";
        }
        $.ajax({
            "type": "POST",
            "contentType": "application/json; charset=utf-8",
            "url": RSRC_BASE + editURL,
            "data": $.toJSON(data),
            "dataType": "json",
            "success": function(data) {
                got_group(data);
            }});
        
        return false;
    });

    check_rsvp_data();
});
