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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . "/course/lib.php");
require_once($CFG->libdir . '/completionlib.php');

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

function core_get_course_image($courseid)
{
   $url = '';
   require_once( $CFG->libdir . '/filelib.php' );

   $context = context_course::instance( $courseid );
   $fs = get_file_storage();
   $files = $fs->get_area_files( $context->id, 'course', 'overviewfiles', 0 );

   foreach ( $files as $f ){
        if ( $f->is_valid_image() ){
            $url = moodle_url::make_pluginfile_url( $f->get_contextid(), $f->get_component(), $f->get_filearea(), null, $f->get_filepath(), $f->get_filename(), false );
        }
   }
   return $url;
}

/**
 * Get course Info fields
 * Param int courseid
 **/
function get_course_metadata($courseid) {
    $handler = \core_customfield\handler::get_handler('core_course', 'course');
    $datas = $handler->get_instance_data($courseid);
    $metadata = [];
    foreach ($datas as $data) {
        $cat = $data->get_field()->get('shortname');
        $fullname = $data->get_field()->get('name');
        $metadata[$data->get_field()->get('shortname')]['shortname'] = $cat;
        $metadata[$data->get_field()->get('shortname')]['name'] = $fullname;
        $metadata[$data->get_field()->get('shortname')]['value'] = strip_tags($data->get_value());
    }
    return $metadata;
}


function get_modules_and_sections($courseid, $userid){
    global $PAGE, $CFG;
    
    $context = context_course::instance($courseid, IGNORE_MISSING);
    $course = get_course($courseid);

    $modinfo = get_fast_modinfo($course);
    $sections = $modinfo->get_section_info_all();
    $courseformat = course_get_format($course);
    $coursenumsections = $courseformat->get_last_section_number();
    $stealthmodules = array();   // Array to keep all the modules available but not visible in a course section/topic.
    $options = array();
    $completioninfo = new completion_info($course);

    //for each sections (first displayed to last displayed)
    $modinfosections = $modinfo->get_sections();
    foreach ($sections as $key => $section) {
        // This becomes true when we are filtering and we found the value to filter with.
        $sectionfound = false;

        // reset $sectioncontents
        $sectionvalues = array();
        $sectionvalues['id'] = $section->id;
        $sectionvalues['name'] = get_section_name($course, $section);
        $sectionvalues['visible'] = $section->visible;

        $options = (object) array('noclean' => true);
        list($sectionvalues['summary'], $sectionvalues['summaryformat']) =
                external_format_text($section->summary, $section->summaryformat,
                        $context->id, 'course', 'section', $section->id, $options);
        $sectionvalues['section'] = $section->section;
        $sectionvalues['hiddenbynumsections'] = $section->section > $coursenumsections ? 1 : 0;
        $sectionvalues['uservisible'] = $section->uservisible;
        if (!empty($section->availableinfo)) {
            $sectionvalues['availabilityinfo'] = \core_availability\info::format_info($section->availableinfo, $course);
        }
        
        $sectioncontents = array();

        // For each module of the section.
        foreach ($modinfosections[$section->section] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            $cminfo = cm_info::create($cm);


            // Stop here if the module is not visible to the user on the course main page:
            // The user can't access the module and the user can't view the module on the course page.
            if (!$cm->uservisible && !$cm->is_visible_on_course_page()) {
                continue;
            }

            // This becomes true when we are filtering and we found the value to filter with.
            $modfound = false;


            $module = array();

            $modcontext = context_module::instance($cm->id);

            //common info (for people being able to see the module or availability dates)
            $module['id'] = $cm->id;
            $module['name'] = external_format_string($cm->name, $modcontext->id);
            $module['instance'] = $cm->instance;
            $module['contextid'] = $modcontext->id;
            $module['modname'] = (string) $cm->modname;
            $module['modplural'] = (string) $cm->modplural;
            $module['modicon'] = $cm->get_icon_url()->out(false);
            $module['indent'] = $cm->indent;
            $module['onclick'] = $cm->onclick;
            $module['afterlink'] = $cm->afterlink;
            $module['customdata'] = json_encode($cm->customdata);
            $module['completion'] = $cm->completion;
            $module['downloadcontent'] = $cm->downloadcontent;
            $module['noviewlink'] = plugin_supports('mod', $cm->modname, FEATURE_NO_VIEW_LINK, false);
  

            // Check module completion.
            $completion = $completioninfo->is_enabled($cm);
            if ($completion != COMPLETION_DISABLED) {
                $exporter = new \core_completion\external\completion_info_exporter($course, $cm, $userid);
                $renderer = $PAGE->get_renderer('core');
                $modulecompletiondata = (array)$exporter->export($renderer);
                $module['completiondata'] = $modulecompletiondata;
            }

            // Always expose formatted intro/description when available so client UIs can render
            // activity context consistently, even if "showdescription" is disabled.
            if (!empty(trim(strip_tags((string)$cm->content)))) {
                $options = array('noclean' => true);
                list($module['description'], $descriptionformat) = external_format_text($cm->content,
                    FORMAT_HTML, $modcontext->id, $cm->modname, 'intro', $cm->id, $options);
            }

            //url of the module
            $url = $cm->url;
            if ($url) { //labels don't have url
                $module['url'] = $url->out(false);
            }

            $canviewhidden = has_capability('moodle/course:viewhiddenactivities',
                                context_module::instance($cm->id));
            //user that can view hidden module should know about the visibility
            $module['visible'] = $cm->visible;
            $module['visibleoncoursepage'] = $cm->visibleoncoursepage;
            $module['uservisible'] = $cm->uservisible;
            // BBB sessions can be intentionally restricted by date/group but still
            // shown on course page (with availability info). Keep them in the
            // payload so "Sesiones Virtuales" can render upcoming sessions.
            if ($cm->modname === 'bigbluebuttonbn' && !$cm->uservisible && $cm->is_visible_on_course_page()) {
                $module['uservisible'] = true;
            }
            if (!empty($cm->availableinfo)) {
                $module['availabilityinfo'] = \core_availability\info::format_info($cm->availableinfo, $course);
            }

            // Availability date (also send to user who can see hidden module).
            if ($CFG->enableavailability && ($canviewhidden)) {
                $module['availability'] = $cm->availability;
            }
            
            $sectioncontents[] = $module;
        }
        $stealthmodules[] = $module;
        
        $sectionvalues['modules'] = $sectioncontents;

        // assign result to $coursecontents
        $coursecontents[$key] = $sectionvalues;
    }
    
    foreach ($coursecontents as $sectionnumber => $sectioncontents) {
        $section = $sections[$sectionnumber];

        if (!$courseformat->is_section_visible($section)) {
            unset($coursecontents[$sectionnumber]);
            continue;
        }

        // Remove section and modules information if the section is not visible for the user.
        if (!$section->uservisible) {
            $coursecontents[$sectionnumber]['modules'] = array();
            // Remove summary information if the section is completely hidden only,
            // even if the section is not user visible, the summary is always displayed among the availability information.
            if (!$section->visible) {
                $coursecontents[$sectionnumber]['summary'] = '';
            }
        }
    }

    // Include stealth modules in special section (without any info).
    if (!empty($stealthmodules)) {
        $coursecontents[] = array(
            'id' => -1,
            'name' => '',
            'summary' => '',
            'summaryformat' => FORMAT_MOODLE,
            'modules' => $stealthmodules
        );
    }

    return $coursecontents;
}
