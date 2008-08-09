var RSVP_DATA = false;
var IS_READY = false;
var IS_ADMIN = false;
var IS_GUEST = false;

function show_progress() {}
function hide_progress() {}
function error_message(data) {
    return data["error"];
}

function check_rsvp_data() {
    if(!RSVP_DATA || !IS_READY) return;

    IS_ADMIN = RSVP_DATA["is_admin"];
    IS_GUEST = RSVP_DATA["is_guest"];
    if(IS_ADMIN) {
        $("#admin_text").show();
    } else {
        $("#admin_text").hide();
        if(IS_GUEST) {
            $("#guest_auth").hide();
            show_progress();
            get_group();
        }
    }
}

function get_grouplist() {
    $.getJSON(RSRC_BASE + "rsvp_server.php/grouplist", function(data, status) {
        RSVP_DATA = data;
        var result = TrimPath.processDOMTemplate("grouplist_template", data);
        $("#grouplist").html(result);
        $("#grouplist").show();
        hide_progress();
    });
}

function get_group(group_id) {
    if(group_id) {
        path = "rsvp_server.php/group/" + group_id;
    } else {
        path = "rsvp_server.php/group";
    }

    $.getJSON(RSRC_BASE + path, function(data, status) {
        RSVP_DATA = data;
        var result = TrimPath.processDOMTemplate("group_template", data);
        $("#group").html(result);
        $("#group").show();

        $("#group_edit_form").submit(function() {
            alert("foo");
            var data = {};
            show_progress();
            if(IS_ADMIN) data["street"] = $("#group_street").val();
            data["guests"] = new Array();
            $(".guest").each(function() {
                var id = /^guest_([0-9]+)/($(this).attr("id"))[1];
                var name = $(".guest_name").val();
                var attending = $(".guest_attending").val();
                var meal = $(".guest_meal").val();
                data["guests"][data["guests"].length++] = {"id": id,
                                                           "name": name,
                                                           "attending": attending,
                                                           "meal": meal};
            });
            $.post(RSRC_BASE + "rsvp_server.php/edit_group", 
                   data,
                   function() {
                       get_group();
                   },
                   "json");

            return false;
        });

        hide_progress();
    });
}

$.getJSON(RSRC_BASE + "rsvp_server.php", function(data, status) {
    RSVP_DATA = data;
    check_rsvp_data();
});

$(document).ready(function() {
    IS_READY = true;

    $("#admin_logout").click(function() {
        show_progress();
        $.getJSON(RSRC_BASE + "rsvp_server.php/logout", function(data, status) {
            RSVP_DATA = data;
            check_rsvp_data();
            $(".wrsvp_action").hide();
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
            $("#guest_auth").show();
            hide_progress();
        });
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
                       $("#admin_auth").hide();
                       $("#guest_auth").hide();
                       $("#admin_text").show();
                       get_grouplist();
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
        $.getJSON(RSRC_BASE + "rsvp_server.php/guest_auth", {"street_name": $("#guest_auth_street_name").val(),
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
                          $("#guest_multi").show();
                          hide_progress();
                      } else {
                          $("#guest_auth").hide();
                          get_group();
                      }
                  });

        return false;
    });

    $("#guest_multi_retry").click(function() {
        $("#guest_multi").hide();
        $("#guest_auth").show();
        return false;
    });

    check_rsvp_data();
});
