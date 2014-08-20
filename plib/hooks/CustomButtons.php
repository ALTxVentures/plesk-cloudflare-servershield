<?php

class Modules_servershield_CustomButtons extends pm_Hook_CustomButtons {
    public function getButtons() {
        return [[
            'place' => self::PLACE_COMMON,
            'title' => 'ServerShield by CloudFlare',
            'description' => 'ServerShield by CloudFlare defends all your websites against online threats while making them load lightning fast',
            'icon' => pm_Context::getBaseUrl() . 'icon.png',
            'link' => pm_Context::getActionUrl('index') . '?simple=1',
        ]];
    }
}