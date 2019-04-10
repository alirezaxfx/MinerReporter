<?php

include 'config.php';


system("mkdir -p $rrdBasePath");

while(true)
{
    $start_timestamp = time();
    $rrd_index_timestamp = $start_timestamp - ($start_timestamp % 60);
    
    // Do something 
    foreach($miners as $minerId => $minerValue){
        $miner_stat = report_miner_stat($IP_Prefix . $minerId, 4028);
        if($miner_stat != NULL){
            $record = &$miner_stat->{"STATS"}[1];            
            
            
            
            
            /*************************************************************/            
            /********************    Temperatures    *********************/
            /*************************************************************/            
            $rrdPath = $rrdBasePath . "/" . $minerId . "_temp" . ".rrd";
            if (file_exists($rrdPath)) {
                $options = array(sprintf("%d:%d:%d:%d:%d:%d:%d"
                                            , $rrd_index_timestamp
                                            , $record->{"temp6"}
                                            , $record->{"temp7"}
                                            , $record->{"temp8"}
                                            
                                            , $record->{"temp2_6"}
                                            , $record->{"temp2_7"}
                                            , $record->{"temp2_8"}
                                        ));                                
                if (!rrd_update($rrdPath, $options)) {
                    echo "RRD UPDATE on temperatures ERROR:" . rrd_error() . "\n";
                }
            }
            else{
                $options = array(
                    "--step", "60",            // Use a step-size of 1 minutes
                    "DS:pcb1:GAUGE:60:-35:150",
                    "DS:pcb2:GAUGE:60:-35:150",
                    "DS:pcb3:GAUGE:60:-35:150",
                    
                    "DS:chip1:GAUGE:60:-35:150",
                    "DS:chip2:GAUGE:60:-35:150",
                    "DS:chip3:GAUGE:60:-35:150",
                    
                    "RRA:AVERAGE:0.5:1:2880",
                    "RRA:AVERAGE:0.5:5:3360",
                    "RRA:AVERAGE:0.5:24:3660",
                    "RRA:AVERAGE:0.5:144:7300"
                );
                
                $ret = rrd_create($rrdPath, $options);
                if (! $ret) {
                    echo "<b>Creation $rrdPath error: </b>".rrd_error()."\n";
                }                         
            }
            
            
            /*************************************************************/
            /************************    Hashrates    ********************/
            /*************************************************************/
            $rrdPath = $rrdBasePath . "/" . $minerId . "_hashrates" . ".rrd";
            if (file_exists($rrdPath)) {
                $options = array(sprintf("%d:%f:%f"
                                            , $rrd_index_timestamp
                                            , $record->{"GHS 5s"} / 1000.0
                                            , $record->{"GHS av"} / 1000.0
                                        ));
                if (!rrd_update($rrdPath, $options)) {
                    echo "RRD UPDATE on hashrate ERROR:" . rrd_error() . "\n";
                }                                   
            }
            else{
                $options = array(
                    "--step", "60",            // Use a step-size of 1 minutes
                    "DS:hrate_5s:GAUGE:60:0:U",
                    "DS:hrate_av:GAUGE:60:0:U",

                    "RRA:AVERAGE:0.5:1:2880",
                    "RRA:AVERAGE:0.5:5:3360",
                    "RRA:AVERAGE:0.5:24:3660",
                    "RRA:AVERAGE:0.5:144:7300"
                );
                
                $ret = rrd_create($rrdPath, $options);
                if (! $ret) {
                    echo "<b>Creation $rrdPath error: </b>".rrd_error()."\n";
                }            
            }
            
            
            
            
        }
    }
    // end

    
    
    
    $end_timestamp = time();    
    $diff = $end_timestamp - $rrd_index_timestamp;
    echo "Get Data of record $rrd_index_timestamp duration:$diff wait:". (60 - $diff) ."\n";
    
    if((60 - $diff) > 0 )
        sleep((60 - $diff));
    
}

?>