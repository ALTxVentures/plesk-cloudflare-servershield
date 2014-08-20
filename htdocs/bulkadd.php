<?php
	function showError($msg) {
		echo json_encode( array("result" => "error", "msg" => $msg) );
		return false;
	}

	function showSuccess($msg) {
		echo json_encode( array("result" => "success", "msg" => $msg) );
		return true;
	}

	function validate() {

		if(!pm_Session::getClient()->isAdmin()) {
			return showError("Unauthorized");
		}

		if(!isset($_POST["userid"])) {
			return showError("User ID required");
		}

		if (!intval($_POST["userid"])) {
			return showError("Invalid User ID");
		}

		if (!isset($_POST["zone"])) {
			return showError("Missing zone");
		}

		if(!isset($_POST["act"])) {
			return showError("Action needed");
		} else {
			$act = $_POST["act"];

			switch($act) {
				case "user_set": case "zone_set":
				break;

				case "rec_set":
					if(!isset($_POST["record"]))
						return showError("Missing record");
				break;

				default:
					return showError("Invalid Action");
			}
		}

		return TRUE;
	}

	function processRecSet($user_id, $record_name) {
			$create_cf_cname = Modules_servershield_PleskAPIHelper::createCFCNAME($record_name, intval($user_id));

			if(empty($create_cf_cname)) {
				return showSuccess("DNS changed");
			} else {
				return showError(implode(",", $create_cf_cname));
			}

	}

	function processZoneSet($user_id, $zone) {
        $cf_ukey_setting_key = md5($user_id . "CF-USER-KEY");
    	$cf_ukey = pm_Settings::get($cf_ukey_setting_key);
    	$cf_host_api = new Modules_servershield_CFHostAPI();
    	$cf_num_zones = intval(pm_Settings::get("cloudflare_zones"));
    	$cf_user_zones_key = md5($user_id . "CF-ZONES");
    	$cf_user_zones = intval(pm_Settings::get($cf_user_zones_key));
		$zone_name = $zone;
        $cf_zname_setting_key = md5($user_id . $zone_name);
        $cf_zname_setting_subdomains_key = md5($user_id . $zone_name . "subdomains");

		$zset = $cf_host_api->zone_set($cf_ukey, $zone_name, "cf.www." . $zone_name, "www");

		if($zset)
			return showError($zset);
		else {
			pm_Settings::set($cf_zname_setting_key, "on");
			pm_Settings::set("cloudflare_zones", $cf_num_zones + 1);
    		pm_Settings::set($cf_user_zones_key, $cf_user_zones + 1);
    		pm_Settings::set($cf_zname_setting_subdomains_key, "www:cf.www." . $zone);

    		$create_cf_resolveto = Modules_servershield_PleskAPIHelper::createCFResolveTo("www." . $zone_name, intval($user_id));

    		Modules_servershield_PleskAPIHelper::logMessage($zone);
    		Modules_servershield_PleskAPIHelper::logMessage($create_cf_resolveto);

    		if(empty($create_cf_resolveto)) {
    			return showSuccess("Zone provisioned successfully");
    		} else {
    			return showError("CloudFlare provisioned, DNS changes failed");
    		}
		}
	}

	function processUserSet($user_id) {
		$cf_host_api = new Modules_servershield_CFHostAPI();
	    $cf_ukey_setting_key = md5($user_id . "CF-USER-KEY");
        $cf_uemail_setting_key = md5($user_id . "CF-USER-EMAIL");
        $cf_apikey_setting_key = md5($user_id . "CF-API-KEY");
    	$cf_ukey = pm_Settings::get($cf_ukey_setting_key);
    	$cf_uemail = pm_Settings::get($cf_uemail_setting_key);
    	$servershield_accounts = intval(pm_Settings::get("servershield_accounts"));

    	Modules_servershield_PleskAPIHelper::logMessage($cf_ukey);
		Modules_servershield_PleskAPIHelper::logMessage($cf_uemail);


    	if(empty($cf_ukey) && empty($cf_uemail)) {
    		if ( $user = pm_Client::getByClientId($user_id) ) {
    			$user_email = $user->getProperty("email");
    			$temp_password = md5( time() . $user_email . $user_id);

    			$ulookup = $cf_host_api->user_lookup($user_email);

    			if($ulookup["user_exists"] && !$ulookup["user_authed"]) {
    				return showError("CloudFlare user exists, proper password required");
    			} else if( !empty($ulookup["user_exists"]) && !empty($ulookup["user_authed"]) ) {
    				pm_Settings::set($cf_ukey_setting_key, $ulookup["user_key"]);
    				pm_Settings::set($cf_apikey_setting_key, $ulookup["user_api_key"]);
    				pm_Settings::set($cf_uemail_setting_key, $user_email);
    				pm_Settings::set("servershield_accounts", $servershield_accounts + 1);

					return showSuccess("User retrieved from Host API");
				} else {
	    			$ucreate = $cf_host_api->user_create($user_email, $temp_password);

	    			if(is_array($ucreate)) {
	    				pm_Settings::set($cf_ukey_setting_key, $ucreate["user_key"]);
	    				pm_Settings::set($cf_apikey_setting_key, $ucreate["user_api_key"]);
	    				pm_Settings::set($cf_uemail_setting_key, $user_email);
	    				pm_Settings::set("servershield_accounts", $servershield_accounts + 1);

	    				return showSuccess("User Created with a random password");
	    			} else {
	    				return showError($ucreate);
	    			}
    			}
    		}
    	} else {
    		return showSuccess("User exists, continuing..");
    	}
	}

	function processBulkAdd() {
		if(validate()) {
			$act = $_POST["act"];
			$userid = $_POST["userid"];
			$zone = $_POST["zone"];

			switch($act) {
				case "zone_set":
					return processZoneSet($userid, $zone);
				break;

				case "rec_set":
					return processRecSet($userid, $_POST["record"]);
				break;

				case "user_set":
					return processUserSet($userid);
				break;

				default:
					return showError("Invalid Action");
			}
		}
	}

	pm_Context::init('servershield');

	if(pm_Session::getClient()->isAdmin())
		processBulkAdd();
	else
		showError("Permission Denied");