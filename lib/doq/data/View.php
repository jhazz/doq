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
    public $viewCfg;
    public $cfgSchema;
    public $viewColumns;
    public $linkedDatasources;
    public $viewModifyTime;
    /** @var  \doq\Cache used cache provider*/
    public $queryCache;
    public $writerCache;
    public $writePlanData;

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
            $viewModifyTime=filemtime($viewFile);
            $viewCfg=require($viewFile);
        } else {
            $viewFile=self::$commonPath.'/views/'.$viewName.'.php';
            if(file_exists($viewFile)){
                $viewModifyTime=filemtime($viewFile);
                $viewCfg=require($viewFile);
            } else {
                $err=\doq\tr('doq','View "%s" not found',$viewName);
                trigger_error($err,E_USER_ERROR);
                return [null,$err];
            }
        }
        $view=new View($viewCfg, $viewModifyTime, $viewName);
        return [&$view, null];
    }

    public function __construct(&$viewCfg, $viewModifyTime, $viewId)
    {
        $this->viewModifyTime=$viewModifyTime;
        $this->viewCfg=&$viewCfg;
        $this->viewId=$viewId;
        $this->isCacheable=false;
        if (isset(self::$defaultQueryCache)) {
            $this->queryCache=self::$defaultQueryCache;
            $this->isCacheable=true;
        }
        if(isset(self::$defaultWriterCache)){
            $this->writerCache=self::$defaultWriterCache;
            $this->isCacheable=true;
        }
    }

    /**
    * Executes queryDefs that reads data from a database to datasets
    * @param mixed[] $params any paramaters to a queryDefs
    *   @type array "@filters" select filter commands sequence
    * @param string $newDatasetName any string identifies creating Dataset 
    * @return [\doq\data\Datanode node, number rowcount, mixed error]
    */
    public function read($params=[], $newDatasetName=null)
    {
        if($newDatasetName==null){
           $newDatasetName=$this->viewId;
        }
        if($this->isPreparedQuery==null){
            $this->prepareQuery($this->viewModifyTime);
        }
        $datanode=new \doq\data\Datanode(\doq\data\Datanode::NT_DATASET, $newDatasetName);
        $err=$this->readByQueryDefs($this->queryDefs, $datanode, $params, $newDatasetName);
        if ($err==null) {
            return [$datanode, $datanode->dataset->rowCount, null];
        } else {
            return [null,0,$err];
        }
    }

    
    /**
     * Prepares queryDefs for view. Create from view configuration or reuse from cache
     * @param int $configModifyTime is a timestamp of a last modification time of the view 
     *            configuration that will be sticked to caching defs
     * @param boolean $forceRebuild force to recreate cache
     */
    public function prepareQuery($viewModifyTime, $forceRebuild=false, array &$roles=null)
    {
        if($roles===null) $roles=[];
        else sort($roles, SORT_STRING);
        
        $rolesStr=implode(',', $roles);
        $doRebuild=(!isset($this->queryCache))||($forceRebuild);
        $cacheId=$this->viewId.'_'.md5($rolesStr);

        if(!$doRebuild){
            list($cachedQueryDefs, $err)=$this->queryCache->get($viewModifyTime, $cacheId);
            if ($err===null) {
                if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                    \doq\Logger::debug('doq.View', 'Reuse queryDefs from cache for the roles ['.$rolesStr.'] and query for the view "'.$this->viewId.'"', __FILE__, __LINE__);
                }
                $this->queryDefs=&$cachedQueryDefs;
            } else {
                if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                    \doq\Logger::debug('doq.View', $err, __FILE__, __LINE__);
                }
                $doRebuild=1;
            }
        }
        
        if($doRebuild){
            if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                \doq\Logger::debug('doq.View', 'Rebuild the queryDefs of view "'.$this->viewId.'"', __FILE__, __LINE__);
            }
            if ($this->makeQueryDefs()) {
                if(isset($this->queryCache)){
                    if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                        \doq\Logger::debug('doq.View', 'Overwrites rebuilt queryDefs for the roles ['.$rolesStr.'] and query of the view "'.$this->viewId.'"', __FILE__, __LINE__);
                    }
                    $this->queryCache->put($viewModifyTime, $cacheId, $this->queryDefs);
                }
            }
        }
        $this->isPreparedQuery=true;
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


    /**
     * Forms queryDefs based on view configuration, queryDefs
     */
    public function makeQueryDefs()
    {
        $this->queryDefs=[];
        $this->lastQueryId=1;
        $viewColumns=null;
        return $this->makeQueryDefsRecursive($this->viewCfg, $this->queryDefs, $viewColumns);
    }

    private function makeQueryDefsRecursive(
        &$viewCfg,
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
    )
    {
        $parentDatasetname=$datasetName;
        if (isset($viewCfg['#dataset'])) {
            list($datasourceName, $schemaName, $datasetName, $isOtherDatasource)
                =\doq\data\Scripter::getDatasetPathElements($viewCfg['#dataset'],$datasourceName, $schemaName, $datasetName);
        }
        $datasetRef=$datasourceName.':'.$schemaName.'/'.$datasetName;
        list($datasourceCfg, $datasetCfg, $mtime, $err)=\doq\data\Datasources::getDatasetCfg($datasourceName,$schemaName,$datasetName);
        if ($err!==null) {
            trigger_error($err, E_USER_ERROR);
            return false;
        }
        $dataConnectionName=$datasourceCfg['@config']['#dataConnection'];

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
        $datasetFieldDef=null;
        foreach ($viewCfg as $fieldAlias=>&$viewFieldDef) {
            $firstChar=$fieldAlias[0];
            if (($firstChar=='#')||($firstChar=='@')) {
                continue;
            }
            $newColumn=[
                '#columnId'=>$queryDefs['#lastColumnId'],
                '#field'=>$fieldAlias,
                '#fieldNo'=>$fieldNo
            ];
            $queryDefs['#lastColumnId']++;
            $fieldNo++;

            unset($datasetFieldDef);
            $fieldOrigin=$fieldAlias;
            if (isset($viewFieldDef['#field'])) {
                $fieldOrigin=$viewFieldDef['#field'];
            }
            if (($keyField!==null) && ($keyField==$fieldAlias)) {
                $foundKeyColumn=&$newColumn;
                #$queryDefs['#keyTupleFieldNo']=$queryDefs['#lastTupleFieldNo'];
            }
            if (isset($datasetCfg['@fields'][$fieldOrigin])) {
                $datasetFieldDef=&$datasetCfg['@fields'][$fieldOrigin];
                $newColumn['#fieldOrigin']=$fieldOrigin;
                if (isset($datasetFieldDef['#type'])) {
                    $type=$newColumn['#type']=$datasetFieldDef['#type'];
                } else {
                    $type='';
                }
            } else {
                trigger_error(\doq\t('Model %s.%s.%s has no defined field [%s] that used by view %s', $datasourceName, $schemaName, $datasetName, $fieldAlias, $this->viewId), E_USER_WARNING);
                continue;
            }
            if ($type!=='virtual') {
                $newColumn['#tupleFieldNo']=$queryDefs['#lastTupleFieldNo'];
                $queryDefs['#lastTupleFieldNo']++;
            }


            if (isset($viewFieldDef['#label'])) {
                $newColumn['#label']=$viewFieldDef['#label'];
            } elseif (isset($datasetFieldDef['#label'])) {
                $newColumn['#label']=$datasetFieldDef['#label'];
            }
            if (isset($datasetFieldDef['#refKind'])) {
                $refKind=$newColumn['#refKind']=$datasetFieldDef['#refKind'];
            } else {
                $refKind='';
            }

            if (isset($datasetFieldDef['#isRequired'])) {
                $newColumn['#isRequired']=1;
            }
            if (isset($datasetFieldDef['#ref'])) {
                $ref=$newColumn['#ref']=$datasetFieldDef['#ref'];
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
                    if ($refKind=='aggregation') {
                        $isROtherDatasource=true;
                    }

                    if ($isROtherDatasource) {
                        $newColumn['#refType']='linknext';
                        $newColumn['#refDatasource']=$RdatasourceName;
                        if ($refKind=='aggregation') {
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
                        $refKind,
                        false,
                        $isROtherDatasource
                    );
                } else {
                    $newColumn['#error']='No #ref defined for linking column';
                }
            } elseif (!isset($datasetFieldDef)) {
                $newColumn['#error']='Unknown field '.$fieldAlias;
            }
            $datasetDefs['@fields'][]=&$newColumn;
            unset($newColumn);
        
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
                $newIdxName='idx_agg_'.$parentRef.'^'.$foundDetailColumnForMaster['#fieldOrigin'];
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

    public function prepareWriter(int $viewModifyTime, bool $forceRebuild=false, array &$roles=null)
    {
        if($roles===null) $roles=[];
        else sort($roles, SORT_STRING);

        $rolesStr=implode(',', $roles);
        $doRebuild=(!isset($this->writerCache))||($forceRebuild);
        $cacheId=$this->viewId.'_'.md5($rolesStr);

        if(!$doRebuild){
            list($cachedQueryDefs, $err)=$this->writerCache->get($viewModifyTime, $cacheId);
            if ($err===null) {
                if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                    \doq\Logger::debug('doq.View', 'Reuse writerDefs from cache for the roles ['.$rolesStr.'] and view "'.$this->viewId.'"', __FILE__, __LINE__);
                }
                $this->writerDefs=&$cachedQueryDefs;
            } else {
                if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                    \doq\Logger::debug('doq.View', $err, __FILE__, __LINE__);
                }
                $doRebuild=1;
            }
        }


        if($doRebuild){
            if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                \doq\Logger::debug('doq.View', 'Rebuild the writerDefs of view for roles ['.$rolesStr.'] and view "'.$this->viewId.'"', __FILE__, __LINE__);
            }
            if ($this->makeWriterDefs($roles)) {
                if(isset($this->writerCache)){
                    if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                        \doq\Logger::debug('doq.View', 'Overwrites rebuilt writerDefs for the query of view "'.$this->viewId.'" and the current roles set', __FILE__, __LINE__);
                    }
                    $this->writerCache->put($viewModifyTime, $cacheId, $this->writerDefs);
                }
            }
        }
        $this->isPreparedWriter=true;
    }

    
    
    private function concatPermissionRule(string $currentRule, string $overrideRule){
        $cnt=strlen($overrideRule);
        $mode='';
        for($i=0;$i<$cnt;$i++){
            $c=$overrideRule[$i];
            if(($c=='+')||($c=='-')) {
                $mode=$c;
                continue;
            }
            if(strpos('siud',$c)===false){
                continue;
            }
            $p=strpos($currentRule,$c);
            if( ($mode=='') || ($mode=='+')){
                if ($p===false){
                    $currentRule.=$c;
                }
            } else {
                $currentRule=substr($currentRule, 0, $p) . substr($currentRule, $p + 1);
            }
        }
        return $currentRule;
    }

    private function concatPermissions($rule, &$applyingPermissions, &$roles)
    {
        if(isset($applyingPermissions['*'])){
            $rule=self::concatPermissionRule($rule, $applyingPermissions['*']);
        }
        foreach($applyingPermissions as $role=>&$appendingRule) {
            if($role!='*') {
                $rule=self::concatPermissionRule($rule, $appendingRule);
            }
        }
        return $rule;
    }
    

    /**
     * Create plan to writing data to the datasource. Must be called after prepareWriter($roles)
     * @param array $deltas - the set of inserts, updates and deletes
     * @return  [&$writePlanData, $err]
     */
    public function getWritePlanData(array &$deltas){
        $writePlan=&$this->writerDefs['@writePlan'];
        $writePlanData=array_fill(0,count($writePlan)-1,null);
        
        $inserts=&$deltas['@insert'];
        foreach($inserts as $i=>&$insertElement){
            $aliasPath=$insertElement['#path'];
            $aliasFields=&$insertElement['@columns'];
            $valueTuples=&$insertElement['@values'];
            $aliasPathElements=explode('/', $aliasPath);
            $aliasStructure=&$this->writerDefs['@aliasStructure'];
            foreach($aliasPathElements as $pathElement) {
                if($pathElement!==''){
                    if(!isset($aliasStructure[$pathElement])){
                        $err=\doq\tr('doq','Path "%s" is unavailable in the view alias names',$aliasPath);
                        trigger_error($err, E_USER_ERROR);
                        return [null, $err];
                    }
                    $aliasStructure=&$aliasStructure[$pathElement];
                }
            }
            if(!isset($aliasStructure['#writePlanIndex'])){
                $err=\doq\tr('doq','Alias structure path "%s" has no #writePlanIndex refering to writePlan ',$aliasPath);
                trigger_error($err, E_USER_ERROR);
                return [null, $err];
            }
            $writePlanIndex=$aliasStructure['#writePlanIndex'];
            
            $writePlanElement=&$writePlan[$writePlanIndex];
            $writePlanData[$writePlanIndex]=['#datasetPath'=> $writePlanElement['#datasetPath'], '@newKeys'=>[],'@fields'=>[],'@values'=>[]];
            $writePlanEntry=&$writePlanData[$writePlanIndex];
            $writePlanValues=&$writePlanEntry['@values'];
            $writePlanFields=&$writePlanEntry['@fields'];
            $writePlanKeys  =&$writePlanEntry['@newKeys'];

            foreach($aliasFields as $aliasFieldName){
                if($aliasFieldName!='+'){
                    if(!isset($aliasStructure[$aliasFieldName])){
                        $fieldName='('.$aliasFieldName.' not found)';
                        $err=\doq\tr('doq','@insert uses undefined field alias "%s" in the section "%s"',$aliasFieldName, $aliasPath);
                        trigger_error($err, E_USER_ERROR);
                    } else {
                        $fieldName=$aliasStructure[$aliasFieldName]['#field'];
                    }
                    $writePlanFields[]=$fieldName;
                }
            }
            
            foreach($valueTuples as $rowNo=>&$valueTuple) {
                $writePlanDataRow=[];
                if(!is_array($valueTuple)){
                    $err=\doq\tr('doq','@values array in the @insert section of "%s" must be an array of rows. Not only a array of values!',$aliasPath);
                    trigger_error($err, E_USER_ERROR);
                    return [null, $err];
                }
                foreach($valueTuple as $tupleColumnNo=>&$value) {
                    $aliasFieldName=$aliasFields[$tupleColumnNo];
                    if($aliasFieldName=='+'){
                        $writePlanKeys[$value]=null;
                        continue;
                    }
                    $writePlanDataRow[]=[$value];
                }
                $writePlanValues[]=&$writePlanDataRow;
                unset($writePlanDataRow);
            }
        }
        
        return [&$writePlanData, null];
    }

    public function makeWriterDefs(array &$roles){
        $this->writerDefs=['@writePlan'=>[],'@aliasStructure'=>[]];
        $r=$this->makeWriterDefsRecursive($this->viewCfg, $this->writerDefs['@writePlan'], $this->writerDefs['@aliasStructure'], $roles,'');
        if($r===false) return;
        $this->convertWPlanPathToWOrder($this->writerDefs['@writePlan'], $this->writerDefs['@aliasStructure']);
    }

    private function convertWPlanPathToWOrder (&$writePlan, &$targetAliases){
        if(isset($targetAliases['#writePlanPath'])){
            $writePlanPath=&$targetAliases['#writePlanPath'];
            foreach($writePlan as $i=>&$WPlanEntry){
                if(isset($WPlanEntry['#path'])){
                    if($WPlanEntry['#path']==$writePlanPath){
                        $targetAliases['#writePlanIndex']=$i;
                        break;
                    }
                }
            }
        }
        foreach($targetAliases as $alias=>&$aliasDefs){
            if($alias[0]!='#'){
                $this->convertWPlanPathToWOrder ($writePlan, $aliasDefs);
            }
        }
    }


    private function makeWriterDefsRecursive(&$viewCfg, &$writePlan, &$targetAliases,  &$roles, $baseRule='', 
            $datasourceName='', $schemaName='', $datasetName='', $path='')
    {
        if (isset($viewCfg['#dataset'])) {
            list($datasourceName, $schemaName, $datasetName, $isOtherDatasource)
                =\doq\data\Scripter::getDatasetPathElements($viewCfg['#dataset'],$datasourceName, $schemaName, $datasetName);
        }
        $datasetPath=$datasourceName.':'.$schemaName.'/'.$datasetName;
        list($datasourceCfg, $datasetCfg, $mtime, $err)=\doq\data\Datasources::getDatasetCfg($datasourceName,$schemaName,$datasetName);
        if ($err!==null) {
            trigger_error($err, E_USER_ERROR);
            return false;
        }
        $dataConnectionName=$datasourceCfg['@config']['#dataConnection'];

        $datasetRule=$baseRule;
        $targetAliases['#writePlanPath']=$path;
        $targetAliases['#writePlanIndex']=-1;
        $upFields=[];
        if(isset($datasetCfg['@permissions'])){
            $datasetRule=self::concatPermissions($datasetRule, $datasetCfg['@permissions'], $roles);
        }
        $keyField=$datasetCfg['#keyField'];
        
        foreach ($datasetCfg['@fields'] as $fieldOrigin=>&$fieldOriginDef) {
            $isAutoValue=(isset($fieldOriginDef['#isAutoValue']))?intval($fieldOriginDef['#isAutoValue']):0;
            $isRequired=(isset($fieldOriginDef['#isRequired']))?intval($fieldOriginDef['#isRequired']):0;
            $isKeyField=($fieldOrigin==$keyField)?1:0;
            $fieldRule=$datasetRule;
            if($isAutoValue || $isRequired || $isKeyField){
                if(isset($fieldOriginDef['@permissions'])){
                    $fieldRule=self::concatPermissions($fieldRule, $fieldOriginDef['@permissions'], $roles);
                }

                $upField=['#permissionRule'=>$fieldRule];
                if($isAutoValue) { 
                    $upField['#isAutoValue']=$isAutoValue;
                }
                if (isset($fieldOriginDef['#refKind'])) {
                    $upField['#refKind']=$fieldOriginDef['#refKind'];
                }
                if($isKeyField) {
                    $upField['#isKeyField']=1;
                }
                $upFields[$fieldOrigin]=$upField;
            }
        }
        
        $putAfter=[];
        foreach ($viewCfg as $fieldAlias=>&$viewFieldDef) {
            $firstChar=$fieldAlias[0];
            $ref=$refKind=null;
            if (($firstChar=='#')||($firstChar=='@')) {
                continue;
            }

            $fieldOrigin=isset($viewFieldDef['#field'])?$viewFieldDef['#field']:$fieldAlias;
            if (isset($datasetCfg['@fields'][$fieldOrigin])) {
                $fieldOriginDef=&$datasetCfg['@fields'][$fieldOrigin];
            }
            
            if(isset($fieldOriginDef['#refKind']) && isset($viewFieldDef['@linked']) && isset($fieldOriginDef['#ref'])) {
                $ref=$fieldOriginDef['#ref'];
                $refKind=$fieldOriginDef['#refKind'];
                $linked=&$viewFieldDef['@linked'];
                if($refKind=='aggregation'){
                    $putAfter[]=[$fieldAlias,&$linked,$ref,$keyField];
                    continue;
                }
            }
            if(isset($upFields[$fieldOrigin])){
                $upField=&$upFields[$fieldOrigin];
                $upField['#alias']=$fieldAlias;
            } else {
                $upFields[$fieldOrigin]=['#alias'=>$fieldAlias];
                $upField=&$upFields[$fieldOrigin];
                $fieldRule=$datasetRule;
                if(isset($fieldOriginDef['@permissions'])){
                    $fieldRule=self::concatPermissions($fieldRule, $fieldOriginDef['@permissions'], $roles);
                }
                $upField['#permissionRule']=$fieldRule;
            }
            // TODO! Remove it. Uses just for debugging purpose
            $upField['##aliasPath']=$path.'/'.$fieldAlias;
            $aliasDefs=['#field'=>$fieldOrigin];
            $targetAliases[$fieldAlias]=&$aliasDefs;
            if($refKind==='lookup') {
                list($RdatasourceName, $RschemaName, $RdatasetName, $isROtherDatasource) = \doq\data\Scripter::getDatasetPathElements($ref, $datasourceName, $schemaName, $datasetName);
                self::makeWriterDefsRecursive($linked, $writePlan, $aliasDefs, $roles, '', $RdatasourceName, $RschemaName, $RdatasetName, $path.'/'.$fieldAlias);
            }
            unset($aliasDefs);
        }
        
        $writePlan[]=['#datasetPath'=>$datasetPath, '#path'=>$path, '#keyField'=>$keyField,'@fields'=>&$upFields ];
        unset($upFields);
        

        foreach($putAfter as $i=>&$item){
            $fieldAlias=$item[0];
            $viewFieldDef=&$item[1];
            $ref=$item[2];
            $fieldOrigin=isset($viewFieldDef['#field']) ? $viewFieldDef['#field'] : $fieldAlias;
            $aliasDefs=['#writePlanPath'=> $path.'/'.$fieldAlias,'#writePlanIndex'=>-1, '#field'=>$fieldOrigin, '##aggregateRefField'=>$item[3]];
            $targetAliases[$fieldAlias]=&$aliasDefs;
            list($RdatasourceName, $RschemaName, $RdatasetName, $isROtherDatasource) = \doq\data\Scripter::getDatasetPathElements($ref, $datasourceName, $schemaName, $datasetName);
            self::makeWriterDefsRecursive($viewFieldDef, $writePlan, $aliasDefs, $roles, '', 
                $RdatasourceName, $RschemaName, $RdatasetName, $path.'/'.$fieldAlias /*, $publisherPlanPos, $publisherPlanKeyField*/);
            
        }

        return true;
    }
    
}
/*
 * 0: PRODUCT_GROUP/LINKED_PARENT_GROUP: PRODUCT_GROUPS
 * 1: PRODUCT_GROUP: PRODUCT_GROUPS
 * 2: SECONDGROUP: PRODUCT_GROUPS
 * 3: TYPES
 * 4: PRODUCTS
 * 5: PARAMETERS/PARAMETER: PARAMETERS
 * 6: PARAMETERS: PRODUCT_PARAMETERS
*/