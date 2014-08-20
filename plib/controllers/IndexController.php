<?php

class IndexController extends pm_Controller_Action {

    public function init() {
    	parent::init();
    	$this->view->pageTitle = '<img style="padding-bottom:10px;width:25%;height:auto" src="/modules/servershield/cf-logo-h-rgb.png" />';

    	$this->view->tabs = array(
            array(
                "title" => "Settings",
                "action" => "settings"
            ),
            array(
                "title" => "Server",
                "action" => "index"
            )
    	);

        if( !pm_Settings::get("servershield_first_visit_done") && !pm_Settings::get("cf_host_api_key") && !pm_Settings::get("cf_host_email") ) {
            pm_Settings::set("servershield_first_visit_done",true);
            $this->activate();
        }

        if((isset($_GET["simple"]) || !pm_Session::getClient()->isAdmin()) && !isset($_GET["context"]))
            $this->view->simple = true;
        else
            $this->view->simple = false;

        $this->view->ucreate_message = NULL;
        $this->view->zone = NULL;
        $this->view->key_saved = false;
        $this->view->host_key_message = NULL;
    }

    public function indexAction() {
        if(pm_Session::getClient()->isAdmin()) {
            if(pm_Settings::get("cf_host_api_key")) {
                if($this->view->simple)
                    $this->_forward("zones");
                else
                    $this->view->content = "server_dashboard";
            } else
                $this->_forward("settings");
        } else if(pm_Session::getClient()->isClient() || pm_Session::isImpersonated()) {
            if(!pm_Settings::get("cf_host_api_key")) {
                $this->activate();
            }

            if(isset($_GET["zone"]) && $_GET["zone"]) {
                $cf_zname_setting_key = md5(Modules_servershield_PleskAPIHelper::getEffectiveId() . $_GET["zone"]);
                $zone_status = pm_Settings::get($cf_zname_setting_key);
                $zone_verify = $this->fetchDomainByName($_GET["zone"]);

                if($zone_status == "on" && is_array($zone_verify[0])) {
                    $this->view->content = "zone_settings";
                    $this->view->zone = $zone_verify[0];
                    $zone_settings = array();

                    if(isset($_POST["security_level"])) {
                        $zone_settings[] = array( "name" => "security_level", "value" => $_POST["security_level"]);
                    }

                    if(isset($_POST["always_online"])) {
                        $zone_settings[] = array("name" => "always_online", "value" => $_POST["always_online"]);
                    }

                    if(isset($_POST["development_mode"])) {
                        $zone_settings[] = array("name" => "development_mode", "value" => $_POST["development_mode"]);
                    }

                    $cf_host_api = new Modules_servershield_CFHostAPI();
                    $result = $cf_host_api->putCFZoneSettings($_GET["zone"], $zone_settings);
                } else {
                    $this->loginOrZones();
                }
             } else {
                $this->loginOrZones();
            }
        }
    }

    public function settingsAction() {
        if(pm_Session::getClient()->isAdmin())
            $this->adminSettingsPage();
        else
            $this->_forward("index");
    }

    public function zonesAction() {
        if(pm_Settings::get("cf_host_api_key")) {
            if(isset($_GET["zone"])) {
                $cf_zname_setting_key = md5(Modules_servershield_PleskAPIHelper::getEffectiveId() . $_GET["zone"]);
                $zone_status = pm_Settings::get($cf_zname_setting_key);
                $zone_verify = $this->fetchDomainByName($_GET["zone"]);


                if($zone_status == "on" && is_array($zone_verify[0])) {
                    $this->view->zone = $zone_verify[0];
                    $this->view->content = "zone_settings";

                    $zone_settings = array();

                    if(isset($_POST["security_level"])) {
                        $zone_settings[] = array( "name" => "security_level", "value" => $_POST["security_level"]);
                    }

                    if(isset($_POST["always_online"])) {
                        $zone_settings[] = array("name" => "always_online", "value" => $_POST["always_online"]);
                    }

                    if(isset($_POST["development_mode"])) {
                        $zone_settings[] = array("name" => "development_mode", "value" => $_POST["development_mode"]);
                    }

                    $cf_host_api = new Modules_servershield_CFHostAPI();
                    $result = $cf_host_api->putCFZoneSettings($_GET["zone"], $zone_settings);

                } else {
                    $this->loginOrZones();
                }
            } else
                $this->loginOrZones();
        }
        else
            $this->_forward("settings");
    }


//Prcoess functions

    private function loginOrZones() {
        $user_id = Modules_servershield_PleskAPIHelper::getEffectiveId();
        $cf_uemail_setting_key = md5($user_id . "CF-USER-EMAIL");
        $cf_ukey_setting_key = md5($user_id . "CF-USER-KEY");
        $cf_ukey = pm_Settings::get($cf_ukey_setting_key);
        $cf_apikey_setting_key = md5($user_id . "CF-API-KEY");
        $cf_user_zones_key = md5($user_id . "CF-ZONES");
        $cf_user_zones = intval(pm_Settings::get($cf_user_zones_key));
        $servershield_accounts = intval(pm_Settings::get("servershield_accounts"));

        if(pm_Session::getClient()->isAdmin() && ($this->view->simple == false)) {
            $zones = $this->fetchUserDomains(true);
        }
        else
            $zones = $this->fetchUserDomains(false);

        if($cf_ukey) {
            if(isset($_GET["clobber"]) && ($cf_user_zones <= 0)) {
                pm_Settings::set($cf_uemail_setting_key, false);
                pm_Settings::set($cf_ukey_setting_key, false);
                pm_Settings::set($cf_apikey_setting_key, false);
                pm_Settings::set($cf_user_zones_key, false);
                pm_Settings::set("servershield_accounts", $servershield_accounts - 1);

                foreach($zones["valid_domains"] as $zone) {
                    $cf_zname_setting_key = md5($user_id . $zone["name"]);
                    pm_Settings::set($cf_zname_setting_key, "off");
                }

                $this->view->content = "user_create";
            } else {
                $this->view->user_domains = $zones;
                $this->view->content = "zones";
            }
        } else {
            if(isset($_POST["cfemail"]) && isset($_POST["cfpass"])) {

                if(filter_var($_POST["cfemail"], FILTER_VALIDATE_EMAIL)) {
                    $cf_host_api = new Modules_servershield_CFHostAPI();
                    $ucreate = $cf_host_api->user_create($_POST["cfemail"], $_POST["cfpass"]);

                    if(is_array($ucreate)) {
                        pm_Settings::set($cf_ukey_setting_key, $ucreate["user_key"]);
                        pm_Settings::set($cf_apikey_setting_key, $ucreate["user_api_key"]);
                        pm_Settings::set($cf_uemail_setting_key, $_POST["cfemail"]);
                        pm_Settings::set("servershield_accounts", $servershield_accounts + 1);

                        if(pm_Session::getClient()->isClient())
                            $this->_forward("index");
                        else {
                            $this->view->user_domains = $zones;
                            $this->view->content = "zones";
                        }
                    } else {
                        $this->view->content = "user_create";
                        $this->view->ucreate_message = $ucreate;
                    }
                } else {
                    $this->view->content = "user_create";
                    $this->view->ucreate_message = "Invalid e-mail";
                }
            } else {
                $this->view->content = "user_create";
            }
        }
    }


    private function adminSettingsPage(){
        $cf_host_api = new Modules_servershield_CFHostAPI();

        if(pm_Settings::get("cf_nonhost_activated")) {
            if( isset($_GET["clobber"]) && (intval(pm_Settings::get("cloudflare_zones")) <= 0) &&  (intval(pm_Settings::get("servershield_accounts")) <= 0)) {
                pm_Settings::set("cf_nonhost_activated", false);
                pm_Settings::set("cf_host_email", false);
                pm_Settings::set("cf_host_api_key", false);
                $this->view->content = "key_form";
            }
            else
                $this->view->content = "activated";
        } else {
            if(isset($_POST["cfhostapikey"]) && isset($_POST["cfhostemail"])) {
                pm_Settings::set("cf_host_email", $_POST["cfhostemail"]);
                pm_Settings::set("cf_host_api_key", $_POST["cfhostapikey"]);
                $auth_check = $cf_host_api->getServerShieldVersion();

                if($auth_check->success) {
                    $this->view->key_saved = true;
                    $this->view->content = "key_form";
                }
                else {
                    pm_Settings::set("cf_host_email", false);
                    pm_Settings::set("cf_host_api_key", false);
                    $this->view->key_saved = false;
                    $this->view->host_key_message = $auth_check->errors[0]->message;
                    $this->view->content = "key_form";
                }
            } else if (isset($_GET["activate"])){
                $this->activate();
            } else {
                $this->view->content = "key_form";
            }
        }
    }

    private function activate() {
        $cf_host_api = new Modules_servershield_CFHostAPI();
        $server_info = json_decode(json_encode(Modules_servershield_PleskAPIHelper::getServerInfo()));
        $generated_host_email = md5($server_info->server_guid . time()) . '@plesk.cloudflare.com';
        $host_name = $server_info->server_name;
        $params = array("email" => $generated_host_email, "server_host" => $host_name);
        $cf_host_api = new Modules_servershield_CFHostAPI();
        $activate = $cf_host_api->activateExtension(json_encode($params));

        if($activate->success) {
            pm_Settings::set("cf_nonhost_activated", TRUE);
            pm_Settings::set("cf_host_api_key", $activate->result->api_key);
            pm_Settings::set("cf_host_email", $activate->result->email);
            $this->view->content = "activated";
        } else {
            $this->view->key_saved = false;
            $this->view->host_key_message = $activate->errors[0]->message;
            $this->view->content = "key_form";
        }
    }

    private function fetchUserDomains($override_user = false) {
        $conn = pm_Bootstrap::getDbAdapter();
        $user_id = ($override_user) ? pm_Session::getClient()->getId() : Modules_servershield_PleskAPIHelper::getEffectiveId();
        $db_result = $conn->query("SELECT * FROM domains WHERE cl_id = ?", array($user_id));
        $plesk_domains = $db_result->fetchAll();
        $reg_dom = new Modules_servershield_regDomain();
        $result = array();
        $result["valid_domains"] = array();
        $result["total_domains"] = array();

        foreach($plesk_domains as $domain) {
            $registered_domain = $reg_dom->getRegisteredDomain($domain["name"]);
            if($registered_domain == $domain["name"]) {
                $result["valid_domains"][] = $domain;
            }

            $result["total_domains"][] = $domain;
        }

        return $result;
    }

    private function fetchDomainByName($zone_name) {
        if($zone_name) {
                $conn = pm_Bootstrap::getDbAdapter();
                $result = $conn->query("SELECT id, name FROM domains WHERE name = ? AND cl_id = ? LIMIT 1", array($zone_name,Modules_servershield_PleskAPIHelper::getEffectiveId()));
                return $result->fetchAll();
        } else
            return false;
    }
}