<?php

/**
 * Teamwork Application
 *
 * This application provides a visual UI for rendering the Teamwork sprint
 * burndown chart on a weekly basis.
 *
 * @changes
 *  1.0.2       Switch to activedial.js instead of activebar.js to fix the high cpu usage on MacBook.
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
    'Version' => '1.0.2',
    'Author' => "Tim Gunter",
    'AuthorEmail' => 'tim@vanillaforums.com',
    'AuthorUrl' => 'http://about.me/timgunter',
    'License' => 'Proprietary',
    'RequiredApplications' => [
        'vfconsole' => '2.3'
    ],
    'RegisterPermissions' => [
        'vfteamwork.burndown.view',
        'vfteamwork.burndown.manage'
    ]
];
