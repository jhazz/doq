/* jshint asi:true, -W100, forin:false, sub:true */

doq.module('proctor.main', ['doq.router'], function(){
    var fileUploadings={}
    var parameters={
        loaded:false, isDownloading:false, isUploading:false,
        data:{},
        schema:{
            testeeNo:{type:'int',size:12,default:0},
            testeeEmail:{type:'email',size:40}
        }
    };


    function addFormEl(targetEl, params){
        var e=document.createElement('div'),e2,t,e3,e4,ctrl={}
        e.className='form-e'
        targetEl.appendChild(e)
        
        if(!!params.label){
            e2=document.createElement('div')
            e2.className=params.labelCl
            e2.innerText=params.label
            e.appendChild(e2)
        }
        if(!!params.ctrltype){
            t=params.ctrltype
            ctrl.type=t
            ctrl.el=e
            if(t=='submit'){
                e2=document.createElement('input')
                e2.setAttribute('type','submit')
                e2.value=params.buttonLabel
            } else if(t=='text') {
                e2=document.createElement('input')
                e2.setAttribute('type','text')
                ctrl.inputEl=e2
            } else {
                e2=document.createElement(params.ctrltype)
            }
            e.appendChild(e2)
        }
        
        if(!!params.hint){
            e3=document.createElement('div')
            e3.className='hint'
            e3.innerText=params.hint
            e.appendChild(e3)
            ctrl.hintEl=e3
        }
        
        e4=document.createElement('div')
        e4.className='warning'
        e4.innerText=''
        e.appendChild(e4)
        ctrl.warnEl=e4
        
        if(!!params.bindParam) {
            e2.id=params.bindParam
            bindParam(ctrl, params.bindParam, 'value')
        }

        
    }

    function setParamValue(name, value, source, ts){
        var d
        if(!(name in parameters.data)){
            d=parameters.data[name]={}
        } else {
            d=parameters.data[name]
        }
        if(!source){
            source='ui'
        }
        if(ts==undefined){
            ts=new Date().getTime()
        }
        d[source]={value:value, ts:ts}
    }
    
    function bindParam(ctrl, name, targetAttr){
        var d,errorMsg
        if(!(name in parameters.schema)){
            alert('Надо добавить параметр в схему '+name)
            return
        }
        
        if(!(name in parameters.data)){
            d=parameters.data[name]={schema:parameters.schema[name],bindCtrls:[],ui:{value:'',error:false, ts:0}}
        } else {
            d=parameters.data[name]
        }
        d.bindCtrls.push(ctrl)
        
        if (targetAttr=='value'){
            ctrl.inputEl.addEventListener('change',function(e){
                var v=e.target.value,errorMsg=''
                switch(d.schema.type){
                    case 'int': 
                        iv=parseInt(v)
                        if((''+iv)!=v)
                            errorMsg='Неправильное число!'
                        break
                    case 'email': 
                        if(/^[^@]+@[^@]+\.[^@]+$/.exec(v)==null)
                            errorMsg='Неправильный email адрес!'
                        break
                }
                if(errorMsg!=''){
                    ctrl.inputEl.classList.add ('error')
                    ctrl.warnEl.innerText=errorMsg
                } else {
                    ctrl.inputEl.classList.remove('error')
                    ctrl.warnEl.innerText=''
                    d.ui.value=v
                    d.ui.ts=new Date().getTime()
                }
            })
        }
    }
    
    function downloadParameters(){
        if(!parameter.sIsLoading){
            doq.sendJSON('?a=downloadParameters',function(e){
                var r,i,ts=new Date().getTime()
                r=e.target.response
                if(!!r){
                    for(i in r){
                        setParamValue(i,r,'origin',ts)
                    }
                }
            })
        }
    }

    
    function getParamValue(paramName){
        if(!parameters.loaded){
            downloadParameters()
            return ''
        }
        return 
    }
    
    function renderRegistrationForm(){
        var place, e,container=document.getElementById('main-container')
        container.innerHTML=''
        place=document.createElement('form')
        place.className='large'
        container.appendChild(place)
        
        addFormEl(place, {labelCl:'form-head', label:'Регистрационные данные'})
        addFormEl(place, {labelCl:'form-label', label:'Ваш номер абитуриента/испытуемого', hint:'Этот номер вы должны получить в Личном кабинете абитуриента', bindParam:'testeeNo', ctrltype:'text', })
        addFormEl(place, {labelCl:'form-label', label:'Ваш e-mail', ctrltype:'text', hint:'Электронный адрес, который использовался при активации Личного кабинета абитуриента', bindParam:'testeeEmail'})
        addFormEl(place, {buttonLabel:'Сохранить', ctrltype:'submit'})
    }
    
    function renderTestTimeChecking(){
        var e,container=document.getElementById('main-container')
        showTimeComparator(document.getElementById("comparator"))
        
    }
    
    function renderTestFilePlaceholders(){
        var e,container=document.getElementById('main-container')
        
        proctor.dropArea1 = document.getElementById('droparea1')
        proctor.disableDrop=false
        var da=document.createElement('div')
        da.className="droparea"
        da.innerText="Перетяните видеофайл сюда (AVI или MP4)"
        proctor.dropArea1.appendChild(da)

        da.addEventListener('dragover', function (event) {
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

        da.addEventListener('dragleave', function(event) {
            event.stopPropagation();
            event.preventDefault();
            da.classList.remove('droparea-allow')
            da.innerText="Перетяните видеофайл сюда (MP4)"
            event.dataTransfer.dropEffect = 'copy';
        });

        da.addEventListener('drop', function (event) {
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



    checkingResults={
    }
    
    function startChecking(){
        
        var checkLog=document.getElementById('checkLog'),
            tab,row,c1,c2,c3,c4,c5,c6,с7,c8,c9,cookiesParts,i,acookie, firstClientId
        tab=document.createElement('table')
        tab.className='tab'
        checkLog.appendChild(tab)
        row=tab.insertRow()
        c1=row.insertCell(); c1.innerText='Шаг 1'; c1.className='step'
        c2=row.insertCell(); c2.innerText='Проверка JAVASCRIPT'; c2.setAttribute('width','60%')
        c3=row.insertCell(); c3.innerText='СООТВЕТСТВУЕТ'; c3.className='good'

        row=tab.insertRow()
        c1=row.insertCell(); c1.innerText='Шаг 2'; c1.className='step'
        c2=row.insertCell(); c2.innerText='Проверка авторизации (cookies)'
        c3=row.insertCell(); c3.innerText='ИДЕТ ПРОВЕРКА'
        checkedGood=false
        
        if(!!document.cookie){
            cookiesParts=document.cookie.split(';')
            for(i in cookiesParts){
                acookie=cookiesParts[i].split('=')
                if (acookie[0]=='PROCTOR_CLIENTID') {
                    firstClientId=acookie[1].trim()
                    break
                }
            }
            if(!!firstClientId){
                sendJSON('?a=echo',{cnonce:''+Math.random()},function(e){
                    var r=e.target.response
                    if((!!r)&&(r['PROCTOR_CLIENTID']!='')&&(r['PROCTOR_CLIENTID']===firstClientId)){
                        checkedGood=true
                    }
                    if(checkedGood){
                        c3.className='good'
                        c3.innerText='СООТВЕТСТВУЕТ'
                    } else {
                        c3.className='bad'
                        if(!r) {
                            c3.innerText='ПРОБЛЕМА С СЕРВЕРОМ'
                        } else {
                            c3.innerText='COOKIES НЕ РАБОТАЮТ'
                        }
                    }
                    
                    
                })
            } else {
                c3.className='bad'
                c3.innerText='ПРОБЛЕМА С COOKIES'
                c2.innerHTML+='<br><br>В памяти браузера не обнаружены cookie системы прокторинга. Возможно, на сервере неправильно настроена конфигурация cookie domain!'
            }
        } else {
            c3.className='bad'
            c3.innerText='COOKIES ЗАБЛОКИРОВАНЫ'
            c2.innerHTML+='<br><br>Для работы необходимо настроить в браузере "Разрешение сохранять cookie"'
        }
        
        row=tab.insertRow()
        c4=row.insertCell(); c4.innerText='Шаг 3'; c4.className='step'
        c5=row.insertCell(); c5.innerText='Проверка времени'
        c6=row.insertCell(); c6.innerText='ИДЕТ ПРОВЕРКА'

        var reqtime = new Date(), 
            phase0=reqtime.getTime()
        
        doq.sendJSON('echotime.php',{},function(e){
            if (!!e.target.response){
                var errStr='',
                    localTime=new Date(), 
                    local=extractLocalTime(localTime),
                    response=e.target.response,
                    phase1=e.target.response.st,
                    phase2=localTime.getTime(),
                    diff=Math.round((phase2+phase0)/2 - phase1)/1000,
                    lsecs=Math.round((phase2-phase0)/2)/1000,lmins=Math.floor(lsecs/60),
                    diffstr,v,mins,secs,hours,lagstr
                //var glocalTime =new Date(local.gyear, local.gmonth, local.gday, local.gh, local.gm, local.gs, local.ms),

                diffstr=(diff>0)?'спешат на ':'отстают на '
                secs=Math.abs(diff)
                
                if(secs<60){
                    diffstr+=secs +' секунд'
                } else if((secs>=60)&&(secs<3600)){
                    secs=Math.ceil(secs)
                    mins=Math.floor(secs/60)
                    secs=secs-mins*60
                    diffstr+=mins+' минут и '+secs +' секунд'
                    if(mins>2){
                        errStr='Рекомендуется настроить часы на компьютере! Разница более 2 минут'
                    }
                } else {
                    errStr='Рекомендуется настроить часы на компьютере! Возможно, часовой пояс. Разница составляет более часа'
                    hours=Math.floor(secs/(3600))
                    secs=secs-hours*3600
                    mins=Math.floor(secs/60)
                    secs=secs-mins*60
                    diffstr+=hours+' часов '+mins+' минут и '+secs +' секунд'
                }
                
                lagstr=''
                if(lmins==0){
                    lagstr+=lsecs+ ' секунд'
                    var high=Math.ceil(Math.abs(lsecs))
                    if(high<9){
                        lagstr+=', что меньше '+high+' секунд и является нормой'
                    }
                    
                } else {
                    lsecs=lsecs-mins*60
                    lagstr+=lmins+' минут и '+secs +' секунд. <span style="color:red">У вас очень медленное соединение!</span>'
                }

                c5.innerHTML+='<br>Ваш часовой пояс:+'+Math.floor(local.z/(60*60),1)
                    +'<br><br>Часы на компьютере '+local.h+':'+local.m+':'+local.s
                    +'<br>'+diffstr
                    +'<br><br>Задержка соединения: '+lagstr
                if(errStr){
                    c6.innerText=errStr
                    c6.className='bad'
                } else {
                    c6.className='good'
                    c6.innerText='СООТВЕТСТВУЕТ'
                }
            }
        })
        
        row=tab.insertRow()
        c7=row.insertCell(); c7.innerText='Шаг 4'; c7.className='step'
        c8=row.insertCell(); c8.innerText='Проверка отправки файлов'
        c9=row.insertCell(); c9.innerText='ИДЕТ ПРОВЕРКА'
        
    }
    
    function extractLocalTime(localTime){
        return {
            gyear:localTime.getUTCFullYear(), gmonth:localTime.getUTCMonth()+1, gday:localTime.getUTCDate(),
            gh:localTime.getUTCHours(), gm:localTime.getUTCMinutes(), gs:localTime.getUTCSeconds(), 
            year:localTime.getFullYear(), month:localTime.getMonth()+1, day:localTime.getDate(),
            h:localTime.getHours(), m:localTime.getMinutes(), s:localTime.getSeconds(), ms:localTime.getMilliseconds(),
            z:(-localTime.getTimezoneOffset())*60
        }
    }
    

    // https://developer.mozilla.org/ru/docs/Web/API/File/Using_files_from_web_applications
    // https://gist.github.com/alediaferia/cfb3a7503039f9278381

    

    function showTimeComparator (targetEl){
        doq.sendJSON('?a=getJsonDateNow',function(e){
            if (!!e.target.response){
                var localTime=new Date(), local=extractLocalTime(localTime)
                targetEl.innerHTML=''
                var row, c, t=document.createElement('table'), 
                    response=e.target.response
                    
                t.setAttribute('border','1')
                targetEl.appendChild(t)
                row=t.insertRow()

                c=row.insertCell()
                c.innerText='Global server time'
                c=row.insertCell()
                c.innerText='Year:'+response.gyear
                c=row.insertCell()
                c.innerText='Month:'+response.gmonth
                c=row.insertCell()
                c.innerText='Day:'+response.gday
                c=row.insertCell()
                c.innerText='Hour:'+response.gh
                c=row.insertCell()
                c.innerText='Mins:'+response.gm
                c=row.insertCell()
                c.innerText='Sec:'+response.gs
                
                c=row.insertCell()
                c.innerText='Local server time'
                c=row.insertCell()
                c.innerText='Year:'+response.year
                c=row.insertCell()
                c.innerText='Month:'+response.month
                c=row.insertCell()
                c.innerText='Day:'+response.day
                c=row.insertCell()
                c.innerText='Hour:'+response.h
                c=row.insertCell()
                c.innerText='Mins:'+response.m
                c=row.insertCell()
                c.innerText='Sec:'+response.s
                c=row.insertCell()
                c.innerText='Msec:'+response.ms
                c=row.insertCell()
                c.innerText='GMT offset:'+response.z


                row=t.insertRow()
                c=row.insertCell()
                c.innerText='Global client time'
                c=row.insertCell()
                c.innerText='Year:'+local.gyear
                c=row.insertCell()
                c.innerText='Month:'+local.gmonth
                c=row.insertCell()
                c.innerText='Day:'+local.gday
                c=row.insertCell()
                c.innerText='Hour:'+local.gh
                c=row.insertCell()
                c.innerText='Mins:'+local.gm
                c=row.insertCell()
                c.innerText='Sec:'+local.gs
                
                c=row.insertCell()
                c.innerText='Local client time'
                c=row.insertCell()
                c.innerText='Year:'+local.year
                c=row.insertCell()
                c.innerText='Month:'+local.month
                c=row.insertCell()
                c.innerText='Day:'+local.day
                c=row.insertCell()
                c.innerText='Hour:'+local.h
                c=row.insertCell()
                c.innerText='Mins:'+local.m
                c=row.insertCell()
                c.innerText='Sec:'+local.s
                c=row.insertCell()
                c.innerText='Msec:'+local.ms
                c=row.insertCell()
                c.innerText='GMT offset:'+local.z
                
                c=document.createElement('div')
                targetEl.appendChild(c)
                var gserverTime=new Date(response.gyear, response.gmonth, response.gday, response.gh, response.gm, response.gs, response.ms)
                var glocalTime =new Date(local.gyear, local.gmonth, local.gday, local.gh, local.gm, local.gs, local.ms)
                var diff=glocalTime-gserverTime
                
                c.innerHTML='<b>local</b>:'+glocalTime+'<br><b>server</b>:'+gserverTime+'<br>local-server: '+(glocalTime-gserverTime)+' msec'
            }
        },'json')
        
    }
    return {
        functions:[showTimeComparator,  renderRegistrationForm, startChecking],
        css:{
            vars:{
            '@menu-bgcolor':'#333355;'
            },
//            '#page-wrap':'display:table; height:100%;width:100%;',
//            '#main-container':'display:table-cell; padding:10pt; box-sizing:border-box; text-align:center; vertical-align:middle;',
//            '#topmenu-title':'display:table-cell; width:100%; height:50pt; background:@menu-bgcolor; color:white; font-family:sans; font-size:12pt; padding:6pt;',
//            '#topmenu-info':'float:right; width:25%; height:24pt; overflow:hidden; font-size:8pt;',

            '.droparea':'border:dashed 3px green; height:150px; width:100%;',
            '.droparea-allow':'border-color:blue;',
            '.droparea-deny':'border-color:red;',
            '.form-place':'display:inline-block; background:red; width:400px;',

            
            'form.large':'font-family:sans,arial; display:inline-block;border-radius:3pt; background:#f8f8ff; border:solid 1px #ddddff; width:400pt; padding:8pt;',
            'form.large > .form-e' : 'padding:10pt; text-align:left;',
            'form.large > .form-e .form-head': 'font-size:24pt;',
            'form.large > .form-e .form-label': 'font-size:16pt;',
            'form.large > .form-e input[type=text]': 'border:solid 2px #202080; border-radius:4pt; background:#fefeff;font-size:16pt; padding:5pt;',
            'form.large > .form-e input.error': ' border:solid 2px red !important;',
            'form.large > .form-e input[type=submit]': 'border-radius:4pt; background:#ccffcc; padding:5pt 10pt;font-size:16pt;',
            'form.large > .form-e .hint': 'color:#666666; font-size:8pt;',
            'form.large > .form-e .warning': 'color:red; font-size:8pt; height:16pt;',
            
        }
    }
    
    
})