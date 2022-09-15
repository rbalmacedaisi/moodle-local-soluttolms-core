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
use user_picture;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/externallib.php';
require_once($CFG->dirroot . '/local/soluttolms_core/lib.php');
require_once($CFG->libdir . '/filelib.php' );
require_once($CFG->dirroot . '/course/externallib.php');

/**
 * External function 'local_soluttolms_core_getcourses_by_token' implementation.
 *
 * @package     local_soluttolms_core
 * @category    external
 * @copyright   2022 Solutto Consulting <devs@soluttoconsulting.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_data_by_courses extends external_api {

    /**
     * Describes parameters of the {@see self::execute()} method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'Id by Courses'),
                'userid' => new external_value(PARAM_INT, 'Id by Users'),
            ]
        );
    }

    /**
     * TODO describe what the function actually does.
     *
     * @param int $userid
     * @return mixed TODO document
     */
    public static function execute(int $courseid, int $userid)
    {
        global $DB,$PAGE,$CFG,$OUTPUT;
        
        $teachersdata = [];
        $managers = [];
        $data = [];
        $coursedata = $DB->get_record('course', array('id' => $courseid));
        $typeenrol = $DB->get_records('enrol', array('courseid' => $courseid));
        $data['courseinfo'] = $coursedata;

        //Check if user is enroll in course
        $context = \context_course::instance($courseid);
        $isenrol = is_enrolled($context, $userid, '', true);
        if($isenrol == 1){
            $data['userenrolled'] = 'enrolled';
        }else{
            $data['userenrolled'] = 'notenrolled';
        }
        
        //Course Image
        $fs = get_file_storage();
        $files = $fs->get_area_files( $context->id, 'course', 'overviewfiles', 0 );
        foreach ( $files as $f ){
        if ($f->is_valid_image()){
                $url = \moodle_url::make_pluginfile_url( $f->get_contextid(), $f->get_component(), $f->get_filearea(), null, $f->get_filepath(), $f->get_filename(), false );
                $urlimg = $url->out(false);
            }
        }
    
        if(!empty($urlimg)){
            $data['courseimage'] = $urlimg;
        }else{
            $data['courseimage'] = 'notcourseimage';
        }
        
        foreach($typeenrol as $enrol){
            if($enrol->status == 0){
                $data['type_enrol']['name']  = $enrol->enrol;
                $data['type_enrol']['status_enrol'] = $enrol->status;
            }
        }
        
        $modinfo = \core_course_external::get_course_contents($courseid);
        $data['activities'] = $modinfo; 
        
        /*
        $teachers = $DB->get_records_sql('SELECT u.firstname, u.lastname, u.email, u.id AS userid, ra.roleid, c.id, r.shortname, r.name AS roleName
                                        FROM {user} u 
                                        JOIN {user_enrolments} ue ON (ue.userid = u.id)
                                        JOIN {role_assignments} ra ON (ra.userid = u.id)
                                        JOIN {context} c ON (c.id = ra.contextid)
                                        JOIN {role} r ON (r.id = ra.roleid)
                                        WHERE c.instanceid = :courseid AND ra.roleid = 1 OR ra.roleid = 3',
                                        array('courseid' => $courseid));
                                        
        foreach($teachers as $teach){
            $teachersdata[$teach->userid]['fullname'] = $teach->firstname.' '.$teach->lastname;
            $teachersdata[$teach->userid]['email'] = $teach->email;
            $user_object = \core_user::get_user($teach->userid);
            $userpicture = new user_picture($user_object);
            $url = $userpicture->get_url($PAGE)->out(false);
            $teachersdata[$teach->userid]['image'] = $url;
            $teachersdata[$teach->userid]['shortname'] = $teach->shortname;
            $teachersdata[$teach->userid]['roleName'] = $teach->roleName;
        }

        $data['teachers'] = array_values($teachersdata);*/

        // Get the list of teachers and managers in this course.
        $context = \context_course::instance($courseid);
        $teachers = get_enrolled_users($context, 'moodle/course:update', 0, 'u.*', null, 0, 0, true);
        $managers = get_enrolled_users($context, 'moodle/category:manage', 0, 'u.*', null, 0, 0, true);
        $teachers = array_merge($teachers, $managers);
        $teachers = array_unique($teachers, SORT_REGULAR);
        $data['teachers'] = $teachers;

        // Get the list of enrollment methods.
        $enrolinstances = enrol_get_instances($courseid, true);
        $enrolmethods = [];
        foreach ($enrolinstances as $instance) {
            $enrolmethods[] = $instance->enrol;
        }
        $data['enrolmethods'] = $enrolmethods;
        
        $badges = $DB->get_records_sql("SELECT
                bi.uniquehash,
                bi.dateissued,
                bi.dateexpire,
                bi.id as issuedid,
                bi.visible,
                u.email,
                b.*
            FROM
                {badge} b,
                {badge_issued} bi,
                {user} u
            WHERE b.id = bi.badgeid
                AND u.id = bi.userid
                AND bi.userid = :userid
                AND b.courseid = :courseid",
                ['userid'  => $userid,
                'courseid' => $courseid]);
        
        $badgecomplete = [];
        $badgeincomplete = [];
        $getallbadges = $DB->get_records('badge', array('courseid' => $courseid));
        foreach($getallbadges as $bd){
            $incomplete = $DB->get_record('badge_issued', array('badgeid' => $bd->id, 'userid' => $userid));
            if(empty($incomplete)){
                $context = ($bd->type == BADGE_TYPE_SITE) ? \context_system::instance() : \context_course::instance($bd->courseid);
                $nameb = $bd->name;
                $imageurl = file_encode_url("$CFG->wwwroot/pluginfile.php",'/'. $context->id. '/'. 'badges'. '/'.'badgeimage'.  '/'.$bd->id.'/'.'f1');
                $badgeincomplete[] = ['name' => $nameb, 'image' => $imageurl];
            }
        }
        
        $data['badgesincomplete'] = $badgeincomplete;
        
        foreach($badges as $badge => $val){

                $context = ($badge->type == BADGE_TYPE_SITE) ? \context_system::instance() : \context_course::instance($val->courseid);
                $bname = $val->name;
                $imageurl = file_encode_url("$CFG->wwwroot/pluginfile.php",'/'. $context->id. '/'. 'badges'. '/'.'badgeimage'.  '/'.$val->id.'/'.'f1');
                $badgecomplete[] = ['name' => $bname, 'image' => $imageurl];
        }
        $data['badgescomplete'] = $badgecomplete;
        
        //Get progress by user
        $objcourse = get_course($courseid);
        $progress = \core_completion\progress::get_course_progress_percentage($objcourse, $userid);
        if ($progress == NULL) {
            $progress = 0;
        }
        $data['progress'] = round($progress);
        
        //Data Custom fields course
        $fields = get_course_metadata($courseid);
        $data['customfields'] = array_values($fields);
        
        return ['coursedata' => json_encode($data)];
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
                'coursedata' => new external_value(PARAM_RAW, ''),
            )
        );
    }
}