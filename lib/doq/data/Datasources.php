<?php
namespace doq\data;

class Datasources{
    public static $datasources;
    public static $isInited;
    public static $destCfg;
    private static $appPath;
    private static $commonPath;
    private static $datasourcesFolderPath;
    
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
        self::$datasourcesFolderPath=$env['#commonPath'].'/datasources';
        self::$isInited=true;
    }
    
    
    /**
     * 
     * @param {string} $datasourceName 
     * @param {string} $schemaName 
     * @param {string} $datasetName 
     * @return  [&$datasourceCfg, &$datasetCfg,$mtime, $err]
     */
    public static function getDatasetCfg($datasourceName,$schemaName,$datasetName){
        list($datasourceCfg,$err)=self::getDatasourceCfg($datasourceName);
        if(!isset($datasourceCfg['@config']['@schemas'][$schemaName])){
            $err=\doq\tr('doq', 'Schema %s not found', $schemaName);
            return [null, null, null, $err];
        }
        $mtime=$datasourceCfg['#mtime'];
        
        $schemaCfg=&$datasourceCfg['@config']['@schemas'][$schemaName];
        if(!isset($schemaCfg['@datasets'][$datasetName])){
            $err=\doq\tr('doq', 'Dataset %s not found', $datasetName);
            return [null, null, null, $err];
        }
        
        $datasetCfg=&$schemaCfg['@datasets'][$datasetName];
        return [&$datasourceCfg, &$datasetCfg, $mtime, null];
        
    }
    
    public static function getDatasourceCfg($datasourceName){
        if(self::$isInited==null){
            self::init();
        }

        if(isset(self::$datasources[$datasourceName])){
            return [&self::$datasources[$datasourceName],null];
        } else {
            $fpath=self::$datasourcesFolderPath.'/'.$datasourceName.'.php';
            if(!file_exists($fpath)){
                $err=\doq\tr('doq', 'Datasource %s not found', $datasourceName);
                trigger_error($err,E_USER_ERROR);
                return [null,null,$err];
            }
            $mtime=filemtime($fpath);
            self::$datasources[$datasourceName]=['#mtime'=>$mtime, '@config'=>require($fpath)];
            return [&self::$datasources[$datasourceName],null];
        }
    }

    
    /** Load datasource configurations by DataSourceNames list from * #datasourcesdatasourcesFolderPath. 
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
                $fpath=self::$datasourcesFolderPath.'/'.$dsn.'.php';
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