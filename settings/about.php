<?php

/**
 * Teamwork Application
 *
 * This application provides a visual UI for rendering the Teamwork sprint
 * burndown chart on a weekly basis.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @copyright 2010-2014 Vanilla Forums Inc
 * @license Proprietary
 * @package infrastructure
 * @subpackage vfteamwork
 * @since 1.0
 */

$ApplicationInfo['vfteamwork'] = [
    'Name' => 'Vanilla Hosting - Teamwork (ui)',
    'Description' => "Console plug-in application that provides a UI for Teamwork burndown.",
    'Version' => '1.0',
    'Author' => "Tim Gunter",
    'AuthorEmail' => 'tim@vanillaforums.com',
    'AuthorUrl' => 'http://about.me/timgunter',
    'License' => 'Proprietary',
    'RequiredApplications' => [
        'vfconsole' => '1.0'
    ],
    'RegisterPermissions' => [
        'vfteamwork.burndown.view',
        'vfteamwork.burndown.manage'
    ]
];
