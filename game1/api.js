(function(exports){
    
function sendJSON(url, json, onload, responseType, method){
    if (!responseType){
        responseType='json'
    }
    var xhr = new XMLHttpRequest(), stringify=JSON.stringify;
    
    if(!method)
        method='POST'
    
    xhr.open(method, url)
    xhr.responseType = responseType
    xhr.setRequestHeader('Content-type', 'application/json; charset=utf-8')
    if(typeof json=='string')
        xhr.send(json)
    else 
        xhr.send(stringify(json))
        
    xhr.onload=function (progress){
        if(progress.target.status!==200){
            return onload({error:'Ошибка подключения '+progress.target.status, url:url},true)
        }
        if(progress.target.response===null) {
            return onload({error:'Ошибка обработки ответа от сервера', url:url, json},true)
        } 
        if('error' in progress.target.response){
            return onload(progress.target.response,true)
        }
        return onload(progress.target.response,false)
        
    };
    xhr.onerror=function (response){return onload(response.target,true)};
    
    return xhr
}

function sendRequestForText(){
    location.href='#requestTabs?do=showResponse'
    document.getElementById("response_area").innerText="Please wait";
    var xhr=doq.sendJSON('?a=json_demo1_post',document.getElementById("request_area").innerText,
        function(){
            document.getElementById("response_area").innerText=this.response;
        },'text')
}

exports.sendJSON=sendJSON
}(window));
