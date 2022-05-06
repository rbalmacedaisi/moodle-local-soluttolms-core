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
require_once($CFG->dirroot . '/local/soluttolms_core/lib.php');

/**
 * External function 'local_soluttolms_core_getcourses_by_token' implementation.
 *
 * @package     local_soluttolms_core
 * @category    external
 * @copyright   2022 Solutto Consulting <devs@soluttoconsulting.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_messages extends external_api
{

    /**
     * Describes parameters of the {@see self::execute()} method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User ID')
        ]);
    }

    /**
     * This method returns a list of messages for the given user.
     *
     * @param integer $userid
     * @param integer $limitfrom
     * @param integer $limitnum
     * @return void
     */
    public static function execute(int $userid)
    {
        global $CFG, $DB;

        // Re-validate parameter.
        [
            'userid' => $userid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
        ]);

        // Variables to indicate how many messages are available.
        $limitfrom = 0;
        $limitnum = 20;

        if (empty($CFG->messaging)) {
            throw new moodle_exception('disabled', 'message');
        }

        $temp = get_messages_from_user_conversations($userid, $limitfrom, $limitnum);
        $message_current_user_return = $temp['return'];
        $usersArray = $temp['usersArray'];

        $return = [
            'userMessages' => $message_current_user_return,
            'careChildMessages' => $child_messages,
        ];

        return $return;
    }

    /**
     * Describes the return value of the {@see self::execute()} method.
     *
     * @return external_description
     */
    public static function execute_returns(): external_description
    {
        $response_structure = new external_multiple_structure(
            new external_single_structure(
                [
                    'currentFullUserName' => new external_value(PARAM_TEXT, 'Fullname of the user that receive the message'),
                    'lastMessage' => new external_value(PARAM_RAW, 'Last message string'),
                    'members' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'username' => new external_value(PARAM_TEXT, 'Username member of this conversation'),
                                'profileimageurl' => new external_value(PARAM_RAW, 'Last message string'),
                            ]

                        ),
                    ),
                    'messageList' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'ID of the message'),
                                'useridfrom' => new external_value(PARAM_INT, 'ID of the user that send the message'),
                                'text' => new external_value(PARAM_RAW, 'String of the message'),
                                'timecreated' => new external_value(PARAM_RAW, 'create timestamp of the message'),
                                'usernamefrom' => new external_value(PARAM_TEXT, 'Fullname of the user that send the message'),
                                'date' => new external_value(PARAM_RAW, ''),
                                'hour' => new external_value(PARAM_RAW, ''),

                            ]
                        )
                    ),
                    'isRead' => new external_value(PARAM_BOOL, 'If the message is read or not'),
                    'conversationid' => new external_value(PARAM_INT, 'ID of the conversation'),
                ]
            )
        );
        return new external_single_structure(
            [
                'userMessages' => $response_structure,
            ]

        );
    }
}
