<?php
    include("../include/databaze.php");
    $sql = "SELECT id, nazev AS title, zacatek AS start, konec AS end, url, allDay FROM kalendar";
    $req = $link->query($sql);
    $row = $req->fetch_assoc();

    $row["start"] = "2011-10-10T18:00:00";
    $row["id"] = 1;
    
    $row["end"] = "2011-10-10T20:00:00";
    $row["url"] = "http://www.seznam.cz";
    if($row["allDay"]==0){$row["allDay"]=false;}else{$row["allDay"]=true;}

   echo "[".json_encode($row)."]";
  
     
   /*  $year = date('Y'); 
   $month = date('m');
   echo json_encode(array( 

      array( 
         'id' => 1, 
         'title' => "Event1", 
         'start' => "$year-$month-10", 
         'url' => "http://yahoo.com/" 
      ), 

      array( 
         'id' => 2, 
         'title' => "Event2", 
         'start' => "$year-$month-20", 
         'end' => "$year-$month-22", 
         'url' => "http://yahoo.com/" 
      ) 

   ));
     */
?>
