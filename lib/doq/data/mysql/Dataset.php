<?php
namespace doq\data\mysql;

class Dataset extends \doq\data\Dataset
{
    public $connection;
    public $tuples;
    public $indexes;
    public static $useFetchAll;


    public function __construct(&$queryDefs, $newDatasetName)
    {
        $this->queryDefs=&$queryDefs;
        $this->name=$newDatasetName;
    }

    public function makeScope(\doq\data\Datanode $datanode, $path='', $indexName='', $masterValue=null, $masterScope=null)
    {
        return new Scope($datanode, $path, $indexName, $masterValue, $masterScope);
    }

    public function connect()
    {
        $r=\doq\data\Connections::getConnection($this->queryDefs['#dataConnection']);
        if($r[1]===null){
            $this->connection=$r[0];
        }
        return $r;
    }

    public function read(&$params)
    {
        $s=$this->queryDefs['#readScript'];
        $where=[];
        if (isset($params['@filter'])) {
            foreach ($params['@filter'] as $i=>&$param) {
                switch ($param['#operand']) {
                    case '=':
                        $columnId=$param['#columnId'];
                        list($fieldDef,$err)=self::getFieldByColumnId($columnId, $this->queryDefs);
                        if ($err!==null) {
                            trigger_error($err, E_USER_ERROR);
                            continue;
                        }
                        $scriptField=$fieldDef['#scriptField'];
                        $where[]=$scriptField.' = '.$param['@value'];
                        break;
                    case 'IN':
                        $columnId=$param['#columnId'];
                        list($fieldDef,$err)=self::getFieldByColumnId($columnId, $this->queryDefs);
                        if ($err!==null) {
                            trigger_error($err, E_USER_ERROR);
                            continue;
                        }
                        $scriptField=$fieldDef['#scriptField'];
                        $where[]=$scriptField.' IN ('.implode($param['@values'], ',').')';
                         break;
                    case 'LIKE':
                        $columnId=$param['#columnId'];
                        list($fieldDef,$err)=self::getFieldByColumnId($columnId, $this->queryDefs);
                        if ($err!==null) {
                            trigger_error($err, E_USER_ERROR);
                            continue;
                        }
                        $scriptField=$fieldDef['#scriptField'];
                        $where[]='(';
                        foreach ($param['@values'] as $j=>$v){
                            if($j>0){
                                $where[]=' OR ';
                            }
                            $where[]='('.$scriptField.' LIKE "%'.$v.'%")';
                        }
                        $where[]=')';
                        break;
                }
            }
        }
        if (count($where)) {
            $s.=' WHERE ('.implode($where, ') AND (').')';
        }
        $s.=';';
        if (\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_DATAQUERY){
            \doq\Logger::debugDataQuery($this->name,$s,__FILE__,__LINE__);
        }
        
        $this->mysqlresult = $this->connection->mysqli->query($s);
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
            $this->rowCount=count($this->tuples);
            $this->mysqlresult->close();
            unset($this->mysqlresult);
            if (isset($this->queryDefs['@indexes'])) {
                foreach ($this->queryDefs['@indexes'] as $i=>&$indexDefs) {
                    $indexName=$indexDefs['#name'];
                    $indexType=$indexDefs['#type'];
                    $keyTupleFieldNo=$indexDefs['#keyTupleFieldNo'];
                    switch ($indexType) {
                        case 'unique':
                            $tuplesByKey=[];
                            $tuplesByNo=[];
                            foreach ($this->tuples as $tupleNo=>&$tuple) {
                                $value=$tuple[$keyTupleFieldNo];
                                if (!is_null($value)) {
                                    if (isset($tuplesByKey[$value])) {
                                        trigger_error(\doq\tr('doq','Unique value %s are repeating in the index %s', $value, $indexName), E_USER_ERROR);
                                    } else {
                                        $tuplesByKey[$value]=&$tuple;
                                    }
                                    $tuplesByNo[$tupleNo]=&$tuple;
                                }
                            }
                            $this->indexes[$indexName]=[
                                '#type'=>$indexType,
                                '#keyTupleFieldNo'=>$keyTupleFieldNo,
                                '@tuplesByKey'=>&$tuplesByKey,
                                '@tuplesByNo'=>&$tuplesByNo
                                ];
                            break;
                        case 'cluster':
                            $tuplesByKey=[];
                            $tuplesByNo=[];
                            $byTupleFieldNo=$indexDefs['#byTupleFieldNo'];
                            foreach ($this->tuples as $tupleNo=>&$tuple) {
                                $byValue=$tuple[$byTupleFieldNo];
                                if (!is_null($byValue)) {
                                    if (!isset($tuplesByKey[$byValue])) {
                                        $tuplesByKey[$byValue]=[];
                                    } 
                                    $key=$tuple[$keyTupleFieldNo];
                                    $tuplesByKey[$byValue][$key]=&$tuple;
                                    if (!isset($tuplesByNo[$byValue])) {
                                        $tuplesByNo[$byValue]=[&$tuple];
                                    } else {
                                        $tuplesByNo[$byValue][]=&$tuple;
                                    }
                                }
                            }

                            $this->indexes[$indexName]=[
                                '#type'=>$indexType,
                                '#byTupleFieldNo'=>$byTupleFieldNo,
                                '#keyTupleFieldNo'=>$keyTupleFieldNo,
                                '@tuplesByKey'=>&$tuplesByKey,
                                '@tuplesByNo'=>&$tuplesByNo
                                ];
                            break;
                    }
                }
                if(\doq\Logger::$logMode & \doq\Logger::LE_DEBUG_DATAQUERY){
                    \doq\Logger::debugDatasetIndexes('Dataset['.$this->name.']', $this->indexesToHTML(), __FILE__, __LINE__);
                }
            } else {
                \doq\Logger::info('Сейчас буду индексировать ВСЕ');
            }
        } else {
            $this->tuples=null;
        }
    }

    /**
     * @param int $tupleFieldNo number of 
     */
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
            return [array_keys($valueSet), null];
        } else {
            return [false,'Has no tuples data'];
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
        foreach ($this->indexes as $indexName=>&$index) {
            $result[]='<table border=1><tr><td colspan=20>'.$index['#type'].' index "<b>' .$indexName.'</b>"</td></tr>';
            $recordVectors=&$index['@tuplesByKey'];
            $keyTupleFieldNo=$index['#keyTupleFieldNo'];

            switch ($index['#type']) {
                case 'unique':
                    foreach ($recordVectors as $value=>&$data) {
                        $result[]='<tr><td bgcolor="#ffff80">[PK='.$value.']</td>';
                        foreach ($data as $col=>&$v) {
                            if ($col!=$keyTupleFieldNo) {
                                $bgColor='#a0ffa0';
                            } else {
                                $bgColor='#ffffa0';
                            }
                            $result[]='<td bgcolor="'.$bgColor.'">'.$v.'</td>';
                        }
                        $result[]='</tr>';
                    }
                break;
                case 'cluster':
                    $byTupleFieldNo=$index['#byTupleFieldNo'];
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
                                if ($col!=$byTupleFieldNo) {
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


    /**
    public function &getTupleFields(){
        if(isset($this->tupleFields)){
            return $this->tupleFields;
        } else {
            $fieldDefs=&$this->queryDefs['@dataset']['@fields'];
            foreach ($fieldDefs as $fieldNo=>&$fieldDef) {
                if(isset($fieldDef['#tupleFieldNo'])){
                    $this->tupleFields[$fieldDef['#tupleFieldNo']]=&$fieldDef;
                }
            }
        };
        return $this->tupleFields;
    }
*/

}
