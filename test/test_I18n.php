<?php
require_once 'test_header.php';
require_once '../doq/I18n.php';

test_start(['area'=>'I18n']);

$config=['#sourceLang'=>'en-US', '#langPackFolder'=>'lang_wrong'];

$runtimePath=dirname(__DIR__,1).'/runtime';
if (!is_dir($runtimePath)) {
    trigger_error("Did not see 'runtime' folder in the up folder. Test is stopping");
    exit(1);
}
$testPath=$runtimePath . '/test';
if(!is_dir($testPath)) {
    mkdir($testPath,0666);
    if (!is_dir($testPath)){
        trigger_error("Unable to create 'test' folder in the 'runtime' folder. Test is stopping");
        exit(1);
    }
}
$langTestPath=$testPath.'/I18n';
if(!is_dir($langTestPath)) {
    mkdir($langTestPath,0666);
}
$p=$langTestPath.'/ru'; 
if(!is_dir($p)) {
    mkdir($p, 0666);
}
$p=$langTestPath.'/ru-RU';
if(!is_dir($p)) {
    mkdir($p, 0666);
}
$p=$langTestPath.'/ru-RU-Siberia';
if(!is_dir($p)) {
    mkdir($p, 0666);
}

file_put_contents($langTestPath.'/ru/worldlayout.php', '<'.'?php return ["up"=>"bad","down"=>"bad"];?'.'>');
file_put_contents($langTestPath.'/ru-RU/worldlayout.php', '<'.'?php return ["up"=>"верх","down"=>"низ"];?'.'>');
file_put_contents($langTestPath.'/ru-RU-Siberia/worldentities.php', '<'.'?php return ["light"=>"свет","dark"=>"темнота"];?'.'>');

if (!\doq\I18n::init($config, $langTestPath)){
    trigger_error('doq\\I18n::init() target path in argument did not override the path specified in the configuration #langPackFolder');
}

$lang=\doq\I18n::getTarget('ru-RU-Siberia');
$result=$lang->getCategory('worldlayout');

assert(($result['success']), "Unable to read worldlayout.php targetted by ru-RU-Siberia lang tag but placed in ru-RU category");
$data=$result['data'];
assert(($data['up']!=="bad"),"Wrong apply language package data from top 'ru' directory instead of 'ru-RU'");

$result=$lang->getCategory('worldentities');
assert($result['success'], "Unable to read worldentities.php targetted by ru-RU-Siberia lang and placed in ru-RU-Siberia category");
$data=$result['data'];
assert(($data['light']==="свет"),"Wrong apply language package data from top 'ru' directory instead of 'ru-RU'");

unlink($langTestPath.'/ru/worldlayout.php');
unlink($langTestPath.'/ru-RU/worldlayout.php');
unlink($langTestPath.'/ru-RU-Siberia/worldentities.php');

rmdir ($langTestPath.'/ru');
rmdir ($langTestPath.'/ru-RU');
rmdir ($langTestPath.'/ru-RU-Siberia');
rmdir ($langTestPath);

assert(\doq\I18n::target('ru-RU'), "I18n::target() did not switched to language ru-RU");
assert(\doq\I18n::category('worldlayout'), "I18n::category()  did not switched to category worldlayout");
$data=\doq\t('up');
assert(($data==='верх'), "\\doq\\t() fail translation");

test_stop();
?>