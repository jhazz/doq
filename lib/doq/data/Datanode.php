<?php
namespace doq\data;

/**
 * Datanode is an addressing data element that could be identifyied by a path.
 * Datanode is creating by a View after Dataset had been successfully read a data inside its structores (i.e. tuples).
 * \doq\Template addresses a Datanode via a traversal Scope and extract data to put to the html page.
*/
class Datanode
{
    const NT_COLUMN='COLUMN';
    const NT_SUBCOLUMNS='SUBCOLUMNS';
    const NT_DATASET='DATASET';

    /** @var NT_COLUMN/NT_SUBCOLUMNS/NT_DATASET  */
    public $type;
    /** @var Array */
    public $parameters;
    /** @var \doq\data\Dataset */
    public $dataset;
    /** @var Array */
    public $childNodes;
    /** @var address name */
    public $name;

    /**
     * @param string $type \Datanode::NT_COLUMN/NT_SUBCOLUMNS/NT_DATASET
     * @param string $name Name of the addressing node
     * @param \doq\data\Datanode $parendNode that owns this datanode
     */
    public function __construct($type, $name, $parendNode=null)
    {
        if ($parendNode!==null) {
            $parendNode->childNodes[$name]=$this;
        }
        $this->name=$name;
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
