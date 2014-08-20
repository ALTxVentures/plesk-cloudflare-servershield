<?php

class Modules_servershield_EventListener implements EventListener {

    public function handleEvent($objectType, $objectId, $action, $oldValues, $newValues) {
    	switch ($action) {
    		case "domain_owner_change":
    			$domain_name = $oldValues["Domain Name"];
    			$uid = $this->getUserId($oldValues["Login Name"]);
    			$new_uid = $this->getUserId($newValues["Login Name"]);
    			$extra_zones = $this->getAssociatedSitesToSubscription($objectId);

				$this->revertDNSandZoneDeleteCloudFlare($uid, $new_uid, $domain_name);

				if(!empty($extra_zones)) {
					foreach($extra_zones as $zone) {
						$this->revertDNSandZoneDeleteCloudFlare($uid, $new_uid, $zone["name"]);
					}
				}

    		break;

    		case "domain_delete":
    		case "site_delete":
    			$this->onlyZoneDeleteCloudFlare($this->getUserId($oldValues["Login Name"]), $oldValues["Domain Name"]);
    		break;
    	}

    }

    private function getDomainId($domain_name) {
		$conn = pm_Bootstrap::getDbAdapter();
		$query = $conn->query("SELECT id FROM domains WHERE name = ?", array($domain_name));
		$result = $query->fetch();
		return $result["id"];
	}

    private function getUserId($user_name) {
		$conn = pm_Bootstrap::getDbAdapter();
		$query = $conn->query("SELECT id FROM clients WHERE login = ?", array($user_name));
		$result = $query->fetch();
		return $result["id"];
    }

    private function getAssociatedSitesToSubscription($domain_id) {
		$conn = pm_Bootstrap::getDbAdapter();
		$query = $conn->query("SELECT name FROM domains WHERE webspace_id = ?", array($domain_id));
		$result = $query->fetchAll();
		return $result;
    }

    private function onlyZoneDeleteCloudFlare($user_id, $zone_name) {
		pm_Context::init('servershield');
		$cf_ukey_setting_key = md5($user_id . "CF-USER-KEY");
		$cf_ukey = pm_Settings::get($cf_ukey_setting_key);
		$cf_host_api = new Modules_servershield_CFHostAPI();
		$cf_num_zones = intval(pm_Settings::get("cloudflare_zones"));
		$cf_user_zones_key = md5($user_id . "CF-ZONES");
		$cf_user_zones = intval(pm_Settings::get($cf_user_zones_key));
        $cf_zname_setting_key = md5($user_id . $zone_name);
	    $cf_zname_setting_subdomains_key = md5($user_id . $zone_name . "subdomains");
	    $cf_uemail_setting_key = md5($user_id . "CF-USER-EMAIL");
		$cf_uemail = pm_Settings::get($cf_uemail_setting_key);
		$cf_sth_zone_key = md5($user_id . $zone_name . "STH");
		$cf_sth_status = pm_Settings::get($cf_sth_zone_key);

		if($cf_sth_status == "on"){
			$sth_result = $cf_host_api->disableSTH($cf_uemail, $zone_name);

			if($sth_result->success) {
				pm_Settings::set($cf_sth_zone_key, false);
				Modules_servershield_PleskAPIHelper::logMessage("STH DISABLED", "onlyZoneDeleteCloudFlare success: ");

			}
		}

        if(pm_Settings::get($cf_zname_setting_key) == "on") {
        	$zdelete = $cf_host_api->zone_delete($cf_ukey, $zone_name);

        	if(is_array($zdelete)) {
        		pm_Settings::set($cf_zname_setting_key, false);
        		pm_Settings::set($cf_zname_setting_subdomains_key, false);
        		pm_Settings::set("cloudflare_zones", $cf_num_zones - 1);
        		pm_Settings::set($cf_user_zones_key, $cf_user_zones - 1);
        		Modules_servershield_PleskAPIHelper::logMessage($zone_name, "onlyZoneDeleteCloudFlare success: ");
        		return true;
        	} else {
        		Modules_servershield_PleskAPIHelper::logMessage($zdelete, "onlyZoneDeleteCloudFlare failed: ");
        		return false;
        	}
        } else {
        	Modules_servershield_PleskAPIHelper::logMessage("Zone not using CloudFlare", "onlyZoneDeleteCloudFlare: ");
        	return false;
        }
    }

    private function revertDNSandZoneDeleteCloudFlare($user_id, $new_user_id, $domain) {
    	pm_Context::init("servershield");

    	if($this->onlyZoneDeleteCloudFlare($user_id, $domain)) {
	    	$dns_records = $this->getZoneDNSRecs($new_user_id, $domain);
	    	Modules_servershield_PleskAPIHelper::logMessage($dns_records);
	    	$new_records = array();

	    	foreach($dns_records as $rec) {
	    		$this->deleteCFCNAME($new_user_id, $rec["host"]);
	    		$new_records = array_merge($new_records, $this->deleteCFResolveTo($new_user_id, $rec["host"]));
	    	}

	    	foreach($new_records as $new_rec) {
    			Modules_servershield_PleskAPIHelper::logMessage($new_rec);
    			$this->createRecord($new_rec["domain_id"], $new_rec);
    		}
	    }
    }

    private function deleteCFResolveTo($user_id, $rec_name) {
    	pm_Context::init("servershield");
    	$current_records = $this->fetchRecordByName($user_id, "cf." . $rec_name);
    	Modules_servershield_PleskAPIHelper::logMessage($rec_name, "Deleting RESOLVE TO: ");
		$regDomain = new Modules_servershield_regDomain();
		$new_records = array();

    	if(is_array($current_records) && !empty($current_records)) {
    		foreach($current_records as $rec) {
                $domain_id = $rec["domain_id"];
                $rec_type = $rec["type"];
                $rec_value = $rec["val"];
                $rec_host = $regDomain->extractSubdomainName($rec_name, $rec["domain_name"]);
                $new_records[] = array("domain_id" => $rec["domain_id"], "type" => $rec_type, "name" => $rec_host, "value" => $rec_value);
                Modules_servershield_PleskAPIHelper::logMessage($rec);

    			$this->deleteRecord($rec["id"]);
    		}
    	}
    	return $new_records;
    }

    private function deleteCFCNAME($user_id, $rec_name) {
    	pm_Context::init("servershield");
        $current_records = $this->fetchRecordByName($user_id, $rec_name);
        Modules_servershield_PleskAPIHelper::logMessage($rec_name, "Deleting CF CNAME: ");

        if(is_array($current_records) && !empty($current_records)) {
            foreach($current_records as $rec) {
                if(strpos($rec["val"], "cdn.cloudflare.net")) {
                    $this->deleteRecord($rec["id"]);
                }
            }
        }
    }


	private function createRecord($zone_id, $content) {
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

		Modules_servershield_PleskAPIHelper::logMessage($pkt->asXML(), "CREATING RECORD: ");
		$api_response = pm_ApiRpc::getService()->call($pkt);
		Modules_servershield_PleskAPIHelper::logMessage($api_response->asXML(), "RECORD CREATED: ");
		return json_decode(json_encode($api_response->dns->add_rec->result));
	}

	private function deleteRecord($rec_id) {
    //Deleting a record on Plesk's API isn't really considered deleted until execution of a particular script ends.
    //Rolling back a change will require a separate execution thread.
		$pkt = new SimpleXMLElement('<dns></dns>');
		$pkt->addChild("del_rec");
		$pkt->del_rec->addChild("filter");
		$pkt->del_rec->filter->addChild("id");
		$pkt->del_rec->filter->id = $rec_id;
		Modules_servershield_PleskAPIHelper::logMessage($pkt->asXML(), "DELETING RECORD: ");
		$api_response = pm_ApiRpc::getService()->call($pkt);
		Modules_servershield_PleskAPIHelper::logMessage($api_response->asXML(), "RECORD DELETED: ");
		return json_decode(json_encode($api_response->dns->del_rec->result));
	}

   	private function fetchRecordByName($user_id, $rec_name) {
    	pm_Context::init("servershield");
	    $conn = pm_Bootstrap::getDbAdapter();
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

	private function getZoneDNSRecs($user_id, $zone_name) {
    	pm_Context::init("servershield");
		$conn = pm_Bootstrap::getDbAdapter();
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
				if(!preg_match(Modules_servershield_PleskAPIHelper::$invalid_subdomain_regex, $rec["host"])) {
					if($rec["host"] != $rec["domain_name"] . ".")
						$validRecords[] = $rec;
				}
		}
		return $validRecords;
	}

}

return new Modules_servershield_EventListener();