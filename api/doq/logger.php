<?php
require_once '../autorun.php';
$headers = getallheaders();
if (stripos($headers["Content-type"], "application/json")!==false) {
    $request=json_decode(file_get_contents("php://input"), true) ?: [];
}

$envLog=$GLOBALS['doq']['env']['@log'];
$clientTokenName=$envLog['#clientTokenName'];
if(!$clientTokenName) {
    $clientTokenName=\doq\Logger::DOQ_CLIENT_TOKEN_NAME;
}
$pageloadTokenName=$envLog['#pageTokenName'];
if(!$pageloadTokenName){
    $pageloadTokenName=\doq\Logger::DOQ_PAGELOAD_TOKEN_NAME;
}

$clientToken=$_COOKIE[$clientTokenName];
$pageloadToken=$_COOKIE[$pageloadTokenName];
$logsPath=$envLog['#logsPath'];

switch($_GET['action']){
    case 'clients':
        if (is_dir($logsPath)) {
            if ($dh1 = opendir($logsPath)) {
                $results=[];
                while (($dn = readdir($dh1)) !== false) {
                    if(($dn=='.')||($dn=='..')) {
                        continue;
                    }
                    $pageLogsPath=$logsPath.'/'.$dn;
                    $results[$dn]=[date('F d Y H:i',filemtime($pageLogsPath))];
                    //$results[]=$dn;
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
                        $results[$pageDir]=[
                            'script'=>$meta['script'], 
                            'timestamp'=>$meta['timestamp'],
                            'time'=>date('H_i_s',$meta['timestamp']),
                            'date'=>date('Y_d_F',$meta['timestamp']),
                        ];
                    }
                    closedir($dh1);
                    print json_encode([
                        'clientToken'=>$targetClientToken ,
                        'clientLogsPath'=>$pageloadsLogsPath,
                        'pageloadTokens'=>$results],
                        JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
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
                        $results[$pageDir]=['script'=>$meta['script'], 
                            'timestamp'=>$meta['timestamp'],
                            'time'=>date('H_i_s',$meta['timestamp']),
                            'date'=>date('Y_d_F',$meta['timestamp'])
                        ];
                    }
                    closedir($dh1);
                    print json_encode([
                        'clientToken'=>$targetClientToken ,
                        'pageloadToken'=>$targetPageloadToken,
                        'pagesPath'=>$pageloadsPath,
                        'pages'=>$results],
                        JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                }
            }
        }
        
        break;
}

?>
