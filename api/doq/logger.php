<?php
require_once '../autorun.php';
$headers = getallheaders();
if (stripos($headers["Content-type"], "application/json")!==false) {
    $data=json_decode(file_get_contents("php://input"), true) ?: [];
}
switch($_GET['action']){
    case 'browse':
        $envLog=$GLOBALS['doq']['env']['@log'];
        $clientTokenName=$envLog['#clientTokenName'];
        $pageTokenName=$envLog['#pageTokenName'];
        $clientToken=$_COOKIE[$clientTokenName];

        if(!$clientToken){
            return;
        }

        $logsPath=$envLog['#logsPath'];

        if (is_dir($logsPath)) {
            $clientLogsPath=$logsPath.'/'.$clientToken;
            if (is_dir($clientLogsPath)) {
                if ($dh1 = opendir($clientLogsPath)) {
                    $results=[];
                    while (($pageDir = readdir($dh1)) !== false) {
                        if(($pageDir=='.')||($pageDir=='..')) {
                            continue;
                        }
                        $pageLogsPath=$clientLogsPath.'/'.$pageDir;
                        if (is_dir($pageLogsPath)) {
                            if ($dh2 = opendir($pageLogsPath)) {
                                $requestLogs=[];
                                while (($requestDir = readdir($dh2)) !== false) {
                                    if(($requestDir=='.')||($requestDir=='..')) {
                                        continue;
                                    }
                                    $requestPath=$pageLogsPath.'/'.$requestDir;
                                    if (is_dir($requestPath)) {
                                        //$env=json_decode(readfile($requestPath.'/env.json'));
                                        $presents=[];
                                        if(is_file($requestPath.'/env.json')){
                                            $presents['env']=1;
                                        }
                                        if(is_file($requestPath.'/datalog.json')){
                                            $presents['datalog']=1;
                                        }
                                        if(is_file($requestPath.'/log.json')){
                                            $presents['log']=1;
                                        }
                                        $requestLogs[$requestDir]=$presents;
                                    }
                                }
                                closedir($dh2);
                                $results[$pageDir]=$requestLogs;
                            }
                        }
                    }
                    closedir($dh1);
                    print json_encode(['pageTokens'=>$results,'clientToken'=>$clientToken],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
                }
            }
        }
        
        break;
}

?>