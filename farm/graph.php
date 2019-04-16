<?php

include 'config.php';

function create_temperatures_graph($mtype, $minerId, $output, $start, $end, $title) 
{
    global $rrdBasePath;
    
    $options = array(
        "--slope-mode",
        "--start", $start,
        "--end", $end,
        "--height", "256",
        "--width", "1024",        
        "--title=$title",
        "--vertical-label=Temperatures",
        "--alt-autoscale",
        "--alt-y-grid",
        "DEF:CHIP1A=$rrdBasePath/". $minerId ."_temp.rrd:chip1A:AVERAGE",
        "DEF:CHIP2A=$rrdBasePath/". $minerId ."_temp.rrd:chip2A:AVERAGE",
        "DEF:CHIP3A=$rrdBasePath/". $minerId ."_temp.rrd:chip3A:AVERAGE",        
        
        "LINE1:CHIP1A#41cdf4:CHIP 1A",
        "LINE1:CHIP2A#416af4:CHIP 2A",
        "LINE1:CHIP3A#7641f4:CHIP 3A",
        
        
);
   if($mtype == "Antminer S11")
   {
	$options = array_merge($options, array(
        "DEF:CHIP1B=$rrdBasePath/". $minerId ."_temp.rrd:chip1B:AVERAGE",
        "DEF:CHIP2B=$rrdBasePath/". $minerId ."_temp.rrd:chip2B:AVERAGE",
        "DEF:CHIP3B=$rrdBasePath/". $minerId ."_temp.rrd:chip3B:AVERAGE",        
        "LINE1:CHIP1B#61cdf4:CHIP 1B",
        "LINE1:CHIP2B#716af4:CHIP 2B",
	"LINE1:CHIP3B#8641f4:CHIP 3B",
	));
   }   
    $ret = rrd_graph($output, $options);
    if (! $ret) {
        die("<b>Graph Temperatures error: </b>".rrd_error()."\n");
    }
}

function create_hashrate_graph($minerId, $output, $start, $end, $title) 
{
    global $rrdBasePath;
    
    $options = array(
        "--slope-mode",
        "--start", $start,
        "--end", $end,
        "--height", "256",
        "--width", "1024",
        "--title=$title",
        "--vertical-label=Temperatures",
        "--alt-autoscale",
        "--alt-y-grid",
        "DEF:hrate_5s=$rrdBasePath/". $minerId ."_hashrates.rrd:hrate_5s:AVERAGE",
        "DEF:hrate_av=$rrdBasePath/". $minerId ."_hashrates.rrd:hrate_av:AVERAGE",
        
        "LINE1:hrate_5s#4bf442:HRate5s",
        "LINE1:hrate_av#4441f4:HRateAVG",
    );

    $ret = rrd_graph($output, $options);
    if (! $ret) {
        die("<b>Graph Hashrate error: </b>".rrd_error()."\n");
    }
}


function create_hashrate_tot_graph( $output, $start, $end, $title) 
{
    global $rrdBasePath, $miners;
        
    $options = array(
        "--slope-mode",
        "--start", $start,
        "--end", $end,
        "--height", "256",
        "--width", "1024",
        "--title=$title",
        "--vertical-label=Temperatures",
        "--alt-autoscale",
        "--alt-y-grid",
    );
    
    $str_5s = "";
    $str_av = "";
    $str_pluses = "";
   
   
    
    foreach($miners as $Id => $minerValue){
        $options[]= "DEF:hrate_5s_$Id=$rrdBasePath/". $Id ."_hashrates.rrd:hrate_5s:AVERAGE";
        $options[]= "DEF:hrate_av_$Id=$rrdBasePath/". $Id ."_hashrates.rrd:hrate_av:AVERAGE";
        
        if( !empty($str_5s) )
            $str_5s .= ",";
        if( !empty($str_av) ){
            $str_av .= ",";
            $str_pluses .= ",+";
        }
        
        $str_5s .= "hrate_5s_$Id";
        $str_av .= "hrate_av_$Id";
    }    
    
    $options = array_merge($options, array (
            "CDEF:Total=" . $str_5s . $str_pluses, 
            "LINE1:Total#4bf442:Total",
        )
    );
    // var_dump($options);
    
    $ret = rrd_graph($output, $options);
    if (! $ret) {
        die("<b>Graph Hashrate error: </b>".rrd_error()."\n");
    }
}

$minerId = -1;
$startTime = 0;
$endTime = 0;
$graphType = 1;


if(isset($_REQUEST["startTime"]))
    $startTime = $_REQUEST["startTime"];
if(isset($_REQUEST["endTime"]))
    $endTime = $_REQUEST["endTime"];
if(isset($_REQUEST["minerId"]))
    $minerId = $_REQUEST["minerId"];
if(isset($_REQUEST["graphType"]))
    $graphType = $_REQUEST["graphType"];



$html_miner_combox_str = '<select name="minerId" id="minerId">';
foreach($miners as $Id => $minerValue){
    $html_miner_combox_str .= '<option value="'. $Id .'" ' . ($Id == $minerId ? " selected" : "" ) . '>'. $Id .'</option>';
}
$html_miner_combox_str .= '<option value="0" ' . ($minerId == 0 ? " selected" : "" ) . '>Total</option>';
$html_miner_combox_str .= "</select>";

echo '
<form method="POST"> 

        <label for="minerId">Miner</label>
        ' . $html_miner_combox_str . '      
        <br>
        <br>


        
        <label for="startTime">Start Time</label>
        <input type="datetime-local" name="startTime" id="startTime" value="' . ( $startTime != 0 ? $startTime : "" ) . '">
        <br>
        <br>
        
        <label for="endTime">End Time</label>
        <input type="datetime-local" name="endTime" id="endTime"  value="' . ( $endTime != 0 ? $endTime : "" ) . '">
        <br>
        <br>
        
        <label for="graphType">Graph Type</label>
        <select name="graphType" id="graphType">
            <option value="1" '. ( ($graphType == 1) ? "selected" : "" ) .'>Temperatures</option>
            <option value="2" '. ( ($graphType == 2) ? "selected" : "" ) .'>Hashrate</option>
        </select>
        <br>
        <br>        
        <br>
        
        <input type="submit" value="Submit">
        
</form>';    

if(isset($_REQUEST["minerId"])){
    
    $startTime = strtotime($startTime);
    $endTime = strtotime($endTime);
    
    // echo "$startTime   $endTime <br>";

    $miner_stat = report_miner_stat($IP_Prefix . $minerId, 4028);
    if($miner_stat != NULL){
    	$record = &$miner_stat->{"STATS"}[1];
        $d1     = &$miner_stat->{"STATS"}[0];
    	if($graphType == 1)
            create_temperatures_graph($d1->{"Type"}, $minerId, "test.png", $startTime, $endTime, "Miner " . $minerId . " Temperatures");
       else if($graphType == 2){
            if($minerId == 0)
                create_hashrate_tot_graph("test.png", $startTime, $endTime, "Miner " . $minerId . " Hashrate");
            else
                create_hashrate_graph($minerId, "test.png", $startTime, $endTime, "Miner " . $minerId . " Hashrate");
       }
    }
    
    echo '<img src="test.png" alt="Generated RRD image" >';    
}
else {

    echo '<script> 
        Number.prototype.AddZero= function(b,c){
            var  l= (String(b|| 10).length - String(this).length)+1;
            return l> 0? new Array(l).join(c|| \'0\')+this : this;
        }    
        
        var st = new Date();
        var et = new Date();
        st.setDate(st.getDate() - 14);
        
        var st_str = st.getFullYear() + "-" + (st.getMonth() + 1).AddZero() + "-" + st.getDate().AddZero() + "T" + st.getHours().AddZero() + ":" + st.getMinutes().AddZero();
        var et_str = et.getFullYear() + "-" + (et.getMonth() + 1).AddZero() + "-" + et.getDate().AddZero() + "T" + et.getHours().AddZero() + ":" + et.getMinutes().AddZero();
        
        
        document.getElementById("startTime").defaultValue = st_str;
        document.getElementById("endTime").defaultValue =  et_str;
    </script>
    ';
}
?>
