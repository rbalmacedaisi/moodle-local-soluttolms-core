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
 * Script to back-jp a moodle instance.
 *
 * @package    local_solutto_core
 * @copyright  2022 Gilson R <dev@soluttoconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', 1);

require_once('../../../config.php');

$filename = 'moodledata.tar.gz';
$temp_dirname = 'backup';
$temp_dirpath = getcwd().'/'.$temp_dirname;

echo "Deleting folders from moodledata...";
exec("rm -Rf {$CFG->dataroot}/cache");
exec("rm -Rf {$CFG->dataroot}/localcache");
exec("rm -Rf {$CFG->dataroot}/sessions");
exec("rm -Rf {$CFG->dataroot}/temp");
exec("rm -Rf {$CFG->dataroot}/localcache");
exec("rm -Rf {$CFG->dataroot}/lock");
exec("rm -Rf {$CFG->dataroot}/muc");
exec("rm -Rf {$CFG->dataroot}/trashdir");

echo "Creating the backup directory...".PHP_EOL;

if(!mkdir($temp_dirname)){
    echo "Error: The backup dir can not be created.";
    exit(1);
}

if(!chdir($CFG->dataroot)){
    echo "Error: Can't CD to the moodledata folder";
    exit(1);
}

echo "We are readty to start zipping the Moodledata folder...".PHP_EOL;

echo system('ls -l').PHP_EOL;
echo system("tar -czvf $filename .").PHP_EOL;

echo "Moving moodledata.zip to backup folder".PHP_EOL;
exec("mv {$filename} {$temp_dirpath}");

if(!chdir($temp_dirpath)){
    echo "Error: Can't CD to the backup folder";
    exit(1);
}

echo "Truncating tables...".PHP_EOL;
system("mysql -h{$CFG->dbhost} -u{$CFG->dbuser} -p'$CFG->dbpass}' -e \"TRUNCATE mdl_logstore_standard_log\"");
system("mysql -h{$CFG->dbhost} -u{$CFG->dbuser} -p'$CFG->dbpass}' -e \"TRUNCATE mdl_config_log\"");
system("mysql -h{$CFG->dbhost} -u{$CFG->dbuser} -p'$CFG->dbpass}' -e \"TRUNCATE mdl_log_display\"");
system("mysql -h{$CFG->dbhost} -u{$CFG->dbuser} -p'$CFG->dbpass}' -e \"TRUNCATE mdl_upgrade_log\"");
system("mysql -h{$CFG->dbhost} -u{$CFG->dbuser} -p'$CFG->dbpass}' -e \"TRUNCATE mdl_sessions\"");

echo "Creating DB sql file...".PHP_EOL;

exec("mysqldump -h{$CFG->dbhost} -u{$CFG->dbuser} -p'{$CFG->dbpass}' {$CFG->dbname} >> db.sql");

echo "Process finished.".PHP_EOL;

echo "You can download your files using these links:".PHP_EOL;
echo "Moodledata: {$CFG->wwwroot}/local/soluttolms_core/cli/{$temp_dirname}/{$filename}".PHP_EOL;
echo "Database: {$CFG->wwwroot}/local/soluttolms_core/cli/{$temp_dirname}/db.sql".PHP_EOL;
