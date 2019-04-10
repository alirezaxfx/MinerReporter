<?php

date_default_timezone_set("Asia/Tehran");

define('PCB_TEMP_MIN_TEMP',45);
define('PCB_TEMP_MAX_TEMP',85);

define('CHIP_TEMP_MIN_TEMP',65);
define('CHIP_TEMP_MAX_TEMP',110);

$minerPassword="alireza1368!@#";
$IP_Prefix = "192.168.5.";
$rrdBasePath="/var/www/html/farm/rrdfiles/";

$miners = array( 
				31 => array ( "Position" => "11", "FanSpec" => "B-XXXX", "HT" => "14.5T" ), 
				32 => array ( "Position" => "12", "FanSpec" => "G-1.65", "HT" => "14.5T" ), 
				33 => array ( "Position" => "13", "FanSpec" => "G-1.65", "HT" => "14.5T" ), 
				34 => array ( "Position" => "14", "FanSpec" => "R-2.76", "HT" => "14.5T" ), 
				35 => array ( "Position" => "21", "FanSpec" => "Y-2.30", "HT" => "14.5T" ), 
				36 => array ( "Position" => "22", "FanSpec" => "Y-2.30", "HT" => "14.5T" ), 
				37 => array ( "Position" => "23", "FanSpec" => "Y-2.30", "HT" => "14.5T" ), 
				);

function report_miner_stat($ip, $port)
{
    /* Create a TCP/IP socket. */
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if ($socket === false) {
        echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
        return NULL;
    } else {
        // Nothing
    }

    // echo "Attempting to connect to '$address' on port '$port'...";
    $result = socket_connect($socket, $ip, $port);
    if ($result === false) {
        // echo "socket_connect() failed.\nReason: ($result) " . socket_strerror(socket_last_error($socket)) . "\n";
        return NULL;
    } else {
        // Nothing
    }
    
    $cmd = '{"command":"stats","parameter":"0"}';
    socket_write($socket, $cmd, strlen($cmd));
    
    $result = "";
    
    while ($out = socket_read($socket, 2048)) {
        $result.=$out;
    }
    
    $result = substr($result, 0, strlen($result) - 1);
    $result = str_replace("}{", "},{", $result);    
    
    $data = json_decode($result);
    socket_close($socket);
    return $data;
}

?>