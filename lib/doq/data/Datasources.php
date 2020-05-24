<?php
return;
namespace doq\data;

class Datasources{
    public static $datasources;
    public static $isInited;
    public static $destCfg;
    private static $appPath;
    private static $commonPath;
    
    public static function init(&$env=null, &$destCfg=null){
        if($env==null){
            $env=&$GLOBALS['doq']['env'];
        }
        
        if($destCfg==null){
            $destCfg=&$GLOBALS['doq'];
        }
        if(!isset($GLOBALS['doq']['@datasources'])){
            $GLOBALS['doq']['@datasources']=[];
        }
        self::$datasources=&$GLOBALS['doq']['@datasources'];
        self::$isInited=true;
    }
    
    
    public static function loadConfig($datasourceName){
        if(self::$isInited==null){
            self::init();
        }

        if(isset(self::$datasources[$datasourceName])){
            return [&self::$datasources[$datasourceName],null];
        } else {
            $fpath=self::$schemaPath.'/'.$datasourceName.'.php';
            if(!file_exists($fpath)){
                $err=\doq\tr('data.Datasource', 'Datasource %s not found', $datasourceName);
                trigger_error($err,E_USER_ERROR);
                return [null,$err];
            }
            $time=filemtime($fpath);
            self::$datasources[$datasourceName]=['#time'=>$time, '@config'=>require($fpath)];
            return self::$datasources[$datasourceName];
        }
    }

    
    /** Load datasource configurations by DataSourceNames list from * #datasourcesSchemaPath. 
     * Configurations are stored to $GLOBALS['doq']['datasources']
     * @param Array $datasourceNames array of datasources
     * @return int timestamp of the last modifyied DS 
     **/
    public static function loadConfigList($datasourceNames){
        if(self::$isInited==null){
            self::init();
        }

        $lastTime=0;
        foreach($datasourceNames as $dsn){
            if(isset(self::$datasources[$dsn])){
                $time=self::$datasources[$dsn]['#time'];
            } else {
                $fpath=self::$schemaPath.'/'.$dsn.'.php';
                if(!file_exists($fpath)){
                    $err=\doq\tr('data.Datasource', 'Datasource %s not found', $dsn);
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