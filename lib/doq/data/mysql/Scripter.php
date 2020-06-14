<?php
namespace doq\data\mysql;

class Scripter extends \doq\data\Scripter
{
    private $tableAliases;
    private $tableAliasNo;
    private $columnList;
    private $joins;
    private $datasourceName;

    /**
     * Build select script according to data provider 
     * @param array $queryDefs
     * @return string
     */
    public function buildSelectScript(&$queryDefs)
    {
        $this->tableAliases=[];
        $this->tableAliasNo=1;
        $this->columnList=[];
        $this->joins=[];
        $this->datasourceName=$queryDefs['#dataSource'];
        $noParent=null;
        if (!$this->collectJoinsRecursive($queryDefs)) {
            return false;
        }
        $s='';
        foreach ($this->columnList as $icol=>&$col) {
            $s.=(($s!=='')?', ':'').$col[0].'.'.$col[1];
        }
        $s='SELECT '.$s." FROM \n";
        if (count($this->joins)) {
            $joinstr='';
            foreach ($this->joins as $ijoin=>&$join) {
                list($jtype, $ltab, $lfield, $rtab, $rfield)=$join;
                if ($ijoin===0) {
                    $js=$this->tableAliases[$ltab].' AS '.$ltab.' '.$jtype.' JOIN '.$this->tableAliases[$rtab].' AS '.$rtab.' ON '.$ltab.'.'.$lfield.'='.$rtab.'.'.$rfield;
                } else {
                    $js=$jtype.' JOIN '.$this->tableAliases[$rtab].' AS '.$rtab.' ON '.$ltab.'.'.$lfield.'='.$rtab.'.'.$rfield;
                }
                if ($ijoin) {
                    $joinstr="($joinstr)\n ";
                }
                $joinstr.=$js;
            }
            $s.=$joinstr;
        } else {
            $s.=$this->tableAliases['ta1'].' AS ta1';
        }
        return $s;
    }

    private function collectJoinsRecursive(&$entry, $parentAlias='', $parentField=false)
    {
        $datasetDef=&$entry['@dataset'];
        $schemaName=&$datasetDef['#schema'];
        $datasetName=&$datasetDef['#datasetName'];
        $tableAlias='ta'.$this->tableAliasNo;
        $dataset['#tableAlias']=$tableAlias;

        if (isset($datasetDef['#keyField'])) {
            $keyField=$datasetDef['#keyField'];
            $datasetDef['#keyFieldScriptName']=$tableAlias.'.'.$keyField;
        } else {
            unset($keyField);
        }

        $this->tableAliases[$tableAlias]=strtolower($datasetName);
        $this->tableAliasNo++;

        if ($parentField!==false) {
            $this->joins[]=['LEFT',$parentAlias,$parentField,$tableAlias,$keyField];
        }

        foreach ($datasetDef['@fields'] as $i=>&$field) {
            $originField=$field['#originField'];
            $field['#scriptField']=$tableAlias.'.'.$originField;
            if (isset($field['#kind'])) {
                if ($field['#kind']=='lookup') {
                    $ref=$field['#ref'];
                    list($RdatasourceName, $RschemaName, $RdatasetName, $isROtherDatasource)
                        =\doq\data\Scripter::getDatasetPathElements($ref, $this->datasourceName, $schemaName, $datasetName);
                    if (isset($field['#refType']) && $field['#refType']=='join') {
                        if ($isROtherDatasource) {
                            trigger_error(\doq\tr('doq', 'Strange join to the other Datasource %s:%s/%s. Joining cancelled', $RdatasourceName, $RschemaName, $RdatasetName), E_USER_ERROR);
                            return false;
                        }
                        $this->columnList[]=[$tableAlias,$originField];
                        $this->collectJoinsRecursive($field, $tableAlias, $originField);
                    } else {
                        # not the join
                        $this->columnList[]=[$tableAlias,$originField];
                    }
                }
            } else {
                # plain field
                $this->columnList[]=[$tableAlias,$originField];
            }
        }
        return true;
    }
}
