<?php
namespace doq\data;

/**
 * Scope is a combination of datanode (dataset/subcolumn/column) 
 * and indexing cursor referred to a dataset
 * */
abstract class Scope
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

    public $datanode;
    public $path;
    abstract protected function seek($origin);
    abstract protected function makeDetailScope($path, $masterFieldName);
    abstract protected function __construct(Datanode $datanode, $path='');
    abstract public function asString();
}
