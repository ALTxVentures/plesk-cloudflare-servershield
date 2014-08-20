/*Ajax.Responders.register({
 onComplete: function() {
    if(Ajax.activeRequestCount == 0) {
        $("currentstatus").innerHTML += '<div><button type="button" style="float: right" onclick="window.location.reload()">Done</button></div>';
    }
  }
});*/

var finished = 0;

UserCreateQueue = {
    outURL: '/modules/servershield/bulkadd.php',
    queue: [],
    sending: false,

    send: function(zone) {
        this.queue.push(zone);
        this.iterate();
    },
    iterate: function() {
        var z = this.queue.pop();
        if (z)
        {
            var e = new Element("div");
            $("currentstatus").insert(e);
            e.innerHTML = "<strong>" + z.zone + "</strong>" + ": Checking for user";
            e.setStyle( { paddingBottom: "10px" } );
            z.element = e;

            new Ajax.Request(this.outURL, {
                parameters: {
                    userid: z.userid,
                    zone: z.zone,
                    act: "user_set"
                },
                method: 'POST',
                onSuccess: function(transport, json) {
                    var resp = JSON.parse(transport.responseText);

                    if(resp.result == "success") {
                        z.element.innerHTML = "<strong>" + z.zone + "</strong>" + ": " + resp.msg;
                        ZoneSetQueue.send(z);
                    }
                    else {
                        z.element.innerHTML = "<strong>" + z.zone + "</strong>" + ": [ERROR] " + resp.msg;
                        finished += 1;
                    }
                    UserCreateQueue.iterate();
                },
                onFailure: function() {
                    z.element.innerHTML = "<strong>" + z.zone + "</strong>" + ": [ERROR] Unexpected error";
                    finished += 1;
                }
            });
        }
    },
}

ZoneSetQueue = {
    outURL: '/modules/servershield/bulkadd.php',
    queue: [],
    sending: false,

    send: function(zone) {
        this.queue.push(zone);
        this.iterate();
    },
    iterate: function() {
        var z = this.queue.pop();
        if (z)
        {
            z.element.innerHTML = "<strong>" + z.zone + "</strong>" + ": Contacting CloudFlare";
            new Ajax.Request(this.outURL, {
                parameters: {
                    userid: z.userid,
                    zone: z.zone,
                    act: "zone_set"
                },
                method: 'POST',
                onSuccess: function(transport, json) {
                    var resp = JSON.parse(transport.responseText);

                    if(resp.result == "success") {
                        var record = {
                                        userid: z.userid,
                                        record: "www." + z.zone,
                                        zone: z.zone,
                                        element: z.element
                                    }
                        RecSetQueue.send(record);
                    } else {
                        z.element.innerHTML = "<strong>" + z.zone + "</strong>" + ": [ERROR] " + resp.msg;
                        finished += 1;
                    }

                    ZoneSetQueue.iterate();
                },
                onFailure: function () {
                    z.element.innerHTML = "<strong>" + z.zone + "</strong>" + ": [ERROR] Unexpected error";
                    finished += 1;
                }
            });

        }
    },
}

RecSetQueue = {
    outURL: '/modules/servershield/bulkadd.php',
    queue: [],
    sending: false,

    send: function(record) {
        this.queue.push(record);
        this.iterate();
    },
    iterate: function() {
        var record = this.queue.pop();
        if (record)
        {
            record.element.innerHTML = "<strong>" + record.zone + "</strong>" + ": Modifying DNS";
            new Ajax.Request(this.outURL, {
		        parameters: {
		            userid: record.userid,
		            record: record.record,
                    zone: record.zone,
		            act: "rec_set"
		        },
                method: 'POST',
                onSuccess: function(transport, json) {
		            var resp = JSON.parse(transport.responseText);

                    if(resp.result == "success") {
                        record.element.innerHTML = "<strong>" + record.zone + "</strong>" + ": Done";
                        finished += 1;
                    } else {
                        record.element.innerHTML = "<strong>" + record.zone + "</strong>" + ": [ERROR] " + resp.msg;
                        finished += 1;
                    }

		            RecSetQueue.iterate();
                },
                onFailure: function () {
                    record.element.innerHTML = "<strong>" + record.zone + "</strong>" + ": [ERROR] Unexpected error";
                    finished += 1;
                }
            });
        }
    },
}

function bulkadd(inactive) {
    $("currentstatus").innerHTML = "";
    $("currentstatus").setStyle( { width: "700px", textAlign: "left" } );
    overlay();

    var i = 0;

    /*while (i < inactive.length) {
        var zone = inactive[i];

        setTimeout( function () {
            i++;
        }, 3000);
    }*/

    var timer = setInterval(function() {
        if (i == inactive.length) {
            if(finished == inactive.length) {
                $("currentstatus").innerHTML += '<div><button type="button" style="float: right" onclick="window.location.reload()">Done</button></div>';
                clearInterval(timer);
            }
        } else {
            UserCreateQueue.send(inactive[i]);
            i += 1;
        }
    }, 500);
}