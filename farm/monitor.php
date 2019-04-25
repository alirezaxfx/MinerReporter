<?php

// ATTENTION THIS FILE MUST RUN AS ROOT

include 'config.php';


system("mkdir -p $rrdBasePath");

$last_send_sms=0;

function send_sms($phone, $text)
{
    global $modemTypes;
    global $BasePath;
    
    global $modemTypes;
    global $modem_ip_addr;
    global $modem_username;
    global $modem_password;
    $cur_timestamp = time();
   
    if( $cur_timestamp > ($last_send_sms + 1200) ){
        $last_send_sms = $cur_timestamp;
        $cmd = "python3.5 $BasePath"."$modemTypes.py $modem_ip_addr $modem_username $modem_password $phone \"$text\"";
        echo $cmd . "\n";
        $output = shell_exec($cmd);
    }
}

while(true)
{
    $start_timestamp = time();
    $rrd_index_timestamp = $start_timestamp - ($start_timestamp % 60);
    
    // Do something 
    foreach($miners as $minerId => $minerValue){
        $miner_stat = report_miner_stat($IP_Prefix . $minerId, 4028);
        if($miner_stat != NULL){
            $record = &$miner_stat->{"STATS"}[1];            
            $d1     = &$miner_stat->{"STATS"}[0];

            /*************************************************************/            
            /********************    Temperatures    *********************/
            /*************************************************************/            
            $rrdPath = $rrdBasePath . "/" . $minerId . "_temp" . ".rrd";
            if (file_exists($rrdPath)) {
                if( $d1->{"Type"} == "Antminer S11"){
                    $options = array(sprintf("%d:%d:%d:%d:%d:%d:%d"
                                                , $rrd_index_timestamp
                                                , $record->{"temp3_1"}
                                                , $record->{"temp2_1"}
                                                , $record->{"temp3_2"}
                                                , $record->{"temp2_2"}
                                                , $record->{"temp3_3"}
                            , $record->{"temp2_3"}
                        ));
                    if( $record->{"temp2_1"} > HIGH_TEMP_DMG 
                        || $record->{"temp2_2"} > HIGH_TEMP_DMG
                        || $record->{"temp2_3"} > HIGH_TEMP_DMG
                        || $record->{"temp3_1"} > HIGH_TEMP_DMG
                        || $record->{"temp3_2"} > HIGH_TEMP_DMG
                        || $record->{"temp3_3"} > HIGH_TEMP_DMG )
                    {
                        send_sms($sms_phone_alert, $sms_temp_text);
                    }                    
                }   
                else{
                    $options = array(sprintf("%d:%d:%d:%d"
                                            , $rrd_index_timestamp
                                            , $record->{"temp2_6"}
                                            , $record->{"temp2_7"}
                                            , $record->{"temp2_8"}
                    ));

                    if( $record->{"temp2_6"} > HIGH_TEMP_DMG 
                        || $record->{"temp2_7"} > HIGH_TEMP_DMG
                        || $record->{"temp2_8"} > HIGH_TEMP_DMG )
                    {
                        send_sms($sms_phone_alert, $sms_temp_text);
                    }                       
                    
                }
                if (!rrd_update($rrdPath, $options)) {
                    echo "RRD UPDATE on temperatures ERROR:" . rrd_error() . "\n";
                }
            }
            else{
                if( $d1->{"Type"} == "Antminer S11"){
                        $options = array(
                        "--step", "60",            // Use a step-size of 1 minutes
                        "DS:chip1A:GAUGE:60:-35:150",
                        "DS:chip1B:GAUGE:60:-35:150",

                        "DS:chip2A:GAUGE:60:-35:150",
                        "DS:chip2B:GAUGE:60:-35:150",
                
                        "DS:chip3A:GAUGE:60:-35:150",
                        "DS:chip3B:GAUGE:60:-35:150",
                
                        "RRA:AVERAGE:0.5:1:2880",
                        "RRA:AVERAGE:0.5:5:3360",
                        "RRA:AVERAGE:0.5:24:3660",
                        "RRA:AVERAGE:0.5:144:7300"
                   );
                }
                else{
                    $options = array(
                        "--step", "60",            // Use a step-size of 1 minutes
                        "DS:chip1A:GAUGE:60:-35:150",

                        "DS:chip2A:GAUGE:60:-35:150",

                        "DS:chip3A:GAUGE:60:-35:150",
                        
                        "RRA:AVERAGE:0.5:1:2880",
                        "RRA:AVERAGE:0.5:5:3360",
                        "RRA:AVERAGE:0.5:24:3660",
                        "RRA:AVERAGE:0.5:144:7300"
                    );
                }
                
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
