<?php
  $root=dirname(__FILE__, 2);

  return [
    '#rootPath'=>$root,
    '#libPath'=>$root.'/lib',
    '#commonPath'=>$root.'/common',
    '#langBasePath'=>$root.'/lang',
    '#caches'=>$root.'/runtime/cache',
    '#sourceLang'=>'en',
    '#templatesPath'=>$root.'/frontend/templates',
    '#parsedTemplatesCachePath'=>'cache/templates',
    '@modules'=>[
      'auth'    =>['actions'=>['default'=>'auth_status.php']],
      'products'=>['actions'=>['default'=>'products_list.php']]
      ],
    '@caches'=>[
      'mysql1_dataplans'=>['#type'=>'serialfile','#cacheFolderPath'=>'cache/dataplans','#filePrefix'=>'vp_','#fileSuffix'=>'.plan.txt','#forceCreateFolder'=>1]
      ],
    '@session'=>[
      '#formNoncesSalt'=>'gJUYGo87fsghgO*sdfsGftu',
      '#sessionDataConnection'=>'MYSQL0', // база данных, в которых есть sys_signups(запросы на регистрацию), sys_users(зарегистрированные), sys_sessions (сессии), sys_client(браузеры), sys_form_nonces(токены для форм авторизации), 
      '#autoApprove'=>'1' // автоматически переносить в список допущенных пользователей
      ],
    '@dataConnections'=>[
      'MYSQL0'=>['#provider'=>'mysql','@params'=>['host'=>'127.0.0.1','port'=>'3306','dbase'=>'dbnavi','login'=>'my1','password'=>'LEULMM8KEiKwbfhX'],'#dataPlanCachePath'=>'cache/dataplans'],
      'MYSQL1'=>['#provider'=>'mysql','@params'=>
         ['host'=>'95.191.130.173',
         'port'=>'8036','dbase'=>'test','login'=>'tester','password'=>'lua2Gee'],'#dataPlanCachePath'=>'cache/dataplans'],
      'localmem'=>['#provider'=>'memory']
    ],
  ];



?>