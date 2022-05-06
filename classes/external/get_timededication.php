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
require_once($CFG->dirroot . '/mod/attendanceregister/lib.php');

/**
 * External function 'local_soluttolms_core_getcourses_by_token' implementation.
 *
 * @package     local_soluttolms_core
 * @category    external
 * @copyright   2022 Solutto Consulting <devs@soluttoconsulting.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_timededication extends external_api
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
                'userid' => new external_value(PARAM_INT, 'The ID of the user.'),
            ]
        );
    }

    /**
     * TODO describe what the function actually does.
     *
     * @param int $userid
     * @return mixed TODO document
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

        //Get username, firstname, lastname...
        $infouser = $DB->get_record('user', array('id' => $userid));

        //Info user
        $username = $infouser->username;
        $firstname = $infouser->firstname;
        $lastname = $infouser->lastname;
        $timezone = $infouser->timezone;

        if ($lastloggin == 0) {
            $timelastlogged = 'N/A';
        } else {
            $timelastlogged = userdate($lastloggin, '', $timezone);
        }

        //Get Courses enrol by user...
        $coursesactive = enrol_get_users_courses($userid);

        //Array saved info courses active users
        $array_courses = [];
        if (!empty($coursesactive)) {
            foreach ($coursesactive as $course) {

                // Getting the course object.
                $objcourse = get_course($course->id);

                //Get time by user dedicated in course using mod_attendaceregister
                $timesession = $DB->get_record_sql("SELECT ag.duration
                                    FROM {attendanceregister_aggregate} ag
                                    INNER JOIN {attendanceregister} AS at ON at.id = ag.register
                                    WHERE at.course = :course AND ag.userid = :userid
                                    AND ag.total = 1",
                    array('course' => $course->id, 'userid' => $userid));

                // Time dedicated by users convert...
                $timeincourse = attendanceregister_format_duration($timesession->duration);

                $array_courses[] = [
                    'coursename' => $objcourse->fullname,
                    'timededicated' => $timeincourse,
                    'timenotformat' => $timesession->duration,
                ];
            }
        }
        $sumtime = 0;
        foreach ($array_courses as $time) {
            $sumtime += $time['timenotformat'];
        }

        //Get time dedicated in courses...
        $total_time_courses = attendanceregister_format_duration($sumtime);

        $arraydata = [
            'userid' => $userid,
            'username' => $username,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'courses' => $array_courses,
            'timededicatedtotal' => $total_time_courses
        ];

        return ['response' => json_encode($arraydata)];
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
                'response' => new external_value(PARAM_RAW, 'A string in JSON format.')
            )
        );

    }
}
