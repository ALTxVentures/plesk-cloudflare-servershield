<?php
	pm_Context::init("servershield");
	$cf_host_api = new Modules_servershield_CFHostAPI();
	$version_info = $cf_host_api->getServerShieldVersion();

	if($version_info->success) {
		if( floatval(pm_Context::getModuleInfo()->version) < floatval($version_info->result->version))
			Modules_servershield_PleskAPIHelper::updateServerShield();
	}