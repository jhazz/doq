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
    
    
    // list($cfgSchemaDataset,$dataConnectionName,$err)=\doq\data\Datasources::getDatasetCfg($datasourceName,$schemaName,$datasetName);
    // $this->cfgSchema['@datasources'][$datasourceName]['@schemas'][$schemaName]['@datasets'][$datasetName];

    /**
     * 
     * @param {string} $datasourceName 
     * @param {string} $schemaName 
     * @param {string} $datasetName 
     * @return  [&$datasourceCfg, &$cfgSchemaDataset,$err]
     */
    public static function getDatasetCfg($datasourceName,$schemaName,$datasetName){
        $datasourceCfg=self::getDatasourceCfg($datasourceName);
        if(!isset($datasourceCfg['@schemas'][$schemaName])){
            $err=\doq\tr('doq', 'Schema %s not found', $schemaName);
            return [null, null, $err];
        }
        
        $schemaCfg=&$datasourceCfg['@schemas'][$schemaName];
        if(!isset($schemaCfg['@datasets'][$datasetName])){
            $err=\doq\tr('doq', 'Dataset %s not found', $datasetName);
            return [null, null, $err];
        }
        
        $cfgSchemaDataset=&$schemaCfg['@datasets'][$datasetName];
        return [&$datasourceCfg, &$cfgSchemaDataset, null];
        
    }
    
    public static function &getDatasourceCfg($datasourceName){
        if(self::$isInited==null){
            self::init();
        }

        if(isset(self::$datasources[$datasourceName])){
            return [&self::$datasources[$datasourceName],null];
        } else {
            $fpath=self::$schemaPath.'/'.$datasourceName.'.php';
            if(!file_exists($fpath)){
                $err=\doq\tr('doq', 'Datasource %s not found', $datasourceName);
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