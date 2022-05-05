<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . "/user/profile/lib.php");
require_once($CFG->dirroot . "/mod/attendanceregister/lib.php");
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/files/externallib.php');
require_once($CFG->dirroot . '/local/student_core/lib.php');

class local_student_core_external extends external_api
{

    /*
     * Param token by user logged in site
     */
    public static function getcourses_bytoken_parameters()
    {
        return new external_function_parameters(
            [
                'userid' => new external_value(PARAM_INT, 'Id by user')
            ]
        );
    }


    /**
     * Check the courses and activities by user token passed as parameter
     *
     * @param string $token
     * @return array The result of the validation in an associative array
     */
    public static function getcourses_bytoken($userid)
    {
        global $CFG, $DB;

        //Get Courses enrol by user logged
        $mycourses = enrol_get_users_courses($userid);

        $coursecontent = [];
        $categoryname = [];
        $content = [];
        $istotal = [];
        if (!empty($mycourses)) {
            foreach ($mycourses as $key => $mc) {

                //Get Categories and subcat
                $categories = $DB->get_record('course_categories', array('id' => $mc->category));

                $path = explode("/", $categories->path);
                $categoryparent = $DB->get_record('course_categories', array('id' => $path[1]));

                $categoryname['id'] = $categoryparent->id;
                $categoryname['namecategory'] = $categoryparent->name;
                $categoryname['urlcategory'] = $CFG->wwwroot . 'course/index.php?categoryid=' . $categoryparent->id . '';

                //Get Progress Course
                $objcourse = get_course($mc->id);

                $progress = \core_completion\progress::get_course_progress_percentage($objcourse, $userid);

                if ($progress == NULL) {
                    $progress = 0;
                }

                $istotal[] = round($progress);

                $coursecontent['id'] = $mc->id;
                $coursecontent['fullname'] = $mc->fullname;
                $coursecontent['shortname'] = $mc->shortname;
                $coursecontent['progress'] = round($progress);
                $coursecontent['urlcourse'] = $CFG->wwwroot . '/course/view.php?id=' . $mc->id . '';

                $categoryname['courses'][] = $coursecontent;
            }

            $categoryname['categoryprogress'] = array_sum($istotal);
            $content[$key] = $categoryname;
        } else {
            $categoryname = [];
        }


        return $content;
    }

    /**
     * Return structure array with info related courses and activities by user logged
     * @return array The result of the validation in an associative array
     */
    public static function getcourses_bytoken_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'ID Category'),
                    'namecategory' => new external_value(PARAM_RAW, 'Name Category'),
                    'urlcategory' => new external_value(PARAM_RAW, 'Url Category'),
                    'categoryprogress' => new external_value(PARAM_RAW, 'Progress category'),
                    'courses' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'ID Course'),
                                'fullname' => new external_value(PARAM_RAW, 'Course Fullname'),
                                'shortname' => new external_value(PARAM_RAW, 'Course Shortname'),
                                'progress' => new external_value(PARAM_RAW, 'Course Progress'),
                                'urlcourse' => new external_value(PARAM_RAW, 'Url course'),
                            )
                        )
                    )
                )
            )
        );
    }

    public static function get_theme_settings_parameters()
    {
        return new external_function_parameters(
            [
                'themename'      => new external_value(PARAM_RAW, ''),
            ]
        );
    }

    public static function get_theme_settings($themename)
    {
        global $DB;

        $params = self::validate_parameters(self::get_theme_settings_parameters(), [
            'themename' => $themename,
        ]);

        $themename = 'theme_edumy';
        $themeobj = $DB->get_records('config_plugins', array('plugin' => $themename));

        $result = array();
        $themevalues = array();
        foreach ($themeobj as $theme) {
            $themevalues['namesetting'] = $theme->name;
            $themevalues['value'] = $theme->value;
            $component = $theme->name;

            $file = get_files_theme($component);
            $themevalues['urlfile'] = $file;

            $result[] = $themevalues;
        }

        return $result;
    }

    public static function get_theme_settings_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'namesetting' => new external_value(PARAM_RAW, ''),
                    'value'   => new external_value(PARAM_RAW, ''),
                    'urlfile'   => new external_value(PARAM_RAW, ''),
                )
            )
        );
    }

    public static function get_user_filed_parameters()
    {
        return new external_function_parameters(
            [
                'email' => new external_value(PARAM_RAW, 'Token user logged')
            ]
        );
    }

    public static function get_user_filed($email)
    {
        global $DB;

        $user = $DB->get_record('user', array('email' => $email));

        $response[] = [
            'id'      => $user->id,
            'username' => $user->username,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname
        ];

        return $response;
    }

    public static function get_user_filed_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, ''),
                    'username'   => new external_value(PARAM_RAW, ''),
                    'firstname'   => new external_value(PARAM_RAW, ''),
                    'lastname'   => new external_value(PARAM_RAW, ''),
                )
            )
        );
    }
}
