var g = new Growler();

function removeAccountReset() {
    if($("resetcfaccount") != null) {
        $("resetcfaccount").innerHTML = "";
    }
}


function isSimple() {
    if (document.URL.toQueryParams().simple != undefined) {
        return "?simple=1";
    } else {
        return "";
    }
}

function isSimpleMiddle() {
    if (document.URL.toQueryParams().simple != undefined) {
        return "&simple=1";
    } else {
        return "";
    }
}

function zoneset(id, zone) {
    overlay();
    $("currentstatus").innerHTML = "Contacting CloudFlare";
    new Ajax.Request("/modules/servershield/servershield.php" + isSimple(), { method: "post",
        parameters: {
            plesk_id: id,
            zone_name: zone,
            act: "zone_set"
        },
        onSuccess: function(transport, json) {
            var response = JSON.parse(transport.responseText);

            if(response.zone_set === "success") {
                initialsetdns(id, zone);
                $("settings" + id).innerHTML = '<a href="' + '?zone=' + zone  + isSimpleMiddle() + '">Manage CloudFlare</a>';
                removeAccountReset();
            } else if (response.zone_delete == "success") {
                revertresolve(JSON.stringify(response.subdomains));
                $("settings" + id).innerHTML = '';
            } else if ( (response.zone_set !== "success") && (response.zone_set != undefined) ) {
                $("zone" + id).checked = false;
                g.error(response.zone_set, {sticky: true});
                overlay();
            }  else if ( (response.zone_delete !== "success") && (response.zone_delete != undefined) ) {
                g.error(response.zone_delete, {sticky: true});
                $("zone" + id).checked = true;
                overlay();
            }
        },
        onFailure: function (transport, json) {
            g.error("Communication error with Plesk API", {sticky: true});
            $("zone" + id).checked = false;
            setTimeout(function(){$("cferrorarea").innerHTML = "";}, 5000);
        }
    } );
}

function initialsetdns(id, zone) {
    $("currentstatus").innerHTML = "Modifying DNS";
    new Ajax.Request("/modules/servershield/servershield.php" + isSimple(), { method: "post",
        parameters: {
            plesk_zone_name: zone,
            plesk_rec_name: "www." + zone,
            act: "zone_rec_set"
        },
        onSuccess: function(transport, json) {
            var resp = JSON.parse(transport.responseText);

            if(resp.rec_set === "success") {
                createcfrec("www." + zone);
            } else if (resp.rec_set != undefined) {
                g.error(resp.rec_set, {sticky: true});
                overlay();
            }
        },
        onFailure: function (transport, json) {
            g.error("Communication error with Plesk API", {sticky: true});
            overlay();
        }
    } );
}

function createcfresolve(rec_name) {
    new Ajax.Request("/modules/servershield/servershield.php" + isSimple(), { method: "post",
        parameters: {
            plesk_rec_name: rec_name,
            act: "create_cf_resolve"
        },
        onSuccess: function(transport, json) {
            var resp = JSON.parse(transport.responseText);

            if(resp.create_cf_resolve === "success") {
                createcfrec(rec_name);
            } else if (resp.create_cf_resolve != undefined) {
                g.error(resp.create_cf_resolve, {sticky: true});
                overlay();
            }
        },
        onFailure: function (transport, json) {
            g.error("Communication error with Plesk API", {sticky: true});
            $("rec" + rec_id).checked = false;
            overlay();
        }
    } );
}

function createcfrec(rec_name) {
    $("currentstatus").innerHTML = "Finishing";
    new Ajax.Request("/modules/servershield/servershield.php" + isSimple(), { method: "post",
        parameters: {
            plesk_rec_name: rec_name,
            act: "create_cf_rec"
        },
        onSuccess: function(transport, json) {
            var r = JSON.parse(transport.responseText);

            if(r.create_cf_rec === "success") {
                $("currentstatus").innerHTML = "Provisioning Successful";
                g.growl("Provisioning Sucessful!", {sticky: true});
                setTimeout(function(){$("cfmessagearea").innerHTML = "";}, 10000);
            } else if (r.create_cf_rec != undefined) {
                g.error(r.create_cf_rec, {sticky: true});
            }

            overlay();
        },
        onFailure: function (transport, json) {
            g.error("Communication error with Plesk API", {sticky: true});
            $("rec" + rec_id).checked = false;
            overlay();
        }
    } );
}


function deletecfresolve(rec_name) {
    new Ajax.Request("/modules/servershield/servershield.php" + isSimple(), { method: "post",
        parameters: {
            plesk_rec_name: rec_name,
            act: "delete_cf_resolve"
        },
        onSuccess: function(transport, json) {
            var result = JSON.parse(transport.responseText);
            if(result.delete_cf_resolve === "success") {
                $("currentstatus").innerHTML = "DNS record reverted";
            } else if ( (result.delete_cf_resolve !== "success") && (result.delete_cf_resolve != undefined) ) {
                g.error(result.delete_cf_rec, {sticky: true});
            }
            overlay();
        },
        onFailure: function (transport, json) {
            g.error("Communication error with Plesk API", {sticky: true});
            $("rec" + rec_id).checked = false;
            overlay();
        }
    } );
}

function revertresolve(subs) {
    new Ajax.Request("/modules/servershield/servershield.php" + isSimple(), { method: "post",
        parameters: {
            subdomains: subs,
            act: "revert_resolve"
        },
        onSuccess: function(transport, json) {
            var result = JSON.parse(transport.responseText);

            if(result.revert_resolve === "success") {
                $("currentstatus").innerHTML = "DNS reverted";
            } else if ( (result.revert_resolve != "success") && (result.revert_resolve != undefined)) {
                g.error(result.revert_resolve, {sticky: true});
            }
            overlay();
        },
        onFailure: function (transport, json) {
            g.error("Communication error with Plesk API", {sticky: true});
            $("rec" + rec_id).checked = false;
        }
    } );
}

function recset(rec_id, rec_name) {
    overlay();
    $("currentstatus").innerHTML = "Contacting CloudFlare";
    new Ajax.Request("/modules/servershield/servershield.php" + isSimple(), { method: "post",
        parameters: {
            plesk_rec_name: rec_name,
            act: "zone_rec_set"
        },
        onSuccess: function(transport, json) {
            var response = JSON.parse(transport.responseText);
            if(response.rec_set === "success") {
                $("currentstatus").innerHTML = "Modifying DNS";
                createcfrec(rec_name);
            } else if (response.rec_del === "success") {
                $("currentstatus").innerHTML = "Modifying DNS";
                deletecfresolve(rec_name);
            } else if(response.rec_set != undefined) {
                g.error(response.rec_set, {sticky: true});
                overlay();
            } else if(response.rec_del != undefined) {
                g.error(response.rec_del, {sticky: true});
                overlay();
            }
        },
        onFailure: function (transport, json) {
            g.error("Communication error with Plesk API", {sticky: true});
            $("rec" + rec_id).checked = false;
            overlay();
        }
    } );
}

function sthset(id, zone) {
    overlay();
    $("currentstatus").innerHTML = "Contacting StopTheHacker";
    new Ajax.Request("/modules/servershield/servershield.php" + isSimple(), { method: "post",
        parameters: {
            plesk_id: id,
            zone_name: zone,
            act: "sth_set"
        },
        onSuccess: function(transport, json) {
            var rsp = JSON.parse(transport.responseText);

            if (rsp.sth_set == "success") {
                g.growl("Reputation monitoring activated. If you are new to StopTheHacker, you may receive an introductory e-mail.", {sticky: true});
                removeAccountReset();
                $("sth" + id).checked = true;
            } else if (rsp.sth_disable === "success") {
                g.growl("Reputation Monitoring Deactivated", {sticky: true});
                $("sth" + id).checked = false;
            } else if(rsp.sth_set != undefined) {
                $("sth" + id).checked = false;
                g.error(rsp.sth_set, {sticky: true});
            } else if (rsp.sth_disable != undefined) {
                $("sth" + id).checked = true;
                g.error(rsp.sth_disable, {sticky: true});
            }

            overlay();
        },
        onFailure: function (transport, json) {
            g.error("Communication error with Plesk API", {sticky: true});
            $("sth" + id).checked = false;
        }
    } );
}


function purgecache(zone) {
    $("purgecache").innerHTML = "<img src=\"/modules/servershield/ajax-loader.gif\" />";
    $("purgecache").disable();

    new Ajax.Request("/modules/servershield/servershield.php" + isSimple(), { method: "post",
        parameters: {
            zone_name: zone,
            act: "purge_cache"
        },
        onSuccess: function(transport, json) {
            var response = JSON.parse(transport.responseText);
            if(response.purge_cache !== "success" && response.purge_cache != null) {
                g.error(response.purge_cache, {sticky: true});
                setTimeout(function(){$("cferrorarea").innerHTML = "";}, 5000);
            } else {
                g.growl("CloudFlare cache purged successfully");
                $("purgecache").innerHTML = "Purge Cache";
                $("purgecache").enable();
            }
        },
        onFailure: function (transport, json) {
            g.error("Communication error with Plesk API", {sticky: true});
        }
    } );
}

function overlay() {
    el = document.getElementById("overlay");
    el.style.visibility = (el.style.visibility == "visible") ? "hidden" : "visible";
    el.style.opacity = (el.style.visibility == "visible") ? .85 : 0;
}