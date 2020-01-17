<?php
namespace doq\data;

abstract class Dataset
{
    public $id;
    public $query;
    abstract protected function makeScope(Datanode $datanode);
    abstract public function dataToHTML();
    abstract public function indexesToHTML();

    public static function create($providerName, &$query, $id)
    {
        switch ($providerName) {
        case 'mysql':
            return [true,new mysql\Dataset($query, $id)];
        default:
            return [false,'Unknown provider '.$providerName];
        }
    }

    public function __construct(&$query, &$params, $id)
    {
        trigger_error('Abstract Dataset class should not used to create itself!', E_USER_ERROR);
    }

    /**
     * @param array config from query
     * @param \doq\Datanode the datanode collects data items
     */
    public function collectDatanodesRecursive(&$config, $datanode)
    {
        $fields=&$config['@dataset']['@fields'];
        foreach ($fields as $i=>&$field) {
            $fieldName=$field['#field'];
            if (isset($datanode->childNodes[$fieldName])) {
                trigger_error(\doq\tr('doq','Dublicate field name "%s" is found in the view config "%s"', $fieldName, $config['#schema'].'/'.$config['#dataset']), E_USER_ERROR);
                continue;
            }
            if (isset($field['@dataset'])) {
                $node=new Datanode(Datanode::NT_SUBCOLUMNS, $fieldName, $datanode);
                $node->dataset=$this;
                $node->fieldDefs=&$field['@dataset'];
                $this->collectDatanodesRecursive($field, $node);
            } else {
                $node=new Datanode(Datanode::NT_COLUMN, $fieldName, $datanode);
                $node->dataset=$this;
                $node->fieldDefs=&$field;
            }
        }
    }

    /** Routine that collects field names from query dataset
     * 
     * @param $query
     * */
    public static function collectFieldList(&$query, &$fieldList)
    {
        $fields=&$query['@dataset']['@fields'];
        foreach ($fields as $i=>&$field) {
            $fieldList[]=&$field;
            if (isset($field['@dataset'])) {
                self::collectFieldList($field, $fieldList);
            }
        }
    }

    public static function getFieldByColumnId($findColumnId, &$query)
    {
        foreach ($query['@dataset']['@fields'] as $i=>&$field) {
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
