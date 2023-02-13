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
require_once($CFG->dirroot . '/files/externallib.php');

/**
 * External function 'local_soluttolms_core_update_user_profile_image' implementation.
 *
 * @package     local_soluttolms_core
 * @category    external
 * @copyright   2022 Solutto Consulting <devs@soluttoconsulting.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_user_profile_image extends external_api {

    /**
     * Describes parameters of the {@see self::execute()} method.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters(
            [
                'userid' => new external_value(PARAM_INT, 'Id of the user'),
            ]
        );
    }

    /**
     * TODO describe what the function actually does.
     *
     * @param int $userid
     * @return mixed TODO document
     */
     public static function execute(int $userid){
        global $CFG, $DB;
        
        // Re-validate parameter.
        [
            'userid' => $userid,
        ] = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
        ]);
        
        // Init the response object
        $response = [];
        
        // It seems that this variable is used below
        $totalsize = 0;
        
        //Init the file object and assign the first attribute (filename)
        $file = new \stdClass;
        $file->filename = clean_param($_FILES['file']['name'], PARAM_FILE);
        
        // check system maxbytes setting
        if ($_FILES['file']['size']> get_max_upload_file_size($CFG->maxbytes)) {
            // oversize file will be ignored, error added to array to notify
            $response['error']['errorCode'] = 'fileoversized';
            return ['response' => json_encode(array_values($response))];
        } 
        
        $file->filepath = $_FILES['file']['tmp_name'];
        // calculate total size of upload
        $totalsize += $_FILES['file']['size'];


        //Get the context object for later use
        $context = \context_user::instance($userid);
        
        // Get any existing file size limits.
        $maxupload = get_user_max_upload_file_size($context, $CFG->maxbytes);
            
        // Check the size of this upload.
        if ($maxupload !== USER_CAN_IGNORE_FILE_SIZE_LIMITS && $totalsize > $maxupload) {
            $response['error']['errorCode'] = 'userquotalimit';
            return ['response' => json_encode(array_values($response))];
            // throw new file_exception('userquotalimit');
        }
        
        $filetype = $_FILES['file']['type'];
        

        if($filetype != "image/jpeg" && $filetype != "image/png" && $filetype != "image/jpg"){
            $response['error']['errorCode'] = 'invalidformat';
            return ['response' => json_encode(array_values($response))];
        }
        
        
        $itemid = file_get_unused_draft_itemid();
        
        //Complete the file object
        $file->component = 'user';
        $file->contextid = $context->id;
        $file->userid    = $userid;
        $file->filearea  = 'draft';
        // $file->filename  = $_FILES['file']['name'];
        $file->filepath  = '/';
        $file->itemid    = $itemid;
        $file->license   = $CFG->sitedefaultlicense;
        $file->author    = fullname($authenticationinfo['user']);
        $file->source    = serialize((object)array('source' =>  $_FILES['file']['name']));
            
        
        //Check if the file already exist
        $fs = get_file_storage();
        $existingfile = $fs->file_exists($file->contextid, $file->component, $file->filearea,
                    $file->itemid, $file->filepath, $file->filename);
        
        if($existingfile) {
            $response['error']['errorCode'] = 'filealreadyexist';
            return ['response' => json_encode(array_values($response))];
        }
        
        $stored_file = $fs->create_file_from_pathname($file,  $_FILES['file']['tmp_name']);
        //Method for update profile picture
        $updatedimage = \core_user_external::update_picture($file->itemid,true,$userid);
        
        if(empty($updatedimage['profileimageurl'])){
            $response['error']['errorCode'] = 'errorupdatingpicture';
            return ['response' => json_encode(array_values($response))];
        }
              
        $response['success']['url'] = $updatedimage['profileimageurl'];
        
        return ['response' => json_encode(array_values($response))];
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
                'response' => new external_value(PARAM_RAW, 'Image URL if everything goes well, error code if something goes wrong.')
            )
        );
        
    }

}