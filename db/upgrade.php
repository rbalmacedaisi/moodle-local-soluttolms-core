<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade steps are defined here.
 *
 * @package     local_soluttolms_core
 * @category    upgrade
 * @copyright   2022 Gilson Ricnón <gilson.rincon@soluttoconsulting.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute local_soluttolms_core upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_soluttolms_core_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Let's add some indexes to some tables.
    if ($oldversion < 2022092000) {

        // Do the same with some moodle tables.
        $table = new xmldb_table('files');
        $index = new xmldb_index('mdl_file_filename_ix', XMLDB_INDEX_NOTUNIQUE, ['filename']);

        if (!$dbman->index_exists($table, $index) && $dbman->table_exists($table)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('course_modules');
        $index = new xmldb_index('mdl_courmodu_section_ix', XMLDB_INDEX_NOTUNIQUE, ['section']);

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('glossary_entries');
        $index = new xmldb_index('mdl_glosentr_src_ix', XMLDB_INDEX_NOTUNIQUE, ['sourceglossaryid']);

        if (!$dbman->index_exists($table, $index) && $dbman->table_exists($table)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('bigbluebuttonbn_logs');
        $index = new xmldb_index('mdl_bigblogs_uid_ix', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->index_exists($table, $index) && $dbman->table_exists($table)) {
            $dbman->add_index($table, $index);
        }

        // Userartifacts savepoint reached.
        upgrade_plugin_savepoint(true, 2022092000, 'local', 'soluttolms_core');
    }

    return true;
}
