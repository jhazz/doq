<?php
return [ // Datasource "main"
    '#dataConnection'=>'MYSQL0',
    '@schemas'=>[
        'store'=>[
            '@datasets'=>[
                # dataset in the schema 'mystore' uses in model 'main' (type = 'memory')
                'PRODUCTS'=>[
                    '#refKind'=>'table',
                    '@permissions'=>['*'=>'s'],
                    '@fields'=>[
                        'PRODUCT_ID'=>['#type'=>'int64','#isAutoValue'=>1, '@permissions'=>['*'=>'+s','store.editors'=>'+iud']],
                        'TITLE'=>['#type'=>'string','#size'=>80,'#isRequired'=>1,'@permissions'=>['*'=>'+s','store.editors'=>'+iud']],
                        'SKU'=>['#type'=>'string','#size'=>30,'@permissions'=>['*'=>'s','store.editors'=>'+iud']],
                        'PRODUCT_GROUP_ID'=>['#type'=>'int64','#refKind'=>'lookup','#ref'=>'main:store/PRODUCT_GROUPS'],
                        'PRODUCT_SECOND_GROUP_ID'=>['#type'=>'int64','#refKind'=>'lookup','#ref'=>'main:store/PRODUCT_GROUPS'],
                        'PRODUCT_TYPE_ID1' =>['#type'=>'int64','#refKind'=>'lookup','#ref'=>'memdata:store/PRODUCT_TYPES'],
                        'PRODUCT_TYPE_ID2' =>['#type'=>'int64','#refKind'=>'lookup','#ref'=>'memdata:store/PRODUCT_TYPES'],
                        'PARAMETERS'=>['#type'=>'virtual','#refKind'=>'aggregation','#ref'=>'memdata:store/PRODUCT_PARAMETERS'],
                    ],
                    '#keyField'=>'PRODUCT_ID'
                ],

                  
                'PRODUCT_GROUPS'=>[
                    '#kind'=>'tree', # = list for dictionaries, = tree for small navigation trees, = table for tables printing via DataGrid
                    '#label'=>'Das ist Product groups',
                    '@fields'=>[
                        'PRODUCT_GROUP_ID'=>['#type'=>'int64','#isAutoValue'=>'1'],
                        'PARENT_ID'=>       ['#type'=>'int64','#refKind'=>'lookup','#ref'=>'store/PRODUCT_GROUPS'],
                        'NAME'=>            ['#type'=>'string','#size'=>'80', '#isRequired'=>'1'],
                        'SUB_NAME'=>        ['#type'=>'string','#size'=>'80'],
                        'TITLE'=>           ['#type'=>'string','#size'=>'180']
                    ],
                    '#keyField'=>'PRODUCT_GROUP_ID',
                ],
            ]
        ]
    ]
];

?>
