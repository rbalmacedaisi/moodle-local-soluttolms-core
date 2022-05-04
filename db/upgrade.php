<?php
/*
 * This file is part of Totara Learn
 *
 * Copyright (C) 2018 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Yuliya Bozhko <yuliya.bozhko@totaralearning.com>
 *
 * @package block_course_navigation
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_student_core_upgrade($oldversion) {
    global $DB;
  
    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes
    if ($oldversion < 2021120900) {

        // Define table student_course_payments to be created.
        $table = new xmldb_table('student_course_payments');

        // Adding fields to table student_course_payments.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('course_status', XMLDB_TYPE_CHAR, '32', null, null, null, null);
        $table->add_field('payment_status_id', XMLDB_TYPE_INTEGER, '5', null, null, null, null);
        $table->add_field('payment_status_str', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('unique_ref', XMLDB_TYPE_CHAR, '254', null, null, null, null);
        $table->add_field('caregiver_id', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('payment_timestamp', XMLDB_TYPE_INTEGER, '12', null, null, null, null);

        // Adding keys to table student_course_payments.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Conditionally launch create table for student_course_payments.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // student_core savepoint reached.
        upgrade_plugin_savepoint(true, 2021120900, 'local', 'student_core');
    }
    
    if ($oldversion < 2021122100) {

        // Define field id to be added to student_course_payments.
        $table = new xmldb_table('student_course_payments');
        $field = new xmldb_field('coursename', XMLDB_TYPE_CHAR, '255', null, null, null, null, null);
        
        // Conditionally launch add field coursename.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('price', XMLDB_TYPE_CHAR, '60', null, null, null, null, null);
        
        // Conditionally launch add field price.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        $field = new xmldb_field('summary', XMLDB_TYPE_CHAR, '255', null, null, null, null, null);
        
        // Conditionally launch add field summary.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // student_core savepoint reached.
        upgrade_plugin_savepoint(true, 2021122100, 'local', 'student_core');
    }
    
    if ($oldversion < 2022010300) {

        // Define field id to be added to student_course_payments.
        $table = new xmldb_table('student_course_payments');
        $field = new xmldb_field('studentid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, null);
        
        // Conditionally launch add field coursename.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    
        // student_core savepoint reached.
        upgrade_plugin_savepoint(true, 2022010300, 'local', 'student_core');
    }

    return true;
}
