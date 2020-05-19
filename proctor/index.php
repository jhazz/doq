<?php

$routes=[
    'getJsonDateNow'=>'getJsonDateNow',
    'uploadRegistration'=>'uploadRegistration',
    'uploadFile'=>'uploadFile',
    'echo'=>'getEcho',
    '*'=>'pageIndex'
];
$action=(isset($_GET['a']))? $_GET['a']: '';
if(isset($routes[$action])){
    $routes[$action]();
} else {
    $routes['*']();
}




function initHTML(){
    ?>
    <html><!DOCTYPE HTML>
    <head>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
    html,body {padding:0;margin:0; height:100%; width:100%;}
    h1 {font-family:sans, arial; font-size:20pt; font-weight:normal;}
    .tab {font-family:sans, arial; font-size:11pt;}
    .tab td.step {background:red; padding:4pt; font-size:12pt; font-weight:bold; color:white; white-space:nowrap;}
    .tab td {border-bottom:solid 1px #888888; padding:4pt;}
    .tab td.bad {color:red;}
    .tab td.good{color:green;}
    #page-wrap {display:table; height:100%;width:100%;}
    #main-container {display:table-cell; padding:10pt; box-sizing:border-box; text-align:center; vertical-align:middle;}
    .place {display:inline-block; background:#fefeff; width:600px;}
    #topmenu-title {display:table-cell; width:100%; height:50pt; background:#333355; color:white; font-family:sans; font-size:12pt; padding:6pt;}
    #topmenu-info {float:right; width:25%; height:24pt; overflow:hidden; font-size:8pt;}
    @media (max-width: 600px) {
        .topmenu {background:#226644 !important; font-size:20pt !important; }
    }
    .topmenu {box-sizing:border-box;display:table-row; height:15pt; background:@menu-bgcolor; font-family:sans,arial; font-size:12pt; color:white; padding:10pt 0 0 20pt; margin:0;}
    .topmenu label {padding:2pt 5pt; margin:2pt 5pt; background:#555555; color:white; white-space:nowrap; box-sizing:border-box;}
    .topmenu label:hover {background-color:#ff2222; cursor:pointer;}
    .topmenu input {display:none;}
    .topmenu input:checked + label {background:#ffffff; color:#0022aa;}
    p {text-align:left;}
    </style>
    
    <script src='../www/doq/doq.js'></script>
    </head>
    <?php
}
/*
function pageUploading(){
    initHTML();
    ?>
    <form>
        <input type="file" id="file-selector" accept=".jpg, .jpeg, .png">
        <table width="100%" border=1>
        <tr valign="top"><td>Перетяните в ячейку ваши видеофайлы записи хода испытания. MP4/AVI/WMV файлы</td><td>Перетяните сюда изображения результатов</td></tr>
        <tr><td id='droparea1'></td><td></td></tr></table>
    </form>
    </div>
    
    <?php
}
*/

function initTop(){
    ?>
    <div id="page-wrap">
        <div id="topmenu-title"><div>Система прокторинга НГУАДИ</div>
            <div id='topmenu-info'>Вы зарегистрированы как antonov@gmail.com</div>
        </div>
    
        <div class="topmenu" style="clear: both;">
    <?php
    function generateTopMenu($menuName, $elements) {
        foreach ($elements as $i=>$d) {
        print ' <input type="radio" id="' . $menuName . '_' . $i . '" name="' . $menuName 
            . '" '.(($i==0)?'checked':''). ' /><label onclick="location.href=\''
            . $d['href'] . '\'" for="' . $menuName . '_' . $i . '">' . $d['label'] . '</label>';
        }
    }
    
    generateTopMenu('main',[
        ['href'=>'',          'label'=>'Проверка'],
        ['href'=>'#reg',      'label'=>'1. Регистрация'],
        ['href'=>'#testing1', 'label'=>'2. Проба'],
        ['href'=>'#testing2', 'label'=>'3. Испытания']
    ] );

    ?>
    </div>
    <?php
}

function pageIndex(){
    initHTML();
    initTop();
    ?>
    <script>
        doq.cfg.jsModulesRoot='../www'
        doq.require('proctor.main', function(){
            doq.router.addRouteHandler('#reg',proctor.main.renderRegistrationForm)
            doq.router.addRouteHandler('#testing1',function(){
            })
            proctor.main.startChecking()
        })
    </script>
    <div id="main-container">
        <div class="place">
            <h1>Проверка устройства</h1>
            <noscript>
            <h2 style='color:red'>Критическая ошибка!</h2>
            <p>Ваш браузер не поддерживает JAVASCRIPT. Вам необходимо выяснить как включить в браузере JAVASCRIPT или установить другой браузер.</p>
            <p>Если на устройстве невозможно установить браузер с JAVASCRIPT вам следует использовать другое устройство!</p>
            <h3>Диагностическая информация</h3>
            <p><b>Ваш браузер:</b></p><p><?=$_SERVER['HTTP_USER_AGENT']?></p>
            <p><b>Время проверки (сервер):</b></p><p><?=date('r')?></p>
            </noscript>
            <div id="checkLog"></div>
        </div>
    </div>
    <?php     
}


function getJsonDateNow(){
    list($usec, $timestamp) = explode(" ", microtime());
    $timestamp+=30;// сервер спешит на 30 секунд
    $msec=round(floatval($usec)*1000);
    $servertime=$timestamp*1000+$msec;
    $requestText=file_get_contents("php://input");
    $request=json_decode($requestText, true) ?: [];
    $now=explode(":",date("Y:n:j:H:i:s:Z",$timestamp));
    $gnow=explode(":",gmdate("Y:n:j:H:i:s",$timestamp));
    print json_encode ([
        'year'=>$now[0], 'month'=>$now[1], 'day'=>$now[2], 
        'h'=>intval($now[3]), 'm'=>intval($now[4]), 
        's'=>intval($now[5]), 'z'=>intval($now[6]),
        'gyear'=>$gnow[0], 'gmonth'=>$gnow[1], 'gday'=>$gnow[2], 
        'gh'=>intval($gnow[3]), 'gm'=>intval($gnow[4]), 'gs'=>intval($gnow[5]), 
        'phase0'=>$request['phase0'], 'phase1'=>$servertime
    ]);
}

function getEcho(){
    $requestText=file_get_contents("php://input");
    $request=json_decode($requestText, true) ?: [];
    
}

function uploadFile(){
    
}



?>