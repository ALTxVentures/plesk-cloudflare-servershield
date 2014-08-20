<?php
	class Modules_servershield_PleskAPIHelper {
		static $recTypes = array("A", "CNAME", "AAAA");
		static $invalid_subdomain_regex = '/(^cf|^autoconfig|^autodiscover|^direct|^ssh|^ftp|^ssl|^ns[^.]*|^imap[^.]*|^pop[^.]*|smtp[^.]*|^mail[^.]*|^mx[^.]*|^exchange[^.]*|^smtp[^.]*|google[^.]*|^secure|^sftp|^svn|^git|^irc|^email|^mobilemail|^pda|^webmail|^e\.|^video|^vid|^vids|^sites|^calendar|^svn|^cvs|^git|^cpanel|^panel|^repo|^webstats|^local|localhost|ipv4|ipv6)/i';


		public static function getRecord($rec_id) {
			$pkt = new SimpleXMLElement('<dns></dns>');
			$pkt->addChild("get_rec");
			$pkt->get_rec->addChild("filter");
			$pkt->get_rec->filter->addChild("id");
			$pkt->get_rec->filter->{"id"} = $rec_id;
			$api_response = pm_ApiRpc::getService()->call($pkt);
            Modules_servershield_PleskAPIHelper::logMessage($api_response);
			return json_decode(json_encode($api_response->dns->get_rec->result));
		}

		public static function createRecord($zone_id, $content) {
            $pkt = new SimpleXMLElement('<dns></dns>');
			$pkt->addChild("add_rec");
			$pkt->add_rec->addChild("site-id");
			$pkt->add_rec->addChild("type");
			$pkt->add_rec->addChild("host");
			$pkt->add_rec->addChild("value");
			$pkt->add_rec->{'site-id'} = $zone_id;
			$pkt->add_rec->type = $content["type"];

            if(isset($content["name"]) && !empty($content["name"]))
                $pkt->add_rec->host = $content["name"];

			$pkt->add_rec->value = $content["value"];

			$api_response = pm_ApiRpc::getService()->call($pkt);
            Modules_servershield_PleskAPIHelper::logMessage($api_response);
			return json_decode(json_encode($api_response->dns->add_rec->result));
		}

		public static function deleteRecord($rec_id) {
			$pkt = new SimpleXMLElement('<dns></dns>');
			$pkt->addChild("del_rec");
			$pkt->del_rec->addChild("filter");
			$pkt->del_rec->filter->addChild("id");
			$pkt->del_rec->filter->id = $rec_id;
			$api_response = pm_ApiRpc::getService()->call($pkt);
            Modules_servershield_PleskAPIHelper::logMessage($api_response);
			return json_decode(json_encode($api_response->dns->del_rec->result));
		}

		public static function createCFCNAME($rec_name, $overrideID = NULL) {
            $rec_name = (substr($rec_name, -1) == ".") ? $rec_name : $rec_name . ".";
            $current_records = static::fetchRecordByName($rec_name, $overrideID);
            $domain_id = "";
            $errors = array();

            if(is_array($current_records) && !empty($current_records)) {
                foreach($current_records as $rec) {
                    $plesk_rec_delete = static::deleteRecord($rec["id"]);
                    if($plesk_rec_delete->status == "error") {
                            if($plesk_rec_delete->errcode != 1013)
                                $errors[] = $plesk_rec_delete->errtext;
                    }
                    $domain_id = $rec["domain_id"];
                }

                if( ($domain_id != "") && empty($errors) ) {
                    $regDomain = new Modules_servershield_regDomain();
                    $rec_value = $rec_name . "cdn.cloudflare.net.";
                    $rec_host = $regDomain->extractSubdomainName($rec_name, $rec["domain_name"]);
                    $new_record = array("type" => "CNAME", "name" => $rec_host, "value" => $rec_value);
                    $plesk_rec_create = static::createRecord($domain_id, $new_record);

                    if($plesk_rec_create->status == "error") {
                        $errors[] = $plesk_rec_create->errtext;
                    }
                }
            } else {
                $errors[] = "Subdomain is not available in DNS Zone";
            }

            return $errors;

		}

        public static function deleteCFCNAME($rec_name) {
            $current_records = static::fetchRecordByName($rec_name);
            $errors = array();


            if(is_array($current_records) && !empty($current_records)) {
                foreach($current_records as $rec) {

                    if(strpos($rec["val"], "cdn.cloudflare.net")) {
                        $plesk_rec_delete = static::deleteRecord($rec["id"]);
                    }

                    if($plesk_rec_delete->status == "error") {
                            if($plesk_rec_delete->errcode != 1013)
                                $errors[] = $plesk_rec_delete->errtext;
                    }
                }
            }

            return $errors;
        }

        public static function createCFResolveTo($rec_name, $overrideID = NULL) {
            $current_records = static::fetchRecordByName($rec_name, $overrideID);
            $errors = array();

            $regDomain = new Modules_servershield_regDomain();

            if(is_array($current_records) && !empty($current_records)) {
                foreach($current_records as $rec) {

                	$sub_name = $regDomain->extractSubdomainName($rec["host"], $rec["domain_name"]);
                    $domain_id = $rec["domain_id"];
                    $rec_type = $rec["type"];
                    $rec_value = $rec["val"];
                    $rec_host = ($sub_name) ? "cf." . $sub_name : "cf";
                    $new_record = array("type" => $rec_type, "name" => $rec_host, "value" => $rec_value);

                    if(strpos($rec_value, "cdn.cloudflare.net"))
                        $errors[] = "Invalid record: already pointing to a CloudFlare CNAME";
                    else {
                        $plesk_rec_create = static::createRecord($domain_id, $new_record);
                        if($plesk_rec_create->status == "error") {
                            if($plesk_rec_create->errcode != 1007) {
                                $errors[] = $plesk_rec_create->errtext;
                            }
                        }
                    }
                }
            } else {
                $errors[] = "Subdomain is not available in DNS Zone";
            }

            return $errors;
        }

        public static function deleteCFResolveTo($rec_name) {
        	$current_records = static::fetchRecordByName("cf." . $rec_name);
        	$errors = array();

			$regDomain = new Modules_servershield_regDomain();

        	if(is_array($current_records) && !empty($current_records)) {
        		foreach($current_records as $rec) {
                    $domain_id = $rec["domain_id"];
                    $rec_type = $rec["type"];
                    $rec_value = $rec["val"];
                    $rec_host = $regDomain->extractSubdomainName($rec_name, $rec["domain_name"]);
                    $new_record = array("type" => $rec_type, "name" => $rec_host, "value" => $rec_value);
        			$plesk_rec_delete = static::deleteRecord($rec["id"]);

                    if($plesk_rec_delete->status == "error") {
        				$errors[] = $plesk_rec_delete->errtext;
        			}

                    $plesk_rec_create = static::createRecord($domain_id, $new_record);

                    if($plesk_rec_create->status == "error") {
                        $errors[] = $plesk_rec_create->errtext;
                    }
        		}
        	} else {
        		$errors[] = "Subdomain is not available in DNS Zone or is already deleted";
        	}

        	return $errors;
        }

		public static function getServerInfo() {
			$pkt = new SimpleXMLElement('<server></server>');
			$pkt->addChild("get");
			$pkt->get->addChild("gen_info");

			$response = pm_ApiRpc::getService()->call($pkt);
			return $response->server->get->result->gen_info;
		}

		public static function updateServerShield() {
			$pkt = new SimpleXMLElement('<server></server>');
			$pkt->addChild("install-module");
			$pkt->{'install-module'}->addChild("url");
			$pkt->{'install-module'}->url = "https://www.cloudflare.com/static/misc/plesk_extension/plesk_extension.zip";

			$response = pm_ApiRpc::getService()->call($pkt);
			return $response;
		}

        public static function addCFSubdomain($rec_name, $zone_name) {
            $regDomain = new Modules_servershield_regDomain();
            $cf_zname_setting_subdomains_key = md5(Modules_servershield_PleskAPIHelper::getEffectiveId() . $zone_name . "subdomains");
            $user_id = Modules_servershield_PleskAPIHelper::getEffectiveId();
            $cf_ukey_setting_key = md5($user_id . "CF-USER-KEY");
            $cf_ukey = pm_Settings::get($cf_ukey_setting_key);
            $zone_subdomains = pm_Settings::get($cf_zname_setting_subdomains_key);
            $resolve_to = "cf." . $rec_name;
            $cf_host_api = new Modules_servershield_CFHostAPI();

            if(!empty($zone_subdomains))
                $sub_arr = explode(",", $zone_subdomains);
            else
                $sub_arr = array();

            $sub_name = $regDomain->extractSubdomainName($rec_name);
            $sub_w_rs2 = $sub_name . ":" . rtrim($resolve_to, '.');
            $sub_index = array_search($sub_w_rs2, $sub_arr);

            if($sub_index === false) {
                $sub_arr[] = $sub_w_rs2;
                $new_sub_list = implode(",", array_values($sub_arr));
                $zset = $cf_host_api->zone_set($cf_ukey, $zone_name, "cf.www." . $zone_name, $new_sub_list);

                if($zset !== FALSE) {
                    return $zset;
                } else {
                    pm_Settings::set($cf_zname_setting_subdomains_key, $new_sub_list);
                    return "success";
                }
            } else {
                if(static::isActiveSubdomain($rec_name, $zone_name))
                    $zset = $cf_host_api->zone_set($cf_ukey, $zone_name, "cf.www." . $zone_name, $zone_subdomains);

                if(isset($zset)) {
                    return $zset;
                } else {
                    return "success";
                }
            }
        }

        public static function removeCFSubdomain($rec_name, $zone_name) {
            $regDomain = new Modules_servershield_regDomain();
            $cf_zname_setting_subdomains_key = md5(Modules_servershield_PleskAPIHelper::getEffectiveId() . $zone_name . "subdomains");
            $user_id = Modules_servershield_PleskAPIHelper::getEffectiveId();
            $cf_ukey_setting_key = md5($user_id . "CF-USER-KEY");
            $cf_ukey = pm_Settings::get($cf_ukey_setting_key);
            $zone_subdomains = pm_Settings::get($cf_zname_setting_subdomains_key);
            $resolve_to = "cf." . $rec_name;
            $cf_host_api = new Modules_servershield_CFHostAPI();

            if(!empty($zone_subdomains))
                $sub_arr = explode(",", $zone_subdomains);
            else
                $sub_arr = array();


            $sub_name = $regDomain->extractSubdomainName($rec_name);
            $sub_w_rs2 = $sub_name . ":" . rtrim($resolve_to, '.');
            $sub_index = array_search($sub_w_rs2, $sub_arr);

            if($sub_index !== false) {
                unset($sub_arr[$sub_index]);
                $new_sub_list = implode(",", array_values($sub_arr));

                if(!empty($new_sub_list)) {
                    $zset = $cf_host_api->zone_set($cf_ukey, $zone_name, "cf.www." . $zone_name, $new_sub_list);

                    if($zset) {
                        return $zset;
                    } else {
                        pm_Settings::set($cf_zname_setting_subdomains_key, $new_sub_list);
                        return "success";
                    }
                } else {
                        pm_Settings::set($cf_zname_setting_subdomains_key, false);
                        return "success";
                }
            } else
                return "success";
        }

        public static function getActiveSubdomains($zone_name) {
            $active_subdomains = array();

            $dns_recs = static::getZoneDNSRecs($zone_name);

            foreach($dns_recs as $rec) {
                if(static::isActiveSubdomain($rec["host"], $zone_name)) {
                    $active_subdomains[] = $rec["host"];
                }
            }

            return $active_subdomains;
        }

		public static function isActiveSubdomain($rec_name, $zone_name) {
            $regDomain = new Modules_servershield_regDomain();
	        $cf_zname_setting_subdomains_key = md5(Modules_servershield_PleskAPIHelper::getEffectiveId() . $zone_name . "subdomains");
    	    $zone_subdomains = pm_Settings::get($cf_zname_setting_subdomains_key);
            $resolve_to = "cf." . $rec_name;
        	$plesk_record = static::fetchRecordByName($rec_name);
        	$sub_arr = explode(",",$zone_subdomains);
        	$sub_name = $regDomain->extractSubdomainName($rec_name);
            $sub_index = array_search($sub_name . ":" . rtrim($resolve_to, '.'), $sub_arr);
			$cf_active = ($sub_index !== false);
            if(!$cf_active)
                return false;
            if(is_array($plesk_record) && !empty($plesk_record) && (count($plesk_record) == 1) ){
                $subdomain_rec = $plesk_record[0];

                if(strpos($subdomain_rec["val"], "cdn.cloudflare.net")) {
                    $rs2_rec = static::fetchRecordByName($resolve_to);
                    return (is_array($rs2_rec) && !empty($rs2_rec));
                } else {
                    return false;
                }
            } else {
                return false;
            }
    	}

        public static function getEffectiveId() {
            $user_id = 0;

            if(pm_Session::getClient()->isAdmin()) {
                if(pm_Session::isImpersonated() && isset($_GET["simple"]) && !isset($_GET["context"]))
                    $user_id = pm_Session::getImpersonatedClientId();
                else
                    $user_id = pm_Session::getClient()->getId();
            } else {
                $user_id = pm_Session::getClient()->getId();
            }

            Modules_servershield_PleskAPIHelper::logMessage($user_id, "User ID: ");

            return $user_id;
        }

//DATABASE

        public static function getServerDomains() {
            $conn = pm_Bootstrap::getDbAdapter();
            $result = $conn->query("SELECT smb_users.ownerId AS id, smb_users.contactName, domains.name FROM domains JOIN smb_users ON smb_users.ownerId = domains.cl_id");
            $domains = $result->fetchAll();
            $reg_domain = new Modules_servershield_regDomain();

            $valid_domains = array();

            foreach($domains as $d) {
                if($d["name"] == $reg_domain->getRegisteredDomain($d["name"])) {
                    $valid_domains[] = $d;
                }
            }

            return $valid_domains;
        }

		public static function getZoneDNSRecs($zone_name, $overrideID = NULL) {
			$conn = pm_Bootstrap::getDbAdapter();

            if(is_int($overrideID) && $overrideID > 0)
                $user_id = $overrideID;
            else
                $user_id = Modules_servershield_PleskAPIHelper::getEffectiveId();

			$zone_string = "%" . $zone_name . ".";
			$result = $conn->query("SELECT domains.id AS domain_id, domains.name AS domain_name, dns_recs.* FROM dns_recs
									JOIN domains ON dns_recs.dns_zone_id = domains.dns_zone_id
									JOIN dns_zone ON dns_recs.dns_zone_id = dns_zone.id
									WHERE domains.cl_id = ?
									AND dns_recs.host LIKE ?
									AND dns_recs.type IN ('A','AAAA','CNAME')
									AND dns_zone.status = 0
                                    GROUP BY dns_recs.host",
									array($user_id, $zone_string));
			$records = $result->fetchAll();

			$validRecords = array();
			foreach($records as $rec) {
					if(!preg_match(static::$invalid_subdomain_regex, $rec["host"]) || ($rec["host"] == $zone_name . "."))
						$validRecords[] = $rec;
			}
			return $validRecords;
		}

  		public static function fetchRecordByName($rec_name, $overrideID = NULL) {
            $conn = pm_Bootstrap::getDbAdapter();

            if(is_int($overrideID) && $overrideID > 0)
                $user_id = $overrideID;
            else
                $user_id = Modules_servershield_PleskAPIHelper::getEffectiveId();

        	$rec_host = (substr($rec_name, -1) == ".") ? $rec_name : $rec_name . ".";

        	$result = $conn->query("SELECT domains.id AS domain_id, domains.name AS domain_name, dns_recs.* FROM dns_recs
        							JOIN domains ON dns_recs.dns_zone_id = domains.dns_zone_id
        							JOIN dns_zone ON dns_recs.dns_zone_id = dns_zone.id
        							WHERE domains.cl_id = ?
        							AND dns_recs.host = ?
        							AND dns_recs.type IN ('A','AAAA','CNAME')
        							AND dns_zone.status = 0",
        							array($user_id, $rec_host));
        	return $result->fetchAll();
    	}

//LOGGING STUFF

        public static function logMessage($content, $pre_pend = false) {
            $message = print_r($content, true);

            if($pre_pend)
                $message = $pre_pend . $message;

            error_log("[SERVERSHIELD] " . $message);
        }

	}