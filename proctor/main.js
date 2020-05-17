/* jshint asi:true, -W100, forin:false, sub:true */

(function (_global,namespace){
    var fileUploadings={}

    function ajax(url, onload, method, jsonText, responseType){
        var xhr = new XMLHttpRequest()
        xhr.onload=onload
        xhr.open((method)?method:'POST', url)
        xhr.responseType = (responseType)?responseType:'json'
        xhr.setRequestHeader('Content-type', 'application/json; charset=utf-8')
        if(jsonText) 
            xhr.send(jsonText) 
        else 
            xhr.send()
        return xhr
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
        ajax('?a=getJsonDateNow',function(e){
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
        })
        
    }
    
    _global[namespace]={}
    var i,f,exports=[showTimeComparator, ajax]
    for(i in exports) f=exports[i],_global[namespace][f.name]=f
})(window,'proctor')