<?php
/**
 * @copyright Copyright (c) 2022 Good Work
 */

namespace simplygoodwork\nag\models;

use craft\base\Model;

class SettingsModel extends Model
{
    public bool $onlyAlertForNewIp = false;
    public bool $alertAdmins = true;
    public bool $alertRestrictedGroups = false;
    public array $alertUserGroups = [];
    public string $alertFooter = '';
    public string $geolocationApiKey = '';
}