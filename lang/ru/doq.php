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
    'Cache folder "%s" not found'=>'Папка для кэша "%s" не найдена'
];
?>