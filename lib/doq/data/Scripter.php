<?php
namespace doq\data;

class Scripter
{
    /**
     * Build select script according to data provider 
     * @param array $queryDefs
     * @return string
     */
    public function buildSelectScript(&$queryDefs){
        return '';
    }

    
    public static function create($providerType)
    {
        
        switch ($providerType) {
            case 'mysql':
                $r=new \doq\data\mysql\Scripter();
                return [true,$r];
            default:
                throw new \Exception('Unknown Data provider type');
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
