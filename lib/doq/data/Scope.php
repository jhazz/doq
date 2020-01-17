<?php
namespace doq\data;

/**
 * Scope is a combination of datanode (dataset/subcolumn/column) 
 * and indexing cursor referred to a dataset
 * */
abstract class Scope
{
    const SEEK_TO_START=0;
    const SEEK_TO_NEXT=1;
    const SEEK_TO_END=2;
    /** @var ScopeWindow указывает тип окна, по которому движется курсор*/
    const SW_ALL_RECORDS=0;
    const SW_INDEX_RECORDS=1;
    const SW_ONE_INDEX_RECORD=2;
    const SW_AGGREGATED_INDEX_RECORDS=3;
    const SW_ONE_FIELD=4;

    public $datanode;
    public $path;
    abstract protected function seek($origin);
    abstract protected function makeDetailScope($path, $masterFieldName);
    abstract protected function __construct(Datanode $datanode, $path='');
    abstract public function asString();
}
