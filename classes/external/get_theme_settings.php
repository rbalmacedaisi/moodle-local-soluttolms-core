<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Solutto LMS Core is a plugin used by the various components developed by Solutto.
 *
 * @package    local_soluttolms_core
 * @copyright  2022 Solutto Consulting <devs@soluttoconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_soluttolms_core\external;

use core_component;
use external_api;
use external_description;
use external_function_parameters;
use external_single_structure;
use external_value;
use theme_config;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/externallib.php';

/**
 * External function 'local_soluttolms_core_get_theme_settings' implementation.
 *
 * @package     local_soluttolms_core
 * @category    external
 * @copyright   2022 Solutto Consulting <devs@soluttoconsulting.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_theme_settings extends external_api
{

    /**
     * Describes parameters of the {@see self::execute()} method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            [
                'themename' => new external_value(PARAM_TEXT, 'The name of the theme from where we want to get the settings'),
            ]
        );
    }

    /**
     * TODO describe what the function actually does.
     *
     * @param int $userid
     * @return mixed TODO document
     */
    public static function execute(string $themename)
    {
        // Re-validate parameter.
        [
            'themename' => $themename,
        ] = self::validate_parameters(self::execute_parameters(), [
            'themename' => $themename,
        ]);

        // Declare global variables.
        global $DB;

        // Get the list of installed themes.
        $themes = core_component::get_plugin_list('theme');

        // Let's see if thepassed theme is installed in the system.
        if (!array_key_exists($themename, $themes)) {
            // Select the first theme in the list.
            $themename = array_keys($themes)[0];
        }

        // Let's get the theme settings.
        $theme = theme_config::load($themename);
        $themeobj = $theme->settings;

        return ['themeobject' => json_encode($themeobj)];
    }

    /**
     * Describes the return value of the {@see self::execute()} method.
     *
     * @return external_description
     */
    public static function execute_returns(): external_description
    {
        return new external_single_structure(
            array(
                'themeobject' => new external_value(PARAM_RAW, 'A JSON string representation of the object containing the settings for the selected theme.'),
            )
        );
    }
}
