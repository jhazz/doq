<?php
namespace doq\data;

abstract class Dataset
{
    const PRIMARY_KEY_NAME='*PRIMARY*';

    public $name;
    public $queryDefs;
    abstract protected function makeScope(Datanode $datanode);
    abstract public function dataToHTML();
    abstract public function indexesToHTML();

    public static function create($providerName, &$queryDefs, $newDatasetName)
    {
        switch ($providerName) {
        case 'mysql':
            return [true,new mysql\Dataset($queryDefs, $newDatasetName)];
        default:
            return [false,'Unknown provider '.$providerName];
        }
    }

    public function __construct(&$queryDefs, &$params, $id)
    {
        trigger_error('Abstract Dataset class should not used to create itself!', E_USER_ERROR);
    }

    /**
     * Sometime useful to make tupleFields list inside Dataset
     */
    public function &getTupleFields(){
        if(isset($this->tupleFields)){
            return $this->tupleFields;
        } else {
            $fieldDefs=&$this->queryDefs['@dataset']['@fields'];
            foreach ($fieldDefs as $fieldNo=>&$fieldDef) {
                if(isset($fieldDef['#tupleFieldNo'])){
                    $this->tupleFields[$fieldDef['#tupleFieldNo']]=&$fieldDef;
                }
            }
        };
        return $this->tupleFields;
    }

    /**
     * Sometime useful to make tupleFields list inside Dataset
     */
    public function &getColumns(){
        if(isset($this->columns)){
            return $this->columns;
        } else {
            $this->columns=[];
            $fieldDefs=&$this->queryDefs['@dataset']['@fields'];
            foreach ($fieldDefs as $fieldNo=>&$fieldDef) {
                $type=$fieldDef['#type'];
                if($type!='virtual'){
                    $this->columns[$fieldDef['#columnId']]=&$fieldDef;
                }
            }
        };
        return $this->columns;
    }

    /** Routine that collects field names from queryDefs dataset
     * 
     * @param $queryDefs
     * */
    public static function collectFieldList(&$queryDefs, &$fieldList)
    {
        $fields=&$queryDefs['@dataset']['@fields'];
        foreach ($fields as $i=>&$field) {
            $fieldList[]=&$field;
            if (isset($field['@dataset'])) {
                self::collectFieldList($field, $fieldList);
            }
        }
    }

    
    public static function getFieldByColumnId($findColumnId, &$queryDefs)
    {
        foreach ($queryDefs['@dataset']['@fields'] as $i=>&$field) {
            if (isset($field['#columnId']) && ($field['#columnId']==$findColumnId)) {
                return [true,&$field];
            }
            if (isset($field['@dataset'])) {
                return self::getFieldByColumnId($findColumnId, $field);
            }
        }
        return[false,'ColumnId '.$findColumnId.' not fount'];
    }


}
