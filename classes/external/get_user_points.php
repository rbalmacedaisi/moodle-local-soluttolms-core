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
 * This file contains the class that define the get_user_points external function.
 * 
 * This function is used to get the points of a user, either in the specified course or in the whole site.
 *
 * @package    local_soluttolms_core
 * @copyright  2022 Solutto Consulting <devs@soluttoconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_soluttolms_core\external;

use stdClass;
use external_api;
use external_description;
use external_function_parameters;
use external_single_structure;
use external_value;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/externallib.php';

/**
 * External function 'local_soluttolms_core_get_user_points' implementation.
 *
 * @package     local_soluttolms_core
 * @category    external
 * @copyright   2022 Solutto Consulting <devs@soluttoconsulting.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_user_points extends external_api {

    /**
     * Describes parameters of the {@see self::execute()} method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'userid' => new external_value(PARAM_INT, 'The ID of the user'),
                'courseid' => new external_value(PARAM_INT, 'The ID of the course; if an ID is not provided, the points of the user in the whole site will be returned', VALUE_DEFAULT, 0),
            ]
        );
    }

    /**
     * TODO describe what the function actually does.
     *
     * @param int $userid
     * @return mixed TODO document
     */
    public static function execute(int $userid, int $courseid = 0) {
        // Re-validate parameter.
        $params = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        global $DB;

        // This is the object for the response.
        $result = new stdClass();
        $result->points = 0;
        $result->levelinfo = new stdClass;

        // If a courseid was not provided, we need to get the sum of the points of the user in all the courses.
        // The total points are the sum of the xp field from the block_xp table.
        if ($courseid == 0) {
            $sql = "SELECT SUM(xp) AS totalpoints FROM {block_xp} WHERE userid = :userid";
            $result->points = $DB->get_field_sql($sql, ['userid' => $userid]);
        } else {
            // Let's get the record for the userid and courseid provided.
            $record = $DB->get_record('block_xp', ['userid' => $userid, 'courseid' => $courseid]);
            $result->points = $record->xp;

            // Let's get the level info for the user.
            $levels = $DB->get_record('block_xp_config', [
                'courseid' => $courseid,
                'enabled' => 1
            ]);

            $levels = json_decode($levels->levelsdata);

            // Let's iterate over the levels to get the level info.
            foreach($levels->name as $key => $level) {
                // If the current item is not the last.
                if(isset($levels->xp->{$key + 1})) {
                    if ($result->points >= $levels->xp->{$key} && $result->points < $levels->xp->{$key + 1}) {
                        echo "Min: ".$levels->xp->{$key}.', Max: '.$levels->xp->{$key + 1}.', Points: '.$result->points."\n";
                        $result->levelinfo->name = $levels->name->{$key};
                        $result->levelinfo->description = $levels->desc->{$key};
                        $result->levelinfo->min = $levels->xp->{$key};
                        $result->levelinfo->max = $levels->xp->{$key + 1};
                        break;
                    }
                } else {
                    $result->levelinfo->name = $levels->name->{$key};
                    $result->levelinfo->description = $levels->desc->{$key};
                    $result->levelinfo->min = $levels->xp->{$key};
                    $result->levelinfo->max = 0;   
                    break;
                }
            }
        }

        return [
            'points' => $result->points,
            'levelinfo' => json_encode($result->levelinfo)
        ];
    }

    /**
     * Describes the return value of the {@see self::execute()} method.
     *
     * @return external_description
     */
    public static function execute_returns(): external_description {
        return new external_single_structure(
            array(
                'points' => new external_value(PARAM_INT, 'The total number of points of the user.'),
                'levelinfo' => new external_value(PARAM_RAW, 'The level where the user is based on the points gained in the given course; if a course is not provided, then this value is an empty string.'),
            )
        );
    }
}
