<?php

$cf_host_api = new Modules_servershield_CFHostAPI();
$zones = Modules_servershield_PleskAPIHelper::getServerDomains();
$stats = array();
$stats["requests"] = 0;
$stats["bandwidth"] = 0;
$stats["threats"] = 0;

$thr = 0;
$bw = 0;
$req = 0;

foreach($zones as $zone) {
	$status = pm_Settings::get(md5($zone["id"] . $zone["name"]));

	if($status == "on") {
		$zone_stats = $cf_host_api->getStats($zone["name"], $zone["id"]);

		if(is_object($zone_stats)) {
			$thr += intval($zone_stats->response->result->objs[0]->trafficBreakdown->pageviews->threat);
			$bw += floor(floatval($zone_stats->response->result->objs[0]->bandwidthServed->cloudflare) / 1000);
			$req += intval($zone_stats->response->result->objs[0]->requestsServed->cloudflare);
		}
	}
}

pm_Settings::set("cf_serverstats_requests", $req );
pm_Settings::set("cf_serverstats_bandwidth", $bw );
pm_Settings::set("cf_serverstats_threats", $thr );