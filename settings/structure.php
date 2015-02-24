<?php

/**
 * @copyright 2010-2014 Vanilla Forums Inc
 * @license Proprietary
 */

if (!defined('APPLICATION')) {
    exit();
}

/**
 * vfteamwork Application Structure
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package infrastructure
 * @subpackage vfteamwork
 * @since 1.0
 */

if (!isset($Drop)) {
    $Drop = false;
}

if (!isset($Explicit)) {
    $Explicit = true;
}

$structure = Gdn::structure();
$sql = Gdn::sql();
$px = $structure->databasePrefix();

// Create Sites Permissions
$permissionModel = Gdn::permissionModel();
$permissionModel->Database = $Database;
$permissionModel->SQL = $SQL;

// Define some permissions for Teamwork management
$permissionModel->define([
    'vfteamwork.burndown.view' => 0,
    'vfteamwork.burndown.manage' => 0
]);
