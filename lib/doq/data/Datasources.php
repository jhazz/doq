<?php
namespace doq;

class Datasources{
    private static $datasourcesSchemaPath;
    public static $datasources;
    public static $isInited;
    public static $target;
    
    public static function init(&$env=null, &$target=null){
        if($env==null){
            $env=&GLOBALS['doq'];
        }
        self::$datasourcesSchemaPath=$env['#datasourcesSchemaPath'];
        if($target==null){
            $target=&$GLOBALS['doq'];
        }
        if(!isset($GLOBALS['doq']['datasources'])){
            $GLOBALS['doq']=['datasources'=>[]];
        }
        self::$datasources=&$GLOBALS['doq']['datasources'];
    }
    
    public static function load($datasourceNames){
        $lastTime=0;
        foreach($datasourceNames as $dsn){
            if(isset(self::$datasources[$dsn])){
                $time=self::$datasources[$dsn]['#time'];
            } else {
                $fpath=self::$schemaPath.'/'.$dsn.'.php';
                if(!file_exists($fpath)){
                    $err=\doq\tr('doq', 'Environment variable DOQ_ENVIRONMENT has value "%s" that not found in configuration. You may use "*" as default connection configuration', $currentEnvironment);
                    trigger_error($err,E_USER_ERROR);
                    continue;
                }
                $time=filemtime($fpath);
            }

            if($time>$lastTime) {
                $lastTime=$time;
            }
            
            self::$datasources[$dsn]=['#time'=>$time, '@config'=>require($fpath)];
        }
        return $lastTime;
    }
}

?>