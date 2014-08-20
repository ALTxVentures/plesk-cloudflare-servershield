<?php
	class Modules_servershield_CFPageBuilder {

		public static function addSimpleParam($simple = false, $beginQuery = false){
			$str = "";

			if($simple) {
				if($beginQuery)
					$str = "?simple=1";
				else
					$str = '&simple=1';
			}
			else
				$str = "";

			return $str;
		}

	    public static function zonesPage($user_domains = array(), $simple = false) {
	        $cf_active_zone = array();
            $user_id = Modules_servershield_PleskAPIHelper::getEffectiveId();
            $cf_user_zones_key = md5($user_id . "CF-ZONES");
            $cf_user_zones = intval(pm_Settings::get($cf_user_zones_key));


	        $html = '<h3>Websites</h3> <h4 class="subheadline">Activate/Deactivate ServerShield</h4><br>';
	        $html .= '<br>';
	        $html .= '<br><div><ul><li style="margin-bottom: 10px;"><strong>CloudFlare</strong> protects your websites from online threats and DDoS attacks, while making them twice as fast around the world. CloudFlare runs a globally distributed network of 25 data centers. Once you enable CloudFlare on your  website customer, the traffic routes through the CloudFlare network before it reaches your server. CloudFlare stops malicious web traffic, fights DDoS attacks and automatically caches and delivers content for faster load times.</li>
	        			<li><strong>StopTheHacker</strong> gives  you unlimited reputation monitoring.  It performs a comprehensive daily check on the status of your website on the Google Safe Browsing List and other search engines as well as malware and phishing blacklists. StopTheHacker notifies you if your website ends up on a blacklist and help you remove it from such lists.</li><ul><br></div>';
	        $html .= '<div id="cfmessagearea"></div><br>';
	        $html .= '<div id="cferrorarea"></div><br>';

	        //if(count($user_domains["valid_domains"]) > 0 ) {
	        if(count($user_domains["total_domains"]) > 0 ) {
	            $html .= '<table id="cftable">';
	            $html .= '<th>Wesbsite</th> <th>CloudFlare</th> <th></th> <th>StopTheHacker</th> <th></th> <th></th>';

	            $cf_active_zone = array();
	            $sth_active_zone = array();

	            //foreach ($user_domains["valid_domains"] as $d) {
	            foreach ($user_domains["total_domains"] as $d) {
		            $cf_zname_setting_key = md5(Modules_servershield_PleskAPIHelper::getEffectiveId() . $d["name"]);
		            $cf_sth_zone_key = md5(Modules_servershield_PleskAPIHelper::getEffectiveId() . $d["name"] . "STH");
		            $current_zone_status = pm_Settings::get($cf_zname_setting_key);
		            $current_sth_status = pm_Settings::get($cf_sth_zone_key);

	                if( $current_zone_status == "on")
	                    $cf_active_zone[] = $d["id"];

	                if($current_sth_status == "on")
	                    $sth_active_zone[] = $d["id"];

	                $html .= '<tr>';
	                $html .= '<td>' . $d["name"] . '</td>';
	                $html .= '<td>' . static::renderSwitch($d["id"], $d["name"]) . '</td>';
	                $html .= '<td style="padding:0" id="cfloading' . $d["id"] . '"></td>';
	                $html .= '<td>' . static::renderSwitch($d["id"], $d["name"], "sth") . '</td>';
	                $html .= '<td style="padding:0" id="sthloading' . $d["id"] . '"></td>';
	                $html .= '<td id="settings' . $d["id"] . '">';
	                if ($current_zone_status == "on") {
	                    $html .= '<a href="?zone=' . $d["name"] . static::addSimpleParam($simple, false) . '">Manage CloudFlare</a>';
	                }
	                $html .= '</td>';
	                $html .= '</tr>';
	            }

	            $html .= '</table>';
	            $html .= '<script>';
	            $html .= 'var enabledzones = ' . json_encode($cf_active_zone) . ';';
	            $html .= 'for ( var i = 0; i < enabledzones.length; i++) { $("zone" + enabledzones[i]).checked = true; }';
	            $html .= 'var sthenabledzones = ' . json_encode($sth_active_zone) . ';';
	            $html .= 'for ( var j = 0; j < sthenabledzones.length; j++) { $("sth" + sthenabledzones[j]).checked = true; }';
	            $html .= '</script>';
	        } else {
	        	if(count($user_domains["total_domains"]) > 0 )
	            	$html .= '<h5 style="text-align:center">All of your websites are subdomains, please add a site that exists as a root domain</h5>';
	            else
	            	$html .= '<h5 style="text-align:center">Please add a website to Plesk in order to use ServerShield</h5>';
	        }

	        if($cf_user_zones <= 0 && (count($sth_active_zone) <= 0))
	        	$html .= '<p style="margin-top: 1em;"><h6 id="resetcfaccount" style="margin-left:auto; margin-right:auto; width:820px; text-align:left" class="subheadline"><em>Need to login into a different account? <a href="?clobber' . static::addSimpleParam($simple, false) . '">Click here to reset.</a></em><h6></p>';
	        return $html;
	    }

		public static function zoneSettings($zone, $simple = false) {
		    $zone_name = $zone["name"];
	        $zone_id = $zone["id"];
	        $zone_recs = Modules_servershield_PleskAPIHelper::getZoneDNSRecs($zone_name);
	        $cf_host_api = new Modules_servershield_CFHostAPI();

	        $cf_zname_setting_subdomains_key = md5(Modules_servershield_PleskAPIHelper::getEffectiveId() . $zone_name . "subdomains");
	        $zone_subdomains = pm_Settings::get($cf_zname_setting_subdomains_key);

	        $zone_stats = $cf_host_api->getStats($zone_name, Modules_servershield_PleskAPIHelper::getEffectiveId());

	        $thr = 0;
			$bw = 0;
			$req = 0;

	        if(is_object($zone_stats)) {
	        	$thr = intval($zone_stats->response->result->objs[0]->trafficBreakdown->pageviews->threat);
				$bw = floor(floatval($zone_stats->response->result->objs[0]->bandwidthServed->cloudflare) / 1000);
				$req = intval($zone_stats->response->result->objs[0]->requestsServed->cloudflare);
	        }

	        if(count($zone_recs) > 0) {
	            foreach($zone_recs as $rec) {
	                if($rec["host"] == $zone_name . ".")
	                    $root_record = $rec;
	                else
	                    $non_root_records[] = $rec;
	            }

	        $html = '<h3>' . $zone_name . '</h3>';
	        $html .= '<h6 style="float:right" class="subheadline"><a href="/modules/servershield/index.php/index/zones' . static::addSimpleParam($simple, true) . '">Return to Website List</a></h6>';
	        $html .= '<h4 class="subheadline">Configure CloudFlare for this website</h4><br>';
	        $html .= '<div id="cfmessagearea"></div>';
	        $html .= '<div id="cferrorarea"></div><br>';


	       	$html .= '<div style="margin-left:auto; margin-right:auto; width:800px; margin-bottom: 10px">';
	        $html .= '<div class="cfinfobox">Requests served <br><div id="requests" class="info" style="color:#2f7bbf">'. $req .'</div></div>';
	        $html .= '<div class="cfinfobox">Bandwidth saved (in MB)<br><div class="info" style="color:#9bca3e" id="bandwidth">' . $bw .'</div></div>';
	        $html .= '<div class="cfinfobox">Threats blocked <br><div id="threats" class="info" style="color:#bd2527">'. $thr .'</div></div>';
	        $html .= '</div>';

	        $html .= '<table id="cftable">';
	        $html .= '<th>DNS Subdomain</th><th>CloudFlare</th><th></th>';

	        $active = array();

	        /*if($root_record) {
	        	$html .= '<tr><td>' . $root_record["host"] . '</td><td>' . static::renderSwitch($root_record["id"], $root_record["host"]) . '</td>';
	        	$html .= '<td id="status' . $root_record["id"] . '"></td></tr>';
	    	}*/

	        foreach($non_root_records as $nrr) {
	                $html .= '<tr>';
	                $html .= '<td>' . $nrr["host"] . '</td>';

	                if($nrr["domain_name"] . "." != $nrr["host"])
	                	$html .= '<td>' . static::renderSwitch($nrr["id"], $nrr["host"], "rec_set") . '</td>';
	                else
	                	$html .= '<td> [Unavailable: Subdomain stored in separate DNS zone] </td>';

	                $html .= '<td id="status' . $nrr["id"] . '"></tr>';

	                if(Modules_servershield_PleskAPIHelper::isActiveSubdomain($nrr["host"], $zone_name)) {
	                    $active[] = $nrr["id"];
	                }
	        }
	            $html .= '</table>';
	            $html .= static::zoneSettingsForm($zone_name, $simple);
	            $html .= '<script>';
	            $html .= 'var enabledrecs = ' . json_encode($active) . ';';
	            $html .= 'for ( var i = 0; i < enabledrecs.length; i++) { $("rec" + enabledrecs[i]).checked = true; }';
	            $html .= '</script>';
	        }

	       	$html .= '<script>
				        function animateValue(id, start, end, duration) {
						    var range = end - start;
						    var current = start;
						    var increment = end > start? 1 : -1;
						    var stepTime = Math.abs(Math.floor(duration / range));
						    var obj = document.getElementById(id);
						    var timer = setInterval(function() {
						        if (current == end) {
						        	obj.innerHTML = current;
						            clearInterval(timer);
						        } else {
						     		current += increment;
						        	obj.innerHTML = current;
						        }
						    }, stepTime);
						}
		        			animateValue("requests", Math.floor(.99 * ' . $req .  '),' . $req . ', 1);
		        			animateValue("bandwidth", Math.floor(.99 * ' . $bw . '),' . $bw . ', 1);
		        			animateValue("threats", Math.floor(.99 * ' . $thr . '),' . $thr . ', 1);
	        			</script>';

	        return $html;
		}

	    public static function zoneSettingsForm($zone, $simple = false) {
	        $cf_host_api = new Modules_servershield_CFHostAPI();
	        $zone_settings =  $cf_host_api->getCFZoneSettings($zone);

	        foreach($zone_settings->result as $setting) {
	            switch($setting->name) {
	                case "security_level":
	                    $security_level_under_attack = ($setting->value == 'under_attack') ? ' selected' : '';
	                    $security_level_high = ($setting->value == 'high') ? ' selected' : '';
	                    $security_level_medium = ($setting->value == 'medium') ? ' selected' : '';
	                    $security_level_low = ($setting->value == 'low') ? ' selected' : '';
	                    $security_level_essentially_off = ($setting->value == 'essentially_off') ? ' selected' : '';
	                break;

	                case "development_mode":
	                    $development_mode_on = ($setting->value == 'on') ? ' selected' : '';
	                    $development_mode_off = ($setting->value == 'off') ? ' selected' : '';
	                break;

	                case "always_online":
	                    $always_online_on = ($setting->value == 'on') ? ' selected' : '';
	                    $always_online_off = ($setting->value == 'off') ? ' selected' : '';
	                break;
	            }

	        }


	        if($zone_settings->success) {
	           	//cache_purge
	            $html = '<div style="max-width:820px"><div style="margin: 20px 0px; background:white; display:inline; padding-left:10px;padding-top:10px;padding-right:10px; float:left; width:100%; font-weight:600; font-size:.9rem">
	            			<div style="display:inline; padding:0; float:left; width:50%; font-weight:600; font-size:.9rem">Purge Cache<br><p style="font-weight:300">Immediately purge cached resources for your website.</p></div>';
	            $html .= '<button style="float:right" id="purgecache" type="button" onclick="purgecache(\'' . $zone . '\')">Purge Cache</button></div></div>';


	            if(pm_Session::getClient()->isClient() || pm_Session::isImpersonated() )
	                $html .= '<form action="/modules/servershield/index.php/index/?zone=' . $zone . static::addSimpleParam($simple, false) . '" method="post">';
	            else
	                $html .= '<form action="/modules/servershield/index.php/index/zones?zone=' . $zone . static::addSimpleParam($simple, false) . '" method="post">';

	            $html .= '<br><br><br><div style="max-width:820px">';
	            //security_level
	            $html .= '<div style="background:white; display:inline; padding-left:10px;padding-top:10px;padding-right:10px; float:left; width:100%; font-weight:600; font-size:.9rem">
	                    <div style="display:inline; padding:0; float:left; width:50%; font-weight:600; font-size:.9rem">Security Level<br><p style="font-weight:300">Adjust the basic security level to modify CloudFlare\'s protection behavior relative to which visitors are shown a captcha/challenge page.</p></div>
	                    <select name="security_level" style="float:right">
	                        <option value="under_attack"'.$security_level_under_attack.'>I\'m Under Attack</option>
	                        <option value="high"'.$security_level_high.'>High</option>
	                        <option value="medium"'.$security_level_medium.'>Medium</option>
	                        <option value="low"'.$security_level_low.'>Low</option>
	                        <option value="essentially_off"'.$security_level_essentially_off.'>Essentially Off</option>
	                    </select>
	                    </div><br>';

	            //always_online
	            $html .= '<div style="background:white; display:inline; padding-left:10px;padding-top:10px;padding-right:10px; float:left; width:100%; font-weight:600; font-size:.9rem">
	                        <div style="display:inline; padding:0; float:left; width:50%; font-weight:600; font-size:.9rem">Always Online<br><p style="font-weight:300">Keep your web pages online when your site loses connectivity or times out.</p></div>
	                        <select name="always_online" style="float:right">
	                            <option value="on"'.$always_online_on.'>On</option>
	                            <option value="off"'.$always_online_off.'>Off</option>
	                        </select></div><br>';

	            //development
	            $html .= '<div style="background:white; display:inline; padding-left:10px;padding-top:10px;padding-right:10px; float:left; width:100%; font-weight:600; font-size:.9rem">
	                    <div style="display:inline; padding:0; float:left; width:50%; font-weight:600; font-size:.9rem">Development Mode<br><p style="font-weight:300">When Development Mode is on the cache is bypassed. Development mode remains
                on for 3 hours or until when it is toggled back off.</p></div>
	                    <select name="development_mode" style="float:right">
	                        <option value="on"'.$development_mode_on.'>On</option>
	                        <option value="off"'.$development_mode_off.'>Off</option>
	                    </select>
	                    </div><br>';

	            $html .= '<div style="background:white; display:inline; padding-left:10px;padding-top:10px;padding-right:10px; padding-bottom:10px; float:left; width:100%; font-weight:600; font-size:.9rem">
	            		 <button style="float:right" type="submit">OK</button>
	            		<p style="font-style: italic; font-weight: normal; font-size: .8em; padding: 0; margin: 0; margin-top: 1.5em;">Manage more CloudFlare settings at <a href="https://www.cloudflare.com/login" target="_blank">CloudFlare.com</a></p>
	            		 </div>';
	            $html .= '</div>';

	            $html .= '</form>';

	        } else {
	            $html .= "Settings could not be retrieved: " . $zone_settings->messages[0];
	        }
	        return $html;
	    }

	    public static function renderSwitch($id, $name, $function = "zone_set") {
	        $html = '<label class="switch"><input ';
	        $html .= 'type="checkbox"';

	        switch ($function) {
	            case "zone_set":
	                $html .= static::zoneSetOnClick($id, $name);
	            break;

	            case "rec_set":
	            	$html .= static::recSetOnClick($id, $name);
	            break;

	            case "sth":
	                $html .= static::STHOnClick($id, $name);
	            break;
	        }
	        $html .= '/><span class="knob"></span></label>';
	        return $html;
	    }

	    public static function STHOnClick($id, $name) {
	        $html = 'onClick="sthset(';
	        $html .= '\'' . $id . '\',';
	        $html .= '\'' . $name . '\'';
	        $html .= ')"';
	        $html .= ' id="sth' . $id . '"';

	        return $html;
	    }


	    public static function recSetOnClick($rec_id, $rec_name) {
            $html = 'onClick="recset(';
            $html .= '\'' . $rec_id . '\',\'' . $rec_name . '\'';
	        $html .= ')"';
            $html .= ' id="rec' . $rec_id . '"';

            return $html;
	    }

	    public static function zoneSetOnClick($id, $name, $rec_id = NULL, $rec_name = NULL) {
            $html = 'onClick="zoneset(';
            $html .= '\'' . $id . '\',';
            $html .= '\'' . $name . '\'';
	        $html .= ')"';
            $html .= ' id="zone' . $id . '"';

	        return $html;
	    }

		public static function CFUserCreateForm($error = false, $simple = false) {
	        $html = '<h3>Welcome to CloudFlare</h3> <h4 class="subheadline">Create a new CloudFlare account or sign in to your existing account </h4>';
	        $html .= '<br><br>';
	        $html .= '<div>
	        				<p style="font-size:16px">ServerShield is the easiest way to defends all your websites against online threats, monitor their reputation,  while making them load lightning fast. It helps you:</p>
	        				<img style="width:30%; height:auto;" src="/modules/servershield/fight.png"></img>
	        				<img style="width:30%; height:auto;" src="/modules/servershield/speed.png"></img>
							<img style="width:30%; height:auto;" src="/modules/servershield/monitor.png"></img>
	        				<br>
						  	<div class="intro">Fight hackers, spammers and botnets. <br><br> Prevent DDoS attacks against websites and APIs on your servers </div>
						  	<div class="intro">Speed up websites, mobile apps, APIs, images and DNS</div>
						  	<div class="intro">Monitor website reputation on a daily basis</div>
					  </div>';

	        if($error) {
	            $html .= '<h5> Error: ' . $error . '</h5>';
	        }
	        $html .= '<div id="cfcreds">';
	        if(pm_Session::getClient()->isClient() || pm_Session::isImpersonated())
	            $html .= '<form action="/modules/servershield/index.php/index/' . static::addSimpleParam($simple, true) . '" method="post">';
	        else
	            $html .= '<form action="/modules/servershield/index.php/index/zones' . static::addSimpleParam($simple, true) . '" method="post">';

	        $html .= '<label for="cfemail">Email:</label>';
	        $html .= '<input id="cfemail" type="text" name="cfemail" placeholder="CloudFlare Email" />';
	        $html .= '<br><br>';
	        $html .= '<label for="cfpass">Password</label>';
	        $html .= '<input id="cfpass" type="password" name="cfpass" placeholder="CloudFlare Password" />';
	        $html .= '<button type="submit">Submit</button>';
	        $html .= '</form>';
	        $html .= '<br><h6 class="subheadline"><em>If your e-mail address is not associated with a CloudFlare account, one will be created using the entered password.</em></h6>';
	        $html .= '</div>';

	        return $html;
	    }

		public static function activatedView() {
	        $cf_host_api = new Modules_servershield_CFHostAPI();
	        $html = '<h3>Settings</h3><h4 class="subheadline">The extension has been activated. You can sign up your sites to CloudFlare</h4>';
	        if( (intval(pm_Settings::get("cloudflare_zones")) <= 0) && (intval(pm_Settings::get("servershield_accounts")) <= 0)  )
	            $html .= '<h6 class="subheadline">Did you acquire a Host Key? <a href="?clobber">Click here to reset this page</a></h6>';
	        return $html;
	    }

		public static function hostAPIKeyForm($key_saved = FALSE, $message = NULL) {
	        $host_key_html = '';
	        $host_email_html = '';
	        $host_key_email = '';

	        $html = '<h3>Settings</h3><h4 class="subheadline">Activate the extension or enter your Host Key.</h4>';

	        if($cf_host_email = pm_Settings::get("cf_host_email")) {
	            $host_email_html = 'value="' . $cf_host_email . '"' ;
	        }

	        if($cf_host_api_key = pm_Settings::get("cf_host_api_key")) {
	            $host_key_html = 'value="totallynotakey"' ;
	        }

	        if($key_saved)
	            $html .= '<h5 class="text-success">Key Saved!</h5>';
	        else {
	            if(isset($message))
	                $html .= '<h5 class="text-error">' . $message . '</h5>';
	        }

	        if(!$cf_host_email || !$cf_host_api_key) {
	            $html .= '<h6 class="subheadline">Not a CloudFlare Certified Partner? <a href="https://www.cloudflare.com/partners" target="_blank">Sign up to be come one</a>.';
	            $html .= '<br>Otherwise, <a href="/modules/servershield/index.php/index/settings?activate">activate this module without a host key and e-mail.</a></h6>';
	        }

	        $html .= '<br>';
	        $html .= '<div id="cfcreds">';
	        $html .= '<form action="/modules/servershield/index.php/index/settings" method="post">';
	        $html .= '<label for="cfhostemail">Host E-mail:</label>';
	        $html .= '<input id="cfhostemail" type="text" name="cfhostemail" placeholder="Host E-mail (i.e. the e-mail you use to log in to the Partner Portal)"' . $host_email_html . '/><br><br>';
	        $html .= '<label for="cfhostapikey">Host API Key:</label>';
	        $html .= '<input id="cfhostapikey" type="password" name="cfhostapikey" placeholder="Host API Key"' . $host_key_html . '/>';
	        $html .= '<button type="submit">Submit</button>';
	        $html .= '</form>';
	        $html .= '</div>';

	        return $html;
	    }


	    public static function getZonesWithAssociatedOwners() {
	    	$zones = Modules_servershield_PleskAPIHelper::getServerDomains();
	    	$result = array();

	    	foreach($zones as $zone) {
	    		$result[$zone["id"]]["owner_name"] = $zone["contactName"];
	    		$result[$zone["id"]]["zones"][] = $zone["name"];
	    	}

	    	return $result;
	    }

	    public static function zoneList() {
	    	$users_with_zones = Modules_servershield_CFPageBuilder::getZonesWithAssociatedOwners();
	    	Modules_servershield_PleskAPIHelper::logMessage($users_with_zones);
	    	$inactive_zones = array();

	       	$html = '<div style="margin-top:25px">';
			$html .= '<h4 id="statustitle"> Website Status List </h4>';

			$html .= '<h6 class="subheadline"><div class="tag" style="vertical-align: middle">CloudFlare</div> = CloudFlare Enabled <br> <div class="tag" style="background-color: #bd2527; vertical-align: middle">StopTheHacker</div> = StopTheHacker enabled</h6></div>';
			$html .= '<br>';
			$html .= '<table id="cftable">';
	        $html .= '<th>Owner</th><th>CloudFlare Account E-mail</th><th>Website(s)</th>';

	       	foreach($users_with_zones as $user_id => $user) {
		       	$cf_uemail = pm_Settings::get(md5($user_id . "CF-USER-EMAIL"));

				$html .= '<tr>';
		       	$html .= '<td>' . $user["owner_name"] . '</td>';

		       	if($cf_uemail)
		       		$html .= '<td> ' . $cf_uemail . '</td>';
		       	else
		       		$html .= '<td>No associated CloudFlare account</td>';

		       	$html .= '<td>';
		       	foreach($user["zones"] as $zone) {
		       		$cf_status = pm_Settings::get(md5($user_id . $zone));
					$sth_status = pm_Settings::get(md5($user_id . $zone . "STH"));

		       		$html .= '<div>' . $zone;

		       		if($cf_status == "on") {
		       			$html .= ' <div class="tag">CloudFlare</div>';
		       		} else {
		       			$inactive_zones[] = array("userid" => $user_id, "zone" => $zone);
		       		}

		       		if($sth_status == "on") {
		       			$html .= ' <div class="tag" style="background-color: #bd2527">StopTheHacker</div> ';
		       		}
		       		$html .= '</div>';
		       	}
		       	$html .= '</td>';
		       	$html .= '</tr>';
		    }
		    $html .= '</table>';

		    if(!empty($inactive_zones)) {
		        $html .= '<script>';
		        $html .= 'var inactivezones = ' . json_encode($inactive_zones) . ';';
		        $html .= '$("statustitle").innerHTML += \'<button id="bulkadd" style="float:right; margin-top: 10px" type="button" onclick="bulkadd(inactivezones)">Activate CloudFlare for All Inactive Websites</button>\';';
		        $html .= '</script>';
	    	}
	        return $html;
	    }

		public static function serverDashboardPage() {
	        $requests = pm_Settings::get("cf_serverstats_requests");
	        $bandwidth = pm_Settings::get("cf_serverstats_bandwidth");
	        $threats = pm_Settings::get("cf_serverstats_threats");

	        $html = '<h3>Server Dashboard</h3><br><br>';
	        $html .= '<div style="margin-left:auto; margin-right:auto; width:800px">';
	        $html .= '<h4>Server Analytics</h4>';
  			$html .= '<h5 class="subheadline">Total stats of websites that have CloudFlare enabled</h5><br>';
	        $html .= '<div class="cfinfobox">Requests served <br><div id="requests" class="info" style="color:#2f7bbf">' . $requests . '</div></div>';
	        $html .= '<div class="cfinfobox">Bandwidth saved (in MB)<br><div class="info" style="color:#9bca3e" id="bandwidth">' . $bandwidth . '</div></div>';
	        $html .= '<div class="cfinfobox">Threats blocked <br><div id="threats" class="info" style="color:#bd2527">'. $threats . '</div></div>';
	        $html .= '<div style="text-align:right"><em>analytics for the last 30 days, updated every 24 hours</em></div>';
	        $html .= '</div>';

	        $html .= Modules_servershield_CFPageBuilder::zoneList();

	        $html .= '<script>
				        function animateValue(id, start, end, duration) {
						    var range = end - start;
						    var current = start;
						    var increment = end > start? 1 : -1;
						    var stepTime = Math.abs(Math.floor(duration / range));
						    var obj = document.getElementById(id);
						    var timer = setInterval(function() {
						        if (current == end) {
						        	obj.innerHTML = current;
						            clearInterval(timer);
						        } else {
						        	current += increment;
						        	obj.innerHTML = current;
						        }
						    }, stepTime);
						}
	        			animateValue("requests", Math.floor(.99 * ' . $requests .  '),' . $requests . ', 1);
	        			animateValue("bandwidth", Math.floor(.99 * ' . $bandwidth . '),' . $bandwidth . ', 1);
	        			animateValue("threats", Math.floor(.99 * ' . $threats . '),' . $threats . ', 1);
	        		</script>';
	        return $html;
    	}
	}