<?php

/**
 * Change a user's password.
 *
 * @param  stdClass  $user      User table object
 * @param  string  $newpassword Plaintext password
 * @return bool                 True on success
 */
function user_update_password($user, $newpassword) {
    global $DB;
    
    $userauth = get_auth_plugin($user->auth);

    if ($userauth->is_internal()) {
        $puser = $DB->get_record('user', array('id'=>$user->id), '*', MUST_EXIST);
        // This will also update the stored hash to the latest algorithm
        // if the existing hash is using an out-of-date algorithm (or the
        // legacy md5 algorithm).
        if (update_internal_user_password($puser, $newpassword)) {
            $user->password = $puser->password;
            return true;
        } else {
            return false;
        }
    } else {
        // We should have never been called!
        return false;
    }
}

function theme_get_revision_cre() {
    global $CFG;

    if (empty($CFG->themedesignermode)) {
        if (empty($CFG->themerev)) {
            // This only happens during install. It doesn't matter what themerev we use as long as it's positive.
            return 1;
        } else {
            return $CFG->themerev;
        }

    } else {
        return -1;
    }
}


function get_files_theme($component){
    global $CFG;
    
    $fs = get_file_storage();
    $context = \context_system::instance();
    $files = $fs->get_area_files($context->id, 'theme_edumy', $component);
    $imageurl = '';
    foreach ($files as $file) {
        if ($file->is_valid_image()) {
            $imagepath = '/' . $file->get_contextid() .
                    '/' . $file->get_component() .
                    '/' . $file->get_filearea() .
                    '/' . $itemid = theme_get_revision_cre() .
                    '/' . $file->get_filename();
            $imageurl = file_encode_url($CFG->wwwroot . '/pluginfile.php', $imagepath,
                    false);
            break;
        }
    }
    return $imageurl;
}
