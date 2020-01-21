<?php
namespace doq\data;

abstract class Dataset
{
    public $id;
    public $queryDefs;
    abstract protected function makeScope(Datanode $datanode);
    abstract public function dataToHTML();
    abstract public function indexesToHTML();

    public static function create($providerName, &$queryDefs, $id)
    {
        switch ($providerName) {
        case 'mysql':
            return [true,new mysql\Dataset($queryDefs, $id)];
        default:
            return [false,'Unknown provider '.$providerName];
        }
    }

    public function __construct(&$queryDefs, &$params, $id)
    {
        trigger_error('Abstract Dataset class should not used to create itself!', E_USER_ERROR);
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
