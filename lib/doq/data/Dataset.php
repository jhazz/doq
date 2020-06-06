<?php
namespace doq\data;

abstract class Dataset
{
    const PRIMARY_KEY_NAME='*PRIMARY*';

    public $name;
    public $queryDefs;
    public $rowCount;
    abstract protected function makeScope(Datanode $datanode);
    abstract public function dataToHTML();
    abstract public function indexesToHTML();
    abstract public function read(&$params);
    
    public static function create($providerName, &$queryDefs, $newDatasetName)
    {
        switch ($providerName) {
        case 'mysql':
            return [new mysql\Dataset($queryDefs, $newDatasetName),null];
        default:
            $err=\doq\tr('doq','Unknown data provider %s',$providerName);
            trigger_error($err,E_USER_ERROR);
            return [false,$err];
        }
    }

    public function __construct(&$queryDefs, &$params, $id)
    {
        trigger_error('Abstract Dataset class should not used to create itself!', E_USER_ERROR);
    }



    /**
     * Useful to make tupleFields list inside Dataset
     */
    public function &getColumns(){
        $columns=[];
        $fieldDefs=&$this->queryDefs['@dataset']['@fields'];
        foreach ($fieldDefs as $fieldNo=>&$fieldDef) {
            $type=$fieldDef['#type'];
            if($type!='virtual'){
                $columns[$fieldDef['#columnId']]=&$fieldDef;
            }
        }
    return $columns;
    }

    /** Routine that collects field names from queryDefs dataset
     * 
     * @param $queryDefs
     * */
    public static function collectFieldDefs(&$queryDefs, &$fieldDefsList)
    {
        $fields=&$queryDefs['@dataset']['@fields'];
        foreach ($fields as $i=>&$field) {
            $fieldDefsList[]=&$field;
            if (isset($field['@dataset'])) {
                self::collectFieldDefs($field, $fieldDefsList);
            }
        }
    }

    
    public static function getFieldDefByColumn($column, &$queryDefs)
    {
        $isInt=is_integer($column);
        foreach ($queryDefs['@dataset']['@fields'] as $i=>&$field) {
            if($isInt) {
                if (isset($field['#columnId']) && ($field['#columnId']==$column)) {
                    return [&$field,null];
                }
            } else {
                if (isset($field['#field']) && ($field['#field']==$column)) {
                    return [&$field,null];
                }
            }
            if (isset($field['@dataset'])) {
                return self::getFieldDefByColumn($column, $field);
            }
        }
        return[null,'ColumnId '.$column.' not found'];
    }


}
