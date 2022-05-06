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

use core_user;

defined('MOODLE_INTERNAL') || die();

function get_messages_from_user_conversations($userid, $limitfrom, $limitnum)
{
    $usersArray = [];

    $return = [];
    $conversations = \core_message\api::get_conversations($userid, $limitfrom, $limitnum);

    if(!isset($usersArray[$userid])) 
    {
        $usersArray[$userid] = core_user::get_user($userid);
    }

    $current_user = $usersArray[$userid];

    foreach ($conversations as $conver) {

        $convid = $conver->id;
        $last_message_array = $conver->messages;
        $last_message = 'null';
        if (isset($last_message_array[0])) {
            $last_message = $last_message_array[0]->text;
        }
        $members = [];
        $conver_members = $conver->members;
        foreach ($conver_members as $convmember) {
            $members[$convmember->id] = ['username' => $convmember->fullname, 'profileimageurl' => $convmember->profileimageurlsmall];
        }

        $messages = \core_message\api::get_conversation_messages($userid, $convid, $limitfrom, $limitnum);

        $messages_list = $messages['messages'];

        foreach ($messages_list as &$msgl) {
            $msgl->hour = userdate($msgl->timecreated, '%I:%M');
            $date = userdate($msgl->timecreated, '%B %d, %Y');
            $msgl->date = $date;

            $useridfrom = $msgl->useridfrom;

            if(!isset($usersArray[$useridfrom]))
            {
                $usersArray[$useridfrom] = core_user::get_user($useridfrom);
            }

            $fromusername = $usersArray[$useridfrom];
            $msgl->usernamefrom = fullname($fromusername);
        }
        $return[] = [
            'currentFullUserName' => fullname($current_user),
            'lastMessage' => $last_message,
            'members' => $members,
            'messageList' => $messages_list,
            'isRead' => $conver->isread,
            'conversationid' => $convid,
        ];
    }
    
    return ['return' => $return, 'usersArray' => $usersArray];
}