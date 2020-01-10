<?php
namespace doq\data;

abstract class Scripter
{
    abstract public function buildSelectScript($planEntry);

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

//     public static function dumpPlan(&$planEntry)
//     {
//         $s=self::dumpDataset($planEntry);
        
//         print '<style>.dpd{font-family:arial,sans;font-size:11px;}</style>';
//         print '<table class="dpd" border=1><tr><td bgcolor="#ffff80" colspan="5">'.$planEntry['#dataConnection'].'(data provider='.$planEntry['#dataProvider'].', datasource='.$planEntry['#dataSource'].')</td></tr>'
//             .$s
//             .'<tr><td colspan="2">Select script:</td><td colspan="5" bgcolor="#e0ffe0"><pre>'.$planEntry['#readScript'].'</pre></td></tr>'
//             .'</table>';

//         if (isset($planEntry['@subPlan'])) {
//             foreach ($planEntry['@subPlan'] as $i=>&$subEntry) {
//                 print '<br/><hr/>Next plan entry:';
//                 self::dumpPlan($subEntry);
//             }
//         }
//     }

//     public static function dumpDataset(&$entry)
//     {
//         $dataset=&$entry['@dataset'];
//         $row1='';
//         $row2='';
//         $row3='';
//         if (isset($entry['#refType'])) {
//             $refType=$entry['#refType'];
//             if ($refType=='linknext') {
//                 return '<tr><td bgcolor="#ffffe0">Will be loaded by one of the next plan entry</td></tr>';
//             }
//         }

// #    if(isset($entry['#filterDetailByColumn'])) {
// #      $row1.='<tr><td bgcolor="#ffa0a0" colspan="5">#filterDetailByColumn: <b> '.$entry['#filterDetailByColumn'].'</b> #filterDetailField:'.$entry['#filterDetailField'].'</td></tr>';
// #    }
//         if (isset($entry['#mastertupleFieldNo'])) {
//             $row1.='<tr><td bgcolor="#ffa0a0" colspan="5">#mastertupleFieldNo: <b>'.$entry['#mastertupleFieldNo'].'</b><br/>#detailDatasetId:'.$entry['#detailDatasetId'].'</td></tr>';
//         }
//         if (isset($entry['@resultIndexes'])) {
//             foreach ($entry['@resultIndexes'] as $i=>&$idx) {
//                 $row1.='<tr><td bgcolor="#eeffff" colspan="5">@index #type:'
//                     .$idx['#type']
//                     .', name:<b>'.$idx['#name']
//                     .'</b> (#byTupleFieldNo: '.$idx['#byTupleFieldNo'].' )</td></tr>';
//             }
//         }
//         $row1.='<tr><td bgcolor="#ff8080" colspan="5">dataset are reading from <b>'.$dataset['#schema'].'/'.$dataset['#datasetName'].'</b></td></tr>';
//         if (!$dataset['@fields']) {
//             trigger_error('пусто', E_USER_ERROR);
//         }
//         foreach ($dataset['@fields'] as $i=>&$field) {
//             $kind=(isset($field['#kind'])?$field['#kind']:'text');
//             $row2.='<tr><td>id#'.$field['#columnId'].(isset($field['#tupleFieldNo'])?'<br/>['.$field['#tupleFieldNo'].']':'(virt)')
//                 .'</td><td>['.$field['#field'].']'
//                 .(((isset($field['#originField'])&&$field['#originField']!==$field['#field'])?':'.$field['#originField']:''))
//                 .'</td><td>'.$kind.'</td>'
//                 .'<td>'.(isset($field['#label'])?'<i>'.$field['#label'].'</i><br/>':'');

//             # Если это лукап-справочник, то он может быть #refType='join' или #refType='linknext'
//             if ($kind=='lookup') {
//                 $refType=isset($field['#refType'])? $field['#refType'] : "";
//                 if ($refType) {
//                     $row2.='Reference type:'.$refType.' ==> <b>'.$field['#ref'].'</b><br/>';
//                     $row2.='<b>'.(isset($field['#refDatasource'])?$field['#refDatasource']:'this').'</b>:'
//                         .(isset($field['#refSchema'])?$field['#refSchema']:'.')
//                         .(isset($field['#refDataset'])?'/'.$field['#refDataset']:'/.');
//                 }
//                 if (isset($field['#uniqueIndex'])) {
//                     $row2.='<br/>'.(isset($field['#uniqueIndex'])?'#uniqueIndex:'.$field['#uniqueIndex']:'(Error! No #uniqueIndex!)');
//                 }
//                 if (isset($field['#refType'])) {
//                     $row2.='<table class="dpd" border=1>'.self::dumpDataset($field).'</table>';
//                 }
//                 # Если это агрегат, то ссылка может быть только удаленной
//             } elseif ($kind=='aggregation') {
//                 $refType=isset($field['#refType'])? $field['#refType'] : "(NO REFTYPE!)";
//                 $row2.='Reference type:'.$refType.' ==> <b>'.$field['#ref'].'</b><br/>'
//                     .'<b>'.(isset($field['#refDatasource'])?$field['#refDatasource']:'this').'</b>:'
//                     .$field['#refSchema'].'/'.$field['#refDataset']
//                     .'<br/>'.(isset($field['#nonuniqueIndex'])?'#nonuniqueIndex:'.$field['#nonuniqueIndex']:'(Error! No #nonuniqueIndex!)');
//                 $row2.='<table class="dpd" border=1>'.self::dumpDataset($field).'</table>';
//             }
//             if (isset($field['#error'])) {
//                 $row2.='ERROR! '.$field['#error'].'</br>';
//             }
//             $row2.='</td></tr>';
//         }
//         return $row1.$row2;
//     }
}
