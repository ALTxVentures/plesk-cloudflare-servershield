<?php
	function processRequest() {
		if(isset($_POST["act"])) {
			$action = $_POST["act"];
			switch($action) {
				case "zone_set":
					echo processZoneSet();
				break;

				case "sth_set":
					echo processSTHSet();
				break;

				case "purge_cache":
					echo processPurgeCache();
				break;

				case "zone_rec_set":
					echo processZoneRecSet();
				break;

				case "create_cf_rec":
					echo processCreateCFRec();
				break;

				case "delete_cf_rec":
					echo processDeleteCFRec();
				break;

				case "create_cf_resolve":
					echo processCreateCFResolve();
				break;

				case "delete_cf_resolve":
					echo processDeleteCFResolve();
				break;

				case "revert_resolve":
					echo processRevertResolve();
				break;

				case "revert_cnames":
					echo processRevertCNAMES();
				break;

				default:
					throw new pm_Exception("Invalid Request");
			}
		}
	}

	function processSTHSet() {
		$user_id = Modules_servershield_PleskAPIHelper::getEffectiveId();
		$cf_uemail_setting_key = md5($user_id . "CF-USER-EMAIL");
		$cf_uemail = pm_Settings::get($cf_uemail_setting_key);
		$plesk_zone_id = $_POST["plesk_id"];
		$plesk_zone_name = $_POST["zone_name"];
		$cf_sth_zone_key = md5($user_id . $plesk_zone_name . "STH");
		$cf_sth_status = pm_Settings::get($cf_sth_zone_key);
		$cf_host_api = new Modules_servershield_CFHostAPI();

		if($cf_sth_status == "on") {
			$result = $cf_host_api->disableSTH($cf_uemail, $plesk_zone_name);

			if($result->success) {
				pm_Settings::set($cf_sth_zone_key, false);
				return json_encode(array("sth_disable" => "success"));
			} else if ($result) {
				return json_encode(array("sth_disable" => $result->errors[0]->message));
			} else {
				return json_encode(array("sth_disable" => "Unknown API error"));
			}
		}
		else {
			$result = $cf_host_api->activateSTH($cf_uemail, $plesk_zone_name);

			if($result->success) {
				pm_Settings::set($cf_sth_zone_key, "on");
				return json_encode(array("sth_set" => "success"));
			} else if ($result) {
				return json_encode(array("sth_set" => $result->errors[0]->message));
			} else {
				return json_encode(array("sth_set" => "Unknown API error"));
			}
		}
	}

	function processRevertResolve() {
		$rec_array = NULL;

		if(!empty($_POST["subdomains"])) {
			$rec_array = json_decode($_POST["subdomains"]);
			$revert_errors = array();

			if(is_array($rec_array)) {
				foreach($rec_array as $rec) {
					$delete_cf_rs2 = Modules_servershield_PleskAPIHelper::deleteCFResolveTo($rec);

					if(!empty($delete_cf_rs2)) {
						foreach($delete_cf_rs2 as $err) {
							$revert_errors[] = $err;
						}
					}
				}

				$response = array();

				if(!empty($revert_errors)) {
					$response["revert_resolve"] = implode(",", $revert_errors);
				} else {
					$response["revert_resolve"] = "success";
				}

				return json_encode($response);
			}
		} else {
			return json_encode(array("revert_resolve" => "Missing parameters"));
		}
	}

	function processRevertCNAMES() {
		$rec_array = NULL;

		if(!empty($_POST["subdomains"])) {
			$rec_array = json_decode($_POST["subdomains"]);
			$revert_errors = array();

			if(is_array($rec_array)) {
				foreach($rec_array as $rec) {
					$delete_cf_rs2 = Modules_servershield_PleskAPIHelper::deleteCFCNAME($rec);

					if(!empty($delete_cf_rs2)) {
						foreach($delete_cf_rs2 as $err) {
							$revert_errors[] = $err;
						}
					}
				}

				$response = array();

				if(!empty($revert_errors)) {
					$response["revert_cnames"] = implode(",", $revert_errors);
				} else {
					$response["revert_cnames"] = "success";
				}

				return json_encode($response);
			}
		} else {
			return json_encode(array("revert_resolve" => "Missing parameters"));
		}
	}

	function processCreateCFResolve() {
		$plesk_rec_name = NULL;

		if(!empty($_POST["plesk_rec_name"])) {
			$plesk_rec_name = $_POST["plesk_rec_name"];
		} else {
			return json_encode(array("create_cf_resolve" => "Unknown failure: missing parameters"));
		}

		$create_cf_rs2 = Modules_servershield_PleskAPIHelper::createCFResolveTo($plesk_rec_name);

		if(empty($create_cf_rs2)) {
			return json_encode(array("create_cf_resolve" => "success"));
		} else {
			return json_encode(array("create_cf_resolve" => implode(",", $create_cf_rs2)));
		}
	}

	function processDeleteCFResolve() {
		$plesk_rec_name = NULL;

		if(!empty($_POST["plesk_rec_name"])) {
			$plesk_rec_name = $_POST["plesk_rec_name"];
		} else {
			return json_encode(array("delete_cf_resolve" => "Unknown failure: missing parameters"));
		}

		$delete_cf_rs2 = Modules_servershield_PleskAPIHelper::deleteCFResolveTo($plesk_rec_name);

		if(empty($delete_cf_rs2)) {
			return json_encode(array("delete_cf_resolve" => "success"));
		} else {
			return json_encode(array("delete_cf_resolve" => implode(",", $delete_cf_rs2)));
		}
	}

	function processCreateCFRec() {
		$plesk_rec_name = NULL;

		if(!empty($_POST["plesk_rec_name"])) {
			$plesk_rec_name = $_POST["plesk_rec_name"];
		} else {
			return json_encode(array("create_cf_rec" => "Unknown failure: missing parameters"));
		}

		$create_cf_cname = Modules_servershield_PleskAPIHelper::createCFCNAME($plesk_rec_name);

		if(empty($create_cf_cname)) {
			return json_encode(array("create_cf_rec" => "success"));
		} else {
			return json_encode(array("create_cf_rec" => implode(",", $create_cf_cname)));
		}
	}

	function processDeleteCFRec() {
		$plesk_rec_name = NULL;

		if(!empty($_POST["plesk_rec_name"])) {
			$plesk_rec_name = $_POST["plesk_rec_name"];
		} else {
			return json_encode(array("delete_cf_rec" => "Unknown failure: missing parameters"));
		}

		$delete_cf_rs2 = Modules_servershield_PleskAPIHelper::deleteCFCNAME($plesk_rec_name);

		if(empty($delete_cf_rs2)) {
			return json_encode(array("delete_cf_rec" => "success"));
		} else {
			return json_encode(array("delete_cf_rec" => implode(",", $create_cf_cname)));
		}
	}

	function processZoneRecSet() {
		$user_id = Modules_servershield_PleskAPIHelper::getEffectiveId();
		$plesk_rec_name = NULL;
		$regDomain = new Modules_servershield_regDomain();

		if(!empty($_POST["plesk_rec_name"])) {
			$plesk_rec_name = $_POST["plesk_rec_name"];
		} else {
			return json_encode(array("zone_set" => "Unknown failure: missing parameters"));
		}

		$plesk_zone_name = $regDomain->getRegisteredDomain($plesk_rec_name);

		if($plesk_zone_name) {
	        $cf_ukey_setting_key = md5($user_id . "CF-USER-KEY");
			$cf_ukey = pm_Settings::get($cf_ukey_setting_key);

			$cf_zname_setting_key = md5($user_id . $plesk_zone_name);
			$zone_status = pm_Settings::get($cf_zname_setting_key);

			$cf_zname_setting_subdomains_key = md5($user_id . $plesk_zone_name . "subdomains");
			$zone_subdomains = pm_Settings::get($cf_zname_setting_subdomains_key);

			$result = array("zone_rec_set" => "Unexpected error: domain not valid" );

			if(Modules_servershield_PleskAPIHelper::isActiveSubdomain($plesk_rec_name, $plesk_zone_name)) {
				$remove_cf_sub = Modules_servershield_PleskAPIHelper::removeCFSubdomain($plesk_rec_name, $plesk_zone_name);

				if($remove_cf_sub == "success") {
					$delete_cf_cname = Modules_servershield_PleskAPIHelper::deleteCFCNAME($plesk_rec_name);

					if(empty($remove_cf_cname)) {
						$result = array("rec_del" => "success");
					} else {
						$result = array("rec_del" => implode(", ", $remove_cf_cname));
					}
				} else {
					$result = array("rec_del" => $remove_cf_sub);
				}
			} else {
				$add_cf_sub = Modules_servershield_PleskAPIHelper::addCFSubdomain($plesk_rec_name, $plesk_zone_name);

				if($add_cf_sub == "success") {
					$create_cf_resolveto = Modules_servershield_PleskAPIHelper::createCFResolveTo($plesk_rec_name);

					if(empty($create_cf_resolveto)) {
						$result = array("rec_set" => "success");
					} else {
						$result = array("rec_set" => implode(", ", $create_cf_resolveto));
					}
				} else {
					$result = array("rec_set" => $add_cf_sub);
				}
			}
		} else {
			$result["zone_rec_set"] = "Invalid record";
		}

		return json_encode($result);
	}

	function processZoneSet() {
        $user_id = Modules_servershield_PleskAPIHelper::getEffectiveId();
        $cf_ukey_setting_key = md5($user_id . "CF-USER-KEY");
    	$cf_ukey = pm_Settings::get($cf_ukey_setting_key);
    	$cf_host_api = new Modules_servershield_CFHostAPI();
    	$cf_num_zones = intval(pm_Settings::get("cloudflare_zones"));
    	$cf_user_zones_key = md5($user_id . "CF-ZONES");
    	$cf_user_zones = intval(pm_Settings::get($cf_user_zones_key));

		if(!isset($_POST["plesk_id"]) || !isset($_POST["zone_name"]) || !$cf_ukey){
			throw new pm_Exception("Missing Parameters");
			return json_encode(array("zone_set" => "Unknown failure: missing parameters"));
		} else {
			$zone_name = $_POST["zone_name"];
	        $cf_zname_setting_key = md5($user_id . $zone_name);
	        $cf_zname_setting_subdomains_key = md5($user_id . $zone_name . "subdomains");
	        $active_subdomains = Modules_servershield_PleskAPIHelper::getActiveSubdomains($zone_name);

	        if(pm_Settings::get($cf_zname_setting_key) == "on") {
	        	$zdelete = $cf_host_api->zone_delete($cf_ukey, $zone_name);

	        	if(is_array($zdelete)) {
	        		pm_Settings::set($cf_zname_setting_key, false);
	        		pm_Settings::set($cf_zname_setting_subdomains_key, false);
	        		pm_Settings::set("cloudflare_zones", $cf_num_zones - 1);
	        		pm_Settings::set($cf_user_zones_key, $cf_user_zones - 1);

	        		$revert_errors = array();

	        		foreach($active_subdomains as $rec) {
	        			$delete_cf_cname = Modules_servershield_PleskAPIHelper::deleteCFCNAME($rec);

	        		if(!empty($delete_cf_cname)) {
	        				foreach($delete_cf_cname as $err) {
	        					$revert_errors[] = $err;
	        				}
	        			}
	        		}

	        		$response = array("zone_delete" => "success");

	        		if(!empty($revert_errors)) {
	        			$response["rec_revert"] = $revert_errors;
	        		} else {
	        			$response["subdomains"] = $active_subdomains;
	        		}

	        		return json_encode($response);
	        	} else if ($zdelete) {
	        		return json_encode(array("zone_delete" => $zdelete));
	        	} else {
	        		return json_encode(array("zone_delete" => "CloudFlare API unreachable"));
	        	}
			} else {
				$zset = $cf_host_api->zone_set($cf_ukey, $zone_name, "cf.www" . $zone_name, "www");

				if($zset)
					return json_encode(array("zone_set" => $zset));
				else {
					pm_Settings::set($cf_zname_setting_key, "on");
					pm_Settings::set("cloudflare_zones", $cf_num_zones + 1);
	        		pm_Settings::set($cf_user_zones_key, $cf_user_zones + 1);
					return json_encode(array("zone_set" => "success"));
				}
			}
		}
	}

	function processPurgeCache() {
		$user_id = Modules_servershield_PleskAPIHelper::getEffectiveId();
		$cf_host_api = new Modules_servershield_CFHostAPI();
		if(isset($_POST["zone_name"]) && $_POST["zone_name"]) {
			$zone_name = $_POST["zone_name"];
			$purge = $cf_host_api->purgeCFCache($zone_name, $user_id);
			if($purge->result == "success") {
				return json_encode(array("purge_cache" => "success"));
			} else if (!$purge) {
				return json_encode(array("purge_cache" => "CloudFlare API unreachable"));
			} else {
				return json_encode(array("purge_cache" => $purge->msg));
			}
		} else {
			return json_encode(array("purge_cache" => "Missing parameters"));
		}
	}

	pm_Context::init('servershield');
	processRequest();