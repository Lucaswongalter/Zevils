var RSVP_DATA = false;
var IS_READY = false;
var IS_ADMIN = false;
var IS_GUEST = false;
var CURRENT_EDIT_GROUP;

function show_progress(show_thanks) {
    var modal_div;
    var old_div;

    if(show_thanks) {
        modal_div = $("#modal-thanks");
        old_div = $("#modal-loading");
    } else {
        modal_div = $("#modal-loading");
        old_div = $("#modal-thanks");
    }

    if($("#modal-overlay").is(":hidden"))
        $("#modal-overlay").show();

    old_div.hide();

    if(modal_div.is(":hidden")) modal_div.show("fast");
}

function hide_progress() {
    //if($("#modal-thanks").is(":hidden"))
        $("#modal-loading").hide("fast");
    //else
        $("#modal-thanks").hide("fast");
    $("#modal-overlay").hide();
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
    var re = new RegExp("^[-a-zA_Z_]+_([0-9]+)");
    var matches = re.exec(id);
    return matches[1];
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
                var url = RSRC_BASE + "rsvp_server.php/rss2?rssauth=" + pass;
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

function set_all_guests(attending, dessert) {
  $(".guest").each(function() {
    var attr_id = $(this).attr("id");
    var sel_class;
    var sel_val;
    if(dessert)
      sel_class = ".guest_attending_dessert";
    else
      sel_class = ".guest_attending";
    if(attending)
      sel_val = "1";
    else
      sel_val = "0";

    var selector = "#" + attr_id + " >> " + sel_class + " > option";
    $(selector).removeAttr("selected");
    $(selector + "[@value='" + sel_val + "']").attr("selected", "selected");

    if(!dessert) {
      var meal_selector = $("#" + attr_id + " >> .guest_meal");
      if(attending)
        meal_selector.removeAttr("disabled");
      else
        meal_selector.attr("disabled", "disabled");
    }
  });
}

function fix_meal_picker_state() {
    var attr_id = $(this).parents(".guest").attr("id");
    var attending = $("#" + attr_id + " >> .guest_attending > option:selected").val();
 
    var meal_selector = $("#" + attr_id + " >> .guest_meal");
    if(attending == "1")
        meal_selector.removeAttr("disabled");
    else
        meal_selector.attr("disabled", "disabled");
}

function fix_share_details_state() {
    if($("#group_wants_share > option:selected").val())
        $("#group_share_details").show("normal");
    else
        $("#group_share_details").hide("normal");
}

function got_group(data) {
    RSVP_DATA = data;
    var result = TrimPath.processDOMTemplate("group_template", data);
    $("#group_edit_form").html(result);
    $("#group .wrsvp_error").text("");

    $("#group_all_attending_dessert").click(function() {
      set_all_guests(true, true);
      return false;
    });
    $("#group_all_attending").click(function() {
      set_all_guests(true, false);
      return false;
    });
    $("#group_all_not_attending_dessert").click(function() {
      set_all_guests(false, true);
      return false;
    });
    $("#group_all_not_attending").click(function() {
      set_all_guests(false, false);
      return false;
    });

    $("#group .guest_attending").each(fix_meal_picker_state);
    $("#group .guest_attending").change(fix_meal_picker_state);

    $("#group_share_textarea").replaceWith("<textarea id=\"group_share_textarea\" cols=\"80\" rows=\"10\"></textarea>");
    $("#group_share_textarea").val(data["share_details"]);

    $("#group_comments_textarea").replaceWith("<textarea id=\"group_comments_textarea\" cols=\"80\" rows=\"10\"></textarea>");
    $("#group_comments_textarea").val(data["comments"]);

    fix_share_details_state();
    $("#group_wants_share").change(fix_share_details_state);

    $("#group").show();
}

function find_group(group_id) {
    var url;
    CURRENT_EDIT_GROUP = null;
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
        hide_progress();
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

    $("#modal-thanks-close-button").click(function() {
        hide_progress();
    });

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
        $.getJSON(RSRC_BASE + "rsvp_server.php/guest_logout", function(data, status) {
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

    $("#admin_show_search").click(function() {
        show_progress();
        $(".wrsvp_action").hide();
        $("#guest_auth .wrsvp_error").text("");
        $("#guest_auth").show();
        hide_progress();
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
        $("#group .wrsvp_error").text("");
        if(IS_ADMIN) data["street"] = $("#group_street").val();
        data["wants_share"] = $("#group_wants_share > option:selected").val();
        data["share_details"] = $("#group_share_details > textarea").val();
        data["comments"] = $("#group_comments > textarea").val();
        data["guests"] = [];

        var anyNotResponded = false;
        var anyAttending = false;
        var guestsOkay = true;
        var errText = "";
        $(".guest").each(function() {
            var attr_id = $(this).attr("id");
            var id = extract_id(attr_id);
            var attending = $("#" + attr_id + " >> .guest_attending > option:selected").val();
            var meal = $("#" + attr_id + " >> .guest_meal > option:selected").val();

            guest_data = {"id": id,
                          "meal": meal}
            var attending_dessert_option = $("#" + attr_id + " >> .guest_attending_dessert > option:selected");
            if(attending == "") {
                anyNotResponded = true;
            } else {
                guest_data["attending"] = attending;

                var guest_err = $("#" + attr_id + " .wrsvp_guest_error");
                var guest_meal_selector = $("#" + attr_id + " >> .guest_meal");
                if(attending == "1") {
                    anyAttending = true;
                    if(meal == "") {
                        guestsOkay = false;
                        guest_meal_selector.css("color", "red");
                        guest_err.text("Select a meal for this guest.");
                        guest_err.show();
                        errText = "Please select a meal for all guests who are attending the wedding.";
                    } else {
                        guest_meal_selector.css("color", "black");
                        guest_err.hide();
                    }
                }
            }
            if(attending_dessert_option) {
                var val = attending_dessert_option.val();
                if(val && val != "") {
                    guest_data["attending_dessert"] = val;
                    if(val == "1") anyAttending = true;
                }
            }

            data["guests"][data["guests"].length++] = guest_data;
        });

        if(!guestsOkay) {
            $("#group .wrsvp_error").text(errText);
            hide_progress();
            return false;
        }

        var attendingText = "Responses updated.  ";
        if(anyNotResponded)
            attendingText += "Please let us know if you can come!";
        else if(anyAttending)
            attendingText += "We look forward to seeing you!";
        else
            attendingText += "Sorry you can't make it!";
        $("#modal-thanks .modal-thanks-text").text(attendingText);

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
                show_progress(true);
            }});
        
        return false;
    });

    check_rsvp_data();
});
