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

use context_system;
use core_component;
use external_api;
use external_description;
use external_function_parameters;
use external_single_structure;
use external_value;
use theme_config;

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir . '/externallib.php';

/**
 * External function 'local_soluttolms_core_get_theme_settings' implementation.
 *
 * @package     local_soluttolms_core
 * @category    external
 * @copyright   2022 Solutto Consulting <devs@soluttoconsulting.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_theme_settings extends external_api
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
                'themename' => new external_value(PARAM_TEXT, 'The name of the theme from where we want to get the settings'),
            ]
        );
    }

    /**
     * TODO describe what the function actually does.
     *
     * @param int $userid
     * @return mixed TODO document
     */
    public static function execute(string $themename)
    {
        // Re-validate parameter.
        [
            'themename' => $themename,
        ] = self::validate_parameters(self::execute_parameters(), [
            'themename' => $themename,
        ]);

        // Declare global variables.
        global $DB, $CFG;

        // Get the list of installed themes.
        $themes = core_component::get_plugin_list('theme');

        // Let's see if thepassed theme is installed in the system.
        if (!array_key_exists($themename, $themes)) {
            // Select the first theme in the list.
            $themename = array_keys($themes)[0];
        }

        // Let's get the theme settings.
        $theme = theme_config::load($themename);
        $themeobj = $theme->settings;

        // If themeobj has the loginbackgroundimage property, we need to get the file basename.
        if (isset($themeobj->loginbackgroundimage) && !empty($themeobj->loginbackgroundimage)) {
            // Let's do an extra check to see if the file exists.
            if (!file_exists($CFG->dirroot . '/theme/' . $themename . '/pix/static/' . basename($themeobj->loginbackgroundimage))) {
                // Let's generate the file again.
                require_once($CFG->dirroot . '/theme/' . $themename . '/lib.php');
                
                // Execute the generate_static_images only if it exists.
                if (function_exists('generate_static_images')) {
                    generate_static_images();
                }
            }

            $themeobj->loginbackgroundimage = basename($themeobj->loginbackgroundimage);

            // Add the static URL to the background image.
            $themeobj->loginbackgroundimageurl = $CFG->wwwroot . '/theme/' . $themename . '/pix/static/' . rawurlencode($themeobj->loginbackgroundimage);
        }

        /***************************************************/
        // Let's do the same for the favicon image.
        /***************************************************/

        // If themeobj has the favicon property, we need to get the file basename.
        if (isset($themeobj->favicon) && !empty($themeobj->favicon)) {
            // Let's do an extra check to see if the file exists.
            if (!file_exists($CFG->dirroot . '/theme/' . $themename . '/pix/static/' . basename($themeobj->favicon))) {
                // Let's generate the file again.
                require_once($CFG->dirroot . '/theme/' . $themename . '/lib.php');
                
                // Execute the generate_static_images only if it exists.
                if (function_exists('generate_static_images')) {
                    generate_static_images();
                }
            }
            
            $themeobj->favicon = basename($themeobj->favicon);

            // Add the static URL to the favicon image.
            $themeobj->faviconurl = $CFG->wwwroot . '/theme/' . $themename . '/pix/static/' . rawurlencode($themeobj->favicon);
        }

        /*****************************************************************/
        // Let's do the same process for the core_admin logo image.
        /*****************************************************************/

        $logo = get_config('core_admin', 'logo');

        // If core_adminlogo is not empty, we need to generate the static image.
        if (!empty($logo)) {
            // Let's do an extra check to see if the file exists.
            if (!file_exists($CFG->dirroot . '/theme/' . $themename . '/pix/static/' . basename($logo))){
                // Getting the file record core_adminlogo.
                $filerecord = $DB->get_record('files', [
                    'filename' => basename($logo),
                    'component' => 'core_admin',
                    'filearea' => 'logo',
                    'contextid' => context_system::instance()->id,
                    'filepath' => '/',
                ]);

                // If we have a file record, we can generate the static image.
                if (isset($filerecord->id)) {

                    // Load file storage from file record.
                    $fs = get_file_storage();

                    // Loading the core_admin logo image.
                    $file = $fs->get_file_by_id($filerecord->id);

                    // Let's see if the file exists in the static folder.
                    $filepath = $CFG->dirroot . '/theme/' . $themename . '/pix/static/' . basename($logo);

                    // If the file doesn't exist, we need to generate it.
                    if (!file_exists($filepath)) {

                        // Save the file in the pix folder.
                        $file->copy_content_to($CFG->dirroot . '/theme/soluttolmsadmin/pix/static/' . $filerecord->filename);
                    }

                    // Return the url of the logo.
                    $themeobj->logourl = $CFG->wwwroot . '/theme/' . $themename . '/pix/static/' . rawurlencode($filerecord->filename);
                }
            } else {
                // Return the url of the logo.
                $themeobj->logourl = $CFG->wwwroot . '/theme/' . $themename . '/pix/static/' . rawurlencode($logo);
            }
        }

        /*****************************************************************
         * Let's do the same process for the core_admin logocompact image.
         * 
         * IMPORTANT: This image will be used as the "light" version
         * of the main logo.
         *****************************************************************/

        $logocompact = get_config('core_admin', 'logocompact');

        // If core_adminlogocompact is not empty, we need to generate the static image.
        if (!empty($logocompact)) {
            // Let's do an extra check to see if the file exists.
            if (!file_exists($CFG->dirroot . '/theme/' . $themename . '/pix/static/' . basename($lologocompactgo))){

                // Getting the file record core_adminlogocompact.
                $filerecord = $DB->get_record('files', [
                    'filename' => basename($logocompact),
                    'component' => 'core_admin',
                    'filearea' => 'logocompact',
                    'contextid' => context_system::instance()->id,
                    'filepath' => '/',
                ]);

                // If we have a file record, we can generate the static image.
                if (isset($filerecord->id)) {

                    // Load file storage from file record.
                    $fs = get_file_storage();

                    // Loading the core_admin logo image.
                    $file = $fs->get_file_by_id($filerecord->id);

                    // Let's see if the file exists in the static folder.
                    $filepath = $CFG->dirroot . '/theme/' . $themename . '/pix/static/' . basename($logo);

                    // If the file doesn't exist, we need to generate it.
                    if (!file_exists($filepath)) {

                        // Save the file in the pix folder.
                        $file->copy_content_to($CFG->dirroot . '/theme/soluttolmsadmin/pix/static/' . $filerecord->filename);
                    }

                    // Return the url of the logo.
                    $themeobj->logocompact = $CFG->wwwroot . '/theme/' . $themename . '/pix/static/' . rawurlencode($filerecord->filename);
                }
            } else {
                // Return the url of the logo.
                $themeobj->logocompact = $CFG->wwwroot . '/theme/' . $themename . '/pix/static/' . rawurlencode($logocompact);
            }
        }

        // Adding the name of the site to the theme settings.
        $themeobj->sitename = $SITE->fullname;

        // Adding a Solutto Copyright to the theme settings.
        $themeobj->soluttocopyright = 'Solutto Consulting LLC. © ' . date('Y');

        /***************************************************/
        // Let's do the same for the logo image.
        /***************************************************/

        // If themeobj has the logo property, we need to get the file basename.
        if (isset($themeobj->logo) && !empty($themeobj->logo)) {
            // Let's do an extra check to see if the file exists.
            if (!file_exists($CFG->dirroot . '/theme/' . $themename . '/pix/static/' . basename($themeobj->logo))) {
                // Let's generate the file again.
                require_once($CFG->dirroot . '/theme/' . $themename . '/lib.php');
                
                // Execute the generate_static_images only if it exists.
                if (function_exists('generate_static_images')) {
                    generate_static_images();
                }
            }

            $themeobj->logo = basename($themeobj->logo);

            // Add the static URL to the logo image.
            $themeobj->logodefaulturl = $CFG->wwwroot . '/theme/' . $themename . '/pix/static/' . rawurlencode($themeobj->logo);
        }

        /***************************************************/
        // Let's do the same for the logodark image.
        /***************************************************/

        // If themeobj has the logodark property, we need to get the file basename.
        if (isset($themeobj->logodark) && !empty($themeobj->logodark)) {
            // Let's do an extra check to see if the file exists.
            if (!file_exists($CFG->dirroot . '/theme/' . $themename . '/pix/static/' . basename($themeobj->logodark))) {
                // Let's generate the file again.
                require_once($CFG->dirroot . '/theme/' . $themename . '/lib.php');
                
                // Execute the generate_static_images only if it exists.
                if (function_exists('generate_static_images')) {
                    generate_static_images();
                }
            }

            $themeobj->logodark = basename($themeobj->logodark);

            // Add the static URL to the logodark image.
            $themeobj->logodarkurl = $CFG->wwwroot . '/theme/' . $themename . '/pix/static/' . rawurlencode($themeobj->logodark);
        }

        return ['themeobject' => json_encode($themeobj)];
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
                'themeobject' => new external_value(PARAM_RAW, 'A JSON string representation of the object containing the settings for the selected theme.'),
            )
        );
    }
}
