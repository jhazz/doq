<?php
namespace doq\data;

/**
* View - is a data loading plan that creates Datasets that will read data according to parameters
*
*/
class View
{
    private static $defaultCache;
    public $viewId;
    public $cfgView;
    public $cfgSchema;
    public $dataset;
    public $viewColumns;
    public $linkedDatasources;
    public $subDatasets;
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
     * Sets default cache provider to store evaluated dataplans
     * @param \doq\Cache $cache refers to a cache provider
     */
    public static function setDefaultCache(&$cache)
    {
        self::$defaultCache=&$cache;
    }

    /**
     * Sets cache provider to store evaluated dataplans only to this view
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
     * Prepares dataplan for view. Create from view configuration or reuse from cache
     * @param int $configMtime timestamp of external configuration file
     * @param boolean $forceRebuild force to recreate cache and set configMtime timestamp to it
     */
    public function prepare($configMtime, $forceRebuild=false)
    {
        if ((!$this->isCacheable)||($forceRebuild)) {
            if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                \doq\Logger::debug('view', 'Rebuild cache for view "'.$this->viewId.'"', __FILE__, __LINE__);
            }
            if ($this->makePlan()) {
                if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                    \doq\Logger::debug('view', 'Overwrite rebuild dataplan for view "'.$this->viewId.'"', __FILE__, __LINE__);
                }

                $this->cache->put($configMtime, $this->viewId, $this->plan);
            }
        } else {
            list($ok, $data)=$this->cache->get($configMtime, $this->viewId);
            if ($ok) {
                if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                    \doq\Logger::debug('view', 'Reuse dataplan from cache for the view "'.$this->viewId.'"', __FILE__, __LINE__);
                }
                $this->plan=&$data;
            } else {
                if ($this->makePlan()) {
                    if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_INFO) {
                        \doq\Logger::debug('view', 'Store dataplan in cache for the view "'.$this->viewId.'"', __FILE__, __LINE__);
                    }
                    $this->cache->put($configMtime, $this->viewId, $this->plan);
                }
            }
        }
    }

    /**
     * @param
     */
    public static function getFieldByColumnId($findColumnId, &$entry)
    {
        foreach ($entry['@dataset']['@fields'] as $i=>&$field) {
            if (isset($field['#columnId']) && ($field['#columnId']==$findColumnId)) {
                return [true,&$field];
            }
            if (isset($field['@dataset'])) {
                $r=self::getFieldByColumnId($findColumnId, $field);
                if ($r[0]) {
                    return $r;
                }
            }
        }
        return[false];
    }


    /**
    * Executes dataplan that reading data from a database
    * @param array $params
    * @param $datasetId
    */
    public function read(&$params, $datasetId)
    {
        /** @var  \doq\data\DataNode is a tree-like node that holds reference to a DataObject
         *        and links to parentNode and to childNodes[]
         */
        $dataNode=new \doq\data\DataNode(\doq\data\DataNode::NT_DATASET, $datasetId);
        $ok=$this->readPlanEntry($this->plan, $dataNode, $params, $datasetId);
        return [$ok,$dataNode];
    }


    /**
    *
    */
    private function readPlanEntry(&$planEntry, $dataNode, &$params, $datasetId)
    {
        $providerName=$planEntry['#dataProvider'];
        /** @var \doq\data\Dataset $dataset */

        list($ok, $dataset)=\doq\data\Dataset::create($providerName, $planEntry, $datasetId);
        if (!$ok) {
            return false;
        }
        $dataNode->dataObject=$dataset;
        if ($dataset->connect()) {
            $dataset->read($params);
        }

        $dataset->collectDataNodesRecursive($planEntry, $dataNode);

        if (isset($planEntry['@subPlan'])) {
            foreach ($planEntry['@subPlan'] as $i=>&$subPlanEntry) {
                if (isset($subPlanEntry['#detailDatasetId'])) {
                    $detailDatasetId=$subPlanEntry['#detailDatasetId'];
                    if (!isset($planEntry['@dataset']['@fields'])) {
                        trigger_error(\doq\t('В planEntry[@subPlan] отсутствуют колонки'), E_USER_ERROR);
                        return false;
                    }
                    $masterFieldNo=$subPlanEntry['#masterFieldNo'];

                    # для виртуального aggregation $masterFieldNo должен указывать всегда на колонку данных
                    # первичного ключа masterDataSet
                    # для lookup это номер колонки данных, из которой идет ссылка на справочник
                    #list($ok,$parentValueSet)=$dataset->uniqueDataOfColumn($masterFieldNo);

                    # ПРИДУМЫВАЙ КАК ПОЛУЧИТЬ ColumnNo
                    # FieldNo работает только в masterDataset,
                    $masterColumnNo=$dataset->planEntry['@dataset']['@fields'][$masterFieldNo]['#tupleFieldNo'];
                    #$dataset-> self::getColumnByFieldNo($masterFieldNo);

                    list($ok, $parentValueSet)=$dataset->uniqueValuesOfTupleSetField($masterColumnNo);

                    if (!$ok) {
                        return false;
                    }
                    $newParams=[];
                    #$newParams['@keyValuesIn']=&$parentValueSet;
                    # ОТСУТСТВУЕТ detailToMasterField!!!
                    $newParams['@filter']=[
                        [
                            '#columnId'=>$subPlanEntry['#detailToMasterColumnId'],
                            '#operand'=>'IN',
                            '@values'=>&$parentValueSet
                        ]
                    ];
                /*
                $newParams['@createIndex']=[
                  'type'=>'single',
                  'indexId'=>$datasetId.'-'.$detailDatasetId,
                  'masterColumnNo'=>$subPlanEntry['#masterColumnNo'],
                  'childToMasterField'=>$subPlanEntry['#childToMasterField']  # ==PRODUCT_TYPES/PRODUCT_TYPE_ID
                  ];
                  */

          # =IF=TYPE AGGREGATION..
          #$newParams['@createIndex']=[
          #  'type'=>'multiple',
          #  'indexId'=>$datasetId.'-'.$detailDatasetId,
          #  'masterColumnNo'=>$subPlanEntry['#masterColumnNo'],  #==PRODUCT_ID
          #  'childToMasterField'=>$subPlanEntry['#childToMasterField']  #==PRODUCT_PARAMETERS/PRODUCT_ID
          #  ];
          #$newParams['@filter']=[
          #$newParams['@filter']=[
          #  ['field'=>$subPlanEntry['#childToMasterField'], 'operand'=>'IN', 'values'=>&$parentValueSet]
          #];
          #  ];
                } else {
                    trigger_error('Unknown plan linking', E_USER_ERROR);
                    return false;
                }

                $childNode=new \doq\data\DataNode(DataNode::NT_DATASET, $detailDatasetId, $dataNode);
                $ok=$this->readPlanEntry($subPlanEntry, $childNode, $newParams, $detailDatasetId);
                if (!$ok) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Forms dataplan based on view configuration, dataplan
     */
    public function makePlan()
    {
        $this->plan=[];
        $this->lastPlanId=1;
        $viewColumns=null;
        return $this->makePlanRecursive($this->cfgView, $this->plan, $viewColumns);
    }

    private function makePlanRecursive(
        &$cfgView,
        &$plan,
        &$parentViewColumn,
        $datasourceName='',
        $schemaName='',
        $datasetName='',
        $parentRef='',
        $masterFieldNo=false,
        $detailDatasetId=false,
        $masterKind=false,
        $isNewPlan=true,
        $isOtherDatasource=false
    ) {
        $parentDatasetname=$datasetName;
        if (isset($cfgView['#dataset'])) {
            list($datasourceName, $schemaName, $datasetName, $isOtherDatasource)=\doq\data\Scripter::getDatasetPathElements($cfgView['#dataset'], $datasourceName, $schemaName, $datasetName);
        }

        $datasetRef=$datasourceName.':'.$schemaName.'/'.$datasetName;

        $cfgSchemaDataset=&$this->cfgSchema['@datasources'][$datasourceName]['@schemas'][$schemaName]['@datasets'][$datasetName];
        if (!$cfgSchemaDataset) {
            trigger_error(\doq\t('Cannot find model schema  %s', $datasetRef), E_USER_ERROR);
            return false;
        }

        if ($isOtherDatasource) {
            $subPlan=[];
            if (!isset($plan['@subPlan'])) {
                $plan['@subPlan']=[&$subPlan];
            } else {
                $plan['@subPlan'][]=&$subPlan;
            }
            $masterPlan=&$plan;
            $plan=&$subPlan;
            $isNewPlan=true;
        }
        $dataset=['#schema'=>$schemaName,'#datasetName'=>$datasetName,'@fields'=>[]];

        if (isset($cfgSchemaDataset['@keyFields'])) {
            trigger_error('Unsupported multiple field primary keys', E_USER_ERROR);
            return false;
        } elseif (isset($cfgSchemaDataset['#keyField'])) {
            $dataset['#keyField']=$cfgSchemaDataset['#keyField'];
        }


        if ($isNewPlan) {
            $plan['#lastColumnId']=0;
            $plan['#lasttupleFieldNo']=0;
            $plan['#planId']=$this->lastPlanId;
            $this->lastPlanId++;
            $plan['#dataSource']=$datasourceName;
            $cfgDatasource=&$this->cfgSchema['@datasources'][$datasourceName];
            $dataConnectionName=$cfgDatasource['#dataConnection'];
            $plan['#dataConnection']=$dataConnectionName;
            list($ok, $connection) = \doq\data\Connections::getConnection($dataConnectionName);
            $providerName=$connection->provider;
            $plan['#dataProvider']=$providerName;
        } else {
            $parentViewColumn['@dataset']=&$dataset;
        }

        $foundDetailColumnForMaster=false;
        $foundKeyColumn=false;

        if (isset($dataset['#keyField'])) {
            $keyField=$dataset['#keyField'];
        } else {
            $keyField=false;
        }

        $fieldNo=0;
        foreach ($cfgView as $localFieldName=>&$viewFieldDef) {
            $fc=$localFieldName[0];
            if (($fc!='#')&&($fc!='@')) {
                $newColumn=['#columnId'=>$plan['#lastColumnId'],'#field'=>$localFieldName,'#fieldNo'=>$fieldNo];
                $plan['#lastColumnId']++;
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
                    $newColumn['#tupleFieldNo']=$plan['#lasttupleFieldNo'];
                    $plan['#lasttupleFieldNo']++;
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
                        $this->makePlanRecursive(
                            $viewFieldDef['@linked'],
                            $plan,
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
                $dataset['@fields'][]=&$newColumn;
                unset($newColumn);
            }
        }


        if ($isNewPlan) {
            $plan['@dataset']=&$dataset;
            if ($masterKind=='lookup') {
                if (!$foundKeyColumn) {
                    if ($keyField) {
                        trigger_error(\doq\t('Not found key field %s in view from %s', $keyField, $datasetRef), E_USER_ERROR);
                    } else {
                        trigger_error(\doq\t('Key field is required for lookup in dataset %s', $datasetRef), E_USER_ERROR);
                    }
                    return false;
                }
                $newIdxName='idx_look_'.$parentRef.'--'.$dataset['#keyField'];
                if (!isset($plan['@resultIndexes'])) {
                    $plan['@resultIndexes']=[];
                }
                if (isset($plan['@resultIndexes'][$newIdxName])) {
                    for ($i=0;$i<10;$i++) {
                        $s=$newIdxName.'/'.$i;
                        if (!isset($plan['@resultIndexes'][$s])) {
                            $newIdxName=$s;
                            break;
                        }
                    }
                }
                $plan['@resultIndexes'][$newIdxName]=[
                    '#type'=>'unique',
                    '#name'=>$newIdxName,
                    '#byTupleFieldNo'=>$foundKeyColumn['#tupleFieldNo']
                ];
                $parentViewColumn['#uniqueIndex']=$newIdxName;
                $plan['#detailToMasterColumnId']=$foundKeyColumn['#columnId'];
            } elseif ($masterKind=='aggregation') {
                # aggregation by the real multilookup field and by the virtual field as default
                if (!$foundDetailColumnForMaster) {
                    trigger_error(\doq\t('Not found back referenced lookup to %s from %s', $parentRef, $datasetRef), E_USER_ERROR);
                    return false;
                }
                $newIdxName='idx_agg_'.$parentRef.'_2_'.$foundDetailColumnForMaster['#originField'];
                if (!isset($plan['@resultIndexes'])) {
                    $plan['@resultIndexes']=[];
                }
                if (isset($plan['@resultIndexes'][$newIdxName])) {
                    for ($i=0;$i<10;$i++) {
                        $s=$newIdxName.'/'.$i;
                        if (!isset($plan['@resultIndexes'][$s])) {
                            $newIdxName=$s;
                            break;
                        }
                    }
                }
                # Этот индекс, в отличие от лукапа, создает неуникальный индекс,
                # в котором ключами являются ID родителей, а внутри них группируются
                # ссылки на записи деток, которые в него входят
                $plan['@resultIndexes'][$newIdxName]=[
                    '#type'=>'nonunique',
                    '#name'=>$newIdxName,
                    '#byTupleFieldNo'=>$foundDetailColumnForMaster['#tupleFieldNo']
                ];
                $parentViewColumn['#nonuniqueIndex']=$newIdxName;
                $plan['#detailToMasterColumnId']=$foundDetailColumnForMaster['#columnId']; # вслепую
            }
            if ($masterFieldNo!==false) {
                $plan['#masterFieldNo']=$masterFieldNo;
                $plan['#detailDatasetId']=$detailDatasetId;
                if (!isset($masterPlan['@detailIndexByFieldNo'])) {
                    $masterPlan['@detailIndexByFieldNo']=[];
                }
                $masterPlan['@detailIndexByFieldNo'][$masterFieldNo]=$newIdxName;
            }

            $scripter=\doq\data\Scripter::create($providerName);
            $selectScript=$scripter->buildSelectScript($plan);
            if ($selectScript!==false) {
                $plan['#readScript']=$selectScript;
            }
        }
        return true;
    }

    /** Routine that collects field names from planEntry dataset
     * 
     * @param $planEntry
     * */
    public static function collectFieldList(&$planEntry, &$fieldList)
    {
        $fields=&$planEntry['@dataset']['@fields'];
        foreach ($fields as $i=>&$field) {
            $fieldList[]=&$field;
            if (isset($field['@dataset'])) {
                self::collectFieldList($field, $fieldList);
            }
        }
    }
}
