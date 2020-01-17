<?php
return [
	'Unknown connection name %s'=>'Неизвестное название соединения к базе даных %s',
	'Environment variable DOQ_ENVIRONMENT has value "%s" that not found in configuration. You may use "*" as default connection configuration' => 'DOQ_ENVIRONMENT имеет значение "%s", которое не определено в конфигурации соединений. Можете использовать "*" для всех окружений по-умолчанию',
    'Environment variable DOQ_ENVIRONMENT is undefined and "*" environment is absent in connection configuration'=>'Переменная окружения DOQ_ENVIRONMENT не определена, также в конфигурации соединений отсутствует окружение по-умолчанию - с символом "*"',

    # Template.php
    'Templates path "%s" do not refers to a directory. Please check out environment or create this directory first' => 'Путь к файлам шаблонов "%s" не указывает на директорию. Проверьте настройки окружения или сначала создайте такую директорию',
    'Template file "%s" not found in folder "%s"'=>'Шаблон "%s" не найден в папке шаблонов "%s"',
    'Template file "%s" is nod readable'=>'Файл шаблона %s не доступен для чтения',

    # Cache
    'Unknown cache type "%s"'=>'Неизвестный тип кэша "%s"',
    # SerialFileCache
    'Undefined parameter #targetFolder in cache config'=>'Отсутствует параметр #targetFolder в конфигурации кэша',
    'Unable to create cache folder "%s". Use local or temporary instead'=>'Не могу создать папку для кэша в "%s". Будут использоваться папки для временных файлов',
    'Cache folder "%s" not found'=>'Папка для кэша "%s" не найдена',

    # Scripter
    'Strange join to the other Datasource %s:%s/%s. Joining cancelled'=>'Неизвестный join к строннему источнику данных %s:%s/%s. Отмена связывания данных',

    # Dataset
    'Dublicate field name "%s" is found in the view config "%s"'=>'Дублированное название поля "%s" обнаружено в конфигурации вида "%s"',

    # mysql\Dataset
    'Unique value %s are repeating in the index %s'=>'Уникальное значение %s повторяется в индексе %s',

    # ScopeStack
    'Dataset "%s" in the local scope has no node with name "%s"'=>'Набор данных "%s" в локальной области видимости не имеет узла с названием "%s"',
    'Dataset %s has no column %s'=>'Объект данных "%s" не содержит в себе колонку "%s"',
    'Column %s has no dataset in previous scopes of path %s. Subcolumn should be the next scope after any dataset scope'=>'У колонки "%s" нет родительского набора данных по адресу "%s". Колонки должны следовать сразу за набором данных dataset',
    'Column %s cannot not have any subnames like %s'=>'Колонка "%s" не может иметь вложенных имен, таких как "%s"',
    'Scope stack reach emptyness. Seems like had called unusable pop from stack'=>'Произведено извлечение из пустого стека областей видимости данных. Похоже, был сделан лишний вызов pop()'
];
?>