<?php
namespace doq\data;

/**
* View - is a data loading queryDefs that creates Datasets that will read data according to parameters
*
*/
class View
{
    private static $defaultCache;
    public $queryDefs; // Used by logger dumper
    public $viewId;
    public $cfgView;
    public $cfgSchema;
    public $viewColumns;
    public $linkedDatasources;
    /** @var  \doq\Cache */
    public $cache;

    public static function create(&$cfgSchema, &$cfgView, $viewId=false)
    {
        $r=new View($cfgSchema, $cfgView, $viewId);
        return[true,&$r];
    }

    public function __construct(&$cfgSchema, &$cfgView, $viewId=false)
    {
        $this->cfgSchema=&$cfgSchema;
        $this->cfgView=&$cfgView;
        $this->viewId=$viewId;
        $this->isCacheable=false;
        if (isset(self::$defaultCache)) {
            $this->cache=self::$defaultCache;
            $this->isCacheable=true;
        }
        if ($viewId===false && isset($cfgView['#viewId'])) {
            $viewId=$cfgView['#viewId'];
        }
    }

    /**
     * Sets default cache provider to store evaluated querys
     * @param \doq\Cache $cache refers to a cache provider
     */
    public static function setDefaultCache(&$cache)
    {
        self::$defaultCache=&$cache;
    }

    /**
     * Sets cache provider to store evaluated querys only to this view
     * @param \doq\Cache $cache refers to a cache provider
     */
    public function setCacher(&$cache)
    {
        if ($this->viewId===false) {
            trigger_error(\doq\t('You must set viewId to get ability for using cachers'), E_USER_ERROR);
            return false;
        }
        $this->cache=&$cache;
        $this->isCacheable=true;
        return true;
    }

    /**
     * Prepares queryDefs for view. Create from view configuration or reuse from cache
     * @param int $configMtime timestamp of external configuration file
     * @param boolean $forceRebuild force to recreate cache and set configMtime timestamp to it
     */
    public function prepare($configMtime, $forceRebuild=false)
    {
        if ((!$this->isCacheable)||($forceRebuild)) {
            if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                \doq\Logger::debug('view', 'Rebuild cache for view "'.$this->viewId.'"', __FILE__, __LINE__);
            }
            if ($this->makeQuery()) {
                if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                    \doq\Logger::debug('view', 'Overwrite rebuild queryDefs for view "'.$this->viewId.'"', __FILE__, __LINE__);
                }

                $this->cache->put($configMtime, $this->viewId, $this->queryDefs);
            }
        } else {
            list($ok, $data)=$this->cache->get($configMtime, $this->viewId);
            if ($ok) {
                if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                    \doq\Logger::debug('view', 'Reuse queryDefs from cache for the view "'.$this->viewId.'"', __FILE__, __LINE__);
                }
                $this->queryDefs=&$data;
            } else {
                if ($this->makeQuery()) {
                    if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                        \doq\Logger::debug('view', 'Store queryDefs in cache for the view "'.$this->viewId.'"', __FILE__, __LINE__);
                    }
                    $this->cache->put($configMtime, $this->viewId, $this->queryDefs);
                }
            }
        }
    }

    /**
    * Executes queryDefs that reads data from a database to datasets
    * @param array $params any paramaters to a queryDefs, i.e. @filters
    * @param string $newDatasetId any string identifies creating Dataset 
    * @return array (boolean status, \doq\data\Datanode node)
    */
    public function read(&$params, $newDatasetId)
    {
        $datanode=new \doq\data\Datanode(\doq\data\Datanode::NT_DATASET, $newDatasetId);
        $r=$this->readQuery($this->queryDefs, $datanode, $params, $newDatasetId);
        if($r[0]){
            return [true,$datanode];
        } else {
            return $r;
        }        
    }

    

    /**
    * Recursive loading data into Dataset wrapped by Datanodes
    */
    private function readQuery(&$queryDefs, \doq\data\Datanode $datanode, &$params, $newDatasetId)
    {

        $providerName=$queryDefs['#dataProvider'];
        list($ok, $dataset)=\doq\data\Dataset::create($providerName, $queryDefs, $newDatasetId);
        if (!$ok) {
            return [false,$dataset];
        }
        $datanode->dataset=$dataset;
        if ($dataset->connect()) {
            $dataset->read($params);
        }
        $datanode->wrap($queryDefs, $dataset);
        if (isset($queryDefs['@subQuery'])) {
            foreach ($queryDefs['@subQuery'] as $i=>&$subQuery) {
                if (isset($subQuery['#detailDatasetId'])) {
                    $detailDatasetId=$subQuery['#detailDatasetId'];
                    $masterFieldNo=$subQuery['#masterFieldNo'];
                    $masterColumnNo=$dataset->queryDefs['@dataset']['@fields'][$masterFieldNo]['#tupleFieldNo'];
                    list($ok, $parentValueSet)=$dataset->getTupleFieldValues($masterColumnNo);
                    if (!$ok) {
                        return false;
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
                    trigger_error('Unknown queryDefs linking', E_USER_ERROR);
                    return false;
                }

                $childNode=new \doq\data\Datanode(Datanode::NT_DATASET, $detailDatasetId, $datanode);
                $ok=$this->readQuery($subQuery, $childNode, $newParams, $detailDatasetId);
                if (!$ok) {
                    return false;
                }
            }
        }
        return [true];
    }

    /**
     * Forms queryDefs based on view configuration, queryDefs
     */
    public function makeQuery()
    {
        $this->queryDefs=[];
        $this->lastQueryId=1;
        $viewColumns=null;
        return $this->makeQueryRecursive($this->cfgView, $this->queryDefs, $viewColumns);
    }

    private function makeQueryRecursive(
        &$cfgView,
        &$queryDefs,
        &$parentViewColumn,
        $datasourceName='',
        $schemaName='',
        $datasetName='',
        $parentRef='',
        $masterFieldNo=null,
        $detailDatasetId=false,
        $masterKind=false,
        $isNewQuery=true,
        $isOtherDatasource=false
    ) {
        $parentDatasetname=$datasetName;
        if (isset($cfgView['#dataset'])) {
            list($datasourceName, $schemaName, $datasetName, $isOtherDatasource)
            =\doq\data\Scripter::getDatasetPathElements(
                $cfgView['#dataset'],
                $datasourceName, 
                $schemaName, 
                $datasetName);
        }

        $datasetRef=$datasourceName.':'.$schemaName.'/'.$datasetName;

        $cfgSchemaDataset=&$this->cfgSchema['@datasources'][$datasourceName]['@schemas'][$schemaName]['@datasets'][$datasetName];
        if (!$cfgSchemaDataset) {
            trigger_error(\doq\t('Cannot find model schema  %s', $datasetRef), E_USER_ERROR);
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

        if (isset($cfgSchemaDataset['@keyFields'])) {
            trigger_error('Unsupported multiple field primary keys', E_USER_ERROR);
            return false;
        } elseif (isset($cfgSchemaDataset['#keyField'])) {
            $datasetDefs['#keyField']=$cfgSchemaDataset['#keyField'];
        }


        if ($isNewQuery) {
            $queryDefs['#lastColumnId']=0;
            $queryDefs['#lastTupleFieldNo']=0;
            $queryDefs['#queryId']=$this->lastQueryId;
            $this->lastQueryId++;
            $queryDefs['#dataSource']=$datasourceName;
            $cfgDatasource=&$this->cfgSchema['@datasources'][$datasourceName];
            $dataConnectionName=$cfgDatasource['#dataConnection'];
            $queryDefs['#dataConnection']=$dataConnectionName;
            list($ok, $connection) = \doq\data\Connections::getConnection($dataConnectionName);
            $providerName=$connection->provider;
            $queryDefs['#dataProvider']=$providerName;
        } else {
            $parentViewColumn['@dataset']=&$datasetDefs;
        }

        $foundDetailColumnForMaster=false;
        $foundKeyColumn=false;

        if (isset($datasetDefs['#keyField'])) {
            $keyField=$datasetDefs['#keyField'];
        } else {
            $keyField=false;
        }

        $fieldNo=0;
        foreach ($cfgView as $localFieldName=>&$viewFieldDef) {
            $fc=$localFieldName[0];
            if (($fc!='#')&&($fc!='@')) {
                $newColumn=['#columnId'=>$queryDefs['#lastColumnId'],'#field'=>$localFieldName,'#fieldNo'=>$fieldNo];
                $queryDefs['#lastColumnId']++;
                $fieldNo++;

                unset($modelFieldDef);
                $originField=$localFieldName;
                if (isset($viewFieldDef['#field'])) {
                    $originField=$viewFieldDef['#field'];
                }
                if ($keyField && ($keyField===$originField)) {
                    $foundKeyColumn=&$newColumn;
                }
                if (isset($cfgSchemaDataset['@fields'][$originField])) {
                    $modelFieldDef=&$cfgSchemaDataset['@fields'][$originField];
                    $newColumn['#originField']=$originField;
                    if (isset($modelFieldDef['#type'])) {
                        $type=$newColumn['#type']=$modelFieldDef['#type'];
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
                } elseif (isset($modelFieldDef['#label'])) {
                    $newColumn['#label']=$modelFieldDef['#label'];
                }
                if (isset($modelFieldDef['#kind'])) {
                    $newColumn['#kind']=$kind=$modelFieldDef['#kind'];
                } else {
                    $kind='';
                }
                if (isset($modelFieldDef['#ref'])) {
                    $newColumn['#ref']=$ref=$modelFieldDef['#ref'];
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
                        list($RdatasourceName, $RschemaName, $RdatasetName, $isROtherDatasource)=\doq\data\Scripter::getDatasetPathElements($ref, $datasourceName, $schemaName, $datasetName);
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
                                    # virtual is a mostly common type of aggregation field
                                    if (!$foundKeyColumn) {
                                        trigger_error('Define primary key field in the View first!');
                                        return false;
                                    }
                                    $subMasterFieldNo=$foundKeyColumn['#fieldNo'];
                                }
                            }
                        } else {
                            $newColumn['#refType']='join';
                        }
                        $this->makeQueryRecursive(
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
                } elseif (!isset($modelFieldDef)) {
                    $newColumn['#error']='Unknown field '.$localFieldName;
                }
                $datasetDefs['@fields'][]=&$newColumn;
                unset($newColumn);
            }
        }


        if ($isNewQuery) {
            $queryDefs['@dataset']=&$datasetDefs;
            if ($masterKind=='lookup') {
                if (!$foundKeyColumn) {
                    if ($keyField) {
                        trigger_error(\doq\t('Not found key field %s in view from %s', $keyField, $datasetRef), E_USER_ERROR);
                    } else {
                        trigger_error(\doq\t('Key field is required for lookup in dataset %s', $datasetRef), E_USER_ERROR);
                    }
                    return false;
                }
                $newIdxName='idx_look_'.$parentRef.'--'.$datasetDefs['#keyField'];
                if (!isset($queryDefs['@resultIndexes'])) {
                    $queryDefs['@resultIndexes']=[];
                }
                if (isset($queryDefs['@resultIndexes'][$newIdxName])) {
                    for ($i=0;$i<10;$i++) {
                        $s=$newIdxName.'/'.$i;
                        if (!isset($queryDefs['@resultIndexes'][$s])) {
                            $newIdxName=$s;
                            break;
                        }
                    }
                }
                $queryDefs['@resultIndexes'][$newIdxName]=[
                    '#type'=>'unique',
                    '#name'=>$newIdxName,
                    '#keyFieldName'=>$foundKeyColumn['#field'],
                    '#keyTupleFieldNo'=>$foundKeyColumn['#tupleFieldNo'],
                    '#byTupleFieldNo'=>$foundKeyColumn['#tupleFieldNo']
                ];
                $parentViewColumn['#uniqueIndex']=$newIdxName;
                $queryDefs['#detailToMasterColumnId']=$foundKeyColumn['#columnId'];
            } elseif ($masterKind=='aggregation') {
                # aggregation by the real multilookup field and by the virtual field as default
                if (!$foundDetailColumnForMaster) {
                    trigger_error(\doq\t('Not found back referenced lookup to %s from %s', $parentRef, $datasetRef), E_USER_ERROR);
                    return false;
                }
                $newIdxName='idx_agg_'.$parentRef.'=='.$foundDetailColumnForMaster['#originField'];
                if (!isset($queryDefs['@resultIndexes'])) {
                    $queryDefs['@resultIndexes']=[];
                }
                if (isset($queryDefs['@resultIndexes'][$newIdxName])) {
                    for ($i=0;$i<10;$i++) {
                        $s=$newIdxName.'/'.$i;
                        if (!isset($queryDefs['@resultIndexes'][$s])) {
                            $newIdxName=$s;
                            break;
                        }
                    }
                }
                
                # Этот индекс, в отличие от лукапа, создает неуникальный индекс,
                # в котором ключами являются ID родителей, а внутри них группируются
                # ссылки на записи деток, которые в него входят
                $queryDefs['@resultIndexes'][$newIdxName]=[
                    '#type'=>'nonunique',
                    '#name'=>$newIdxName,
                    '#keyFieldName'=>$foundKeyColumn['#field'],
                    '#keyTupleFieldNo'=>$foundKeyColumn['#tupleFieldNo'],
                    '#byTupleFieldNo'=>$foundDetailColumnForMaster['#tupleFieldNo']
                ];
                $parentViewColumn['#nonuniqueIndex']=$newIdxName;
                $queryDefs['#detailToMasterColumnId']=$foundDetailColumnForMaster['#columnId'];
            }
            if ($masterFieldNo!==null) {
                $queryDefs['#masterFieldNo']=$masterFieldNo;
                $queryDefs['#detailDatasetId']=$detailDatasetId;
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
