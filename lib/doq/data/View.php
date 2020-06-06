<?php
namespace doq\data;

/**
* doq\data\View - is a collection of a database schema, view configuration and columns.
* It propose conversion those configurations to a query definitions (queryDefs) within 
* SQL scripts to select data from database Connection.
* QueryDefs stores to a doq\Cache and reuses if a main configuration of schema has not 
* been changed by admin. 
*
*/
class View
{
    /** @var  \doq\Cache default cache provider used within created \doq\View */
    public $queryDefs; // Used by logger dumper
    public $viewId;
    public $cfgView;
    public $cfgSchema;
    public $viewColumns;
    public $linkedDatasources;
    public $configMTime;
    /** @var  \doq\Cache used cache provider*/
    public $queryCache;
    public $writerCache;

    private $isPreparedQuery;
    private $isPreparedWriter;
    private static $defaultQueryCache;
    private static $defaultQueryCacheName;
    private static $defaultWriterCache;
    private static $defaultWriterCacheName;
    
    private static $isInited;
    private static $appPath;
    private static $commonPath;

    public static function init($appPath=null, $commonPath=null,$defaultQueryCacheName='queries', $defaultWriterCacheName='writers'){
       
        self::$defaultQueryCacheName=$defaultQueryCacheName;
        self::$defaultWriterCacheName=$defaultWriterCacheName;
        list($cache,$err)=\doq\Cache::create($defaultQueryCacheName);
        if($err){
            trigger_error($err,E_USER_ERROR);
            return;
        }
        self::$defaultQueryCache=$cache;
        list($cache,$err)=\doq\Cache::create($defaultWriterCacheName);
        if($err){
            trigger_error($err,E_USER_ERROR);
            return;
        }
        self::$defaultWriterCache=$cache;

                
        if($appPath==null){
            $appPath=$GLOBALS['APP_PATH'];
        }
        self::$appPath=$appPath;
        
        if($commonPath==null){
            $commonPath=$GLOBALS['doq']['env']['#commonPath'];
        }
        self::$commonPath=$commonPath;
        self::$isInited=true;
    }
    
    
    public static function create($viewName)
    {   
        if(!self::$isInited){
            self::init();
        }
        
        $viewFile=self::$appPath.'/views/'.$viewName.'.php';
        if(file_exists($viewFile)){
            $time=filemtime($viewFile);
            $cfgView=require($viewFile);
        } else {
            $viewFile=self::$commonPath.'/views/'.$viewName.'.php';
            if(file_exists($viewFile)){
                $time=filemtime($viewFile);
                $cfgView=require($viewFile);
            } else {
                $err=\doq\tr('doq','View "%s" not found',$viewName);
                trigger_error($err,E_USER_ERROR);
                return [null,$err];
            }
        }
        $view=new View($cfgView, $time, $viewName);
        return [&$view, null];
    }

    public function __construct(&$cfgView, $configMTime, $viewId)
    {
        $this->configMTime=$configMTime;
        $this->cfgView=&$cfgView;
        $this->viewId=$viewId;
        $this->isCacheable=false;
        if (isset(self::$defaultQueryCache)) {
            $this->queryCache=self::$defaultQueryCache;
            $this->isCacheable=true;
        }
    }



    
    /**
     * Prepares queryDefs for view. Create from view configuration or reuse from cache
     * @param int $configMtime timestamp of a querydef file or a querydef collection database modifying time
     * @param boolean $forceRebuild force to recreate cache and set configMtime timestamp to it
     */
    public function prepareQuery($configMtime, $forceRebuild=false)
    {
        $doRebuild=(!$this->isCacheable)||($forceRebuild);
        
        if(!$doRebuild){
            list($cachedQueryDefs, $err)=$this->queryCache->get($configMtime, $this->viewId);
            if ($err===null) {
                if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                    \doq\Logger::debug('doq.View', 'Reuse queryDefs from cache for the query of view "'.$this->viewId.'"', __FILE__, __LINE__);
                }
                $this->queryDefs=&$cachedQueryDefs;
            } else {
                $doRebuild=1;
            }
        }
        
        if($doRebuild){
            if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                \doq\Logger::debug('doq.View', 'Rebuild cache for the query of view "'.$this->viewId.'"', __FILE__, __LINE__);
            }
            if ($this->makeQueryDefs()) {
                if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                    \doq\Logger::debug('doq.View', 'Overwrites rebuilt queryDefs for the query of view "'.$this->viewId.'"', __FILE__, __LINE__);
                }
                $this->queryCache->put($configMtime, $this->viewId, $this->queryDefs);
            }
        }
        $this->isPreparedQuery=true;
    }

    /**
    * Executes queryDefs that reads data from a database to datasets
    * @param array $params any paramaters to a queryDefs, i.e. @filters
    * @param string $newDatasetName any string identifies creating Dataset 
    * @return array (\doq\data\Datanode node, err)
    */
    public function read($params=[], $newDatasetName=null)
    {
        
        if($newDatasetName==null){
           $newDatasetName=$this->viewId;
        }
        if($this->isPreparedQuery==null){
            $this->prepareQuery($this->configMTime);
        }
        $datanode=new \doq\data\Datanode(\doq\data\Datanode::NT_DATASET, $newDatasetName);
        $err=$this->readByQueryDefs($this->queryDefs, $datanode, $params, $newDatasetName);
        if ($err==null) {
            return [&$datanode, $datanode->dataset->rowCount, null];
        } else {
            return [null,0,$err];
        }
    }

    

    /**
    * Recursive loading data into Dataset wrapped by Datanodes
    * @return $err Exception error or null if no errors occured
    */
    private function readByQueryDefs(&$queryDefs, \doq\data\Datanode $datanode, &$params, $newDatasetName)
    {
        $providerName=$queryDefs['#dataProvider'];
        list($dataset, $err)=\doq\data\Dataset::create($providerName, $queryDefs, $newDatasetName);
        if ($err!==null) {
            return $err;
        }
        $datanode->dataset=$dataset;
        list($connection,$err)=$dataset->connect();
        if($err!==null) {
            return $err;
        }
        $dataset->read($params);
        $datanode->wrap($queryDefs, $dataset);
        if (isset($queryDefs['@subQuery'])) {
            foreach ($queryDefs['@subQuery'] as $i=>&$subQuery) {
                if (isset($subQuery['#detailDatasetName'])) {
                    $detailDatasetName=$subQuery['#detailDatasetName'];
                    $masterFieldNo=$subQuery['#masterFieldNo'];
                    // TODO: Заменить в getTupleFieldValues использование tuples, так как это реализация только табличных данных. Надо использовать индексы
                    $masterTupleFieldNo=$dataset->queryDefs['@dataset']['@fields'][$masterFieldNo]['#tupleFieldNo'];
                    list($parentValueSet, $err)=$dataset->getTupleFieldValues($masterTupleFieldNo);
                    if ($err!==null) {
                        return $err;
                    }
                    $newParams=[];
                    # detailToMasterField STILL NOT KNOWN ! Will be evaluated later
                    $newParams['@filter']=[
                        [
                            '#columnId'=>$subQuery['#detailToMasterColumnId'], // detail index ColumnID
                            '#operand'=>'IN',
                            '@values'=>&$parentValueSet
                        ]
                    ];
                } else {
                    $err='Unknown queryDefs linking';
                    trigger_error($err, E_USER_ERROR);
                    return $err;
                }

                $childNode=new \doq\data\Datanode(Datanode::NT_DATASET, $detailDatasetName, $datanode);
                $err=$this->readByQueryDefs($subQuery, $childNode, $newParams, $detailDatasetName);
                if ($err!==null) {
                    return $err;
                }
            }
        }
        return null;
    }


    
    public function prepareWriter($configMtime, $forceRebuild=false)
    {
        $doRebuild=(!$this->isCacheable)||($forceRebuild);
        
        $this->makeWriterDefs();
        $this->isPreparedWriter=true;
        
    }
    public function makeWriterDefs(){
        $this->writerDefs=[];
        
    }
    
    
    /**
     * Forms queryDefs based on view configuration, queryDefs
     */
    public function makeQueryDefs()
    {
        $this->queryDefs=[];
        $this->lastQueryId=1;
        $viewColumns=null;
        return $this->makeQueryDefsRecursive($this->cfgView, $this->queryDefs, $viewColumns);
    }

    
    
    
    
    private function makeQueryDefsRecursive(
        &$cfgView,
        &$queryDefs,
        &$parentViewColumn,
        $datasourceName='',
        $schemaName='',
        $datasetName='',
        $parentRef='',
        $masterFieldNo=null,
        $detailDatasetName=false,
        $masterKind=false,
        $isNewQuery=true,
        $isOtherDatasource=false
    ) {
        $parentDatasetname=$datasetName;
        if (isset($cfgView['#dataset'])) {
            list($datasourceName, $schemaName, $datasetName, $isOtherDatasource)
                =\doq\data\Scripter::getDatasetPathElements($cfgView['#dataset'],$datasourceName, $schemaName, $datasetName);
        }
        $datasetRef=$datasourceName.':'.$schemaName.'/'.$datasetName;
        list($datasourceCfg, $datasetCfg, $mtime, $err)=\doq\data\Datasources::getDatasetCfg($datasourceName,$schemaName,$datasetName);
        $dataConnectionName=$datasourceCfg['@config']['#dataConnection'];
        if ($err!==null) {
            trigger_error($err, E_USER_ERROR);
            return false;
        }

        if ($isOtherDatasource) {
            $subQuery=[];
            if (!isset($queryDefs['@subQuery'])) {
                $queryDefs['@subQuery']=[&$subQuery];
            } else {
                $queryDefs['@subQuery'][]=&$subQuery;
            }
            $masterQuery=&$queryDefs;
            $queryDefs=&$subQuery;
            $isNewQuery=true;
        }
        $datasetDefs=['#schema'=>$schemaName,'#datasetName'=>$datasetName,'@fields'=>[]];

        if (isset($datasetCfg['@keyFields'])) {
            trigger_error('Unsupported multiple field primary keys', E_USER_ERROR);
            return false;
        } elseif (isset($datasetCfg['#keyField'])) {
            $datasetDefs['#keyField']=$datasetCfg['#keyField'];
        }


        if ($isNewQuery) {
            $queryDefs['#lastColumnId']=0;
            $queryDefs['#lastTupleFieldNo']=0;
            $queryDefs['#queryId']=$this->lastQueryId;
            $this->lastQueryId++;
            $queryDefs['#dataSource']=$datasourceName;
            $queryDefs['#dataConnection']=$dataConnectionName;
            list($connection,$err) = \doq\data\Connections::getConnection($dataConnectionName);
            $providerName=$connection->provider;
            $queryDefs['#dataProvider']=$providerName;
        } else {
            $parentViewColumn['@dataset']=&$datasetDefs;
        }

        $foundDetailColumnForMaster=null;
        $foundKeyColumn=null;

        if (isset($datasetDefs['#keyField'])) {
            $keyField=$datasetDefs['#keyField'];
        } else {
            $keyField=null;
        }

        $fieldNo=0;
        $datasourceFieldDef=null;
        foreach ($cfgView as $localFieldName=>&$viewFieldDef) {
            $fc=$localFieldName[0];
            if (($fc!='#')&&($fc!='@')) {
                $newColumn=[
                    '#columnId'=>$queryDefs['#lastColumnId'],
                    '#field'=>$localFieldName,
                    '#fieldNo'=>$fieldNo
                ];
                $queryDefs['#lastColumnId']++;
                $fieldNo++;

                unset($datasourceFieldDef);
                $originField=$localFieldName;
                if (isset($viewFieldDef['#field'])) {
                    $originField=$viewFieldDef['#field'];
                }
                
                if (($keyField!==null) && ($keyField==$localFieldName)) {
                    $foundKeyColumn=&$newColumn;
                    #$queryDefs['#keyTupleFieldNo']=$queryDefs['#lastTupleFieldNo'];
                }
                if (isset($datasetCfg['@fields'][$originField])) {
                    $datasourceFieldDef=&$datasetCfg['@fields'][$originField];
                    $newColumn['#originField']=$originField;
                    if (isset($datasourceFieldDef['#type'])) {
                        $type=$newColumn['#type']=$datasourceFieldDef['#type'];
                    } else {
                        $type='';
                    }
                } else {
                    trigger_error(\doq\t('Model %s.%s.%s has no defined field [%s] that used by view %s', $datasourceName, $schemaName, $datasetName, $localFieldName, $this->viewId), E_USER_WARNING);
                    continue;
                }
                if ($type!=='virtual') {
                    $newColumn['#tupleFieldNo']=$queryDefs['#lastTupleFieldNo'];
                    $queryDefs['#lastTupleFieldNo']++;
                }


                if (isset($viewFieldDef['#label'])) {
                    $newColumn['#label']=$viewFieldDef['#label'];
                } elseif (isset($datasourceFieldDef['#label'])) {
                    $newColumn['#label']=$datasourceFieldDef['#label'];
                }
                if (isset($datasourceFieldDef['#kind'])) {
                    $newColumn['#kind']=$kind=$datasourceFieldDef['#kind'];
                } else {
                    $kind='';
                }
                
                if (isset($datasourceFieldDef['#isRequired'])) {
                    $newColumn['#isRequired']=1;
                }
                if (isset($datasourceFieldDef['#ref'])) {
                    $newColumn['#ref']=$ref=$datasourceFieldDef['#ref'];
                    if ($masterKind=='aggregation') {
                        if ($parentRef==$ref) {
                            $foundDetailColumnForMaster=&$newColumn;
                        }
                    }
                }

                if (isset($viewFieldDef['@linked'])) {
                    if ($ref) {
                        $subMasterFieldNo=isset($newColumn['#fieldNo'])?$newColumn['#fieldNo']:false;
                        $subDatasetId=$newColumn['#field'];
                        list($RdatasourceName, $RschemaName, $RdatasetName, $isROtherDatasource)
                            = \doq\data\Scripter::getDatasetPathElements($ref, $datasourceName, $schemaName, $datasetName);
                        $newColumn['#refSchema']=$RschemaName;
                        $newColumn['#refDataset']=$RdatasetName;
                        if ($kind=='aggregation') {
                            $isROtherDatasource=true;
                        }

                        if ($isROtherDatasource) {
                            $newColumn['#refType']='linknext';
                            $newColumn['#refDatasource']=$RdatasourceName;
                            if ($kind=='aggregation') {
                                if ($type=='virtual') {
                                    // virtual is a type of aggregation field for now
                                    if ($foundKeyColumn===null) {
                                        trigger_error('Define primary key field in the View first!');
                                        return false;
                                    }
                                    $subMasterFieldNo=$foundKeyColumn['#fieldNo'];
                                }
                            }
                        } else {
                            $newColumn['#refType']='join';
                        }
                        $this->makeQueryDefsRecursive(
                            $viewFieldDef['@linked'],
                            $queryDefs,
                            $newColumn,
                            $RdatasourceName,
                            $RschemaName,
                            $RdatasetName,
                            $datasetRef,
                            $subMasterFieldNo,
                            $subDatasetId,
                            $kind,
                            false,
                            $isROtherDatasource
                        );
                    } else {
                        $newColumn['#error']='No #ref defined for linking column';
                    }
                } elseif (!isset($datasourceFieldDef)) {
                    $newColumn['#error']='Unknown field '.$localFieldName;
                }
                $datasetDefs['@fields'][]=&$newColumn;
                unset($newColumn);
            }
        }

        if ($isNewQuery) {
            $queryDefs['@dataset']=&$datasetDefs;
            if ($masterKind=='lookup') {
                if (is_null($foundKeyColumn)) {
                    if ($keyField) {
                        trigger_error(\doq\t('Not found key field %s in view from %s', $keyField, $datasetRef), E_USER_ERROR);
                    } else {
                        trigger_error(\doq\t('Key field is required for lookup in dataset %s', $datasetRef), E_USER_ERROR);
                    }
                    return false;
                }
                $newIdxName='idx_look_'.$parentRef.'>'.$datasetDefs['#keyField'];
                if (!isset($queryDefs['@indexes'])) {
                    $queryDefs['@indexes']=[];
                }
                if (isset($queryDefs['@indexes'][$newIdxName])) {
                    for ($i=0;$i<10;$i++) {
                        $s=$newIdxName.'/'.$i;
                        if (!isset($queryDefs['@indexes'][$s])) {
                            $newIdxName=$s;
                            break;
                        }
                    }
                }
                $queryDefs['@indexes'][$newIdxName]=[
                    '#type'=>'unique',
                    '#name'=>$newIdxName,
                    '#keyFieldName'=>$foundKeyColumn['#field'],
                    '#keyTupleFieldNo'=>$foundKeyColumn['#tupleFieldNo']
                ];
                $parentViewColumn['#uniqueIndex']=$newIdxName;
                $queryDefs['#detailToMasterColumnId']=$foundKeyColumn['#columnId'];
            } elseif ($masterKind=='aggregation') {
                // aggregation index by the real multilookup field and
                // by the virtual field as default
                if (is_null($foundDetailColumnForMaster)) {
                    trigger_error(\doq\tr('doq','Not found back referenced lookup to %s from %s', $parentRef, $datasetRef), E_USER_ERROR);
                    return false;
                }
                $newIdxName='idx_agg_'.$parentRef.'^'.$foundDetailColumnForMaster['#originField'];
                if (!isset($queryDefs['@indexes'])) {
                    $queryDefs['@indexes']=[];
                }
                if (isset($queryDefs['@indexes'][$newIdxName])) {
                    for ($i=0;$i<10;$i++) {
                        $s=$newIdxName.'/'.$i;
                        if (!isset($queryDefs['@indexes'][$s])) {
                            $newIdxName=$s;
                            break;
                        }
                    }
                }
                
                # Этот индекс, в отличие от лукапа, создает неуникальный индекс,
                # в котором ключами являются ID родителей, а внутри них группируются
                # ссылки на записи деток, которые в него входят
                $queryDefs['@indexes'][$newIdxName]=[
                    '#type'=>'cluster',
                    '#name'=>$newIdxName,
                    '#keyFieldName'=>$foundKeyColumn['#field'],
                    '#keyTupleFieldNo'=>$foundKeyColumn['#tupleFieldNo'],
                    '#byTupleFieldNo'=>$foundDetailColumnForMaster['#tupleFieldNo']
                ];
                $parentViewColumn['#clusterIndex']=$newIdxName;
                $queryDefs['#detailToMasterColumnId']=$foundDetailColumnForMaster['#columnId'];
            } else {
                if (!is_null($foundKeyColumn)) {
                    if (!isset($queryDefs['@indexes'])) {
                        $queryDefs['@indexes']=[];
                    }
                    $queryDefs['@indexes'][\doq\data\Dataset::PRIMARY_KEY_NAME]=[
                        '#type'=>'unique',
                        '#name'=>\doq\data\Dataset::PRIMARY_KEY_NAME,
                        '#keyFieldName'=>$foundKeyColumn['#field'],
                        '#keyTupleFieldNo'=>$foundKeyColumn['#tupleFieldNo']
                    ];
                }
            }

            if ($masterFieldNo!==null) {
                $queryDefs['#masterFieldNo']=$masterFieldNo;
                $queryDefs['#detailDatasetName']=$detailDatasetName;
                if (!isset($masterQuery['@detailIndexByFieldNo'])) {
                    $masterQuery['@detailIndexByFieldNo']=[];
                }
                $masterQuery['@detailIndexByFieldNo'][$masterFieldNo]=$newIdxName;
            }

            list($ok,$scripter)=\doq\data\Scripter::create($providerName);
            $selectScript=$scripter->buildSelectScript($queryDefs);
            if ($selectScript!==false) {
                $queryDefs['#readScript']=$selectScript;
            }
        }
        return true;
    }

}
