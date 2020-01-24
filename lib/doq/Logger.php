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
        $r=['<b>Exception: '.$exception->getMessage().'</b><br>'];
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
            $file=isset($stackItem['file'])?' in '.$stackItem['file']:'';
            $line=isset($stackItem['line'])?'@'.$stackItem['line']:'';
            $r[]=$ss.$stackItem['function'].'('.$s.')'.$file.$line.'<br>';
        }
        Logger::error(\implode('', $r), $exception->getFile(), $exception->getLine());
    }
);
  
  
abstract class Logger
{
    /** @const Log entry types */
    const LE_NONE=0;
    const LE_ERROR=1;
    const LE_WARNING=2;
    const LE_INFO=4;
    const LE_DEBUG_INFO=8;
    const LE_DEBUG_ENV=16;
    const LE_DEBUG_GLOBALS=32;
    const LE_DEBUG_QUERY=64;
    const LE_DEBUG_DATAQUERY=128;
    const LE_DEBUG_ALL=32767;

    /** @const Log targets */
    const LT_NONE=0;
    const LT_FILE='file';
    const LT_HTML_END='html_end';
    #const LT_HTML_INLINE='html_inline';

    private static $loggerInstance;
    public static $entryTypeNames=[
        self::LE_ERROR=>'Errors log section',
        self::LE_WARNING=>'Warnings log section',
        self::LE_INFO=>'Information log section',
        self::LE_DEBUG_INFO=>'Debug information',
        self::LE_DEBUG_QUERY=>'Debugging data querys log section',
        self::LE_DEBUG_DATAQUERY=>'Debugging data query log section'];
    public static $logMode;

    abstract public function pushMessageToLog($entryType,$data);

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
            self::$loggerInstance->pushMessageToLog(self::LE_INFO, ['data'=>&$data,'file'=>$file,'line'=>$line]);
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
            self::$loggerInstance->pushMessageToLog(self::LE_ERROR, ['data'=>&$data,'file'=>$file,'line'=>$line]);
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
            // if(is_array($data)){
            //     $data=print_r($data, true);
            // }
            self::$loggerInstance->pushMessageToLog(self::LE_DEBUG_INFO, ['category'=>$category,'data'=>&$data,'file'=>$file,'line'=>$line]);
        }
    }

    public static function debugQuery (&$query, $id='', $file=null, $line=null)
    {
        if (self::$logMode & self::LE_DEBUG_QUERY) {
            if (!is_object(self::$loggerInstance)) {
                print "<hr>DataLog not initialized. Bad try to dump query in (${file} @ ${line})<br>";
                print_r($query);
                return;
            }

            self::$loggerInstance->pushQuery([
                'id'=>$id,
                'query'=>&$query,
                'file'=>$file,'line'=>$line]);
        }

    }
    public static function debugDataQuery($id, $queryString,  $file = null, $line = null){
        self::$loggerInstance->pushToDataLog([
            'type'=>'queryString',
            'id'=>$id, 
            'queryString'=>$queryString,
            'file'=>$file,'line'=>$line]);
    }

    public static function debugDatasetIndexes($id, $indexDump,  $file = null, $line = null){
        self::$loggerInstance->pushToDataLog([
            'type'=>'indexDump',
            'id'=>$id,
            'indexDump'=>$indexDump,
            'file'=>$file,'line'=>$line]);
    }

    public static function addPHPError($phpErrorType, $data, $file = null, $line = null)
    {
        if (self::$logMode & self::LE_ERROR) {
            if (!is_object(self::$loggerInstance)) {
                print "<hr>Log not initialized. PHP Error in (${file} @ ${line})<br>";
                print_r($data);
                return;
            }
            self::$loggerInstance->pushMessageToLog(self::LE_ERROR, ['phpErrorType'=>$phpErrorType,'data'=>&$data,'file'=>$file,'line'=>$line]);
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
                    if (!is_null($d)) {
                        if(!$first) {
                            array_push($result, ',');
                        }
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
                    array_push($result, '"'.\addslashes($v).'"');
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

    public static function dumpQueryAsHTML(&$query,&$result=null)
    {
        if ($result==null) {
            $result=[];
        }
        
        $result[]='<style>'
            .'td.dpd {border: 1px solid black; padding: 3px; font-family:arial,sans;font-size:11px;}'
            .'table.dpd {border-collapse: collapse;}</style>'
            .'<table class="dpd" border="1" cellspacing="0" cellpadding="5"><tr><td bgcolor="#ffff80" colspan="5">'
            .$query['#dataConnection'].'(data provider='
            .$query['#dataProvider'].', datasource='.$query['#dataSource'].')</td></tr>';

        $result[]=self::dumpQueryEntry($query);

        $result[]='<tr><td colspan="2">Select script:</td><td colspan="5" bgcolor="#e0ffe0"><pre>'
            .$query['#readScript'].'</pre></td></tr>'
            .'</table>';

        if (isset($query['@subQuery'])) {
            foreach ($query['@subQuery'] as $i=>&$subEntry) {
                array_push($result, '<br/><hr/>Next query entry:');
                self::dumpQueryAsHTML($subEntry,$result);
            }
        }


    }

    public static function dumpQueryEntry(&$entry)
    {
        $dataset=&$entry['@dataset'];
        $row1='';
        $row2='';
        $row3='';
        if (isset($entry['#refType'])) {
            $refType=$entry['#refType'];
            if ($refType=='linknext') {
                return '<tr><td bgcolor="#ffffe0">Will be loaded by one of the next query entry</td></tr>';
            }
        }

        #    if(isset($entry['#filterDetailByColumn'])) {
        #      $row1.='<tr><td bgcolor="#ffa0a0" colspan="5">#filterDetailByColumn: <b> '.$entry['#filterDetailByColumn'].'</b> #filterDetailField:'.$entry['#filterDetailField'].'</td></tr>';
        #    }
        if (isset($entry['#mastertupleFieldNo'])) {
            $row1.='<tr><td bgcolor="#ffa0a0" colspan="5">#mastertupleFieldNo: <b>'.$entry['#mastertupleFieldNo'].'</b><br/>#detailDatasetName:'.$entry['#detailDatasetName'].'</td></tr>';
        }
        if (isset($entry['@indexes'])) {
            foreach ($entry['@indexes'] as $i=>&$idx) {
                $row1.='<tr><td bgcolor="#eeffff" colspan="5">@index #type:'
                    .$idx['#type']
                    .', name:<b>'.$idx['#name']
                    .'</b> (#keyFieldName:"'.$idx['#keyFieldName'].'",#keyTupleFieldNo:'.$idx['#keyTupleFieldNo'].' )</td></tr>';
            }
        }
        $row1.='<tr><td style="border:solid 4px;" bgcolor="#eeeeee" colspan="5">Dataset schema: <b>'.$dataset['#schema'].'/'.$dataset['#datasetName'].'</b></td></tr>';
        if (!$dataset['@fields']) {
            trigger_error('пусто', E_USER_ERROR);
        }
        foreach ($dataset['@fields'] as $i=>&$field) {
            $kind=(isset($field['#kind'])?$field['#kind']:'text');
            $row2.='<tr valign="top"><td>'
                .'<span title="columnId#">columnId:'.$field['#columnId'].'</span>'
                .(isset($field['#tupleFieldNo'])?'<br/>tupleFieldNo:'.$field['#tupleFieldNo'].'</span>':'<br/>(virtual)')
                .'</td><td>#field:"<b>'.$field['#field'].'</b>"'
                .(((isset($field['#originField'])&&$field['#originField']!==$field['#field'])?'<br/>#originField:'.$field['#originField']:''))
                .'</td><td>'.$kind.'</td>'
                .'<td>'.(isset($field['#label'])?'<i>'.$field['#label'].'</i><br/>':'');

            # Если это лукап-справочник, то он может быть #refType='join' или #refType='linknext'
            if ($kind=='lookup') {
                $refType=isset($field['#refType'])? $field['#refType'] : "";
                if ($refType) {
                    $row2.='Reference type:<font color=green>'.$refType.'</font> ==> <b>'.$field['#ref'].'</b><br/>';
                    $row2.='#refDatasource:"<b>'
                        .(isset($field['#refDatasource'])?$field['#refDatasource']:'').'</b>", ' 
                        .'#refSchema:"<b>'.$field['#refSchema'].'</b>", '
                        .'#refDataset:"<b>'.$field['#refDataset'].'</b>"';
                }
                if (isset($field['#uniqueIndex'])) {
                    $row2.='<br/>'.(isset($field['#uniqueIndex'])?'#uniqueIndex:"<b>'.$field['#uniqueIndex'].'</b>"'
                    :'(Error! No #uniqueIndex!)');
                }
                if (isset($field['#refType'])) {
                    $row2.='<table class="dpd" border=1 cellspacing="0" cellpadding="5">'.self::dumpQueryEntry($field).'</table>';
                }
            } elseif ($kind=='aggregation') {
                # Если это агрегат, то ссылка может быть только удаленной
                $refType=isset($field['#refType'])? $field['#refType'] : "(NO REFTYPE!)";
                $row2.='Reference type:<font color=red>'.$refType.' ==> <b>'.$field['#ref'].'</font></b><br/>'
                    .'#refDatasource:"<b>'.(isset($field['#refDatasource'])?$field['#refDatasource']:'').'</b>", ' 
                    .'#refSchema:"<b>'.$field['#refSchema'].'</b>", '
                    .'#refDataset:"<b>'.$field['#refDataset'].'</b>"'
                    .'<br/>'.(isset($field['#clusterIndex'])?'#clusterIndex:"<b>'.$field['#clusterIndex'].'</b>"':'(Error! No #clusterIndex!)');
                $row2.='<table class="dpd" border=1>'.self::dumpQueryEntry($field).'</table>';
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

    public function pushMessageToLog($entryType,$data)
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
     * @param array $data ['queryName'=>$queryName,'query'=>&$query,'file'=>$file,'line'=>$line]
     */
    public function pushQuery($data)
    {
        $logSection=self::LE_DEBUG_QUERY.'';
        if(!isset($this->logArray[$logSection])){
            $this->logArray[$logSection]=[];
        }
        $this->logArray[$logSection][]=[
            'data'=>'Dumps data query '.$data['id'].' to the data log',
            'time'=>date('Y-m-d H:i:s'),
            'type'=>self::LE_DEBUG_QUERY];
        $data['type']='query';
        $this->dataLogArray[]=&$data;
    }

    public function pushToDataLog($data){

        $this->dataLogArray[]=&$data;
    }

    public function onHTTPEnd()
    {
        print "<div id='debugPane' style='right:20px;position:fixed; bottom:20px; align:right'>
        <button style='padding:8px;   box-shadow: 2px 6px 16px #2c2779; border-radius:8px; background:#3060ff; color:white;' onclick=\"document.getElementById('debugPaneSections').style.display='block'\">Show debug</button>
        </div>
        <div style='display:none;' id='debugPaneSections'>";
        foreach ($this->logArray as $section=>&$SectionArray) {
            print '<h4>'.\doq\tr('log',self::$entryTypeNames[$section.'']).'</h4><table>';
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
                    case self::LE_DEBUG_QUERY: 
                    case self::LE_DEBUG_DATAQUERY:
                        $typeName = 'Debug';
                        if ($category) {
                            $typeName.="[${category}]";
                        }
                        $bgColor="#dddddd";
                        $fw= ""; 
                    break;

                    default:
                        $typeName = 'Error '.$type;
                        $bgColor="#ffb0b0";
                        $fw="font-weight:bold;";
                }

                $text='';
                if (!is_scalar($data)) {
                    $text = '<pre>'.var_export($data, true).'</pre>';
                } else {
                    $text = $data;
                }
                print "<tr bgcolor='${bgColor}' valign='top'><td>${time}</td><td>${typeName}<td>${text}</td><td>${file}</td><td>${line}</td></tr>";

            }
            print "</table>";
        }
        
        /** @var $data ['queryName'=>$queryName,'query'=>&$query,'file'=>$file,'line'=>$line] */
        foreach ($this->dataLogArray as $i=>&$data) {
            $type=$data['type'];
            switch($type){
                case 'query':
                    print "<h4>".$data['queryName'].'</h4>';
                    #$this->dumpQueryEntry($data['query']);
                    $result=[];
                    self::dumpQueryAsHTML($data['query'], $result);
                    foreach($result as $j=>&$s){
                        print $s;
                    }                    
                    break;
                case 'queryString':
                    print '<h4>Query "'.$data['id'].'" dumped in '.$data['file'].' at '. $data['line'].'</h4>';
                    print $data['queryString'];
                    print '<hr>';
                    break;
                case 'indexDump':
                    print '<h4>Indexes filled by '.$data['id'].' dumped in '.$data['file'].' at '. $data['line'].'</h4>';
                    print $data['indexDump'];
                    print '<hr>';
                    break;
            }
        }
        print "</div>";

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
    private $MessagesWasPushed;

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
            $this->MessagesWasPushed=false;
            $this->dataLogFileHandle=fopen($this->targetDataLogFile, 'w');
            $this->targetEnvLogFileHandle=fopen($this->targetEnvLogFile, 'w');
        }
    }

    public function pushMessageToLog($entryType,$data)
    {
        if(!$this->MessagesWasPushed){
            fputs($this->logFileHandle,"[\n");
            $this->MessagesWasPushed=true;
        } else {
            fputs($this->logFileHandle,",\n\n");
        }
        if($entryType & (self::LE_INFO | self::LE_ERROR | self::LE_WARNING | self::LE_DEBUG_INFO)){
            if (!$this->logFileHandle) {
                return;
            }
            $data['type']=$entryType;
            $data['typeName']=self::$entryTypeNames[$entryType];
            $data['time']=date('Y-m-d H:i:s');
            fputs($this->logFileHandle, self::jsonSerialize($data));
        }
    }

    /**
     * @param int $entryType 
     * @param array['queryName'=>$queryName,'query'=>&$data,'file'=>$file,'line'=>$line]
     */
    public function pushQuery($data)
    {
        if (!$this->dataLogFileHandle) {
            return;
        }
        $data['type']=$entryType;
        $data['time']=date('Y-m-d H:i:s');
        
        fputs ($this->dataLogFileHandle, self::jsonSerialize($data));

    }
    public function pushToDataLog($data){
        if (!$this->dataLogFileHandle) {
            return;
        }
        $data['type']='datalog';
        $data['time']=date('Y-m-d H:i:s');
        if (isset($data['queryString'])) {
            fputs($this->dataLogFileHandle, "\n\n\nDataquery:\n");
            fputs($this->dataLogFileHandle, self::jsonSerialize($data));
        }
        if (isset($data['indexDump'])){
            fputs($this->dataLogFileHandle, "\n\n\nIndexes:\n");
            fputs($this->dataLogFileHandle, self::jsonSerialize($data));
        }

    }


    public function onHTTPEnd()
    {
        if ($this->MessagesWasPushed) {
            fputs($this->logFileHandle, "\n]");
        }
        \fclose($this->logFileHandle);
        \fclose($this->dataLogFileHandle);
        \fclose($this->targetEnvLogFileHandle);
    }



}
