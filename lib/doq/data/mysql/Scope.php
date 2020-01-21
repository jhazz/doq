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

    public function __construct(\doq\data\Datanode $datanode, $indexName='', $masterValue=null, $masterScope=null, $path='')
    {
        $this->datanode=$datanode;
        $this->path=$path;
        $this->curType=null;
        $this->rowNo=null;
        $this->curTuple=null;
        $this->indexSize=0;
        $this->index=null;

        if ($indexName!='') {
            $this->index=&$datanode->dataset->resultIndexes[$indexName];
            $this->tuplesByKey=null;
            switch ($this->index['#type']) {
                case 'unique':
                    $this->curType=self::SW_ONE_INDEX_RECORD;
                    if ($masterValue!==null) {
                        $this->curTuple=&$this->index['@tuplesByKey'][$masterValue];
                        $this->indexSize=1;
                    }
                    break;
                case 'nonunique':
                    $this->curType=self::SW_AGGREGATED_INDEX_RECORDS;
                    if ($masterValue!==null) {
                        if (isset($this->index['@tuplesByKey'][$masterValue])) {
                            $this->tuplesByKey=&$this->index['@tuplesByKey'][$masterValue];
                            $this->tuplesByNo=&$this->index['@tuplesByNo'][$masterValue];
                            $this->indexSize=count($this->tuplesByNo);
                        }
                    } else {
                        trigger_error(\doq\t('FATAL ERROR! Do not use aggregated index without master value defining scope window'), E_USER_ERROR);
                    }
                    break;
                default:
                    trigger_error(\doq\t('FATAL ERROR! Unknown index type [%s]', $index['#type']), E_USER_ERROR);
            }
        } else {
            if ($this->datanode->type==\doq\data\Datanode::NT_COLUMN) {
                $this->curType=self::SW_ONE_FIELD;
                $this->curTuple=&$masterScope->curTuple;
            } else {
                $this->curType=self::SW_ALL_RECORDS;
                $this->indexSize=count($this->datanode->dataset->tuples);
                if($this->indexSize>0){

                }
            }
        }
    }


    public function seek($to=self::SEEK_TO_NEXT)
    {
        $EOT=false;
        switch ($this->curType) {
            case self::SW_ONE_FIELD:
            case self::SW_ONE_INDEX_RECORD:
                $EOT=true;
                break;
            case self::SW_INDEX_RECORDS:
            case self::SW_AGGREGATED_INDEX_RECORDS:
                
                if (!is_array($this->tuplesByNo)) {
                    $this->curTuple=null;
                    return true;
                    //throw new \Exception('Scope::seek() called inside aggregated index but tuplesByKey is not an array');
                }
                switch ($to) {
                    case self::SEEK_TO_START:
                        $newRowNo=0;
                        break;
                    case self::SEEK_TO_NEXT:
                        $newRowNo=$this->rowNo+1;
                        break;
                    case self::SEEK_TO_END:
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
                break;

        case self::SW_ALL_RECORDS:
            if ($this->indexSize) {
                switch ($to) {
                    case self::SEEK_TO_START:
                        $newRowNo=0;
                        break;
                    case self::SEEK_TO_NEXT:
                        $newRowNo=$this->rowNo+1;
                        break;
                    case self::SEEK_TO_END:
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
            break;
        default:
            #throw new \Exception('Unknown cursor type in the scope');
            return true;
        }
        return $EOT;
    }

    /** @return \doq\data\Scope */
    public function makeDetailScope($path, $masterFieldName)
    {
        $masterDatanode=$this->datanode;
        $masterDataset=$masterDatanode->dataset;
        $detailDatanode=$masterDatanode->childNodes[$masterFieldName];
        $masterFieldNo=$detailDatanode->dataset->queryDefs['#masterFieldNo'];
        $masterTupleFieldNo=$masterDataset->queryDefs['@dataset']['@fields'][$masterFieldNo]['#tupleFieldNo'];
        $masterValue=$this->curTuple[$masterTupleFieldNo];
        $detailIndexName=$masterDataset->queryDefs['@detailIndexByFieldNo'][$masterFieldNo];
        return $detailDatanode->dataset->makeScope($detailDatanode, $detailIndexName, $masterValue, null, '['.$masterValue.']'.'/'.$masterFieldName.'/'.$path);
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
        if ($this->datanode->type===\doq\data\Datanode::NT_COLUMN) {
            return $this->curTuple[$this->datanode->fieldDefs['#tupleFieldNo']];
        }
    }
}
