<?php  
#  Dataset schemas
#  $GLOBALS['doq']['schema'] = 

   return [
    '@datasources'=>[
      'main'=>[
        '#dataConnection'=>'MYSQL0',
        '@schemas'=>[
          'store'=>[
            '@datasets'=>[
              # dataset in the schema 'mystore' uses in model 'main' (type = 'memory')
              'PRODUCT_GROUPS'=>[
                '#refKind'=>'tree', # = list for dictionaries, = tree for small navigation trees, = table for tables printing via DataGrid
                '#label'=>'Das ist Product groups',
                '@fields'=>[
                  'PRODUCT_GROUP_ID'=>['#type'=>'int64','#isAutoValue'=>'1'],
                  'PARENT_ID'=>       ['#type'=>'int64','#refKind'=>'lookup','#ref'=>'store/PRODUCT_GROUPS'],
                  'NAME'=>            ['#type'=>'string','#size'=>'80'],
                  'SUB_NAME'=>        ['#type'=>'string','#size'=>'80'],
                  'TITLE'=>           ['#type'=>'string','#size'=>'180']
                ],
                '#keyField'=>'PRODUCT_GROUP_ID',
                '#nesting'=>['#rootId'=>0,'#parentIdField'=>'PARENT_ID']
              ],
              'PRODUCTS'=>[
                '#refKind'=>'table',
                '@fields'=>[
                  'PRODUCT_ID'      =>[
                    '#type'=>'int64',
                    '#isAutoValue'=>1
                    ],
                  'PRODUCT_GROUP_ID'=>[
                    '#type'=>'int64',
                    '#refKind'=>'lookup',
                    '#ref'=>'main:store/PRODUCT_GROUPS'],
                  'PRODUCT_SECOND_GROUP_ID'=>[
                    '#type'=>'int64',
                    '#refKind'=>'lookup',
                    '#ref'=>'main:store/PRODUCT_GROUPS'],
                  'PRODUCT_TYPE_ID1' =>[
                    '#type'=>'int64',
                    '#refKind'=>'lookup',
                    '#ref'=>'memdata:store/PRODUCT_TYPES',
                    ],
                  'PRODUCT_TYPE_ID2' =>[
                    '#type'=>'int64',
                    '#refKind'=>'lookup',
                    '#ref'=>'memdata:store/PRODUCT_TYPES',
                    ],
                  'PARAMETERS'=>[
                    '#type'=>'virtual',
                    '#refKind'=>'aggregation',
                    '#ref'=>'memdata:store/PRODUCT_PARAMETERS'
                    ],
                  'TITLE'=>['#type'=>'string','#size'=>80],
                  'SKU'=>['#type'=>'string','#size'=>30],

                ],
                '#keyField'=>'PRODUCT_ID'
              ],
            ],
          ]
        ],
      ],

      'memdata'=>[
      	'#dataConnection'=>'MYSQL0',
        '@schemas'=>[
          'store'=>[
            '@datasets'=>[
              'PRODUCT_TYPES'=>[
                '#kind'=>'list',
                '@fields'=>[
                  'PRODUCT_TYPE_ID'=>[
                    '#type'=>'int64',
                    '#isAutoValue'=>1,
                  ],
                  'NAME'=>[
                    '#type'=>'string',
                    '#size'=>50
                  ]
                ],
              '#keyField'=>'PRODUCT_TYPE_ID'
              ],
              'PARAMETERS'=>[
                '@fields'=>[
                  'PARAMETER_ID'=>['#type'=>'int64'],
                  'PARAMETER_GROUP_ID'=>['#type'=>'int64'],
                  'NAME'=>['#type'=>'string','#size'=>'100'],
                  'UNITS'>['#type'=>'string','#size'=>'50']
                ],
                '#keyField'=>'PARAMETER_ID'
              ],
              'PRODUCT_PARAMETERS'=>[
                '@fields'=>[
                  'PRODUCT_PARAMETER_ID'=>['#type'=>'int64'],
                  'PARAMETER_ID'=>['#type'=>'int64'],
                  'VALUE_STRING'=>['#type'=>'string','#size'=>'250'],
                  'PRODUCT_ID'=>[
                    '#type'=>'int64',
                    '#refKind'=>'lookup',
                    '#ref'=>'main:store/PRODUCTS'
                  ],
                ],
                '#keyField'=>'PRODUCT_PARAMETER_ID'
              ]
            ]
          ]
        ]
      ]
    ]
  ];
?>
