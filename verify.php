<?php

require_once('../../config.php');

// define headers for json response
header('Content-Type: application/json');

header('Access-Control-Allow-Origin: '.$CFG->appurl);

header('Access-Control-Allow-Credentials: true');

$res = new stdClass;
// check if the user is loggedin
if(isloggedin()) {
    $res->status = 'Ok';
}else{
    $res->status = 'Error';
}

echo json_encode($res);