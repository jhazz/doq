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
                $newDatanode=new Datanode(self::NT_SUBCOLUMNS, $fieldName, $this);
                $newDatanode->dataset=$dataset;
                $newDatanode->fieldDefs=&$field['@dataset'];
                $newDatanode->wrap($field, $dataset);
            } else {
                $newDatanode=new Datanode(self::NT_COLUMN, $fieldName, $this);
                $newDatanode->dataset=$dataset;
                $newDatanode->fieldDefs=&$field;
            }
        }
    }
    
    public function toArray(&$dstArray=null, $parentPath='',  $level=10){
        if($dstArray==null){
            $dstArray=[];
        }
        if($level<0){
            return;
        }
        if($this->type!==\doq\data\Datanode::NT_DATASET){
            throw new \Exception('Not a dataset!');
        }
        $attrs=[
            '#nodeName'=>$this->name,
            '#dataSource'=>$this->dataset->queryDefs['#dataSource'],
            '#schema'=>$this->dataset->queryDefs['@dataset']['#schema'],
            '#dataset'=>$this->dataset->queryDefs['@dataset']['#datasetName'],
            '#keyField'=>$this->dataset->queryDefs['@dataset']['#keyField']
        ];
        $dstArray[$this->name]=&$attrs;
        $this->collectFieldDefs($parentPath, $attrs['#dataSource'], $attrs['#schema'], $attrs['#dataset'],$this->dataset->queryDefs['@dataset'], $r);
        $attrs['@fields']=$r;
        if ($this->dataset->tuples!==null) {
            $rows=[];
            foreach ($this->dataset->tuples as $rowNo=>&$tuple) {
                $rows[]=$tuple;
            }
            $attrs['@tuples']=&$rows;
        }
        if(isset($this->childNodes)){
            foreach($this->childNodes as $childNodeName=>&$childNode){
                if($childNode->type==\doq\data\Datanode::NT_DATASET){
                    $childNode->toArray($dstArray, $parentPath, $level-1);
                }
            }
        }
        return $dstArray;
    }
    
    private function collectFieldDefs($currentPath, $datasource, $schema, $dataset, &$fieldDefs, &$result){
        $keyField=$fieldDefs['#keyField'];
        foreach ($fieldDefs['@fields'] as $fieldNo=>&$fieldDef) {
            $ref=isset($fieldDef['#ref'])?$fieldDef['#ref']:false; 
            $refKind=false;
            
            if(isset($fieldDef['#refKind'])){
                $refKind = $f['#refKind'] = $fieldDef['#refKind'];
            }
            $f=['#type'=>$fieldDef['#type'], '#datasource'=>$datasource, '#schema'=>$schema, '#dataset'=>$dataset];
            // if(isset($fieldDef['#refSchema'])){$f['#refSchema']=$fieldDef['#refSchema'];}
            if(isset($fieldDef['#label'])){
                $f['#label'] = $fieldDef['#label'];
            }
            if(isset($fieldDef['#isRequired'])){
                $f['#isRequired'] = $fieldDef['#isRequired'];
            }
            if($currentPath!='') {
                $path=$currentPath.'/'.$fieldDef['#field'];
            } else {
                $path=$fieldDef['#field'];
            }
            if($path==$keyField){
                $f['#isKeyField']=1;
            }
            if(isset($fieldDef['#columnId'])){
                $f['#columnId']=$fieldDef['#columnId'];
            }
            if(isset($fieldDef['#tupleFieldNo'])) {
                $f['#tupleFieldNo']=intval($fieldDef['#tupleFieldNo']);
            }

            if(($refKind=='lookup')||($refKind=='aggregation')) {
                $f['#refDatasource'] = (isset($fieldDef['#refDatasource'])) ? $fieldDef['#refDatasource'] : $datasource;
                $f['#refSchema'] = (isset($fieldDef['#refSchema']))? $fieldDef['#refSchema'] : $schema;
                $f['#refDataset']=(isset($fieldDef['#refDataset']))? $fieldDef['#refDataset']: $dataset;
                $reftype=false;
                if(isset($fieldDef['#refType'])) {
                    $reftype= $f['#refType']=$fieldDef['#refType'];
                }
                $result[$path] = $f;

                if ($reftype=='join') {
                    $this->collectFieldDefs($path, $f['#refDatasource'], $f['#refSchema'], $f['#refDataset'], $fieldDef['@dataset'], $result);
                }
            } else {
                $result[$path] = $f;
            }
        }
    }

}
