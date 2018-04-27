<?php
/*
Script to output clf text format file as sql for import into kl-access-logs table
*/
// thanks https://docstore.mik.ua/orelly/webprog/pcook/ch11_14.htm

$log_file = 'kl_access.log';
$fh = fopen($log_file,'r') or die($php_errormsg);
$pattern = '/^([^ ]+) ([^ ]+) ([^ ]+) (\[[^\]]+\]) "(.*) (.*) (.*)" ([0-9\-]+) ([0-9\-]+) "(.*)" "(.*)"$/';

while (! feof($fh)) {
    // read each line and trim off leading/trailing whitespace
    $line = trim(fgets($fh,16384));


    if (preg_match($pattern,$line,$matches)) {
        list($whole_match,$remote_host,$client,$userid,$time,
         $method,$request,$protocol,$status,$size,$referer,$useragent) = $matches; // (breaks up request into method,request and protocol)
         $timestring = substr($time,1,strlen($time)-2);
        $datetime = date("Y-m-d H:i:s",strtotime($timestring));
                 
         $sql = "INSERT INTO wp_kl_access_logs (remote_host, client, userid, time, datetime, method, request, protocol, status, size, referer, useragent) 
            VALUES ( '$remote_host','$client','$userid','$time','$datetime','$method','$request','$protocol','$status',$size,'$referer','$useragent');";
           
         echo $sql."\n";
    }
}
