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
 * This CLI script restores a site from the S3 bucket.
 *
 * @package    local_soluttolms_core
 * @copyright  2022 Gilson R <dev@soluttoconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Include the aws sdk.
require_once($CFG->dirroot . '/local/soluttolms_core/lib/aws/aws-autoloader.php');

// Including the restore_db file.
require_once($CFG->dirroot . '/local/soluttolms_core/lib/restore_db.php');

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

// Setting some variables.
$restoredirectory = 'restore';

// Let's create the restore directory if it doesn't exist.
if (!file_exists($restoredirectory)) {
    mkdir($restoredirectory, 0777, true);
}

// We can't continue if the restore directory doesn't exist.
if (!file_exists($restoredirectory)) {
    echo "The restore directory doesn't exist. Please create it and try again." . PHP_EOL;
    exit(1);
}

// If the db.sql file exists, let's delete it.
if (file_exists($restoredirectory . '/db.sql')) {
    unlink($restoredirectory . '/db.sql');
}

// Do the same for the moodledata.tar.gz file.
if (file_exists($restoredirectory . '/moodledata.tar.gz')) {
    unlink($restoredirectory . '/moodledata.tar.gz');
}

// Create the S3Client.
try {
    $s3 = new S3Client([
        'version' => $CFG->aws['version'],
        'region' => $CFG->aws['region'],
        'credentials' => [
            'key' => $CFG->aws['key'],
            'secret' => $CFG->aws['secret'],
        ],
    ]);
} catch (S3Exception $e) {
    echo "There was an error connecting to the S3 bucket: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Let's get the db.sql file from the bucket.
try {
    echo "Downloading the db.sql file from the S3 bucket..." . PHP_EOL;
    $result = $s3->getObject([
        'Bucket' => $CFG->aws['bucket'],
        'Key' => 'db.sql',
        'SaveAs' => $restoredirectory . '/db.sql',
    ]);
    echo "The db.sql file was downloaded successfully." . PHP_EOL;
} catch (S3Exception $e) {
    echo "There was an error getting the db.sql file from the S3 bucket: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Let's get the moodledata.tar.gz file from the bucket.
try {
    echo "Downloading the moodledata.tar.gz file from the S3 bucket..." . PHP_EOL;
    $result = $s3->getObject([
        'Bucket' => $CFG->aws['bucket'],
        'Key' => 'moodledata.tar.gz',
        'SaveAs' => $restoredirectory . '/moodledata.tar.gz',
    ]);
    echo "The moodledata.tar.gz file was downloaded successfully." . PHP_EOL;
} catch (S3Exception $e) {
    echo "There was an error getting the moodledata.tar.gz file from the S3 bucket: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Let's get the list of tables in the database.
echo "Getting the list of tables in the database..." . PHP_EOL;
$tables = $DB->get_tables();

// Let's drop all the tables in the database.
foreach ($tables as $table) {
    echo "Dropping the table {$table}..." . PHP_EOL;
    $DB->get_manager()->drop_table(new xmldb_table($table));
}

// Let's restore the database.
echo "Restoring the database..." . PHP_EOL;
restoreDatabaseTables(
    $CFG->dbhost,
    $CFG->dbuser,
    $CFG->dbpass,
    $CFG->dbname,
    $restoredirectory . '/db.sql'
);

// Let's delete all the files in the moodledata directory.
echo "Deleting all the files in the moodledata directory..." . PHP_EOL;
$files = glob($CFG->dataroot . '/*');
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    } else {
        exec("rm -Rf $file");
    }
}

// Let's extract the moodledata.tar.gz file.
echo "Extracting the moodledata.tar.gz file..." . PHP_EOL;
exec("tar -xzf {$restoredirectory}/moodledata.tar.gz -C {$CFG->dataroot}");

// Let's delete the moodledata.tar.gz file.
echo "Deleting the moodledata.tar.gz file..." . PHP_EOL;
unlink($restoredirectory . '/moodledata.tar.gz');

// Let's delete the db.sql file.
echo "Deleting the db.sql file..." . PHP_EOL;
unlink($restoredirectory . '/db.sql');

// Let's purge the cache.
echo "Purging the cache..." . PHP_EOL;
purge_all_caches();

echo "The site was restored successfully." . PHP_EOL;
