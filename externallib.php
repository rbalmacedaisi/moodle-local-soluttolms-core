<?php

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot.'/course/lib.php');
require_once("{$CFG->libdir}/completionlib.php");
require_once("../../user/profile/lib.php");
require_once("../../mod/attendanceregister/lib.php");
require_once($CFG->dirroot . '/user/externallib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
require_once($CFG->dirroot . '/files/externallib.php');
require_once ($CFG->dirroot . '/local/student_core/lib.php');

class local_student_core_external extends external_api
{

    /*********************************
     * The following methods define the 'local_student_core_verification_email' webservice function
     */
    public static function email_verification_parameters()
    {
        return new external_function_parameters(
            [
                'email'=>new external_value(PARAM_TEXT, 'Caregiver email')
            ]
        );
    }

    /**
     * Check the user email passed as parameter
     *
     * @param string $email
     * @return array The result of the validation in an associative array
     */
    public static function email_verification($email)
    {
        global $DB;
        
        $useremail = $DB->get_record('user', array('email' => $email));

        if (!empty($useremail)){
            $response = true;
        }else{
            $response = false;
        }
        return ['emailTaken'=>$response];
    }

    public static function email_verification_returns()
    {
        return new external_single_structure(
            array(
                'emailTaken' => new external_value(PARAM_BOOL, 'True if everything goes well, False if something went wrong.')
            )
        );
    }
    
    /*
     * Param token by user logged in site
     */
    public static function getcourses_bytoken_parameters()
    {
        return new external_function_parameters([
                'userid' => new external_value(PARAM_INT, 'Id by user')]
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
        if(!empty($mycourses)){
            foreach($mycourses as $key => $mc){
                
                //Get Categories and subcat
                $categories = $DB->get_record('course_categories', array('id' => $mc->category));
                
                $path = explode("/", $categories->path);
                $categoryparent = $DB->get_record('course_categories', array('id' => $path[1]));
                
                $categoryname['id'] = $categoryparent->id;
                $categoryname['namecategory'] = $categoryparent->name;
                $categoryname['urlcategory'] = $CFG->wwwroot.'course/index.php?categoryid='.$categoryparent->id.''; 
                
                //Get Progress Course
                $objcourse = get_course($mc->id);
                   
                $progress = \core_completion\progress::get_course_progress_percentage($objcourse, $userid);
              
                if($progress == NULL){
                    $progress = 0;
                }
                
                $istotal[] = round($progress);
                
                $coursecontent['id'] = $mc->id;
                $coursecontent['fullname'] = $mc->fullname;
                $coursecontent['shortname'] = $mc->shortname;
                $coursecontent['progress'] = round($progress);
                $coursecontent['urlcourse'] = $CFG->wwwroot.'/course/view.php?id='.$mc->id.''; 

                $categoryname['courses'][] = $coursecontent;
            }

            $categoryname['categoryprogress'] = array_sum($istotal);
            $content[$key] = $categoryname;
        }else{
            $categoryname = [];
        }

        
        return $content;
    }
        
    /**
     * Return structure array with info related courses and activities by user logged
     * @return array The result of the validation in an associative array
     */
    public static function getcourses_bytoken_returns(){
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
    
    /*
     * Info users related caregiver parameters
     */
    public static function user_data_caregiver_parameters()
    {
        return new external_function_parameters([
            'token' => new external_value(PARAM_TEXT, 'Token user logged')]
        );
    }
    
    /*
     * Method get info users related caregiver
     */
    public static function user_data_caregiver($token)
    {
        global $CFG, $DB;
        
        $webservicelib = new webservice();
        //Get userid and info by token param get
        $authenticationinfo = $webservicelib->authenticate_user($token);
        
        //User id caregiver
        $userid = $authenticationinfo['user']->id;

            //Get username, firstname, lastname...
            $infouser  = $DB->get_record('user', array('id' => $userid));
      
            //Info user
            $username   = $infouser->username;
            $firstname  = $infouser->firstname;
            $lastname   = $infouser->lastname;
            
            if($lastloggin == 0){
                $timelastlogged = 'N/A';
            }else{
                $timelastlogged = date("d/m/Y H:i:s", $lastloggin);
            }
           

            //Get Courses enrol by user...
            $coursesactive = enrol_get_users_courses($userid);
             
            
            //Array saved info courses active users
            $array_courses = [];
            if(empty($coursesactive)){
                $array_courses = [];
            }else{
                foreach($coursesactive as $course){
                   
                    $objcourse = get_course($course->id);
                   
                    //Progres course
                    $progress = \core_completion\progress::get_course_progress_percentage($objcourse, $userid);
                
                    if($progress == NULL){
                        $progress = 0;
                    }
                    
                    //Get time by user dedicated in course using mod_attendaceregister
                    $timesession = $DB->get_record_sql("SELECT ag.duration 
                                        FROM {attendanceregister_aggregate} ag
                                        JOIN {attendanceregister} AS at ON at.id = ag.register
                                        WHERE at.course = :course AND ag.userid = :userid
                                        AND ag.total = 1",
                                        array('course' => $course->id, 'userid' => $userid));
                    //Time dedicated by users convert...
                    $timeincourse = attendanceregister_format_duration($timesession->duration);
                   
                    $array_courses[] = [
                       'coursename'    => $objcourse->fullname,
                       'progress'      => round($progress),
                       'timededicated' => $timeincourse,
                       'timenotformat' => $timesession->duration
                    ];
                }
            }
            $sumtime = 0;
            foreach($array_courses as $time){
                $sumtime += $time['timenotformat'];
            }
            //print_object($sumtime);
                  // die;
            
            
             //Get time dedicated in courses...
            $total_time_courses = attendanceregister_format_duration($sumtime);
            
            $arraydata[] = ['userid'                  => $userid,
                            'username'                => $username,
                            'firstname'               => $firstname,
                            'lastname'                => $lastname,
                            'courses'                 => $array_courses,
                            'timededicatedtotal'      => $total_time_courses];
    
        $info[] = ['response' => $arraydata];

        return $info;
    }
    
    
    /*
     * Info users related caregiver returns
     */
    public static function user_data_caregiver_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'response' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'userid'                 => new external_value(PARAM_INT, 'Id of user'),
                                'username'               => new external_value(PARAM_RAW, 'Username of user'),
                                'firstname'              => new external_value(PARAM_RAW, 'Firstname of user'),
                                'lastname'               => new external_value(PARAM_RAW, 'Lastname of user'),
                                'courses' => new external_multiple_structure(
                                    new external_single_structure(
                                        [   
                                            'coursename'    => new external_value(PARAM_RAW, 'Name of courses enrolled user'),
                                            'progress'      => new external_value(PARAM_RAW, 'Progress of courses active'),
                                            'timededicated' => new external_value(PARAM_RAW, 'Time dedicated in courses active'),
                                        ]    
                                    )
                                ),
                                'timededicatedtotal'        => new external_value(PARAM_RAW, 'knutosRole user'),
                            )
                        )
                    ),
                )
            )
        );
    }
    
    
    /*
     * Parameter Get profile image
     */
    public static function get_profile_image_parameters()
    {
        return new external_function_parameters([
            'token' => new external_value(PARAM_TEXT, 'Token user logged')]
        );
    }
    
    
    /*
     * Method Get profile image
     */
    public static function get_profile_image($token)
    {
        global $CFG, $DB;
        
        $webservicelib = new webservice();
        //Get userid and info by token param get
        $authenticationinfo = $webservicelib->authenticate_user($token);
        
        //User id caregiver
        $useridcaregiver = $authenticationinfo['user']->id;
        
        //Method get Info download report
        $profileimage = core_user_external::get_users_by_field('id', [$useridcaregiver]);
        
        //user info name and lastname
        $userinfo = $DB->get_record('user', array('id' => $useridcaregiver));
        $default = false;
        
        foreach($profileimage as $profile){
            $image = $profile['profileimageurl'];
            $isdefault = strpos($image, 'rev=');
            if(empty($isdefault)){
                $default = true;
            }
            $data[] =  ['image' => $image,
                        'firstname' => $userinfo->firstname,
                        'lastname'  => $userinfo->lastname,
                        'default'   => $default];
        }
        
        $response[] = ['response' => $data];
        
        return $response;
    }
    
    
    /*
     * Get profile image Returns
     */
    public static function get_profile_image_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'response' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'image'      => new external_value(PARAM_RAW, 'Image profile User'),
                                'firstname'  => new external_value(PARAM_RAW, 'Firstname User'),
                                'lastname'   => new external_value(PARAM_RAW, 'Lastname profile User'),
                                'default'    => new external_value(PARAM_BOOL,'Default profile Image'),
                            )
                        )
                    ),
                )
            )
        );
    }
    
    
    /*
     * Parameter Updated profile image
     */
    public static function update_profile_image_parameters()
    {
        return new external_function_parameters([
            'token'    => new external_value(PARAM_TEXT, 'Token user logged'),
            ]
        );
    }
    
    /*
     * Method Updated profile image
     */
    public static function update_profile_image($token){
        
        global $CFG, $DB, $CFG;

        header('Access-Control-Allow-Origin: '.$CFG->appurl);
        header('Content-Type: application/x-www-form-urlencoded; multipart/form-data');
        
        $webservicelib = new webservice();
        //Get userid and info by token param get
        $authenticationinfo = $webservicelib->authenticate_user($token);
        
        //User id caregiver
        $useridcaregiver = $authenticationinfo['user']->id;
        
        $context = context_user::instance($useridcaregiver);
        $file = new stdClass();
        $file->filename = clean_param($_FILES['file']['name'], PARAM_FILE);

        // check system maxbytes setting
        if (($_FILES['file']['size'] > get_max_upload_file_size($CFG->maxbytes))) {
            // oversize file will be ignored, error added to array to notify
            // web service client
            $file->errortype = 'fileoversized';
            $file->error = get_string('maxbytes', 'error');
        } else {
            $file->filepath = $_FILES['file']['tmp_name'];
            // calculate total size of upload
            $totalsize += $_FILES['file']['size'];
        }
        $files[] = $file;
    
        $fs = get_file_storage();
        
        $itemid = file_get_unused_draft_itemid();
        
        // Get any existing file size limits.
        $maxupload = get_user_max_upload_file_size($context, $CFG->maxbytes);
        
        // Check the size of this upload.
        if ($maxupload !== USER_CAN_IGNORE_FILE_SIZE_LIMITS && $totalsize > $maxupload) {
            throw new file_exception('userquotalimit');
        }
        
        if($_FILES['file']['type'] == "image/jpeg" || $_FILES['file']['type'] == "image/png" || $_FILES['file']['type'] == "image/jpg"){
            $results = array();
            foreach ($files as $file) {
                if (!empty($file->error)) {
                    // including error and filename
                    $results[] = $file;
                    continue;
                }
                $file_record = new stdClass;
                $file_record->component = 'user';
                $file_record->contextid = $context->id;
                $file_record->userid    = $useridcaregiver;
                $file_record->filearea  = 'draft';
                $file_record->filename  = $_FILES['file']['name'];
                $file_record->filepath  = '/';
                $file_record->itemid    = $itemid;
                $file_record->license   = $CFG->sitedefaultlicense;
                $file_record->author    = fullname($authenticationinfo['user']);
                $file_record->source    = serialize((object)array('source' =>  $_FILES['file']['name']));
                
                //Check if the file already exist
                $existingfile = $fs->file_exists($file_record->contextid, $file_record->component, $file_record->filearea,
                            $file_record->itemid, $file_record->filepath, $file_record->filename);
                if ($existingfile) {
                    $file->errortype = 'filenameexist';
                    $file->error = get_string('filenameexist', 'webservice', $file->filename);
                    $results[] = $file;
                } else {
                    $stored_file = $fs->create_file_from_pathname($file_record, $file->filepath);
                    $results[] = $file_record;
                }
            }
                
            foreach($results as $result){
                //Method get update profile picture
                $updatedimage = core_user_external::update_picture($result->itemid,true,$useridcaregiver);
          
                if(!empty($updatedimage['profileimageurl']) && $_FILES['file']['type'] == "image/jpeg" || $_FILES['file']['type'] == "image/png" || $_FILES['file']['type'] == "image/jpg"){
                    $response = $updatedimage['profileimageurl'];
                }else{
                    $response = 'Error';
                }
            }  
        }else{
            $response = 'Error';
        }
        
        if($response == NULL){
            $response = 'Error';
        }
  
        
        return ['response' => $response];
    }
    
    /*
     * Get profile image Returns
     */
    public static function update_profile_image_returns()
    {
        return new external_single_structure(
            array(
                'response' => new external_value(PARAM_RAW, 'Image URL if everything goes well, False if something went wrong.')
            )
        );
    }
    
    /**
     * Get the user message
     */
     
    public function get_user_message_parameters() 
    {
        return new external_function_parameters([
            'userid'    => new external_value(PARAM_INT, 'User ID'),
            'limitfrom' => new external_value(PARAM_INT, 'Limit From', VALUE_OPTIONAL),
            'limitnum'  => new external_value(PARAM_INT, 'Limit Num', VALUE_OPTIONAL),
        ]);
    }
     
    public function get_user_message_returns() 
    {
        $response_structure = new external_multiple_structure(
                    new external_single_structure(
                        [
                            'currentFullUserName' => new external_value(PARAM_TEXT, 'Fullname of the user that receive the message'),
                            'lastMessage'   => new external_value(PARAM_RAW, 'Last message string'),
                            'members'       => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'username' => new external_value(PARAM_TEXT, 'Username member of this conversation'),
                                        'profileimageurl' => new external_value(PARAM_RAW, 'Last message string')
                                    ]
                                    
                                ),
                            ),
                            'messageList'   => new external_multiple_structure(
                                new external_single_structure(
                                    [
                                        'id'            => new external_value(PARAM_INT,  'ID of the message'),
                                        'useridfrom'    => new external_value(PARAM_INT,  'ID of the user that send the message'),
                                        'text'          => new external_value(PARAM_RAW,  'String of the message'),
                                        'timecreated'   => new external_value(PARAM_RAW,  'create timestamp of the message'),
                                        'usernamefrom'  => new external_value(PARAM_TEXT, 'Fullname of the user that send the message'),
                                        'date' => new external_value(PARAM_RAW, ''),
                                        'hour' => new external_value(PARAM_RAW, '')
                                        
                                    ] 
                                )
                            ),
                            'isRead' => new external_value(PARAM_BOOL, 'If the message is read or not'),
                            'conversationid' => new external_value(PARAM_INT,  'ID of the conversation')
                        ]
                    )
                );
        return new external_single_structure(
            [
                'userMessages' => $response_structure,
            ]
            
        );
    }
    public function get_user_message($userid, $limitfrom = 0, $limitnum = 20) 
    {
        global $CFG, $DB;
        if (empty($CFG->messaging)) {
            throw new moodle_exception('disabled', 'message');
        }
        
        //$caregiverid_field = $DB->get_record('user_info_field', ['shortname' => 'caregiverid']);
        //$childs = $DB->get_records_sql("SELECT * FROM {user_info_data} WHERE fieldid = $caregiverid_field->id AND data = $userid");
        
        $usersArray = [];

        $message_current_user_return = self::get_messages_from_user_conversations($userid, $limitfrom, $limitnum, $usersArray);
        /*$child_messages = [];
        foreach($childs as $child) {
            $child_userid = $child->userid;
            $child_messages[$child_userid] = self::get_messages_from_user_conversations($child_userid, $limitfrom, $limitnum, $usersArray);
        }*/
        $return = [
            'userMessages'      => $message_current_user_return,
            'careChildMessages' => $child_messages,
        ];
        
        return $return;
        
    }
    
    public static function get_messages_from_user_conversations($userid, $limitfrom, $limitnum, &$usersArray) {
        $return = [];
        $conversations = \core_message\api::get_conversations($userid, $limitfrom, $limitnum); // array of conversations. Every index have a sub index 'message' that retrieve the last message
        
        if(!isset($usersArray[$userid])) // If not is set in the array, then search and assign to the array (Only search for the user in the db one time)
        {
            $usersArray[$userid] = core_user::get_user($userid);
        }
        $current_user = $usersArray[$userid];
       
        foreach($conversations as $conver) {
            
            $convid = $conver->id;
            $last_message_array = $conver->messages;
            $last_message = 'null';
            if(isset($last_message_array[0])) {
                $last_message = $last_message_array[0]->text;
            }
            $members = [];
            $conver_members = $conver->members;
            foreach($conver_members as $convmember) {
               $members[$convmember->id] = ['username' => $convmember->fullname, 'profileimageurl' => $convmember->profileimageurlsmall];
            }
          
            $messages = \core_message\api::get_conversation_messages($userid, $convid, $limitfrom, $limitnum);
            
            $messages_list = $messages['messages'];
            
            
            foreach($messages_list as &$msgl) {
                $msgl->hour = date('h:i A', $msgl->timecreated);
                $date = date('d-m-Y' , $msgl->timecreated);
                $msgl->date = $date;
                
                
                
                $useridfrom = $msgl->useridfrom;
                if(!isset($usersArray[$useridfrom])) // If not is set in the array, then search and assign to the array (Only search for the user in the db one time)
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
                'conversationid'=> $convid,
            ];
        }
        return $return;
    }
    
    public static function get_caregiver_parameters()
    {
        return new external_function_parameters([
            'token' => new external_value(PARAM_TEXT, 'Token user logged')]
        );
    }
    
    public static function get_caregiver($token) {
        global $DB;
        
        $webservicelib = new webservice();
        //Get userid and info by token param get
        
        $authenticationinfo = $webservicelib->authenticate_user($token);
        
        //User id caregiver
        $useridson = $authenticationinfo['user']->id;
        
        $sondata = $DB->get_record('user', array('id' => $useridson));
        
        $fieldid = $DB->get_record('user_info_field', array('shortname' => 'caregiverid'),'id');
        
        //Get users related with caregiverid
        $compare_scale_clause = $DB->sql_compare_text('userid')  . ' = ' . $DB->sql_compare_text(':data');
        $userdata = $DB->get_record_sql("SELECT * FROM {user_info_data} uid WHERE $compare_scale_clause AND fieldid = $fieldid->id",array('data' => $useridson));
        
        $username = $DB->get_record('user', array('id' => $userdata->data));
        $name = $username->firstname.' '.$username->lastname;
        $email = $username->email;
        
        return ['caregivername'  => $name,
                'caregiveremail' => $email,
                'studentname'    => $sondata->firstname.' '.$sondata->lastname];
        
    }
    
    public static function get_caregiver_returns()
    {
        return new external_single_structure(
            array(
                'caregivername' => new external_value(PARAM_RAW, 'Name of caregiver'),
                'caregiveremail' => new external_value(PARAM_RAW, 'Name of caregiver'),
                'studentname' => new external_value(PARAM_RAW, 'Name of caregiver'),
                
            )
        );
    }
    
    /*
     * Function get data table mdl_student_course_payment parameters
     */
    public static function get_payment_data_parameters()
    {
        return new external_function_parameters(
            [
                'careGiverId' => new external_value(PARAM_RAW, 'Caregiver Id')
            ]
        );
    }
    
     /*
     * Function get data table mdl_student_course_payment
     */
    public static function get_payment_data($careGiverId)
    {
        global $DB;
        
        // $webservicelib = new webservice();
        // //Get userid and info by token param get
        // $authenticationinfo = $webservicelib->authenticate_user($token);
        
        // $caregiverid = $authenticationinfo['user']->id;
        
        //Get data payment with idcaregiver
        $datapayment = $DB->get_record('student_course_payments', array('caregiver_id' => $careGiverId));
 
        return  ['id'                 => $datapayment->id,
                 'courseid'           => $datapayment->courseid,
                 'course_status'      => $datapayment->course_status,
                 'payment_status_id'  => $datapayment->payment_status_id,
                 'payment_status_str' => $datapayment->payment_status_str,
                 'unique_ref'         => $datapayment->unique_ref,
                 'caregiver_id'       => $datapayment->caregiver_id,
                 'payment_timestamp'  => $datapayment->payment_timestamp,
                 'coursename'         => $datapayment->coursename,
                 'price'              => $datapayment->price,
                 'summary'            => $datapayment->summary,
                 'studentid'          => $datapayment->studentid,
                ];
    }
    
    public static function get_payment_data_returns()
    {
        return new external_single_structure(
            array(
                'id'                => new external_value(PARAM_INT, 'Id'),
                'courseid'          => new external_value(PARAM_INT, 'Course Id'),
                'course_status'     => new external_value(PARAM_RAW, 'Status Course'),
                'payment_status_id' => new external_value(PARAM_RAW, 'Status Payment Id'),
                'payment_status_str'=> new external_value(PARAM_RAW, 'Status Payment description'),
                'unique_ref'        => new external_value(PARAM_RAW, 'Ref payment'),
                'caregiver_id'      => new external_value(PARAM_RAW, 'Id of caregiver'),
                'payment_timestamp' => new external_value(PARAM_RAW, 'Time payment'),
                'coursename'        => new external_value(PARAM_RAW, 'Name of curse'),
                'price'             => new external_value(PARAM_RAW, 'Price payment'),
                'summary'           => new external_value(PARAM_RAW, 'Summary description course'),
                'studentid'         => new external_value(PARAM_INT, 'Id of student related caregiver'),
            )
        );
    }
    
    /*
     * Function parameters get level and points xp block_level_up
     */
    public static function get_level_points_parameters()
    {
        return new external_function_parameters(
            [
                'token' => new external_value(PARAM_RAW, 'Token Caregiver')
            ]
        );
    }
    
    /*
     * Function get level and points xp block_level_up
     */
    public static function get_level_points($token)
    {
        global $DB;
        
        $webservicelib = new webservice();
        //Get userid and info by token param get
        $authenticationinfo = $webservicelib->authenticate_user($token);
        
        $userid = $authenticationinfo['user']->id;
        
        $datalevel = $DB->get_records('block_xp', array('userid' => $userid));
        
        foreach($datalevel as $level){
            
            $return[] = ['courseid' => $level->courseid,
                        'points'    => $level->xp,
                        'level'     => $level->lvl];
        }
        
        return $return;
    }
    
    /*
     * Function returns level and points xp block_level_up
     */
    public static function get_level_points_returns()
    {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'courseid' => new external_value(PARAM_INT, 'Id course'),
                    'points'   => new external_value(PARAM_RAW, 'Points course level'),
                    'level'    => new external_value(PARAM_RAW, 'Level course'),
                )
            )
        );
    }
    
    /*
     * Function parameters update payment data
     */
    public static function update_payment_data_parameters()
    {
        return new external_function_parameters(
            [
                'courseid'          => new external_value(PARAM_RAW, 'Id of course'),
                'course_status'     => new external_value(PARAM_RAW, 'Status new course payment'),
                'payment_status_str'=> new external_value(PARAM_RAW, 'payment status'),
                'unique_ref'        => new external_value(PARAM_RAW, 'payment id'),
                'payment_timestamp' => new external_value(PARAM_INT, 'payment creation date')
            ]
        );
    }
    
    public static function update_payment_data($courseid,$course_status,$payment_status_str, $unique_ref,$payment_timestamp)
    {
        global $DB;
        
        $payment = $DB->get_record('student_course_payments', array('courseid' => $courseid));
        
        if(!empty($payment)){
            $payment->id = $payment->id;
            $payment->course_status = $course_status;
            $payment->payment_status_str = $payment_status_str;
            $payment->unique_ref = $unique_ref;
            $payment->payment_timestamp = $payment_timestamp;
            $result = $DB->update_record('student_course_payments', $payment);
        }else{
            $result = 0;
        }
        
        return ['result' => $result];
    }
    
    /*
     * Function returns level and points xp block_level_up
     */
    public static function update_payment_data_returns()
    {
        new external_single_structure(
            array(
                'result'   => new external_value(PARAM_INT, 'Result update payment'),
            )
        );
    }
    
    /*
     * Function parameters update payment data
     */
    public static function change_password_parameters()
    {
        return new external_function_parameters(
            [
                'userid'      => new external_value(PARAM_INT, 'Id of course'),
                'newpassword' => new external_value(PARAM_RAW, 'Status new course payment'),
            ]
        );
    }
    
    public static function change_password($userid,$newpassword)
    {
        global $DB;
        
        $userobj = $DB->get_record('user', array('id' => $userid));
        
        //Method get objuser and confirm id newpassword is update
        $updatepass = user_update_password($userobj,$newpassword);
        
        return ['result' => $updatepass];
    }
    
    /*
     * Function returns level and points xp block_level_up
     */
    public static function change_password_returns()
    {
        new external_single_structure(
            array(
                'result'   => new external_value(PARAM_BOOL, 'Result update change password'),
            )
        );
    }
    
    public static function get_theme_settings_parameters(){
       return new external_function_parameters(
            [
                'themename'      => new external_value(PARAM_RAW, ''),
            ]
        );
    }
    
    public static function get_theme_settings($themename)
    {
        global $DB;
        
        $themename = 'theme_edumy';
        $themeobj = $DB->get_records('config_plugins', array('plugin' => $themename));
        
        $result = array();
        $themevalues = array();
        foreach($themeobj as $theme){
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
    
    public static function get_user_filed_parameters(){
        return new external_function_parameters([
            'email' => new external_value(PARAM_RAW, 'Token user logged')]
        );
    }
    
    public static function get_user_filed($email){
        global $DB;
        
        $user = $DB->get_record('user', array('email' => $email));
        
        $response[] = ['id'      => $user->id,
                      'username' => $user->username,
                      'firstname'=> $user->firstname,
                      'lastname' => $user->lastname];
                      
        return $response;
    }
    
    public static function get_user_filed_returns(){
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