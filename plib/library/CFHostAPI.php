<?php
    class Modules_servershield_CFHostAPI	{
 		private $response = NULL; //where we store the most current response

		function user_create($email, $passwd)	{ //create a new user
			if(!filter_var($email, FILTER_VALIDATE_EMAIL))	{ return NULL; }
			$params = array('cloudflare_email' => $email, 'cloudflare_pass' => $passwd);

			if($this->_call_api('user_create', $params)) {
				return $this->response['response']; //return the response of the response
			} else {
				return $this->response['msg'];
			}
		}

		function zone_set($user_key, $zone_name, $resolve_to, $subdomains) { //set or edit a zone
			$params = array('user_key' => $user_key, 'zone_name' => $zone_name, 'resolve_to' => $resolve_to, 'subdomains'=>$subdomains);

            if( !($this->_call_api('zone_set', $params)) ) {
                if(!empty($this->response["msg"]))
                    return $this->response['msg'];
                else
                    return "CloudFlare API connection error";
            } else {
                return FALSE;
            }
		}

		function user_lookup($email=NULL, $unique=NULL){ //lookup for a user to get host_key
			if (isset($email) && filter_var($email, FILTER_VALIDATE_EMAIL))	{ //make sure the email is vaild
				$params['cloudflare_email'] =$email;
			}
			elseif(isset($unique))	{ //if the email is not vaild lets hope they have a unique
				$params['unique'] = $unique;
			} else {
				return NULL;
			}

			if($this->_call_api('user_lookup', $params)) {
				return $this->response['response']; //return the response of the response
			} else {
				return  FALSE;
			}
		}

		function zone_lookup($user_key, $zone_name) {
			$params = array('user_key' => $user_key, 'zone_name' => $zone_name);
			if($this->_call_api('zone_lookup', $params))	{
				return $this->response['response']; //return the response of the response
			} else {
				return FALSE;
			}
        }

		function zone_delete($user_key, $zone_name) {
			$params = array('user_key' => $user_key, 'zone_name' => $zone_name);
			if($this->_call_api('zone_delete', $params))	{
				return $this->response['response'];
			} else {
				return $this->response['msg'];
			}
        }

        function zone_list($zone_name) {
            $params = array('zone_name' => $zone_name, 'zone_status' => 'V');
            if($this->_call_api('zone_list', $params)) {
                return $this->response['response'];
            }
            else{
                return FALSE;
            }
        }

        function reseller_plan_list()
        {
            if($this->_call_api('reseller_plan_list', array()))
            {
                $resp = $this->response['response'];
                return $resp['objs'];
            }
            else
            {
                return $this->response['msg'];
            }
        }

        function reseller_sub_new($user_key, $zone_name, $plan_tag)
        {
            $params = array("user_key" => $user_key, "zone_name" => $zone_name, "plan_tag" => $plan_tag);

            if($this->_call_api('reseller_sub_new', $params))
            {
                return $this->response['response'];
            }
            else
                return $this->response['msg'];

        }

        function reseller_sub_cancel($user_key, $zone_name, $plan_tag, $sub_id)
        {
            $params = array("user_key" => $user_key, "zone_name" => $zone_name, "plan_tag" => $plan_tag, "sub_id" => $sub_id);

            if( $this->_call_api("reseller_sub_cancel", $params) )
            {
                return $this->response["response"];
            }
            else
                return $this->response["msg"];
        }

        function getResponse(){
            return $this->response;
        }

        function _call_api($act, $params) {
            $params['act'] = $act;
            $params["host_key"] = pm_Settings::get("cf_host_api_key");
            $this->response = json_decode($this->curlCall("https://api.cloudflare.com/host-gw.html", $params), true);
            Modules_servershield_PleskAPIHelper::logMessage($this->response, "CloudFlare API: ");

            if($this->response['result'] == 'success')  {
                return TRUE;
            } else {
                return FALSE;
            }
        }

        private function curlCall($url, $params) {
            $args = $params;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $result = curl_exec($ch);
            curl_close($ch);

            return $result;
        }

        //newer API calls

        function getServerShieldVersion() {
            $url = "https://partner-api.cloudflare.com/v4/integration/plesk";
            $auth_key_header = "X-Auth-Host-Key:" . pm_Settings::get("cf_host_api_key");
            $email_header = "X-Auth-Host-Email:" . pm_Settings::get("cf_host_email");
            $xheaders = array($auth_key_header, $email_header);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $xheaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $result = curl_exec($ch);
            curl_close($ch);
            Modules_servershield_PleskAPIHelper::logMessage($result, "CloudFlare API: ");

            return json_decode($result);
        }

        function activateSTH($email, $zone) {
            $params = array("email" => $email, "target" => $zone);
            $url = "https://partner-api.cloudflare.com/v4/integration/stopthehacker";
            $auth_key_header = "X-Auth-Host-Key:" . pm_Settings::get("cf_host_api_key");
            $email_header = "X-Auth-Host-Email:" . pm_Settings::get("cf_host_email");
            $xheaders = array($auth_key_header, $email_header);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $xheaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $result = curl_exec($ch);
            curl_close($ch);
            Modules_servershield_PleskAPIHelper::logMessage($result, "CloudFlare API: ");

            return json_decode($result);
        }

        function disableSTH($email, $zone) {
            $params = array("email" => $email, "target" => $zone);
            $url = "https://partner-api.cloudflare.com/v4/integration/stopthehacker";
            $auth_key_header = "X-Auth-Host-Key:" . pm_Settings::get("cf_host_api_key");
            $email_header = "X-Auth-Host-Email:" . pm_Settings::get("cf_host_email");
            $xheaders = array($auth_key_header, $email_header);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $xheaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $result = curl_exec($ch);
            curl_close($ch);
            Modules_servershield_PleskAPIHelper::logMessage($result, "CloudFlare API: ");

            return json_decode($result);
        }

        function activateExtension($params) {
            $url = "https://partner-api.cloudflare.com/v4/integration/plesk";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $result = curl_exec($ch);
            curl_close($ch);
            Modules_servershield_PleskAPIHelper::logMessage($result, "CloudFlare API: ");

            return json_decode($result);
        }

        function getCFZone($zone) {
            $filters = array("status" => "active", "zone" => $zone, "limit" => 1);
            $url = "https://partner-api.cloudflare.com/v4/zones?" . http_build_query($filters);
            $auth_key_header = "X-Auth-Host-Key:" . pm_Settings::get("cf_host_api_key");
            $email_header = "X-Auth-Host-Email:" . pm_Settings::get("cf_host_email");
            $xheaders = array($auth_key_header, $email_header);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $xheaders);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $result = curl_exec($ch);
            curl_close($ch);
            Modules_servershield_PleskAPIHelper::logMessage($result, "CloudFlare API: ");

            return json_decode($result);
        }

        function getCFZoneSettings($zone) {
            $get_zone = $this->getCFZone($zone);

            if($get_zone->success) {
                $zone_id = $get_zone->result[0]->id;
                $url = "https://partner-api.cloudflare.com/v4/zones/" . $zone_id . "/settings";
                $auth_key_header = "X-Auth-Host-Key:" . pm_Settings::get("cf_host_api_key");
                $email_header = "X-Auth-Host-Email:" . pm_Settings::get("cf_host_email");
                $xheaders = array($auth_key_header, $email_header);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $xheaders);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

                $result = curl_exec($ch);
                curl_close($ch);
                Modules_servershield_PleskAPIHelper::logMessage($result, "CloudFlare API: ");

                return json_decode($result);
            } else {
                return $get_zone;
            }
        }

        function putCFZoneSettings($zone, $settings = array()) {
            $get_zone = $this->getCFZone($zone);
            $params = array("settings" => $settings);

            if($get_zone->success) {
                $zone_id = $get_zone->result[0]->id;
                $url = "https://partner-api.cloudflare.com/v4/zones/" . $zone_id . "/settings";
                $auth_key_header = "X-Auth-Host-Key:" . pm_Settings::get("cf_host_api_key");
                $email_header = "X-Auth-Host-Email:" . pm_Settings::get("cf_host_email");
                $xheaders = array($auth_key_header, $email_header);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
                curl_setopt($ch, CURLOPT_HTTPHEADER, $xheaders);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

                $result = curl_exec($ch);
                curl_close($ch);
                Modules_servershield_PleskAPIHelper::logMessage($result, "CloudFlare API: ");

                return json_decode($result);
            } else {
                return $get_zone;
            }
        }

        function getStats($zone, $user_id) {
            $cf_uemail_setting_key = md5($user_id . "CF-USER-EMAIL");
            $cf_apikey_setting_key = md5($user_id . "CF-API-KEY");
            $cf_api_key = pm_Settings::get($cf_apikey_setting_key);
            $cf_email = pm_Settings::get($cf_uemail_setting_key);
            $url = "https://www.cloudflare.com/api_json.html";

            $params = array(
                    "a" => "stats",
                    "tkn" => $cf_api_key,
                    "email" => $cf_email,
                    "z" => $zone
                    );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $result = curl_exec($ch);
            curl_close($ch);

            return json_decode($result);
        }


        function purgeCFCache($zone, $user_id) {
            $cf_uemail_setting_key = md5($user_id . "CF-USER-EMAIL");
            $cf_apikey_setting_key = md5($user_id . "CF-API-KEY");
            $cf_api_key = pm_Settings::get($cf_apikey_setting_key);
            $cf_email = pm_Settings::get($cf_uemail_setting_key);
            $url = "https://www.cloudflare.com/api_json.html";

            $params = array(
                    "a" => "fpurge_ts",
                    "tkn" => $cf_api_key,
                    "email" => $cf_email,
                    "z" => $zone,
                    "v" => 1);


            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

            $result = curl_exec($ch);
            curl_close($ch);
            Modules_servershield_PleskAPIHelper::logMessage($result, "CloudFlare API: ");
            return json_decode($result);
        }
    }