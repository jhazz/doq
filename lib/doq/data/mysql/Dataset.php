<?php
namespace doq\data\mysql;

class Dataset extends \doq\data\Dataset
{
#    public $params;
    public $connection;
    public $tuples;
    public $resultIndexes;
    public static $useFetchAll;

    public function __construct(&$planEntry, $id)
    {
        $this->planEntry=&$planEntry;
        $this->id=$id;
    }

    public function makeScope(\doq\data\DataNode $dataNode, $indexName='', $indexKey=null, $datasetScope=null, $path='')
    {
        return new Scope($dataNode, $indexName, $indexKey, $datasetScope, $path);
    }

    public function connect()
    {
        $r=\doq\data\Connections::getConnection($this->planEntry['#dataConnection']);
        if ($r[0]) {
            $this->connection=$r[1];
            return true;
        }
        return false;
    }


    public function read(&$params)
    {
        $s=$this->planEntry['#readScript'];
        $where=[];
        if (isset($params['@filter'])) {
            foreach ($params['@filter'] as $i=>&$param) {
                switch ($param['#operand']) {
          case 'IN':
            $columnId=$param['#columnId'];
            #  ЭТОТ $fieldName=$this->planEntry['@dataset']['@fields'][$columnNo]['#scriptField'];
            $res=\doq\data\View::getFieldByColumnId($columnId, $this->planEntry);
            if (!$res[0]) {
                trigger_error(\doq\t('Column [# %d] not found in %s', $columnId, 'dataset'), E_USER_ERROR);
            }
            $fieldDef=&$res[1];
            $fieldName=$fieldDef['#scriptField'];
            $where[]=$fieldName.' IN ('.implode($param['@values'], ',').')';
            break;
        }
            }
        }
        if (count($where)) {
            $s.=' WHERE ('.implode($where, ') AND (').')';
        }
        $s.=';';
        if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_DATAQUERY){
            \doq\Logger::debugDataQuery($this->id,$s,__FILE__,__LINE__);
        }
        
        $this->mysqlresult=$this->connection->mysqli->query($s);
        if ($this->mysqlresult!==false) {
            if (self::$useFetchAll) {
                $this->tuples=$this->mysqlresult->fetch_all(MYSQLI_NUM);
            } else {
                $this->tuples=[];
                while ($tuple=$this->mysqlresult->fetch_row()) {
                    $this->tuples[]=&$tuple;
                    unset($tuple);
                }
            }
            $this->mysqlresult->close();
            unset($this->mysqlresult);
            if (isset($this->planEntry['@resultIndexes'])) {
                foreach ($this->planEntry['@resultIndexes'] as $i=>&$resultIndexDef) {
                    $indexName=$resultIndexDef['#name'];
                    $indexByTupleFieldNo=$resultIndexDef['#byTupleFieldNo'];
                    $indexType=$resultIndexDef['#type'];
                    switch ($indexType) {
                        case 'unique':
                            $indexedTuples=[];
                            # тупо проходим по всем данным. Возможно есть способ более скоростного обхода
                            # когда индекс имеет тип 'unique' тогда каждый вектор - это ссылка на строку
                            # с уникальным значением
                            foreach ($this->tuples as $tupleNo=>&$tuple) {
                                $value=$tuple[$indexByTupleFieldNo];
                                if (!is_null($value)) {
                                    if (isset($indexedTuples[$value])) {
                                        trigger_error(\doq\tr('doq','Unique value %s are repeating in the index %s', $value, $indexName), E_USER_ERROR);
                                    } else {
                                        $indexedTuples[$value]=&$tuple;
                                    }
                                }
                            }
                            $this->resultIndexes[$indexName]=[
                                '#type'=>$indexType,
                                '#indexByTupleFieldNo'=>$indexByTupleFieldNo,
                                '@indexedTuples'=>&$indexedTuples
                                ];
                        break;
                        case 'nonunique':
                            $indexedTuples=[];
                            # когда индекс имеет тип 'nonunique' тогда каждый вектор - это
                            # набор ссылок на строки
                            # с найденными значениями индекса
                            foreach ($this->tuples as $tupleNo=>&$tuple) {
                                $value=$tuple[$indexByTupleFieldNo];
                                if (!is_null($value)) {
                                    if (!isset($indexedTuples[$value])) {
                                        $indexedTuples[$value]=[&$tuple];
                                    } else {
                                        $indexedTuples[$value][]=&$tuple;
                                    }
                                }
                            }
                            $this->resultIndexes[$indexName]=[
                                '#type'=>$indexType,
                                '#indexByTupleFieldNo'=>$indexByTupleFieldNo,
                                '@indexedTuples'=>&$indexedTuples
                                ];
                        break;
                    }
                }
                if(\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_DATAQUERY){
                    \doq\Logger::debugDatasetIndexes('INDEX', $this->dumpIndexes());
                }
            }
        } else {
            $this->tuples=false;
        }
    }

    public function uniqueValuesOfTupleSetField($tupleFieldNo)
    {
        if (isset($this->tuples)) {
            $valueSet=[];
            foreach ($this->tuples as $tupleNo => &$tuple) {
                $v=&$tuple[$tupleFieldNo];
                if (!is_null($v)) {
                    $valueSet[$v]=1;
                }
            }
            return [true,array_keys($valueSet)];
        } else {
            return [false,null];
        }
    }


    public function dumpData()
    {
        $fieldList=[];
        \doq\data\View::collectFieldList($this->planEntry, $fieldList);
        $s='';
        foreach ($fieldList as $i=>$field) {
            if (!isset($field['#tupleFieldNo'])) {
                continue;
            }

            $s.='<td>#id:'.$field['#columnId']
            .'<br/>#tupleFieldNo:'.$field['#tupleFieldNo']
            .'<br/>#field:['.$field['#field'].']'
            .'<br>#originField:['.$field['#originField'].']'
            .'<br>#scriptField:['.$field['#scriptField'].']'
            .(isset($column['#label'])?'<br/>#label:'.$field['#label']:'').'</td>';
        }
        print '<table class="dpd" border=1><tr valign="top" bgcolor="#ffffa0">'.$s.'</tr>';
        foreach ($this->tuples as $tupleNo=>&$tuple) {
            $s='';
            foreach ($tuple as $j=>&$v) {
                $s.='<td>'.$v.'</td>';
            }
            print '<tr>'.$s.'</tr>';
        }
        print '</table>';
    }

    public function dumpIndexes()
    {
        $result=[];
        foreach ($this->resultIndexes as $indexName=>&$index) {
            $result[]='<table border=1><tr><td colspan=20>Index name: "' .$indexName.'", type:'.$index['#type'].'</td></tr>';
            $recordVectors=&$index['@indexedTuples'];
            $indexByTupleFieldNo=$index['#indexByTupleFieldNo'];
            switch ($index['#type']) {
                case 'unique':
                    foreach ($recordVectors as $value=>&$data) {
                        $result[]='<tr><td bgcolor="#ffff80">'.$value.'</td>';
                        foreach ($data as $col=>&$value) {
                            if ($col!=$indexByTupleFieldNo) {
                                $bgColor='#a0ffa0';
                            } else {
                                $bgColor='#a0a0a0';
                            }
                            $result[]='<td bgcolor="'.$bgColor.'">'.$value.'</td>';
                        }
                        $result[]='</tr>';
                    }
                break;
                case 'nonunique':
                    foreach ($recordVectors as $value=>&$portions) {
                        $count=sizeof($portions);
                        $result[]= '<tr><td bgcolor="#ffaa80" rowspan='.$count.'>'.$value.'</td>';
                        foreach ($portions as $i=>&$data) {
                            if ($i>0) {
                                $result[]='<tr>';
                            }
                            foreach ($data as $col=>&$value) {
                                if ($col!=$indexByTupleFieldNo) {
                                    $bgColor='#a0ffa0';
                                } else {
                                    $bgColor='#a0a0a0';
                                }
                                $result[]= '<td bgcolor="'.$bgColor.'">'.$value.'</td>';
                            }
                            $result[]= '</tr>';
                        }
                    }
                break;
            }
            $result[]= '</table>';
        }
        return implode('', $result);
    }


}
