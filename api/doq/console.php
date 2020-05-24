<?php
require_once 'autorun.php';
\doq\Logger::init($GLOBALS['doq']['env']['@log']);

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
        $pageLogsPath=$logsPath.'/'.$clientToken.'/'.$pageloadToken.'/'.$pageToken;
        $x=file_get_contents($pageLogsPath.'/meta.json');
        $meta=json_decode($x,true,10);
        $pageFileLogPath=$pageLogsPath.'/log.json';
        if(file_exists($pageFileLogPath)){
            print '{"url":"'.$meta['url'].'", "timestamp":'.$meta['timestamp'].',"entries":[';
            readfile($pageFileLogPath);
            print ']}';
        }
        break;
    case 'datalog':
        $pageToken=$request['pageToken'];
        $clientToken=$request['clientToken'];
        $pageloadToken=$request['pageloadToken'];
        $pageLogsPath=$logsPath.'/'.$clientToken.'/'.$pageloadToken.'/'.$pageToken;
        $x=file_get_contents($pageLogsPath.'/meta.json');
        $meta=json_decode($x,true,10);
        $filePath=$pageLogsPath.'/datalogidx.json';
        if(file_exists($filePath)){
            print '{"url":"'.$meta['url'].'", "timestamp":'.$meta['timestamp'].',"entries":[';
            readfile($filePath);
            print ']}';
        }
        break;
    case 'datalogentry_light':
        $filePath='/var/www/html/doq/www/doq/sample_light1.json';
        readfile($filePath);
        
        break;
    case 'datalogentry':
        $pageToken=$request['pageToken'];
        $clientToken=$request['clientToken'];
        $pageloadToken=$request['pageloadToken'];
        $requstRowNo=intval($request['rowNo']);
        $pageLogsPath=$logsPath.'/'.$clientToken.'/'.$pageloadToken.'/'.$pageToken;
        $idxFile=$pageLogsPath.'/datalogidx.json';
        if(file_exists($idxFile)){
            $c=file_get_contents($idxFile);
            $logEntriesIndex=json_decode('['.$c.']',true,10);
            $row=&$logEntriesIndex[$requstRowNo];
            $start=intval($row['start']);
            $len=intval($row['len']);
            
            $datalogPath=$pageLogsPath.'/datalog.dat';
            if(file_exists($datalogPath)){
                $fl=fopen($datalogPath,'rb');
                fseek($fl,$start);
                $data=fread($fl,$len);
                fclose($fl);
                if ($row['type']=='queryDefs'){
                    $jsonData=json_decode($data,true);
                    print '{"type":"queryDefs","html":"'.addslashes(\doq\Logger::dumpQueryEntry($jsonData['queryDefs'])).'"}';
                    return;
                }
                
                print $data;
            } else {
                print '{"error":"No file '. $datalogPath.'"}';
            }
        } else {
            print '{"error":"No file '. $idxFile.'"}';
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
                    $m=file_get_contents($pageLogsPath.'/mainmeta.json');
                    $mainmeta=json_decode($m,true,10);
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
                        'firstPageToken'=>$mainmeta['firstPageToken'],
                        'pages'=>$results
                        ],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                }
            }
        }
        
        break;
}

?>
