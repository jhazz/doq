<?php



function getJsonDateNow(){
    //$requestText=file_get_contents("php://input");

    //$request=json_decode($requestText, true) ?: [];

    
    list($usec, $sec) = explode(" ", microtime());
    $timestamp=time();
    $msec=round(floatval($usec)*1000);
    
    $now=explode(":",date("Y:n:j:H:i:s:Z",$timestamp));
    $gnow=explode(":",gmdate("Y:n:j:H:i:s",$timestamp));
    print json_encode ([
        'year'=>$now[0], 'month'=>$now[1], 'day'=>$now[2], 
        'h'=>intval($now[3]), 'm'=>intval($now[4]), 
        's'=>intval($now[5]), 'z'=>intval($now[6]),
        'gyear'=>$gnow[0], 'gmonth'=>$gnow[1], 'gday'=>$gnow[2], 
        'gh'=>intval($gnow[3]), 'gm'=>intval($gnow[4]), 'gs'=>intval($gnow[5]), 
        'ms'=>$msec
    ]);
}



function initHTML(){
    ?>
    <html><!DOCTYPE HTML>
    <head>
    <script src='main.js'></script>
    </head>
    <?php
}

function getIndex(){
    initHTML();
    ?>
    <div id="comparator"></div>
    <script>
    window.onload=function(){
        proctor.showTimeComparator(document.getElementById("comparator"))
    }
    </script>
    <?php     
}


$routes=[
    'getJsonDateNow'=>'getJsonDateNow',
    '*'=>'getIndex'
];
$action=(isset($_GET['a']))? $_GET['a']: '';
if(isset($routes[$action])){
    $routes[$action]();
} else {
    $routes['*']();
}



?>
