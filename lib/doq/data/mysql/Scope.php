<?php
namespace doq\data\mysql;

class Scope extends \doq\data\Scope
{
    public const SEEK_TO_START=0;
    public const SEEK_TO_NEXT=1;
    public const SEEK_TO_END=2;
    /** @var ScopeWindow указывает тип окна, по которому движется курсор*/
    public const SW_ALL_RECORDS='all';
    public const SW_INDEX_RECORDS='index';
    public const SW_ONE_INDEX_RECORD='one index';
    public const SW_AGGREGATED_INDEX_RECORDS='agg index';
    public const SW_ONE_FIELD='one field';

    //inherited public $path;
    //inherited public $datanode;
    /** @var array|null Текущий индекс */
    public $curIndex;
    /** @var array|null Текущий индекс агрегата. Предназначен для быстрого доступа к кортежу по ключевому значению */
    public $curIndexAggregate;
    /** @var array|null Текущий массив строк агрегата индекса, именно по нему производится обход через seek */
    public $curRowsAggregate;

    /** @var integer позиция в индексе, если он есть или позиция в агрегате индекса, или позиция в dataset->rows */
    
    public $curTupleNo;
    /** @var array|null ссылка на ту запись данных, на которую указывает позиция */
    public $curTuple;
    /** @var int одно из значений констант SW_ */
    public $curType;
    /** @var int длина выборки индекса по которому перемещаем указатель через seek*/
    public $curIndexLen;

    public function __construct(\doq\data\Datanode $datanode, $indexName='', $masterValue=null, $datasetScope=null, $path='')
    {
        $this->datanode=$datanode;
        $this->path=$path;
        $this->curType=null;
        $this->curTupleNo=null;
        $this->curTuple=null;
        $this->curIndexLen=0;
        if ($indexName!='') {
            $this->curIndex=&$datanode->dataset->resultIndexes[$indexName];
            $this->curIndexAggregate=null;
            switch ($this->curIndex['#type']) {
                case 'unique':
                    if ($masterValue!==null) {
                        $this->curType=self::SW_ONE_INDEX_RECORD;
                        #$this->curIndexAggregate=&$this->curIndex['@indexedTuples'][$masterValue];
                        #$this->curRowsAggregate=&$this->curIndex['@rowsOfTuples'][$masterValue];
                        $this->curTuple=&$this->curIndex['@indexedTuples'][$masterValue];
                        $this->curIndexLen=1;
                    } else {
                        // $this->curType=self::SW_INDEX_RECORDS;
                        // $this->curIndexAggregate=&$this->curIndex['@indexedTuples'];
                        // $this->curRowsAggregate=&$this->curIndex['@rowsOfTuples'];
                        // $this->curIndexLen=count($this->curRowsAggregate);
                        $this->curType=null;
                        $this->curIndexAggregate=null;
                        $this->curRowsAggregate=null;
                    }
                    break;
                case 'nonunique':
                    $this->curType=self::SW_AGGREGATED_INDEX_RECORDS;
                    if ($masterValue!==null) {
                        if (isset($this->curIndex['@indexedTuples'][$masterValue])) {
                            $this->curIndexAggregate=&$this->curIndex['@indexedTuples'][$masterValue];
                            $this->curRowsAggregate=&$this->curIndex['@rowsOfTuples'][$masterValue];
                            $this->curIndexLen=count($this->curRowsAggregate);
                            
                        } else {
                            //  если в текущем индексе кортежей нет индексного значения 
                            $this->curIndexAggregate=null;
                            $this->curRowsAggregate=null;
                            $this->curIndexLen=0;
                        }
                    } else {
                        trigger_error(\doq\t('FATAL ERROR! Do not use aggregated index without master value defining scope window'), E_USER_ERROR);
                    }
                    break;
                default:
                    trigger_error(\doq\t('FATAL ERROR! Unknown index type [%s]', $index['#type']), E_USER_ERROR);
            }
        } else {
            $this->curIndex=null;
            if ($this->datanode->type==\doq\data\Datanode::NT_COLUMN) {
                $this->curType=self::SW_ONE_FIELD;
                $this->curTuple=&$datasetScope->curTuple;
            } else {
                $this->curType=self::SW_ALL_RECORDS;
                $this->curIndexLen=count($this->datanode->dataset->tuples);
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
                if (!is_array($this->curRowsAggregate)) {
                    $this->curTuple=null;
                    return true;
                    //throw new \Exception('Scope::seek() called inside aggregated index but curIndexAggregate is not an array');
                }
                switch ($to) {
                    case self::SEEK_TO_START:
                        $position=0;
                        break;
                    case self::SEEK_TO_NEXT:
                        $position=$this->curTupleNo+1;
                        break;
                    case self::SEEK_TO_END:
                        $position=$this->curIndexLen-1;
                        break;
                }
                if ($position >= $this->curIndexLen) {
                    $position=$this->curIndexLen-1;
                    $EOT=true;
                }
                $this->curTupleNo=$position;
                
                if (isset($this->curTuple)) {
                    unset($this->curTuple);
                }
                $this->curTuple=&$this->curRowsAggregate[$position];
                break;

        case self::SW_ALL_RECORDS:
            if ($this->curIndexLen) {
                switch ($to) {
                    case self::SEEK_TO_START:
                        $position=0;
                        break;
                    case self::SEEK_TO_NEXT:
                        $position=$this->curTupleNo+1;
                        break;
                    case self::SEEK_TO_END:
                        $position=$this->curIndexLen-1;
                        break;
                    default:
                        trigger_error('Unknown seeking type '.$origin, E_USER_ERROR);
                        return false;
                }
                if ($position >= $this->curIndexLen) {
                    $position=$this->curIndexLen-1;
                    $EOT=true;
                }
                $this->curTupleNo=$position;
            }
            if (isset($this->curTuple)) {
                unset($this->curTuple);
            }
            $this->curTuple=&$this->datanode->dataset->tuples[$position];
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
                    trigger_error(\doq\t('Column index %s is out of data columns range %s', $tupleFieldNo, count($this->curTuple)), E_USER_ERROR);
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
