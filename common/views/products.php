<?php
return [
    '#dataset'=>'main:store/PRODUCTS',
    'PRODUCT_ID'=>['#label'=>'Product Id'],
    'SKU'=>['#label'=>'SKU code'],
    'TITLE'=>['#label'=>'Product title'],
    'PARAMETERS'=>[
      '#label'=>'Product parameters',
      '@linked'=>[
        'PRODUCT_PARAMETER_ID'=>[
          '#label'=>'ProdParameterID'
          ],
        'PRODUCT_ID'=>[
          '#label'=>'The owner PRODUCT'
          ],
        'PARAMETER_VALUE'=>[
          '#field'=>'VALUE_STRING',
          '#label'=>'Parameter value',
        ],
      ],
    ],
    'PRODUCTGROUP'=>[
      '#field'=>'PRODUCT_GROUP_ID',
      '@linked'=>[
        'THE_PRODUCT_GROUP_NAME'=>[
          '#field'=>'NAME',
          '#label'=>'Group name',
        ],
        'THE_PRODUCT_GROUP_TITLE'=>[
          '#field'=>'TITLE',
          '#label'=>'Group title',
        ],
        'LINKED_PARENT_GROUP'=>[
          '#field'=>'PARENT_ID',
          '@linked'=>[
            'THE_PARENT_GROUP_NAME'=>[
              '#field'=>'NAME',
              '#label'=>'Parent group name'
            ]
          ]
        ]
      ]
    ],

    'SECONDGROUP'=>[
      '#field'=>'PRODUCT_SECOND_GROUP_ID',
      '@linked'=>[
        'PRODUCT_SECOND_GROUP_NAME'=>[
          '#field'=>'NAME',
          '#label'=>'Second group name',
        ],
      ],
    ],

    'THE_PRODUCT_TYPE'=>[
      '#field'=>'PRODUCT_TYPE_ID1',
      '@linked'=>[
        'PRODUCT_TYPE_ID'=>[
          '#field'=>'PRODUCT_TYPE_ID',
          '#label'=>'Prod Type ID'
        ],
        'TYPE_NAME'=>[
            '#field'=>'NAME',
            '#label'=>'Type of of product'
        ]
      ]
    ],

    '@orderBy'=>[
        'SKU'
    ],

    '@searchForms'=>[
      'default'=>[
        'params'=>[
          '#bySKU'=>['#field'=>'SKU','#type'=>'filterByString','#filterMode'=>'like','#askLabel'=>'Укажите артикул или его часть'],
          '#byProductGroup'=>['#field'=>'PRODUCT_GROUP_ID','#type'=>'filterByOneOfComboBox','#askLabel'=>'Укажите группу товаров']
        ]
      ]
    ]
];
