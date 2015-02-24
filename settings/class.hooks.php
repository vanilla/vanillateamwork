<?php

/**
 * @copyright 2010-2014 Vanilla Forums Inc
 * @license Proprietary
 */

/**
 * VanillaTeamwork Application Hooks
 *
 * Event hooks for VanillaTeamwork application.
 *
 * @author Tim Gunter <tim@vanillaforums.com>
 * @package infrastructure
 * @subpackage vfteamwork
 * @since 1.0
 */
class VanillaTeamworkHooks implements Gdn_IPlugin {

    /**
     * Console menu items
     *
     * @param VanillaConsoleController $sender
     */
    public function VanillaConsoleHooks_SettingsMenu_Handler($sender) {
        $menu = $sender->EventArguments['Menu'];

        // TEAMWORK

        // Add group
        $menu->addItem('Teamwork', T('Teamwork'), 'teamwork', array('class' => 'Teamwork'));
        // Add group URL
        $menu->addLink('Teamwork', false, 'teamwork', 'vfteamwork.burndown.view');

        // Items
        $menu->addLink('Teamwork', 'Burndown', 'teamwork/burndown', 'vfteamwork.burndown.view');
    }

    /**
     *
     * @param type $sender
     */
    public function AssetModel_VfconsoleCss_Handler($sender) {
        $sender->addCssFile('vfteamwork.css', 'vfteamwork', array('Sort' => -10));
    }

    public function structure() {

    }

    public function setup() {
        $this->structure();
    }

}
