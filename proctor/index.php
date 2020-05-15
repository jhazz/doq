<?php



function getJsonDateNow(){
    //$requestText=file_get_contents("php://input");

    //$request=json_decode($requestText, true) ?: [];

    
    list($usec, $sec) = explode(" ", microtime());
    $timestamp=time();
    $msec=round(floatval($usec)*1000);
    
    $now=explode(":",date("Y:n:j:H:i:s:Z",$timestamp));
    $gnow=explode(":",gmdate("Y:n:j:H:i:s",$timestamp));
    print json_encode ([
        'year'=>$now[0], 'month'=>$now[1], 'day'=>$now[2], 
        'h'=>intval($now[3]), 'm'=>intval($now[4]), 
        's'=>intval($now[5]), 'z'=>intval($now[6]),
        'gyear'=>$gnow[0], 'gmonth'=>$gnow[1], 'gday'=>$gnow[2], 
        'gh'=>intval($gnow[3]), 'gm'=>intval($gnow[4]), 'gs'=>intval($gnow[5]), 
        'ms'=>$msec
    ]);
}



function initHTML(){
    ?>
    <html><!DOCTYPE HTML>
    <head>
    <script src='main.js'></script>
    </head>
    <?php
}

function getIndex(){
    initHTML();
    ?>
    <style>
    .droparea{border:dashed 3px green; height:100px; }
    .droparea-allow{border-color:blue;}
    .droparea-deny{border-color:red;}
    </style>
    <div id="comparator"></div>

    <script>
    window.onload=function(){
        proctor.showTimeComparator(document.getElementById("comparator"))
        proctor.dropArea1 = document.getElementById('droparea1')
        proctor.disableDrop=false
        var da=document.createElement('div')
        da.className="droparea"
        da.innerText="Перетяните видеофайл сюда (AVI или MP4)"
        proctor.dropArea1.appendChild(da)

        da.addEventListener('dragover', (event) => {
            event.stopPropagation();
            event.preventDefault();
            da.classList.add('droparea-allow')

            var i,l,f,s='', parts
            l=event.dataTransfer.items.length
            proctor.disableDrop=false
            for(i=0;i<l;i++){
                f=event.dataTransfer.items[i]
                parts=f.type.split('/')
                if(parts[0]!='video'){
                    proctor.disableDrop=true
                }
            }
            da.innerText='Вы пытаетесь положить файл(ы) '+s
            da.classList.remove('droparea-allow')
            da.classList.remove('droparea-deny')
            if(proctor.disableDrop){
                da.classList.add('droparea-deny')
                event.dataTransfer.dropEffect = 'none'
            } else {
                da.classList.add('droparea-allow')
                event.dataTransfer.dropEffect = 'copy'
            }
            
            console.log(event.dataTransfer)
        });

        da.addEventListener('dragleave', (event) => {
            event.stopPropagation();
            event.preventDefault();
            da.classList.remove('droparea-allow')
            da.innerText="Перетяните видеофайл сюда (MP4)"
            event.dataTransfer.dropEffect = 'copy';
        });

        da.addEventListener('drop', (event) => {
            event.stopPropagation();
            event.preventDefault();
            da.classList.remove('droparea-allow')
            da.innerHTML='<p>Загрузка</p>'
            var fsize,sizeName,e,s,i,l,f, fileList = event.dataTransfer.files;
            l=fileList.length
            
            for(i=0;i<l;i++){
                f=fileList[i]
                parts=f.type.split('/')
                if(parts[0]=='video'){
                    e=document.createElement('div')
                    fsize=f.size
                    if(fsize>1024*1024){
                        sizeName=Math.round(f.size/(1024*1024),2)+' Мегабайт'
                    } else if (fsize>1024*1024*1024) {
                        sizeName=Math.round(f.size/(1024*1024*1024),2)+' Гигабайт'
                    } else {
                        sizeName=fsize+' байт'
                    }
                    e.innerHTML='<p>Видео "'+f.name+'"</p>'
                        +'<p><b>длина файла:</b>'+sizeName+'</p>'
                        +'<p><b>дата изменения:</b>'
                        +f.lastModifiedDate.getDate()+'.'+f.lastModifiedDate.getMonth()+'.'+f.lastModifiedDate.getFullYear()+' '
                        +f.lastModifiedDate.getHours()+':'+f.lastModifiedDate.getMinutes()+':'+f.lastModifiedDate.getSeconds()+'</p> '
                    da.appendChild(e)
                    console.log ('Good!')
                }
                console.log(f);
            }
        });
    }


    </script>

    <input type="file" id="file-selector" accept=".jpg, .jpeg, .png">
    <table><tr><td id='droparea1'>
    </td><td></td></tr></table>

    <?php     
}


$routes=[
    'getJsonDateNow'=>'getJsonDateNow',
    '*'=>'getIndex'
];
$action=(isset($_GET['a']))? $_GET['a']: '';
if(isset($routes[$action])){
    $routes[$action]();
} else {
    $routes['*']();
}



?>
