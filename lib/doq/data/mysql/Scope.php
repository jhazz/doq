<?php
namespace doq\data\mysql;


class Scope extends \doq\data\Scope {
  //inherited public $path;
  //inherited public $datanode;
  /** @var array|null Текущий индекс */
  public $curIndex;
  /** @var array|null Текущий массив агрегата индекса, именно по нему производится обход через seek */
  public $curIndexAggregate;
  /** @var integer позиция в индексе, если он есть или позиция в агрегате индекса, или позиция в dataset->rows */
  public $curTupleNo;
  /** @var array|null ссылка на ту запись данных, на которую указывает позиция */
  public $curTuple;
  /** @var int одно из значений констант SW_ */
  public $curType;
  /** @var int длина выборки индекса по которому перемещаем указатель через seek*/
  public $curIndexLen;

  public function __construct(\doq\data\Datanode $datanode,$indexName='',$indexKey=NULL,$datasetScope=NULL,$path='') {
    $this->datanode=$datanode;
    $this->path=$path;
    $this->curType='';
    $this->curTupleNo=0;
    $this->curIndexLen=0;
    if($indexName!='') {
      $this->curIndex=&$datanode->dataset->resultIndexes[$indexName];
      $this->curIndexAggregate=NULL;
      switch($this->curIndex['#type']) {
        case 'unique':
          if($indexKey!==NULL) {
            $this->curType=self::SW_ONE_INDEX_RECORD;
            $this->curIndexAggregate=&$this->curIndex['@indexedTuples'][$indexKey];
            $this->curTuple=&$this->curIndexAggregate;
          } else {
            $this->curType=self::SW_INDEX_RECORDS;
            $this->curIndexAggregate=&$this->curIndex['@indexedTuples'];
            reset($this->curIndexAggregate);
            $this->curIndexLen=count($this->curIndexAggregate);
          }
          break;
        case 'nonunique':
          $this->curType=self::SW_AGGREGATED_INDEX_RECORDS;
          if($indexKey!==NULL) {
            if(!isset($this->curIndex['@indexedTuples'][$indexKey])) {
              #TODO: надо будет проверять может ли вызывать ошибку попытка получить ссылочное значение по неправильному значению ключа
              $this->curIndexAggregate=NULL;
              $this->curIndexLen=0;
            } else {
              $this->curIndexAggregate=&$this->curIndex['@indexedTuples'][$indexKey];
              $this->curIndexLen=count($this->curIndexAggregate);
            }

          } else {
            trigger_error(\doq\t('FATAL ERROR! Do not use aggregated index without master value defining scope window'),E_USER_ERROR);
          }
          break;
        default:
          trigger_error(\doq\t('FATAL ERROR! Unknown index type [%s]',$index['#type']),E_USER_ERROR);
      }
    } else {
      $this->curIndex=NULL;
      if($this->datanode->type==\doq\data\Datanode::NT_COLUMN) {
        $this->curType=self::SW_ONE_FIELD;
        $this->curTuple=&$datasetScope->curTuple;
      } else {
        $this->curType=self::SW_ALL_RECORDS;
        $this->curIndexLen=count($this->datanode->dataset->tuples);
      }
    }
  }


  public function seek($to=self::SEEK_TO_NEXT){
    $EOT=false;
    switch ($this->curType) {
      # Nothing to do, don't move parent dataset scope
      case self::SW_ONE_FIELD:
        $EOT=true;
        break;
      case self::SW_INDEX_RECORDS:
      case self::SW_AGGREGATED_INDEX_RECORDS:
        // TODO reset,next,end могут создавать копию массива, которая нам не нужна. Надо избавиться от таких функций
        if (!is_array($this->curIndexAggregate)) {
          $this->curTuple=NULL;
          #trigger_error(\doq\t('scope::seek called to move inside aggregated index but curIndexAggregate is not an array'),E_USER_ERROR);
          return true;
        }
        switch ($to) {
          case self::SEEK_TO_START:
            reset($this->curIndexAggregate);
            $position=0;
            break;
          case self::SEEK_TO_NEXT:
            if(next($this->curIndexAggregate)!==false) {
              $position=$this->curTupleNo+1;
            } else $EOT=true;
            break;
          case self::SEEK_TO_END:
            end($this->curIndexAggregate);
            $position=$this->curIndexLen-1;
            break;
        }
        $this->curTupleNo=$position;
        $k=key($this->curIndexAggregate);
        if(isset($this->curTuple)) unset($this->curTuple);
        $this->curTuple=&$this->curIndexAggregate[$k];
        break;
      case self::SW_ALL_RECORDS:
        if($this->curIndexLen) {
          switch($to) {
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
              trigger_error('Unknown seeking type '.$origin,E_USER_ERROR);
              return false;
          }
          if($position >= $this->curIndexLen) {
            $position=$this->curIndexLen-1;
            $EOT=true;
          }
          $this->curTupleNo=$position;
        }
        if(isset($this->curTuple)) unset($this->curTuple);
        $this->curTuple=&$this->datanode->dataset->tuples[$this->curTupleNo];
        break;
    }
    return $EOT;
  }

  /** @return \doq\data\Scope */
  public function makeDetailScope($path,$masterFieldName) {
    $masterDatanode=$this->datanode;
    $masterDataset=$masterDatanode->dataset;
    $detailDatanode=$masterDatanode->childNodes[$masterFieldName];
    $masterFieldNo=$detailDatanode->dataset->query['#masterFieldNo'];
    $masterTupleFieldNo=$masterDataset->query['@dataset']['@fields'][$masterFieldNo]['#tupleFieldNo'];
    $masterValue=$this->curTuple[$masterTupleFieldNo];
    $detailIndexName=$masterDataset->query['@detailIndexByFieldNo'][$masterFieldNo];
    return $detailDatanode->dataset->makeScope($detailDatanode,$detailIndexName,$masterValue,null,'['.$masterValue.']'.'/'.$masterFieldName.'/'.$path);
  }

  public function asString() {
    switch($this->datanode->type) {
      case \doq\data\Datanode::NT_COLUMN:
        $fieldDef=&$this->datanode->fieldDefs;
        if(!isset($fieldDef['#tupleFieldNo'])) {
          trigger_error('Unknown #tupleFieldNo in dataset for path '.$this->path,E_USER_ERROR);
          return '{ERROR}';
        }
        $fieldType=isset($fieldDef['#type'])?$fieldDef['#type']:'string';
        $fieldLabel=isset($fieldDef['#label'])?$fieldDef['#label']:$fieldDef['#field'];
        $tupleFieldNo=$fieldDef['#tupleFieldNo'];
        if($tupleFieldNo>=count($this->curTuple)) {
          trigger_error(\doq\t('Column index %s is out of data columns range %s',$tupleFieldNo,count($this->curTuple)),E_USER_ERROR);
        }
        $value=$this->curTuple[$tupleFieldNo];
        return $value;
      break;
    }
  }

  public function value() {
    if($this->datanode->type===\doq\data\Datanode::NT_COLUMN) {
      return $this->curTuple[$this->datanode->fieldDefs['#tupleFieldNo']];
    }
  }
}
?>