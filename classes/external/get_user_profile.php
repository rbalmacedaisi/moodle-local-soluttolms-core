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
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
require_once("../../user/profile/lib.php");

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
class get_user_profile extends external_api {

    /**
     * Describes parameters of the {@see self::execute()} method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        
        return new external_function_parameters(
            [
                'userid' => new external_value(PARAM_INT, 'Id by user'),
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

        
        $userInfo = \core_user_external::user_get_users_by_field('id',array($userid));
        // $userInfo = \user_get_users(array(array('key'=>'id','value'=>$userid)));
        var_dump($userInfo);
        echo($CFG->dirroot . '/user/externallib.php');
        die();
        
        
        //Get Courses enrol by user logged
        $mycourses = enrol_get_users_courses($userid);
        // var_dump($mycourses);
        // die();
        $coursecompleted = 0;
        $countcourses = 0;
        $coursecontent = [];
        $categorycontent = [];
        if (!empty($mycourses)) {
            foreach ($mycourses as $key => $mc) { 
                
                $coursecontent['id'] = $mc->id;
                $coursecontent['fullname'] = $mc->fullname;
                $coursecontent['shortname'] = $mc->shortname;
                $coursecontent['visible'] = $mc->visible;
                $objcourse = get_course($mc->id);
                $progress = \core_completion\progress::get_course_progress_percentage($objcourse, $userid);
                if ($progress == NULL) {
                    $progress = 0;
                }
                $coursecontent['progress'] = round($progress);
                if($progress == 100){
                    $coursecompleted++;
                }
                $coursecontent['urlcourse'] = $CFG->wwwroot . '/course/view.php?id=' . $mc->id . '';
                
                $categories = $DB->get_record('course_categories', array('id' => $mc->category));
                
                
                $categorycontent[$categories->id]['namecategory'] = $categories->name;
                $categorycontent[$categories->id]['urlcategory'] = $CFG->wwwroot . 'course/index.php?categoryid=' . $categories->id . '';
                $categorycontent[$categories->id]['id'] = $categories->id;
                $categorycontent[$categories->id]['visible'] = $categories->visible;
                $categorycontent[$categories->id]['coursecount'] = $categories->coursecount;
                $categorycontent[$categories->id]['courses'][] = $coursecontent;
            }
        } else {
            $categorycontent = [];
        }
        
        return ['profileobj' => json_encode(array_values($categorycontent))];
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
                'profileobj' => new external_value(PARAM_RAW, ''),
            )
        );
        
    }

}
