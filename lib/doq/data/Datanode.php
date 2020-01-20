<?php
namespace doq\data;

/**
 * Datanode is an addressing data element that could be identifyied by a path.
 * Datanode creating by a View after Dataset had been successfully read a data inside.
 * Template addresses a Datanode via Scope address and put data from referred.
 * Dataset to the template controls
 */
class Datanode
{
    const NT_COLUMN='! Column';
    const NT_SUBCOLUMNS='! Subcolumns';
    const NT_DATASET='! Dataset';

    public $type;
    /** @var Array */
    public $parameters;
    /** @var \doq\data\Dataset */
    public $dataset;
    /** @var Array */
    public $childNodes;

    public function __construct($type, $nodeId, $parendNode=null)
    {
        if ($parendNode!==null) {
            $parendNode->childNodes[$nodeId]=$this;
        }
        $this->type=$type;
        if ($this->type!==self::NT_COLUMN) {
            $this->childNodes=[];
        }
    }

    /**
     * Wrap Dataset elements (columns, subcolumns, datasets) by Datanodes 
     * tree-like structure using query definitions
     * @param Array &$queryEntry Defines query fields and subqueries
     * @param \doq\data\Dataset $dataset current dataset that should be wrapped by nodes
     */
    public function wrap(&$queryEntry, \doq\data\Dataset $dataset)
    {
        $fields=&$queryEntry['@dataset']['@fields'];
        foreach ($fields as $i=>&$field) {
            $fieldName=$field['#field'];
            if (isset($this->childNodes[$fieldName])) {
                trigger_error(\doq\tr(
                  'doq',
                  'Dublicate field name "%s" is found in the view query entry "%s"',
                  fieldName,
                  $queryEntry['#schema'].'/'.$queryEntry['#dataset']
              ), E_USER_ERROR);
                continue;
            }
            if (isset($field['@dataset'])) {
                $newDatanode=new Datanode(Datanode::NT_SUBCOLUMNS, $fieldName, $this);
                $newDatanode->dataset=$dataset;
                $newDatanode->fieldDefs=&$field['@dataset'];
                $newDatanode->wrap($field, $dataset);
            } else {
                $newDatanode=new Datanode(Datanode::NT_COLUMN, $fieldName, $this);
                $newDatanode->dataset=$dataset;
                $newDatanode->fieldDefs=&$field;
            }
        }
    }
}
