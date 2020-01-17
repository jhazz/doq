<?php
namespace doq\data;

abstract class Scripter
{
    abstract public function buildSelectScript($query);

    public static function create($providerName)
    {
        switch ($providerName) {
        case 'mysql':
            return \doq\data\mysql\Scripter::create();
        default:
            return new Scripter();
        }
    }

    public static function getDatasetPathElements(&$path, $datasourceName='', $schemaName='', $datasetName='')
    {
        $a=explode(':', $path, 2);
        $isOtherDatasource=false;
        if (count($a)==2) {
            $isOtherDatasource=(($datasourceName!='')&&($a[0]!=$datasourceName));
            $datasourceName=$a[0];
            $datasetName=$a[1];
        }
        $a=explode('/', $datasetName, 10);
        if (count($a)==2) {
            $schemaName=$a[0];
            $datasetName=$a[1];
        }
        return [$datasourceName,$schemaName,$datasetName,$isOtherDatasource];
    }
}
