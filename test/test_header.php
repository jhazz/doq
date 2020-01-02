<?php
assert_options(ASSERT_ACTIVE, true);
assert_options(ASSERT_WARNING, false);
$GLOBALS['testResults']=[];
$GLOBALS['testArea']=null;


if (php_sapi_name() === 'cli') {
    print "=== Test called in cli mode ===\n";
    function print_error($type, $message, $file, $line){
        print $type.': '.$message.' in '.$file.' at '.$line."\n";
        test_add_counter($type);
    }
    
    function print_assert($file, $line, $code, $desc = null){
        print 'ASSERT FAIL: in '.$file.' at '.$line.' '.$code.' :: '. $desc."\n";
        test_add_counter('error');
    }

    function print_test_title($area){
        print "\n#### Tesing area is '".$area."' ####\n";
    }

    function print_test_results(){
        print "########################\nTEST RESULTS:\n";
        $totalErrors=0;
        foreach($GLOBALS['testResults'] as $area=>&$data) {
            if($data){
                print "AREA '{$area}' -- ";
                foreach($data as $counterName=>$v) {
                    print "{$counterName}:{$v} ";
                }
                if (isset($data['error'])) {
                    $totalErrors += $data['error'];
                }
                print "\n";
            }
        }
        print "\n------------------------\n";
        if($totalErrors>0){
            print "ERRORS TOTAL: {$totalErrors}\n";
        } else {
            print "NO ERRORS\n";
        }
        return $totalErrors;
    }
} else {
    print "<h1>Test called in web mode</h1>";
    function print_error($type, $message, $file, $line){
        print '<div><div style="width:20px;">'.$type.'</div><div>'.$message.'</div></div>';
    }
    function print_assert($file, $line, $code, $desc = null){
        print '<div style="margin:8px;"><div style="padding:2px;background-color:#ff8888;width:110px; display:inline;">ASSERT FAIL&nbsp;</div><div style=" display:inline;">'.$file.' at '.$line.' '.$code.' :: '. $desc.'</div></div>';;
        test_add_counter('error');
    }
    function print_test_title($area){
        print '<h2>Testing area "'.$area.'"</h2>';
    }

    function print_test_results(){
        print "<h2>TEST RESULTS</h2><table>";
        $totalErrors=0;
        foreach($GLOBALS['testResults'] as $area=>&$data) {
            if($data){
                print "<tr><td>AREA '{$area}'</td><td>";
                foreach($data as $counterName=>$v) {
                    print "{$counterName}:{$v} ";
                }
                if (isset($data['error'])) {
                    $totalErrors += $data['error'];
                }
                print "</td></tr>";
            }
        }
        print "</table><hr>";
        if($totalErrors>0){
            print "<h1>ERRORS TOTAL: {$totalErrors}</h1>";
        } else {
            print "<h1>NO ERRORS</h1>";
        }
        return $totalErrors;
    }

}

function test_add_counter($counterName){
    $area=$GLOBALS['testArea'];
    if(!$area){
        die("Test without declared area!");
    }

    if(!isset($GLOBALS['testResults'][$area])){
        $GLOBALS['testResults'][$area]=[];
    }
    $data=&$GLOBALS['testResults'][$area];
    if(!isset($data[$counterName])){
        $data[$counterName]=0;
    }
    $data[$counterName]++;
}

function test_start($config){
    $area=$config['area'];
    if(!$area){
        die('No area selected in test_start([area=>])');
    }
    if($GLOBALS['testArea']!==null){
        die('You must call test_stop() of '.$GLOBALS['testArea'].' before test_start('.$area.')');
    }
    $GLOBALS['testArea']=$area;
    $GLOBALS['testResults'][$area]=[];
    print_test_title($area);
    
}

function test_stop(){
    $GLOBALS['testArea']=null;
}

assert_options(ASSERT_CALLBACK, 'print_assert');

set_error_handler (
    function($errno, $errstr, $errfile, $errline)
    {
      switch($errno)
      {
        case E_NOTICE:
            print_error ('warning', $errstr,$errfile,$errline);
        break;
        case E_PARSE:
            print_error ('parse', $errstr,$errfile,$errline);
        break;
        default:
            print_error ('error ', $errstr,$errfile,$errline);
      }
      return true;
    }, E_ALL | E_STRICT);

register_shutdown_function(function(){
    if ($GLOBALS['testArea']!==null){
        print_error('SHUTDOWN', 'You did not stopped test of area!', $GLOBALS['testArea']);
    }
    $totalErrors=print_test_results();
    $GLOBALS['testTotalErrors']=$totalErrors;
    if($totalErrors>0){
        exit($totalErrors);
    }

});

?>