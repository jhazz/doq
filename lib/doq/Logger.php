<?php
namespace doq;


set_error_handler(
    function ($phpErrorType, $errstr, $errfile, $errline) {
        Logger::addPHPError($phpErrorType, $errstr, $errfile, $errline);
        return true;
    },
    E_ALL | E_STRICT
);


register_shutdown_function(
    function () {
        $error = @error_get_last();
        if (isset($error['type'])) {
            Logger::addPHPError($error['type'], $error['message'], $error['file'], $error['line']);
        }
        Logger::HTTPEnd();
    }
);

set_exception_handler(
    function ($exception) {
        Logger::error('Exception', $exception->getMessage(), $exception->getFile(), $exception->getLine());
        $stack=$exception->getTrace();
        $ss='';
        foreach ($stack as $i=>&$stackItem) {
            $s='';
            $ss.='&nbsp;&nbsp;';
            if (isset($stackItem['args'])) {
                foreach ($stackItem['args'] as $name=>&$value) {
                    if ($s!='') {
                        $s.=',';
                    }
                    if (is_scalar($value)) {
                        $s.=$name.'='.$value;
                    } else {
                        $s.=$name.'='.gettype($value);
                    }
                }
            }
            Logger::info('STACK:'.$ss.$stackItem['function'].'('.$s.')', $stackItem['file'], $stackItem['line']);
        }
    }
);
  
  
abstract class Logger
{
    /** @const Log entry types */
    const LE_NONE=0;
    const LE_INFO=1;
    const LE_WARNING=2;
    const LE_ERROR=4;
    const LE_DEBUG_INFO=8;
    const LE_DEBUG_ENV=16;
    const LE_DEBUG_GLOBALS=32;
    const LE_DEBUG_DATAPLAN=64;
    const LE_DEBUG_DATAQUERY=128;
    const LE_DEBUG_ALL=32767;

    /** @const Log targets */
    const LT_NONE=0;
    const LT_FILE='file';
    const LT_HTML_END='html_end';
    #const LT_HTML_INLINE='html_inline';

    private static $loggerInstance;
    public static $entryTypeNames=['1'=>'Information log section','2'=>'Warnings log section',
        '4'=>'Errors log section','8'=>'Debug information',
        '64'=>'Debugging data plans log section','128'=>'Debugging data query log section'];
    public static $logMode;

    abstract public function pushData($entryType,$data);

    public static function init(&$env=null)
    {
        if (is_object(self::$loggerInstance)) {
            return;
        }
        if(isset($env['#logMode'])) {
            self::$logMode=$env['#logMode'];
        } else {
            self::$logMode=self::LE_DEBUG_ALL;
        }

        $targetType=self::LT_HTML_END;
        if (isset($env['#targetType'])) {
            $targetType=$env['#targetType'];
        }
        
        switch($targetType){
            case self::LT_FILE:
                $r=FileLogger::create($env);
                if ($r[0]) {
                    self::$loggerInstance=&$r[1];
                } else {
                    self::$loggerInstance=new HTMLEndLogger();
                }
            break;
            case self::LT_HTML_END:
                self::$loggerInstance=new HTMLEndLogger($env);
            break;
        }
    }

    /**
     * @param mixed $data a copy of any user data as like as info message
     * @param string $file name of the php-source file
     * @param int $line the line number in the php-source that generates this information data
     */
    public static function info($data, $file = false, $line = false)
    {
        if (self::$logMode & self::LE_INFO) {
            if (!is_object(self::$loggerInstance)) {
                print "<hr>Log not initialized. (${file} @ ${line}) Info:<br>";
                print_r($data);
                return;
            }
            self::$loggerInstance->pushData(self::LE_INFO, ['data'=>&$data,'file'=>$file,'line'=>$line]);
        }
    }

    public static function error($data, $file = false, $line = false)
    {
        if (self::$logMode & self::LE_ERROR) {
            if (!is_object(self::$loggerInstance)) {
                print "<hr>Log not initialized. Error in (${file} @ ${line}):<br>";
                print_r($data);
                return;
            }
            self::$loggerInstance->pushData(self::LE_ERROR, ['data'=>&$data,'file'=>$file,'line'=>$line]);
        }
    }

    public static function debug($category, $data, $file=null, $line=null)
    {
        if (self::$logMode & self::LE_DEBUG_INFO) {
            if (!is_object(self::$loggerInstance)) {
                print "<hr>Log not initialized. Debug in (${file} @ ${line}):<br>";
                print_r($data);
                return;
            }
            self::$loggerInstance->pushData(self::LE_DEBUG_INFO, ['category'=>$category,'data'=>&$data,'file'=>$file,'line'=>$line]);
        }
    }

    public static function dataPlan (&$data, $planName='', $file=null, $line=null)
    {
        if (self::$logMode & self::LE_DEBUG_DATAPLAN) {
            if (!is_object(self::$loggerInstance)) {
                print "<hr>DataLog not initialized. Bad try to dump dataplan in (${file} @ ${line})<br>";
                print_r($data);
                return;
            }
            self::$loggerInstance->pushDataPlan(self::LE_DEBUG_DATAPLAN, ['planName'=>$planName,'dataplan'=>&$data,'file'=>$file,'line'=>$line]);
        }

    }

    public static function addPHPError($phpErrorType, $data, $file = null, $line = null)
    {
        if (self::$logMode & self::LE_ERROR) {
            if (!is_object(self::$loggerInstance)) {
                print "<hr>Log not initialized. PHP Error in (${file} @ ${line})<br>";
                print_r($data);
                return;
            }
            self::$loggerInstance->pushData(self::LE_ERROR, ['phpErrorType'=>$phpErrorType,'data'=>&$data,'file'=>$file,'line'=>$line]);
        }
    }

    public static function HTTPEnd()
    {
        if (self::$logMode !== self::LE_NONE) {
            if (is_object(self::$loggerInstance)) {
                    self::$loggerInstance->onHTTPEnd();
            }
        }
    }

    private static function _jsonSerializeWalker(&$result,&$v)
    {
        if (!is_null($v)) {
            if (\is_array($v)) {
                array_push($result, '{');
                $first=true;
                foreach ($v as $k=>&$d) {
                    if(!$first) {
                        array_push($result, ',');
                    }
                    if (!is_null($d)) {
                        array_push($result, '"'.\addslashes($k).'":');
                        self::_jsonSerializeWalker($result, $d);
                        $first=false;
                    }
                }
                array_push($result, '}');
            } elseif (\is_scalar($v)) {
                if (\is_numeric($v)) {
                    array_push($result, $v);
                } elseif (\is_string($v)) {
                    array_push($result, '"'.$v.'"');
                } elseif (\is_bool($v)){
                    array_push($result,$v?'true':'false');
                }
            } elseif (\is_object($v)) {
                if (\method_exists($v, 'asString')) {
                    array_push($result, $v->asString());
                } else {
                    array_push($result, '\"(Class '.\gettype($v).' has no method asString)\"');
                }
            }
        } // not null
    } //func

    public static function jsonSerialize(&$data)
    {
        $result=[];
        self::_jsonSerializeWalker($result,$data);
        return \implode('', $result);
    }

    public static function dumpPlanAsHTML(&$planEntry,&$result=null)
    {
        if ($result==null) {
            $result=[];
        }
        
        
        array_push($result, '<style>.dpd{font-family:arial,sans;font-size:11px;}</style>'
            .'<table class="dpd" border=1><tr><td bgcolor="#ffff80" colspan="5">'
            .$planEntry['#dataConnection'].'(data provider='
            .$planEntry['#dataProvider'].', datasource='.$planEntry['#dataSource'].')</td></tr>');

        array_push($result, self::dumpPlanEntry($planEntry));

        array_push($result, '<tr><td colspan="2">Select script:</td><td colspan="5" bgcolor="#e0ffe0"><pre>'
            .$planEntry['#readScript'].'</pre></td></tr>'
            .'</table>');

        if (isset($planEntry['@subPlan'])) {
            foreach ($planEntry['@subPlan'] as $i=>&$subEntry) {
                array_push($result, '<br/><hr/>Next plan entry:');
                self::dumpPlanAsHTML($subEntry,$result);
            }
        }


    }

    public static function dumpPlanEntry(&$entry)
    {
        $dataset=&$entry['@dataset'];
        $row1='';
        $row2='';
        $row3='';
        if (isset($entry['#refType'])) {
            $refType=$entry['#refType'];
            if ($refType=='linknext') {
                return '<tr><td bgcolor="#ffffe0">Will be loaded by one of the next plan entry</td></tr>';
            }
        }

#    if(isset($entry['#filterDetailByColumn'])) {
#      $row1.='<tr><td bgcolor="#ffa0a0" colspan="5">#filterDetailByColumn: <b> '.$entry['#filterDetailByColumn'].'</b> #filterDetailField:'.$entry['#filterDetailField'].'</td></tr>';
#    }
        if (isset($entry['#mastertupleFieldNo'])) {
            $row1.='<tr><td bgcolor="#ffa0a0" colspan="5">#mastertupleFieldNo: <b>'.$entry['#mastertupleFieldNo'].'</b><br/>#detailDatasetId:'.$entry['#detailDatasetId'].'</td></tr>';
        }
        if (isset($entry['@resultIndexes'])) {
            foreach ($entry['@resultIndexes'] as $i=>&$idx) {
                $row1.='<tr><td bgcolor="#eeffff" colspan="5">@index #type:'
                    .$idx['#type']
                    .', name:<b>'.$idx['#name']
                    .'</b> (#byTupleFieldNo: '.$idx['#byTupleFieldNo'].' )</td></tr>';
            }
        }
        $row1.='<tr><td bgcolor="#ff8080" colspan="5">dataset are reading from <b>'.$dataset['#schema'].'/'.$dataset['#datasetName'].'</b></td></tr>';
        if (!$dataset['@fields']) {
            trigger_error('пусто', E_USER_ERROR);
        }
        foreach ($dataset['@fields'] as $i=>&$field) {
            $kind=(isset($field['#kind'])?$field['#kind']:'text');
            $row2.='<tr><td>id#'.$field['#columnId'].(isset($field['#tupleFieldNo'])?'<br/>['.$field['#tupleFieldNo'].']':'(virt)')
                .'</td><td>['.$field['#field'].']'
                .(((isset($field['#originField'])&&$field['#originField']!==$field['#field'])?':'.$field['#originField']:''))
                .'</td><td>'.$kind.'</td>'
                .'<td>'.(isset($field['#label'])?'<i>'.$field['#label'].'</i><br/>':'');

            # Если это лукап-справочник, то он может быть #refType='join' или #refType='linknext'
            if ($kind=='lookup') {
                $refType=isset($field['#refType'])? $field['#refType'] : "";
                if ($refType) {
                    $row2.='Reference type:'.$refType.' ==> <b>'.$field['#ref'].'</b><br/>';
                    $row2.='<b>'.(isset($field['#refDatasource'])?$field['#refDatasource']:'this').'</b>:'
                        .(isset($field['#refSchema'])? $field['#refSchema']:'.')
                        .(isset($field['#refDataset'])?'/'.$field['#refDataset']:'/.');
                }
                if (isset($field['#uniqueIndex'])) {
                    $row2.='<br/>'.(isset($field['#uniqueIndex'])?'#uniqueIndex:'.$field['#uniqueIndex']:'(Error! No #uniqueIndex!)');
                }
                if (isset($field['#refType'])) {
                    $row2.='<table class="dpd" border=1>'.self::dumpPlanEntry($field).'</table>';
                }
                # Если это агрегат, то ссылка может быть только удаленной
            } elseif ($kind=='aggregation') {
                $refType=isset($field['#refType'])? $field['#refType'] : "(NO REFTYPE!)";
                $row2.='Reference type:'.$refType.' ==> <b>'.$field['#ref'].'</b><br/>'
                    .'<b>'.(isset($field['#refDatasource'])?$field['#refDatasource']:'this').'</b>:'
                    .$field['#refSchema'].'/'.$field['#refDataset']
                    .'<br/>'.(isset($field['#nonuniqueIndex'])?'#nonuniqueIndex:'.$field['#nonuniqueIndex']:'(Error! No #nonuniqueIndex!)');
                $row2.='<table class="dpd" border=1>'.self::dumpPlanEntry($field).'</table>';
            }
            if (isset($field['#error'])) {
                $row2.='ERROR! '.$field['#error'].'</br>';
            }
            $row2.='</td></tr>';
        }
        return $row1.$row2;
    }
    
}


class HTMLEndLogger extends Logger {
    public $logArray;
    public $dataLogArray;

    public function __construct (&$env=null)
    {
        $this->logArray=[];
        $this->dataLogArray=[];
    }

    public function pushData($entryType,$data)
    {
        $logSection=$entryType.'';
        if(!isset($this->logArray[$logSection])){
            $this->logArray[$logSection]=[];
        }
        $data['type']=$entryType;
        $data['time']=date('Y-m-d H:i:s');
        array_push($this->logArray[$logSection], $data);
    }

    /**
     * @param mixed $entryType
     * @param array $data ['planName'=>$planName,'dataplan'=>&$dataPlan,'file'=>$file,'line'=>$line]
     */
    public function pushDataPlan($entryType,$data)
    {
        $logSection=$entryType.'';
        if(!isset($this->logArray[$logSection])){
            $this->logArray[$logSection]=[];
        }
        array_push($this->logArray[$logSection], ['data'=>'Dumps dataplan to dataplans log','time'=>date('Y-m-d H:i:s'),'type'=>'dataplan']);
        array_push($this->dataLogArray, $data);
    }

    public function onHTTPEnd()
    {
        
        $s = '';
        foreach ($this->logArray as $section=>&$SectionArray) {
            $s.='<h4>'.\doq\tr('log',self::$entryTypeNames[$section.'']).'</h4>';
            foreach ($SectionArray as $i=>&$record) {
                $data=null;
                $file=null;
                $line=null;
                $type=self::LE_NONE;
                \extract($record, EXTR_OVERWRITE);
            
                switch ($type) {
                case self::LE_ERROR:
                    $typeName ='Doq Error';
                    $bgColor="#f8e0e0";
                    $fw=""; break;
                case self::LE_INFO:  $typeName ='Info'; $bgColor="#f8f8f0"; $fw=""; break;
                case self::LE_WARNING: $typeName = 'Warning'; $bgColor="#80f880"; $fw= ""; break;
                case self::LE_DEBUG_INFO:
                    $typeName = 'Debug';
                    if ($category) {
                        $typeName.="[${category}]";
                    }
                    $bgColor="#aaaaaa";
                    $fw= ""; break;
                case 0: $typeName = ''; $bgColor="#bbbbbb"; $fw= ""; break;
            default:
                $typeName = 'Error '.$type;
                $bgColor="#ffb0b0";
                $fw="font-weight:bold;";
            }

                $text='';
                if (!is_scalar($data)) {
                    $text = var_export($data, true);
                } else {
                    $text = $data;
                }

                if ($file) {
                    $text .= ' -- '.$file;
                }
                if ($line) {
                    $text .= '@'.$line;
                }
                $s .= "<div style='text-align:left;margin:1px;padding:3px;font-family:arial,sans;font-size:12px;color:#0;background-color:$bgColor;$fw'>${typeName}: ${text}</div>";
            }
        }
        print '<div style="white-space: normal;padding:1px; background-color:#808080;">'.$s.'</div>';
        
        /** @var $data ['planName'=>$planName,'dataplan'=>&$dataPlan,'file'=>$file,'line'=>$line] */
        foreach ($this->dataLogArray as $i=>&$data) {
            $this->dumpPlan($data['dataplan']);
        }
    }


    public function dumpPlan(&$planEntry){
        $result=[];
        self::dumpPlanAsHTML($planEntry, $result);
        foreach($result as $i=>&$s){
            print $s;
        }
    }


}

class FileLogger extends Logger 
{
    public $target;
    public $targetLogFile;
    public $targetDataLogFile;
    public $targetEnvLogFile;
    private $logFileHandle;
    private $dataLogFileHandle;
    private $targetEnvLogFileHandle;

    public static function create(&$env){
        if(!isset($env['#logsPath'])){
            return [false,'Undefined enviroment parameter #logsPath. Cannot logging to a files'];
        }

        $targetLogDir=$env['#logsPath'];
        
        if(!is_dir($targetLogDir)){
            if (!\mkdir($targetLogDir, 0777, true)) {
                $s=' Target logs path "'.$targetLogDir.'" do not points to any directory and this application has no rights to make logs directory there';
                print $s;
                return [false,$s];
            }
        }
        return [true,new self($env)];
    }

    public function __construct (&$env)
    {
        $this->targetLogDir=$env['#logsPath'];
        $d=$this->targetLogDir.'/'.$_SERVER['REMOTE_ADDR'].'/'.date('y-m-d__H.i.s');
        if (\mkdir($d, 0777, true)) {
            $this->targetLogFile=$d.'/log.json';
            $this->targetDataLogFile=$d.'/datalog.json';
            $this->targetEnvLogFile=$d.'/env.json';

            $this->logFileHandle=fopen($this->targetLogFile, 'w');
            fputs($this->logFileHandle, "[\n");
            $this->dataLogFileHandle=fopen($this->targetDataLogFile, 'w');
            $this->targetEnvLogFileHandle=fopen($this->targetEnvLogFile, 'w');
        }
    }

    public function pushData($entryType,$data)
    {
        if($entryType & (self::LE_INFO | self::LE_ERROR | self::LE_WARNING)){
            if (!$this->logFileHandle) {
                return;
            }
            $data['type']=$entryType;
            $data['time']=date('Y-m-d H:i:s');
            fputs($this->logFileHandle, self::jsonSerialize($data));
            fputs($this->logFileHandle, ",\n\n");
        }
    }

    # self::$loggerInstance->pushDataPlan(self::LE_DEBUG_DATAPLAN, ['planName'=>$planName,'dataplan'=>&$data,'file'=>$file,'line'=>$line]);
    public function pushDataPlan($entryType,$data)
    {
        if (!$this->dataLogFileHandle) {
            return;
        }
        $data['type']=$entryType;
        $data['time']=date('Y-m-d H:i:s');
        
        fputs ($this->dataLogFileHandle, self::jsonSerialize($data['dataplan']));

    }

    public function onHTTPEnd()
    {
        fputs($this->logFileHandle, ']');
        \fclose($this->logFileHandle);
        \fclose($this->dataLogFileHandle);
        \fclose($this->targetEnvLogFileHandle);
        
    }



}
