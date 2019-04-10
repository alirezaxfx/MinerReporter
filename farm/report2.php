<?php
	$connection=ssh2_connect("127.0.0.1", 2233);

	if (ssh2_auth_password($connection, 'pi', 'alireza')) {
		#echo "Authentication Successful!\n";
	} else {
		die('Authentication Failed...');
	}
    
    $arg = "";
    
    if(isset($_REQUEST["cmd"]))
        $arg .= " " . $_REQUEST["cmd"];
    
    if(isset($_REQUEST["minerId"]))
        $arg .= " " . $_REQUEST["minerId"];            

    
	$stream = ssh2_exec($connection, "php /var/www/html/farm/report.php " . $arg);
    // echo "php /var/www/html/ra.php " . $arg;
	stream_set_blocking($stream, true);

	echo stream_get_contents($stream);
	fclose($stream);
	//var_dump($ssh2);
?>

