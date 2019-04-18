<?php

include 'config.php';

echo '<script src="https://www.kryogenix.org/code/browser/sorttable/sorttable.js"></script>';


function restart_miner($ip, $port, $password)
{
    /* Create a TCP/IP socket. */
    /*
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
    
    $cmd = '{"command":"restart","parameter":"0"}';
    
    socket_write($socket, $cmd, strlen($cmd));
    
    $result = "";
    
    while ($out = socket_read($socket, 2048)) {
        $result.=$out;
    }
    
    $result = substr($result, 0, strlen($result) - 1);
    $result = str_replace("}{", "},{", $result);    
    
    $data = json_decode($result);
    return $data;
    */
    
	$connection=ssh2_connect($ip, $port);

	if (ssh2_auth_password($connection, 'root', $password)) {
		#echo "Authentication Successful!\n";
	} else {
		echo 'Authentication Failed...';
	}
	$stream = ssh2_exec($connection, "/sbin/reboot");
	
    stream_set_blocking($stream, true);
	echo stream_get_contents($stream);
    
	fclose($stream);  
    ssh2_disconnect($connection);
}

function print_cell($value, $colspan = 1, $color = "")
{
    if(empty($color))
        echo "<td align=\"center\" colspan=\"".$colspan."\">" . $value . "</td>\n";
    else
        echo "<td align=\"center\" colspan=\"".$colspan."\" bgcolor=\"$color\">" . $value . "</td>\n";
}

function get_fan_value_color($value)
{
    if( $value < 4400 )
        return "Green";
    else if( $value < 5300 )
        return "";
    else
        return "Red";    
}


function get_color_baseon_min_max($value, $min, $max)
{
    $step = ($max - $min) / 3.0;
    if($value <= ($min + $step ))
        return "Green";
   else if($value >= ($max - $step ) )
       return "Red";
   
   return "";
}

function get_temp_value_color($value)
{
    if( $value < 80 )
        return "Green";
    else if( $value > 85 )
        return "Red";
    return "";
}

function get_ghs_value_color($value)
{
    if( $value < 14100 )
        return "Red";
    else if( $value > 14500 )
        return "Green";
    return "";    
}

function secondsToTime($seconds) {
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");
    return $dtF->diff($dtT)->format('%a days, %h hours, %i minutes and %s seconds');
}

echo '<table class="sortable" border="1" width="100%">
  
	<thead>
	<tr>
		<th colspan="4">Mining Stats</th>
        <th colspan="3">FAN</th>
        <th colspan="7">Chip Temp</th>
		<th colspan="5">Device Specification</th>		
	</tr>
    <tr>
            <th >Miner</th>
            <th >ElapsedTime</th>
            <th >GHS 5s</th>
            <th >GHS av</th>
        
            <th>1</th>
            <th>2</th>
            <th>Tot</th>        
        
            <th colspan="2">1</th>
            <th colspan="2">2</th>
            <th colspan="2">3</th>
            <th>Avg</th>
        
			<th title="Fan Information">FI</th>
			<th title="Device Type">DT</th>
			<th title="Position">Pos</th>
			<th title="Reboot">Reboot</th>
    </tr>	
    <thead>
    <tbody>
    ';

$Id = 0;
$cmd="";
$arg_miner_id = 0;

if(isset($_REQUEST["CMD"]))
    $cmd = $_REQUEST["CMD"];

if(isset($_REQUEST["minerId"]))
    $arg_miner_id = $_REQUEST["minerId"];

if(isset($argc)){
    if($argc >= 2)
        $cmd = $argv[1];
    
    if($argc >= 3)
        $arg_miner_id = $argv[2];
}

$row = 0;

foreach($miners as $minerId => $minerValue){
    
        if(floor($minerValue["Position"] / 10) != $row)
	{
		$row = floor($minerValue["Position"] / 10);
                echo '\n<tr bgcolor = "#c2c2f0"><th  colspan=18=>Row ';
                echo $row;
                echo "</th></tr>";
        }


	echo "\n<tr>";    
    print_cell( $minerId );

    
    if(!empty($cmd)){
        if(strcmp($cmd, "reboot") == 0){
            if($arg_miner_id == $minerId ){
                restart_miner($IP_Prefix . $arg_miner_id, 22, $minerPassword);
                echo "<td>REBOOTING</td>";
               echo "</tr>";                
              continue;
            }
        }
    }
    
    $miner_stat = report_miner_stat($IP_Prefix . $minerId, 4028);
   // if($minerId == 54)
    // var_dump($miner_stat);
    
    if($miner_stat != NULL){
        $record = &$miner_stat->{"STATS"}[1];
        print_cell(secondsToTime($record->{"Elapsed"}));
        print_cell($record->{"GHS 5s"}, 1, get_ghs_value_color($record->{"GHS 5s"}));
        print_cell($record->{"GHS av"}, 1, get_ghs_value_color($record->{"GHS av"}));
	       

	$d1 = &$miner_stat->{"STATS"}[0];
	
        if( $d1->{"Type"} == "Antminer S11")
        {

	print_cell($record->{"fan1"}, 1, get_fan_value_color($record->{"fan1"}));
        print_cell($record->{"fan2"}, 1, get_fan_value_color($record->{"fan2"}));
        print_cell($record->{"fan1"} + $record->{"fan2"} );


	print_cell($record->{"temp3_1"}, 1, get_color_baseon_min_max($record->{"temp3_1"}, CHIP_TEMP_MIN, CHIP_TEMP_MAX));
	print_cell($record->{"temp2_1"}, 1, get_color_baseon_min_max($record->{"temp2_1"}, CHIP_TEMP_MIN, CHIP_TEMP_MAX));

	print_cell($record->{"temp3_2"}, 1, get_color_baseon_min_max($record->{"temp3_2"}, CHIP_TEMP_MIN, CHIP_TEMP_MAX));
	 print_cell($record->{"temp2_2"}, 1, get_color_baseon_min_max($record->{"temp2_2"}, CHIP_TEMP_MIN, CHIP_TEMP_MAX));

        print_cell($record->{"temp3_3"}, 1, get_color_baseon_min_max($record->{"temp3_3"}, CHIP_TEMP_MIN, CHIP_TEMP_MAX));
        print_cell($record->{"temp2_3"}, 1, get_color_baseon_min_max($record->{"temp2_3"}, CHIP_TEMP_MIN, CHIP_TEMP_MAX));


        $avg = round(($record->{"temp3_1"} + $record->{"temp3_2"} + $record->{"temp3_3"} + $record->{"temp2_1"} + $record->{"temp2_2"} + $record->{"temp2_3"} ) / 6, 1);
        print_cell( $avg, 1, get_color_baseon_min_max($avg, CHIP_TEMP_MIN, CHIP_TEMP_MAX));

	}
	else
	{
	print_cell($record->{"fan3"}, 1, get_fan_value_color($record->{"fan3"}));
        print_cell($record->{"fan6"}, 1, get_fan_value_color($record->{"fan6"}));
        print_cell($record->{"fan3"} + $record->{"fan6"} );                

       
        print_cell($record->{"temp2_6"}, 2, get_color_baseon_min_max($record->{"temp2_6"}, CHIP_TEMP_MIN, CHIP_TEMP_MAX));
        print_cell($record->{"temp2_7"}, 2, get_color_baseon_min_max($record->{"temp2_7"}, CHIP_TEMP_MIN, CHIP_TEMP_MAX));
        print_cell($record->{"temp2_8"}, 2, get_color_baseon_min_max($record->{"temp2_8"}, CHIP_TEMP_MIN, CHIP_TEMP_MAX));
        $avg = round(($record->{"temp2_6"} + $record->{"temp2_7"} + $record->{"temp2_8"} ) / 3, 1);
        print_cell( $avg, 1, get_color_baseon_min_max($avg, CHIP_TEMP_MIN, CHIP_TEMP_MAX));
       
	}
        //print_cell( ( $record->{"fan3"} + $record->{"fan6"} ) * round(($record->{"temp6"} + 15 + $record->{"temp7"} + 15 + $record->{"temp8"} + 15) / 3, 2));        
        
        // Specifications
        $record = &$miner_stat->{"STATS"}[0];

        
        
        print_cell($minerValue["FanSpec"]);
        //print_cell($minerValue["DevType"]);
        print_cell($record->{"Type"} . "-" . $minerValue["HT"]);
        print_cell($minerValue["Position"] % 10);
        
        
        echo '<td><form method="POST" style="margin-block-end: auto;"><a href="#" onclick="if(confirm(\'Do you want reboot?\')) parentNode.submit(); else window.location.assign(window.location.href);return false;">Reboot</a><input type="hidden" name="cmd" value="reboot"/><input type="hidden" name="minerId" value="'. $minerId .'" /></form></td>';
        
        //echo '<td><a href="?cmd=reboot&minerId=' . $minerId .'" target="_blank">Reboot</a></td>';
    }
    
    echo "</tr>";
}

echo "\n</tbody>\n</table>";

?>
