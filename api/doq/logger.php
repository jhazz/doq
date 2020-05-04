<?php
require_once '../autorun.php';
$requestText=file_get_contents("php://input");
$request=json_decode($requestText, true) ?: [];

$envLog=$GLOBALS['doq']['env']['@log'];
$logsPath=$envLog['#logsPath'];
$clientToken=\doq\Logger::getClientToken();
$pageloadToken=\doq\Logger::getPageloadToken();
\doq\Logger::$isSystemRequest=1;

switch($_GET['action']){
    case 'phplogs':
        $pageToken=$request['pageToken'];
        $clientToken=$request['clientToken'];
        $pageloadToken=$request['pageloadToken'];
        $pageFileLogPath=$logsPath.'/'.$clientToken.'/'.$pageloadToken.'/'.$pageToken.'/log.json';
        if(file_exists($pageFileLogPath)){
            $data=file_get_contents($pageFileLogPath);
            print json_encode(['entries'=>$data],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        }
        break;
    case 'clients':
        if (is_dir($logsPath)) {
            if ($dh1 = opendir($logsPath)) {
                $results=[];
                while (($dn = readdir($dh1)) !== false) {
                    if(($dn=='.')||($dn=='..')) {
                        continue;
                    }
                    $pageLogsPath=$logsPath.'/'.$dn;
                    $timestamp=filemtime($pageLogsPath);
                    
                    $results[]=[
                        'clientToken'=>$dn,
                        'timestamp'=>$timestamp,
                        'date'=>date('Y-m-d H:i',$timestamp)
                    ];
                    
                }
                closedir($dh1);
            }
        }
        print json_encode(['clients'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        break;

    case 'pageloads':
        if(!$clientToken){
            return;
        }
        $targetClientToken=$request['clientToken'];
        if (!$targetClientToken){
            $targetClientToken = $clientToken;
        }

        if (is_dir($logsPath)) {
            $pageloadsLogsPath=$logsPath.'/'.$targetClientToken;
            if (is_dir($pageloadsLogsPath)) {
                if ($dh1 = opendir($pageloadsLogsPath)) {
                    $results=[];
                    while (($pageDir = readdir($dh1)) !== false) {
                        if(($pageDir=='.')||($pageDir=='..')) {
                            continue;
                        }
                        $x=file_get_contents($pageloadsLogsPath.'/'.$pageDir.'/mainmeta.json');
                        $meta=json_decode($x,true,10);
                        $timestamp_float=isset($meta['timestamp_float'])?$meta['timestamp_float']:$meta['timestamp'];
                        $results[]=[
                            'pageloadToken'=>$pageDir,
                            'script'=>$meta['script'], 
                            'timestamp'=>$meta['timestamp'],
                            'timestamp_float'=>$timestamp_float,
                            'date'=>date('Y-m-d H:i:s',$meta['timestamp'])
                        ];
                    }
                    closedir($dh1);
                    print json_encode([
                        'clientToken'=>$targetClientToken ,
                        'clientLogsPath'=>$pageloadsLogsPath,
                        'pageloadTokens'=>$results
                        ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                }
            }
        }
        
        break;
    case 'pages':
        if(!$clientToken){
            return;
        }
        $targetClientToken=$request['clientToken'];
        if (!$targetClientToken){
            $targetClientToken = $clientToken;
        }
        $targetPageloadToken=$request['pageloadToken'];
        if (!$targetPageloadToken){
            $targetPageloadToken = $pageloadToken;
        }
        
        if (is_dir($logsPath)) {
            $clientLogsPath=$logsPath.'/'.$targetClientToken;
            if (is_dir($clientLogsPath)) {
                $pageLogsPath=$clientLogsPath.'/'.$targetPageloadToken;
                if ($dh1 = opendir($pageLogsPath)) {
                    $results=[];
                    while (($pageDir = readdir($dh1)) !== false) {
                        if(($pageDir=='.')||($pageDir=='..')||($pageDir=='mainmeta.json')) {
                            continue;
                        }
                        $x=file_get_contents($pageLogsPath.'/'.$pageDir.'/meta.json');
                        $meta=json_decode($x,true,10);
                        $timestamp_float=isset($meta['timestamp_float'])?$meta['timestamp_float']:$meta['timestamp'];
                        $results[]=[
                            'pageToken'=>$pageDir,
                            'url'=>$meta['url'],
                            'script'=>$meta['script'], 
                            'timestamp'=>$meta['timestamp'],
                            'timestamp_float'=>$timestamp_float,
                            'time'=>date('H:i:s',$meta['timestamp']),
                            'date'=>date('d.m.Y',$meta['timestamp']),
                        ];
                    }
                    closedir($dh1);
                    print json_encode([
                        'clientToken'=>$targetClientToken ,
                        'pageloadToken'=>$targetPageloadToken,
                        'pageLogsPath'=>$pageLogsPath,
                        'pages'=>$results
                        ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                }
            }
        }
        
        break;
}

?>
