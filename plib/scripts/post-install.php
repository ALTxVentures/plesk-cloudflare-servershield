<?php
	pm_Context::init("servershield");

	$update_task_id = pm_Settings::get('servershield_update_task');

	if(!$update_task_id) {
		$update_task_id = new pm_Scheduler_Task();
		$update_task_id->setSchedule(pm_Scheduler::$EVERY_WEEK);
		$update_task_id->setCmd('update-servershield.php');
		pm_Scheduler::getInstance()->putTask($update_task_id);
		pm_Settings::set('servershield_update_task', $update_task_id->getId());
	}

	$aggregate_stats_task_id = pm_Settings::get("servershield_aggregate_stats_task");

	if(!$aggregate_stats_task_id) {
		$aggregate_stats_task_id = new pm_Scheduler_Task();
		$aggregate_stats_task_id->setSchedule(pm_Scheduler::$EVERY_DAY);
		$aggregate_stats_task_id->setCmd('aggregate-stats.php');
		pm_Scheduler::getInstance()->putTask($aggregate_stats_task_id);
		pm_Settings::set('servershield_aggregate_stats_task', $aggregate_stats_task_id->getId());
	}

	if(	pm_Settings::get("cloudflare_zones") === null )
		pm_Settings::set("cloudflare_zones",0);

	if(	intval(pm_Settings::get("servershield_accounts")) <= 0 )
		pm_Settings::set("servershield_accounts",0);

	if(pm_Settings::get("cf_serverstats_requests") === null)
		pm_Settings::set("cf_serverstats_requests", 0);

	if(pm_Settings::get("cf_serverstats_bandwidth") === null)
		pm_Settings::set("cf_serverstats_bandwidth", 0);

	if(pm_Settings::get("cf_serverstats_threats") === null)
		pm_Settings::set("cf_serverstats_threats", 0);