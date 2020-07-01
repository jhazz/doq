<?php
require_once 'autorun.php';
doq\Html::subTitle('Testing');
?><style>body,html {padding:0; margin:0; width:100%; height:100vh;}
pre {font-family:'dejavu sans mono',consolas;font-size:8pt;height:90vh; overflow:auto; width:100%; white-space:pre-wrap;}
</style><?php
doq\Logger::placeConsole();
doq\Html::body();


list($viewProducts,$err)=doq\data\View::create('products');
$roles=['store.editors'];
$viewProducts->prepareWriter($viewProducts->viewModifyTime,true, $roles);

?><table border=1 width='100%' ><tr><td width="50%"><pre>
<?php

$params=<<<END
    {
    "@filter":[{"#columnId":"SKU", "#operand":"LIKE", "@values":["ОР"]}],
    "#pageSize":10,"#pageNo":1
    }
END;
$phpParams=json_decode($params,true);
list($datanode, $rowCount, $err)=$viewProducts->read($phpParams);

print "<h1>Dump of Datanode->queryDefs</h1>";
print json_encode($viewProducts->queryDefs,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

print "<hr><h1>Datanode->toArray()</h1>";
print json_encode($datanode->toArray(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

print "<hr><h1>data\\Context walker over Datanode using next(), extractAllFields()</h1>";
list($products, $err)=\doq\data\Context::create($datanode);
do{
    $r=$products->extractAllFields();
    print json_encode($r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    print '<br>';
} while (!$products->next());


?>
</pre></td>
<td width="50%"><pre>

<?php

$updates=[[
    '#targetNode'=>'VIEW1',
    '@insert'=>[
        [
            '#target'=>'',
            '@columns'=>['+','SKU','TITLE','PRODUCTGROUP', 'SECONDGROUP','THE_PRODUCT_TYPE'], 
            '@values'=>[
                // добавляется два товара. Оба товара состоят в новой первичной группе, и в новой вторичной
                ['!1', 'ОМ.АРМ-1', 'ОРБИТА.АРМ-1 Машиниста', [1,'%1'],  [1,'%1'],  [0, 1]],
                ['!2', 'ОМ.УЗЧ-100', 'ОРБИТА.УЗЧ Усилитель', [1,'%2'],  [1,'%2'],  [1, '*1']]
            ]
        ],[
            '#target'=>'PRODUCTGROUP',
            '@columns'=>['+','THE_PRODUCT_GROUP_NAME'], 
            '@values'=>[
                ['%1', 'Оборудование метро'],
                ['%2', 'Усилители']
            ]
        ],[
            '#target'=>'SECONDGROUP',
            '@columns'=>['+','PRODUCT_SECOND_GROUP_NAME'], 
            '@values'=>[
                ['%1', 'Госзаказ'],
            ]
        ],[
            '#target'=>'THE_PRODUCT_TYPE',
            '@columns'=>['+','TYPE_NAME'],
            '@values'=>['*1','Запасные части']
        ],[
            '#target'=>'PARAMETERS',
            '@columns'=>['+','../PRODUCT_ID','PARAMETER_ID','PARAMETER_VALUE'],
            '@values'=>[
                ['@1',[1,'!1'], [0 ,1],'88 камер'],
                ['@2',[1,'!1'], [0 ,6],'макс.ширина 3']
            ]
        ]
    ],
    '@update'=>[
        [   
            //'#target'=>'',
            //'@shorts'=>['SKU'=>'a','TITLE'=>'b','PRODUCTGROUP'=>'c', 'SECONDGROUP'=>'d','THE_PRODUCT_TYPE'=>'e'], 
            '@set'=>[
                ['=' => '3','SKU'=>'ОР-Д3-4.1-АА', 'PRODUCTGROUP'=>[1,'%1']]
            ]
        ],[
            '#target'=>'PRODUCTGROUP',
            '@set'=>[
                ['=' => '103','THE_PRODUCT_GROUP_NAME'=>'Навигационное оснащение' ]
            ]
        ],[
            '#target'=>'PRODUCTGROUP/LINKED_PARENT_GROUP',
            '@set'=>['=' => '4','THE_PARENT_GROUP_NAME'=>'Видеонаблюдение' ]
        ]
    ],
    '@delete'=>[
        [
            '-'=>['6','26']
        ],
        [
            '#target'=>'PRODUCTGROUP',
            '-'=>['101']
        ]
    ]
]];

print json_encode($viewProducts->writerDefs,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
?>
</pre></td></tr></table>
