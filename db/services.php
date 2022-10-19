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

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_soluttolms_core_getcourses_by_token' => array(
        'classname' => 'local_soluttolms_core\external\getcourses_by_token',
        'methodname' => 'execute',
        'description' => 'Return courses and activities by user logged',
        'type' => 'read',
        "ajax" => true,
        'loginrequired' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_soluttolms_core_get_theme_settings' => array(
        'classname' => 'local_soluttolms_core\external\get_theme_settings',
        'methodname' => 'execute',
        'description' => 'Get Theme settings',
        'type' => 'read',
        "ajax" => true,
        'loginrequired' => false,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_soluttolms_core_get_timededication' => array(
        'classname' => 'local_soluttolms_core\external\get_timededication',
        'methodname' => 'execute',
        'description' => 'Get the dedication time for a given user.',
        'type' => 'read',
        "ajax" => true,
        'loginrequired' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_soluttolms_core_get_messages' => array(
        'classname' => 'local_soluttolms_core\external\get_messages',
        'methodname' => 'execute',
        'description' => 'Return messages by user logged',
        'type' => 'read',
        "ajax" => true,
        'loginrequired' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_soluttolms_core_get_data_by_courses' => array(
        'classname' => 'local_soluttolms_core\external\get_data_by_courses',
        'methodname' => 'execute',
        'description' => 'Return data related to courses',
        'type' => 'read',
        "ajax" => true,
        'loginrequired' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_soluttolms_core_get_user_points' => array(
        'classname' => 'local_soluttolms_core\external\get_user_points',
        'methodname' => 'execute',
        'description' => 'Return the number of points of a user, either in a course or in the entire platform.',
        'type' => 'read',
        "ajax" => true,
        'loginrequired' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_soluttolms_core_get_user_badges' => array(
        'classname' => 'local_soluttolms_core\external\get_user_badges',
        'methodname' => 'execute',
        'description' => 'Return the list of badges for a given user.',
        'type' => 'read',
        "ajax" => true,
        'loginrequired' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
    'local_soluttolms_core_get_leaderboard_course' => array(
        'classname' => 'local_soluttolms_core\external\get_leaderboard_course',
        'methodname' => 'execute',
        'description' => 'RReturns the leaderboard for the given course.',
        'type' => 'read',
        "ajax" => true,
        'loginrequired' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ),
);
