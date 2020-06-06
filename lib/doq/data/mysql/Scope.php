<?php
namespace doq\data\mysql;

class Scope extends \doq\data\Scope
{
    //inherited public $path;
    //inherited public $datanode;
    /** @var array|null Текущий индекс */
    public $index;
    /** @var array|null Ссылки на кортежи по ключам индекса. Предназначен для быстрого доступа к кортежу по ключевому значению */
    public $tuplesByKey;
    /** @var array|null Ссылки на строки кортежа в выборке индекса, именно по нему производится обход через seek */
    public $tuplesByNo;

    /** @var integer позиция в индексе, если он есть или позиция в агрегате индекса, или позиция в dataset->rows */
    
    public $rowNo;
    /** @var array|null ссылка на ту запись данных, на которую указывает позиция */
    public $curTuple;
    /** @var одно из значений констант SW_ */
    public $curType;
    /** @var int длина выборки индекса по которому перемещаем указатель через seek*/
    public $indexSize;

    public function __construct(\doq\data\Datanode $datanode, $path='', $indexName='', $masterValue=null, $masterScope=null)
    {
        $this->datanode=$datanode;
        $this->path=$path;
        $this->curType=null;
        $this->rowNo=null;
        $this->curTuple=null;
        $this->indexSize=0;
        $this->index=null;

        if ($indexName!='') {
            $this->index=&$datanode->dataset->indexes[$indexName];
            $this->tuplesByKey=null;
            switch ($this->index['#type']) {
                case 'unique':
                    $this->curType=self::SW_ONE_INDEX_RECORD;
                    if ($masterValue!==null) {
                        $this->curTuple=&$this->index['@tuplesByKey'][$masterValue];
                        $this->indexSize=1;
                    }
                    break;
                case 'cluster':
                    $this->curType=self::SW_AGGREGATED_INDEX_RECORDS;
                    if ($masterValue!==null) {
                        if (isset($this->index['@tuplesByKey'][$masterValue])) {
                            $this->tuplesByKey=&$this->index['@tuplesByKey'][$masterValue];
                            $this->tuplesByNo=&$this->index['@tuplesByNo'][$masterValue];
                            $this->indexSize=count($this->tuplesByNo);
                        }
                    } else {
                        throw new \Exception('Do not use aggregated index without master value defining scope window');
                    }
                    break;
                default:
                    throw new \Exception('FATAL ERROR! Unknown index type '.$index['#type']);
            }
        } else {
            if ($this->datanode->type==\doq\data\Datanode::NT_COLUMN) {
                $this->curType=self::SW_ONE_FIELD;
                $this->curTuple=&$masterScope->curTuple;
            } else {
                $pkIndexName=\doq\data\Dataset::PRIMARY_KEY_NAME;
                if(isset($this->datanode->dataset->indexes[$pkIndexName])) {
                    $this->curType=self::SW_INDEX_RECORDS;
                    $this->index=&$datanode->dataset->indexes[$pkIndexName];
                    $this->tuplesByKey=&$this->index['@tuplesByKey'];
                    $this->tuplesByNo=&$this->index['@tuplesByNo'];
                    $this->indexSize=count($this->tuplesByNo);
                    if($this->indexSize>0){
                        $this->rowNo=0;
                        $this->curTuple=&$this->tuplesByNo[0];
                    }
                    
        } else {
                    $this->curType=self::SW_ALL_RECORDS;
                    $this->indexSize=count($this->datanode->dataset->tuples);
                }
            }
        }
    }


    /**
     * Moves scope cursor TO_START, TO_END or TO_NEXT. 
     * Scope cursor moves over array of tuples stored in the array scope->tuplesByNo[]. 
     * This scope->curTuple is an active tuple and scope->curTupleKey contains current tuple key value
     * that could be used for accessing tuple by key value. Tuples arranged by keys are stored in scope->tuplesByKey[]
     * @param mixed $to=Scope::TO_NEXT direction
     * @return boolean true if end of index is reached
     */
    public function seek($to=self::TO_NEXT)
    {
        $EOT=false;
        switch ($this->curType) {
            case self::SW_ONE_FIELD:
            case self::SW_ONE_INDEX_RECORD:
                return true;
            case self::SW_INDEX_RECORDS:
            case self::SW_AGGREGATED_INDEX_RECORDS:

                if (!is_array($this->tuplesByNo)) {
                    $this->curTuple=null;
                    return true;
                    //throw new \Exception('Scope::seek() called inside aggregated index but tuplesByKey is not an array');
                }
                switch ($to) {
                    case self::TO_START:
                        $newRowNo=0;
                        break;
                    case self::TO_NEXT:
                        $newRowNo=$this->rowNo+1;
                        break;
                    case self::TO_END:
                        $newRowNo=$this->indexSize-1;
                        break;
                }
                if ($newRowNo >= $this->indexSize) {
                    $newRowNo=$this->indexSize-1;
                    $EOT=true;
                }
                $this->rowNo=$newRowNo;
                
                if (isset($this->curTuple)) {
                    unset($this->curTuple);
                }
                $this->curTuple=&$this->tuplesByNo[$newRowNo];
                $this->curTupleKey=$this->curTuple[$this->index['#keyTupleFieldNo']];
                return $EOT;

        case self::SW_ALL_RECORDS:
            if ($this->indexSize) {
                switch ($to) {
                    case self::TO_START:
                        $newRowNo=0;
                        break;
                    case self::TO_NEXT:
                        $newRowNo=$this->rowNo+1;
                        break;
                    case self::TO_END:
                        $newRowNo=$this->indexSize-1;
                        break;
                    default:
                        trigger_error('Unknown seeking type '.$origin, E_USER_ERROR);
                        return false;
                }
                if ($newRowNo >= $this->indexSize) {
                    $newRowNo=$this->indexSize-1;
                    $EOT=true;
                }
                $this->rowNo=$newRowNo;
            }
            if (isset($this->curTuple)) {
                unset($this->curTuple);
            }
            $this->curTuple=&$this->datanode->dataset->tuples[$newRowNo];
            $this->curTupleKey='$R'.$newRowNo;
            return $EOT;
        default:
            return true;
        }
    }

    /** 
     * @param string $path to 
     * @param string $masterFieldName is a master's dataset field name that a new scope will be created by
     * @return \doq\data\Scope */
    public function makeDetailScope($path, $masterFieldName)
    {
        $masterDatanode=$this->datanode;
        $masterDataset=$masterDatanode->dataset;
        $detailDatanode=$masterDatanode->childNodes[$masterFieldName];
        $masterFieldNo=$detailDatanode->dataset->queryDefs['#masterFieldNo'];
        $masterTupleFieldNo=$masterDataset->queryDefs['@dataset']['@fields'][$masterFieldNo]['#tupleFieldNo'];
        $masterValue=$this->curTuple[$masterTupleFieldNo];
        $detailIndexName=$masterDataset->queryDefs['@detailIndexByFieldNo'][$masterFieldNo];
        return $detailDatanode->dataset->makeScope($detailDatanode, $path.'/'.$masterFieldName, $detailIndexName, $masterValue, null);
    
    }

    public function asString()
    {
        switch ($this->datanode->type) {
            case \doq\data\Datanode::NT_COLUMN:
                $fieldDef=&$this->datanode->fieldDefs;
                if (!isset($fieldDef['#tupleFieldNo'])) {
                    trigger_error('Unknown #tupleFieldNo in dataset for path '.$this->path, E_USER_ERROR);
                    return '{ERROR}';
                }
                $fieldType=isset($fieldDef['#type'])?$fieldDef['#type']:'string';
                $fieldLabel=isset($fieldDef['#label'])?$fieldDef['#label']:$fieldDef['#field'];
                $tupleFieldNo=$fieldDef['#tupleFieldNo'];
                if($this->curTuple==null) {
                    return '';
                }
                if ($tupleFieldNo>=count($this->curTuple)) {
                    throw new \Exception(\sprintf('Column index %s is out of data columns range %s', $tupleFieldNo, count($this->curTuple)));
                }
                $value=$this->curTuple[$tupleFieldNo];
                return $value;
            break;
        }
    }

    public function value()
    {
        if ($this->datanode->type === \doq\data\Datanode::NT_COLUMN) {
            return $this->curTuple[$this->datanode->fieldDefs['#tupleFieldNo']];
        }
    }
    
    /*public function field($fieldName){
        if ($this->datanode->type === \doq\data\Datanode::NT_DATASET) {
            return $this->curTuple[$this->datanode->dataset->queryDefs['@dataset']['fields'][ПО НОМЕРАМ ТОЛЬКО] ];
        }        
    }*/
}
