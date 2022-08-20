<?php
/***************************
 * This script is used to upgrade all Solutto LMS
 * instances on this server.
 * 
 * It is required to have a sites.json file with the list of sites
 * as described below:
 * 
 * sites.json
 * {
 *  "sites": [
 *   {
 *      "name": "Yamaha Academy",
 *      "url": "http://cursos.yamahaacademy.com",
 *      "path": "/var/www/moodle/yamaha-academy.soluttolabs.com"
 *   }]
 * }
 * 
 * NOTE: Run this command as sudo.
 */

 // Extract the list of sites from the sites.json file.
$sites = json_decode(file_get_contents('sites.json'), true);

// Let's get the list of submodules from the submodules.json file.
$submodules = json_decode(file_get_contents('submodules.json'), true);

// Create a log file to store the results; the name of the file 
// should be mm-dd-yyyy-upgrade-logs.log.
$logFile = date('m-d-Y').'-upgrade-logs.log';
$logFile = getcwd().'/'.$logFile;

// If the file exists, delete it.
if (file_exists($logFile)) {
    unlink($logFile);
}

// Log is an array that will be constantly passed to the exec funtion, and then
// will be stored in the log file.
$log = array();

// Loop through each site and store its details in the log file.
foreach ($sites['sites'] as $site) {
    $log[] = "Upgrading site: ".$site['name'];
    $log[] = "URL: ".$site['url'];
    $log[] = "Path: ".$site['path'];
    file_put_contents($logFile, print_r($log, true));

    // Show the log process to the user.
    print_r($log);

    // Change to the site's directory.
    $log[] = "Changing to the site's directory: ".$site['path'];
    file_put_contents($logFile, print_r($log, true));
    print_r($log);
    chdir($site['path']);

    // Pull the latest code from the Solutto LMS repository.
    exec("git checkout .", $log);
    exec("git pull " . $site['remote'] . " " . $site['branch'], $log);
    print_r($log);
    file_put_contents($logFile, print_r($log, true));

    // Update the submodules.
    $log[] = "Updating submodules";
    print_r($log);
    file_put_contents($logFile, print_r($log, true));

    // Let's loop through each submodule and pull the latest version.
    foreach ($submodules['submodules'] as $submodule) {
        // Proceed only if the directory exist.
        if (file_exists($site['path'].'/'.$submodule['path'])) {
            // Change to the submodule's directory.
            $log[] = "*********************************************";
            $log[] = "Changing to the submodule's directory: ".$submodule['path'];
            print_r($log);
            file_put_contents($logFile, print_r($log, true));
            chdir($site['path'].'/'.$submodule['path']);
            
            // Let's checkout to the proper branch.
            $log[] = "Checking out branch: ".$submodule['branch'];
            exec('git checkout .', $log);
            exec('git checkout '.$submodule['branch'], $log);

            print_r($log);
            file_put_contents($logFile, print_r($log, true));

            $log[] = "Pulling latest version of submodule: ".$submodule['path'];
            print_r($log);
            file_put_contents($logFile, print_r($log, true));
            
            exec('git pull '.$submodule['remote'].' '.$submodule['branch'], $log);
            print_r($log);
            file_put_contents($logFile, print_r($log, true));
        }
    }

    // Changing back to the site's directory.
    $log[] = "Changing to the site's directory: ".$site['path'];
    file_put_contents($logFile, print_r($log, true));
    print_r($log);
    chdir($site['path']);

    // Run the upgrade script.
    $command = "php admin/cli/upgrade.php --non-interactive";
    $log[] = "Running the upgrade script: ".$command;
    exec($command, $log);
    print_r($log);
    file_put_contents($logFile, print_r($log, true));
}