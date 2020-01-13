<?php
namespace doq\data;

abstract class DataObject
{
    public $id;
    abstract protected function makeScope(DataNode $dataNode);
}

abstract class Dataset extends DataObject
{
    public $planEntry;

    public static function create($providerName, &$planEntry, $id)
    {
        switch ($providerName) {
      case 'mysql':
        return [true,new mysql\Dataset($planEntry, $id)];
      default:
        return [false,'Unknown provider '.$providerName];
    }
    }

    /**
     * @param array config from planEntry
     * @param DataNode the datanode collects data items
     */
    public function collectDataNodesRecursive(&$config, &$dataNode)
    {
        $fields=&$config['@dataset']['@fields'];
        foreach ($fields as $i=>&$field) {
            $fieldName=$field['#field'];
            if (isset($dataNode->childNodes[$fieldName])) {
                trigger_error(\doq\t('Field dublicate name %s is found in view config %s', $fieldName, $config['#schema'].'/'.$config['#dataset']), E_USER_ERROR);
                continue;
            }
            if (isset($field['@dataset'])) {
                $node=new DataNode(DataNode::NT_SUBCOLUMNS, $fieldName, $dataNode);
                $node->dataObject=$this;
                # TODO: Некрасиво!
                $node->parameters=&$field['@dataset'];
                $this->collectDataNodesRecursive($field, $node);
            } else {
                $node=new DataNode(DataNode::NT_COLUMN, $fieldName, $dataNode);
                $node->dataObject=$this;
                $node->parameters=&$field;
            }
        }
    }

    public function __construct(&$planEntry, &$params, $id)
    {
        trigger_error('Abstract Dataset class should not used to create itself!', E_USER_ERROR);
    }
}
