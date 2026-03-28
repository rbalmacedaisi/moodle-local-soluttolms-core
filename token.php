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

// Step 1: Send CORS header BEFORE loading Moodle.
// If config.php sends a redirect (302) for any reason, this header is already
// in the response so the browser won't get a CORS error.
// We reflect the request Origin; after config.php loads we overwrite with $CFG->appurl.
$_cors_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($_cors_origin !== '') {
    header('Access-Control-Allow-Origin: ' . $_cors_origin);
    header('Access-Control-Allow-Credentials: true');
}
unset($_cors_origin);

// Step 2: Remove MoodleSession cookies so config.php starts a fresh session.
// Also send Set-Cookie headers with past expiry to DELETE them from the browser —
// without this, the user's browser keeps sending the cookie on every request.
foreach (array_keys($_COOKIE) as $_cookie_name) {
    if (strncmp($_cookie_name, 'MoodleSession', 13) === 0) {
        unset($_COOKIE[$_cookie_name]);
        setcookie($_cookie_name, '', [
            'expires'  => time() - 86400,
            'path'     => '/',
            'httponly' => true,
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);
    }
}
unset($_cookie_name);

define('AJAX_SCRIPT', true);
define('REQUIRE_CORRECT_ACCESS', true);
define('NO_MOODLE_COOKIES', false);


require_once('../../config.php');
require_once($CFG->libdir . '/externallib.php');

// Overwrite with the configured origin now that $CFG is available.
header('Access-Control-Allow-Origin: ' . $CFG->appurl);
header('Access-Control-Allow-Credentials: true');

if (!$CFG->enablewebservices) {
    throw new moodle_exception('enablewsdescription', 'webservice');
}

// This script is used by the mobile app to check that the site is available and web services
// are allowed. In this mode, no further action is needed.
if (optional_param('appsitecheck', 0, PARAM_INT)) {
    echo json_encode((object)['appsitecheck' => 'ok']);
    exit;
}

$username = required_param('username', PARAM_USERNAME);
$password = required_param('password', PARAM_RAW);
$serviceshortname  = required_param('service',  PARAM_ALPHANUMEXT);

$username = trim(core_text::strtolower($username));
if (is_restored_user($username)) {
    throw new moodle_exception('restoredaccountresetpassword', 'webservice');
}

$systemcontext = context_system::instance();

$reason = null;
$user = authenticate_user_login($username, $password, false, $reason, false);
if (!empty($user)) {

    // Cannot authenticate unless maintenance access is granted.
    $hasmaintenanceaccess = has_capability('moodle/site:maintenanceaccess', $systemcontext, $user);
    if (!empty($CFG->maintenance_enabled) and !$hasmaintenanceaccess) {
        throw new moodle_exception('sitemaintenance', 'admin');
    }

    if (isguestuser($user)) {
        throw new moodle_exception('noguest');
    }
    if (empty($user->confirmed)) {
        throw new moodle_exception('usernotconfirmed', 'moodle', '', $user->username);
    }
    // check credential expiry
    $userauth = get_auth_plugin($user->auth);
    if (!empty($userauth->config->expiration) and $userauth->config->expiration == 1) {
        $days2expire = $userauth->password_expire($user->username);
        if (intval($days2expire) < 0 ) {
            throw new moodle_exception('passwordisexpired', 'webservice');
        }
    }

    // let enrol plugins deal with new enrolments if necessary
    enrol_check_plugins($user);

    // setup user session to check capability
    \core\session\manager::set_user($user);

    //check if the service exists and is enabled
    $service = $DB->get_record('external_services', array('shortname' => $serviceshortname, 'enabled' => 1));
    if (empty($service)) {
        // will throw exception if no token found
        throw new moodle_exception('servicenotavailable', 'webservice');
    }

    // Get an existing token or create a new one.
    $token = external_generate_token_for_current_user($service);
    $privatetoken = $token->privatetoken;
    external_log_token_request($token);

    $siteadmin = has_capability('moodle/site:config', $systemcontext, $USER->id);

    $usertoken = new stdClass;
    $usertoken->token = $token->token;
    // Private token, only transmitted to https sites and non-admin users.
    if (is_https() and !$siteadmin) {
        $usertoken->privatetoken = $privatetoken;
    } else {
        $usertoken->privatetoken = null;
    }
    
    //Get user profile data, field studentstatus
    $field = $DB->get_record('user_info_field', array('shortname' => 'studentstatus'));
    
    //Get user info data Custom Fields
    $user_info_data = $DB->get_record_sql("
        SELECT d.*
        FROM {user_info_data} d
        JOIN {user} u ON u.id = d.userid
        WHERE d.fieldid = ? AND u.deleted = 0 AND d.userid = ?
    ", array($field->id, $user->id));
    
    
    $customfield_value = $user_info_data->data;
    
    if(empty($customfield_value)){
        $customfield_value = 'Activo';
    }

    // Let's see if the current user has any of the folloing roles:
    // - Teacher.
    // - Manager.
    // - Non-editing teacher.
    // - Course creator.
    $ismanager = false;

    $sql = "SELECT r.shortname
              FROM {role_assignments} ra, {role} r
             WHERE ra.userid = ? AND ra.roleid = r.id
                    AND r.shortname IN ('teacher', 'manager', 'editingteacher', 'coursecreator')";

    $userroles = $DB->get_records_sql($sql , array($user->id));
    if(!empty($userroles)){
        $ismanager = true;
    }

    // If the current user is a site administrator.
    if (is_siteadmin($user->id)) {
        $ismanager = true;
    }

    // Let's create a return object with the needed information.
    $returns = array();
    $returns['username'] = $user->username;
    $returns['firstname'] = $user->firstname;
    $returns['lastname'] = $user->lastname;
    $returns['fullname'] = fullname($user);
    $returns['email'] = $user->email;
    $returns['userid'] = $user->id;
    $returns['usertoken'] = $usertoken;
    $returns['manager'] = $ismanager;
    $returns['userstatus'] = $customfield_value;
    echo json_encode($returns);
} else {
    throw new moodle_exception('invalidlogin');
}
