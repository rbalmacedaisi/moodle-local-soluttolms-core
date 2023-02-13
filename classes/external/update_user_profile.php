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

use external_api;
use external_description;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/externallib.php';
require_once($CFG->dirroot . '/user/externallib.php');
// require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
// require_once("../../user/profile/lib.php");

// require_once $CFG->dirroot . '/user/externallib.php';
// require_once $CFG->dirroot . '/externallib.php';

/**
 * External function 'local_soluttolms_core_getcourses_by_token' implementation.
 *
 * @package     local_soluttolms_core
 * @category    external
 * @copyright   2022 Solutto Consulting <devs@soluttoconsulting.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_user_profile extends external_api {

    /**
     * Describes parameters of the {@see self::execute()} method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        global $CFG;
        $userfields = [
            'id' => new external_value(\core_user::get_property_type('id'), 'ID of the user'),
            // General.
            'username' => new external_value(\core_user::get_property_type('username'),
                'Username policy is defined in Moodle security config.', VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'auth' => new external_value(\core_user::get_property_type('auth'), 'Auth plugins include manual, ldap, etc',
                VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'suspended' => new external_value(\core_user::get_property_type('suspended'),
                'Suspend user account, either false to enable user login or true to disable it', VALUE_OPTIONAL),
            'password' => new external_value(\core_user::get_property_type('password'),
                'Plain text password consisting of any characters', VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'firstname' => new external_value(\core_user::get_property_type('firstname'), 'The first name(s) of the user',
                VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'lastname' => new external_value(\core_user::get_property_type('lastname'), 'The family name of the user',
                VALUE_OPTIONAL),
            'email' => new external_value(\core_user::get_property_type('email'), 'A valid and unique email address', VALUE_OPTIONAL,
                '', NULL_NOT_ALLOWED),
            'maildisplay' => new external_value(\core_user::get_property_type('maildisplay'), 'Email display', VALUE_OPTIONAL),
            'city' => new external_value(\core_user::get_property_type('city'), 'Home city of the user', VALUE_OPTIONAL),
            'country' => new external_value(\core_user::get_property_type('country'),
                'Home country code of the user, such as AU or CZ', VALUE_OPTIONAL),
            'timezone' => new external_value(\core_user::get_property_type('timezone'),
                'Timezone code such as Australia/Perth, or 99 for default', VALUE_OPTIONAL),
            'description' => new external_value(\core_user::get_property_type('description'), 'User profile description, no HTML',
                VALUE_OPTIONAL),
            // User picture.
            'userpicture' => new external_value(PARAM_INT,
                'The itemid where the new user picture has been uploaded to, 0 to delete', VALUE_OPTIONAL),
            // Additional names.
            'firstnamephonetic' => new external_value(\core_user::get_property_type('firstnamephonetic'),
                'The first name(s) phonetically of the user', VALUE_OPTIONAL),
            'lastnamephonetic' => new external_value(\core_user::get_property_type('lastnamephonetic'),
                'The family name phonetically of the user', VALUE_OPTIONAL),
            'middlename' => new external_value(\core_user::get_property_type('middlename'), 'The middle name of the user',
                VALUE_OPTIONAL),
            'alternatename' => new external_value(\core_user::get_property_type('alternatename'), 'The alternate name of the user',
                VALUE_OPTIONAL),
            // Interests.
            'interests' => new external_value(PARAM_TEXT, 'User interests (separated by commas)', VALUE_OPTIONAL),
            // Optional.
            'idnumber' => new external_value(\core_user::get_property_type('idnumber'),
                'An arbitrary ID code number perhaps from the institution', VALUE_OPTIONAL),
            'institution' => new external_value(\core_user::get_property_type('institution'), 'Institution', VALUE_OPTIONAL),
            'department' => new external_value(\core_user::get_property_type('department'), 'Department', VALUE_OPTIONAL),
            'phone1' => new external_value(\core_user::get_property_type('phone1'), 'Phone', VALUE_OPTIONAL),
            'phone2' => new external_value(\core_user::get_property_type('phone2'), 'Mobile phone', VALUE_OPTIONAL),
            'address' => new external_value(\core_user::get_property_type('address'), 'Postal address', VALUE_OPTIONAL),
            // Other user preferences stored in the user table.
            'lang' => new external_value(\core_user::get_property_type('lang'), 'Language code such as "en", must exist on server',
                VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'calendartype' => new external_value(\core_user::get_property_type('calendartype'),
                'Calendar type such as "gregorian", must exist on server', VALUE_OPTIONAL, '', NULL_NOT_ALLOWED),
            'theme' => new external_value(\core_user::get_property_type('theme'),
                'Theme name such as "standard", must exist on server', VALUE_OPTIONAL),
            'mailformat' => new external_value(\core_user::get_property_type('mailformat'),
                'Mail format code is 0 for plain text, 1 for HTML etc', VALUE_OPTIONAL),
            // Custom user profile fields.
            'customfields' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'type'  => new external_value(PARAM_ALPHANUMEXT, 'The name of the custom field'),
                        'value' => new external_value(PARAM_RAW, 'The value of the custom field')
                    ]
                ), 'User custom fields (also known as user profil fields)', VALUE_OPTIONAL),
            // User preferences.
            'preferences' => new external_multiple_structure(
                new external_single_structure(
                    [
                        'type'  => new external_value(PARAM_RAW, 'The name of the preference'),
                        'value' => new external_value(PARAM_RAW, 'The value of the preference')
                    ]
                ), 'User preferences', VALUE_OPTIONAL),
        ];
        return new external_function_parameters(
            [
                'users' => new external_multiple_structure(
                    new external_single_structure($userfields)
                )
            ]
        );
    }

    /**
     * TODO describe what the function actually does.
     *
     * @param int $userid
     * @return mixed TODO document
     */
    public static function execute($users)
    {
        $response = [];
        $updateResult = \core_user_external::update_users($users);
        
        if(!empty($updateResult['warnings'])){
            $response['error']['errorCode'] = $updateResult['warnings'][0]['warningcode'];
            $response['error']['message']= $updateResult['warnings'][0]['message'];
            return ['response' => json_encode(array_values($response))];
        }
        $response['success']['updated'] = true;
        return ['response' => json_encode(array_values($response))];
        
    }

    public static function execute_returns(): external_description
    {
        return new external_single_structure(
            array(
                'response' => new external_value(PARAM_RAW, 'true if the user is updated, error code and message otherwise.'),
            )
        );
        
    }

}