<?php
namespace doq\data\mysql;

class Dataset extends \doq\data\Dataset
{
#    public $params;
    public $connection;
    public $tuples;
    public $resultIndexes;
    public static $useFetchAll;

    public function __construct(&$queryDefs, $id)
    {
        $this->queryDefs=&$queryDefs;
        $this->id=$id;
    }

    public function makeScope(\doq\data\Datanode $datanode, $indexName='', $indexKey=null, $datasetScope=null, $path='')
    {
        return new Scope($datanode, $indexName, $indexKey, $datasetScope, $path);
    }

    public function connect()
    {
        $r=\doq\data\Connections::getConnection($this->queryDefs['#dataConnection']);
        if ($r[0]) {
            $this->connection=$r[1];
            return true;
        }
        return false;
    }


    public function read(&$params)
    {
        $s=$this->queryDefs['#readScript'];
        $where=[];
        if (isset($params['@filter'])) {
            foreach ($params['@filter'] as $i=>&$param) {
                switch ($param['#operand']) {
                case 'IN':
                    $columnId=$param['#columnId'];
                    $res=self::getFieldByColumnId($columnId, $this->queryDefs);
                    if (!$res[0]) {
                        trigger_error(\doq\t('Column with id=%d not found in %s', $columnId, 'dataset'), E_USER_ERROR);
                    }
                    $fieldDef=&$res[1];
                    $scriptField=$fieldDef['#scriptField'];
                    $where[]=$scriptField.' IN ('.implode($param['@values'], ',').')';
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
            if (isset($this->queryDefs['@resultIndexes'])) {
                foreach ($this->queryDefs['@resultIndexes'] as $i=>&$resultIndexDef) {
                    $indexName=$resultIndexDef['#name'];
                    $indexByTupleFieldNo=$resultIndexDef['#byTupleFieldNo'];
                    $indexType=$resultIndexDef['#type'];
                    $keyTupleFieldNo=$resultIndexDef['#keyTupleFieldNo'];
                    switch ($indexType) {
                        case 'unique':
                            $indexedTuples=[];
                            $rowsOfTuples=[];
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

                                    $rowsOfTuples[$tupleNo]=&$tuple;
                                }
                            }
                            $this->resultIndexes[$indexName]=[
                                '#type'=>$indexType,
                                '#indexByTupleFieldNo'=>$indexByTupleFieldNo,
                                '@indexedTuples'=>&$indexedTuples,
                                '@rowsOfTuples'=>&$rowsOfTuples
                                ];
                        break;
                        case 'nonunique':
                            $indexedTuples=[];
                            $rowsOfTuples=[];
                            # когда индекс имеет тип 'nonunique' тогда каждый вектор - это
                            # набор ссылок на строки
                            # с найденными значениями индекса
                            // TODO ИСПРАВИТЬ ЗДЕСЬ

                            foreach ($this->tuples as $tupleNo=>&$tuple) {
                                $byValue=$tuple[$indexByTupleFieldNo];
                                if (!is_null($byValue)) {
                                    if (!isset($indexedTuples[$byValue])) {
                                        $indexedTuples[$byValue]=[];
                                    } 
                                    $key=$tuple[$keyTupleFieldNo];
                                    $indexedTuples[$byValue][$key]=&$tuple;
                                    

                                    if (!isset($rowsOfTuples[$byValue])) {
                                        $rowsOfTuples[$byValue]=[&$tuple];
                                    } else {
                                        $rowsOfTuples[$byValue][]=&$tuple;
                                    }

                                }
                            }

                            $this->resultIndexes[$indexName]=[
                                '#type'=>$indexType,
                                '#indexByTupleFieldNo'=>$indexByTupleFieldNo,
                                '#$keyTupleFieldNo'=>$keyTupleFieldNo,
                                '@indexedTuples'=>&$indexedTuples,
                                '@rowsOfTuples'=>&$rowsOfTuples
                                ];
                        break;
                    }
                }
                if(\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_DATAQUERY){
                    \doq\Logger::debugDatasetIndexes('Dataset['.$this->id.']', $this->indexesToHTML(), __FILE__, __LINE__);
                }
            }
        } else {
            $this->tuples=false;
        }
    }

    public function getTupleFieldValues($tupleFieldNo)
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


    public function dataToHTML()
    {
        $result=[];
        $fieldList=[];
        self::collectFieldList($this->queryDefs, $fieldList);
        $result[]='<table class="dpd" border=1><tr valign="top" bgcolor="#ffffa0">';
        foreach ($fieldList as $i=>$field) {
            if (!isset($field['#tupleFieldNo'])) {
                continue;
            }

            $result[]='<td>#id:'.$field['#columnId']
            .'<br/>#tupleFieldNo:'.$field['#tupleFieldNo']
            .'<br/>#field:['.$field['#field'].']'
            .'<br>#originField:['.$field['#originField'].']'
            .'<br>#scriptField:['.$field['#scriptField'].']'
            .(isset($column['#label'])?'<br/>#label:'.$field['#label']:'').'</td>';
        }
        $result[]='</tr>';
        foreach ($this->tuples as $tupleNo=>&$tuple) {
            $s='';
            foreach ($tuple as $j=>&$v) {
                $s.='<td>'.$v.'</td>';
            }
            $result[]= '<tr>'.$s.'</tr>';
        }
        $result[]='</table>';
        return \implode('', $result);
    }

    public function indexesToHTML()
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
                        foreach ($data as $col=>&$v) {
                            if ($col!=$indexByTupleFieldNo) {
                                $bgColor='#a0ffa0';
                            } else {
                                $bgColor='#a0a0a0';
                            }
                            $result[]='<td bgcolor="'.$bgColor.'">'.$v.'</td>';
                        }
                        $result[]='</tr>';
                    }
                break;
                case 'nonunique':
                    foreach ($recordVectors as $value=>&$portions) {
                        $count=count($portions);
                        $result[]= '<tr><td bgcolor="#ffaa80" rowspan="'.$count.'">Aggregated by'.$value.'</td>';
                        $first=true;
                        foreach ($portions as $i=>&$data) {
                            if (!$first) {
                                $result[]='<tr>';
                            }
                            $first=false;
                            foreach ($data as $col=>&$item) {
                                if ($col!=$indexByTupleFieldNo) {
                                    $bgColor='#a0ffa0';
                                } else {
                                    $bgColor='#a0a0a0';
                                }
                                $result[]= '<td bgcolor="'.$bgColor.'">'.$item.'</td>';
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
