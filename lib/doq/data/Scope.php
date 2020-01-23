<?php
namespace doq\data;

/**
 * Scope is a combination of datanode (dataset/subcolumn/column) 
 * and indexing cursor referred to a dataset
 * */
abstract class Scope
{
    const TO_START=0;
    const TO_NEXT=1;
    const TO_END=2;
    /** @var ScopeWindow указывает тип окна, по которому движется курсор*/
    const SW_ALL_RECORDS='all';
    const SW_INDEX_RECORDS='index';
    const SW_ONE_INDEX_RECORD='one index';
    const SW_AGGREGATED_INDEX_RECORDS='agg index';
    const SW_ONE_FIELD='one field';

    public $datanode;
    public $path;
    abstract protected function seek($origin);
    abstract protected function makeDetailScope($path, $masterFieldName);
    abstract protected function __construct(Datanode $datanode, $path='');
    abstract public function asString();
}
