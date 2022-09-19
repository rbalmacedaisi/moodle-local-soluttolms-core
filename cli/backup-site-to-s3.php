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
 * This CLI script takes a copy of the site and put it in a S3 bucket.
 *
 * @package    local_soluttolms_core
 * @copyright  2022 Gilson R <dev@soluttoconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

// We will proceed only if the config.php file exists.
if (!file_exists(__DIR__ . '/../../../config.php')) {
    echo "The config.php file does not exist. Please, create it.".PHP_EOL;
    exit(1);
}

// Validate the config file integrity.
try {
    require(__DIR__ . '/../../../config.php');
} catch (Exception $e) {
    echo $e->errorcode . PHP_EOL;
    return(1);
}

require_once($CFG->libdir . '/clilib.php');

// Include the aws sdk.
require_once($CFG->dirroot . '/local/soluttolms_core/lib/aws/aws-autoloader.php');

// Including dumper class.
require_once($CFG->dirroot . '/local/soluttolms_core/lib/dbdump/dumper.php');

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\ObjectUploader;
use Aws\S3\MultipartUploader;
use Aws\Exception\MultipartUploadException;

// Setting some variables.
$filename = 'moodledata.tar.gz';
$temp_dirname = 'backup';
$temp_dirpath = getcwd() . '/' . $temp_dirname;

echo "Purge caches...".PHP_EOL;
purge_all_caches();

// Let's delete the backup folder if it exists.
if (file_exists($temp_dirpath)) {
    // Delete the files inside the folder.
    $files = glob($temp_dirpath . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }

    echo "Deleting the backup directory..." . PHP_EOL;
    rmdir("{$temp_dirpath}");
}

echo "Creating the backup directory..." . PHP_EOL;

if (!mkdir($temp_dirname)) {
    echo "Error: The backup dir can not be created.";
    exit(1);
}

try {
    echo "Creating DB sql file..." . PHP_EOL;

    $world_dumper = Shuttle_Dumper::create(array(
        'host' => $CFG->dbhost,
        'username' => $CFG->dbuser,
        'password' => $CFG->dbpass,
        'db_name' =>  $CFG->dbname,
    ));

    // dump the database to gzipped file
    $world_dumper->dump($temp_dirpath . '/db.sql');
    echo 'DB sql file created.' . PHP_EOL;
} catch (Shuttle_Exception $e) {
    echo "Couldn't dump database: " . $e->getMessage();
    exit(1);
}

if (!chdir($CFG->dataroot)) {
    echo "Error: Can't CD to the moodledata folder";
    exit(1);
}

echo "We are readty to start zipping the Moodledata folder..." . PHP_EOL;

system("tar -czvf $filename .") . PHP_EOL;

echo "Moving moodledata.zip to backup folder" . PHP_EOL;
exec("mv {$filename} {$temp_dirpath}");

if (!chdir($temp_dirpath)) {
    echo "Error: Can't CD to the backup folder";
    exit(1);
}

// Let's upload the files to S3.
echo "Uploading files to S3..." . PHP_EOL;

// Create an S3Client
try {
    $s3 = new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1',
        'credentials' => [
            'key' => $CFG->aws['key'],
            'secret' => $CFG->aws['secret'],
        ],
    ]);
} catch (AwsException $e) {
    // output error message if fails
    echo $e->getMessage();
    exit(1);
}

// Let's see if the bucket exists.
try {
    $result = $s3->headBucket([
        'Bucket' => $CFG->aws['bucket'],
    ]);
} catch (AwsException $e) {
    // Let's try to create the bucket.
    echo "The bucket doesn't exist. Creating it..." . PHP_EOL;
    try {
        $result = $s3->createBucket([
            'Bucket' => $CFG->aws['bucket'],
        ]);
    } catch (AwsException $e) {
        // output error message if fails
        echo $e->getMessage();
        exit(1);
    }
}

// Upload the file $temp_dirpath/db.sql to the bucket.
// If the $temp_dirpath/db.sql file is larger than 5GB, we need to use multipart upload.
try {
    if (filesize($temp_dirpath.'/db.sql') > 2368709120) {
        echo "The DB is larger than 2GB. Using multipart upload..." . PHP_EOL;
        echo "The file is " . filesize($temp_dirpath.'/db.sql') / 1024 . " MB" . PHP_EOL;

        $source = fopen($temp_dirpath.'/db.sql', 'rb');

        $uploader = new ObjectUploader(
            $s3,
            $CFG->aws['bucket'],
            $filename,
            $source,
            'application/x-gzip'
        );
        
        do {
            try {
                $result = $uploader->upload();
                if ($result["@metadata"]["statusCode"] == '200') {
                    echo "File successfully uploaded to  ".$result['ObjectURL'] . PHP_EOL;
                }
                print($result);
            } catch (MultipartUploadException $e) {
                rewind($source);
                $uploader = new MultipartUploader($s3, $source, [
                    'state' => $e->getState(),
                ]);
            }
        } while (!isset($result));
        
        fclose($source);
    } else {
        echo "The DB is smaller than 5GB. Using single upload..." . PHP_EOL;

        $result = $s3->putObject([
            'Bucket' => $CFG->aws['bucket'],
            'Key' => 'db.sql',
            'SourceFile' => $temp_dirpath.'/db.sql',
        ]);
    }
} catch (AwsException $e) {
    // output error message if fails
    echo $e->getMessage();
    exit(1);
}

// Upload the file $temp_dirpath/moodledata.tar.gz to the bucket.
// If the $temp_dirpath/$filename file is larger than 5GB, we need to use multipart upload.
try {
    if (filesize($temp_dirpath . '/' . $filename) > 2368709120) {
        echo "The moodledata is larger than 2GB. Using multipart upload..." . PHP_EOL;
        echo "The file is " . filesize($temp_dirpath . '/' . $filename) / 1024 . " MB" . PHP_EOL;

        $source = fopen($temp_dirpath . '/' . $filename, 'rb');

        $uploader = new ObjectUploader(
            $s3,
            $CFG->aws['bucket'],
            $filename,
            $source
        );
        
        do {
            try {
                $result = $uploader->upload();
                if ($result["@metadata"]["statusCode"] == '200') {
                    echo "File successfully uploaded to  ".$result['ObjectURL'] . PHP_EOL;
                }
                echo $result. PHP_EOL;
            } catch (MultipartUploadException $e) {
                rewind($source);
                $uploader = new MultipartUploader($s3, $source, [
                    'state' => $e->getState(),
                ]);
            }
        } while (!isset($result));
        
        fclose($source);
    } else {
        echo "Moodledata is smaller than 5GB. Using single upload..." . PHP_EOL;

        $result = $s3->putObject([
            'Bucket' => $CFG->aws['bucket'],
            'Key' => $filename,
            'SourceFile' => $temp_dirpath . '/' . $filename,
        ]);
    }
} catch (AwsException $e) {
    // output error message if fails
    echo $e->getMessage();
    exit(1);
}

// Delete the db.sql and moodledata.tar.gz files.
echo "Deleting the db.sql and moodledata.tar.gz files..." . PHP_EOL;
unlink($temp_dirpath.'/db.sql');
unlink($temp_dirpath . '/' . $filename);

echo "Done!" . PHP_EOL;
exit(0);
