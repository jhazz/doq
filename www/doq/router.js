/* jshint asi:true, -W100, forin:false, sub:true */
(function(_global,moduleName)
{
if(!_global.doq)
    _global.doq={modules:{}}
if(!(moduleName in _global.doq.modules)){
    _global.doq.modules[moduleName]={}
}
var exports=_global.doq.modules[moduleName]
var routeHandlers={}

function executeHashHandlers(){
    var p1, p2, part, hashLeast=window.location.hash,handlerName, params, i
    do {
        p1=hashLeast.indexOf('#')
        //debugger;
        if(p1!=-1){
            p2=hashLeast.indexOf('#',p1+1)
            if(p2!==-1){
                part=hashLeast.substring(p1,p2)
                hashLeast=hashLeast.substring(p2)
            } else {
                part=hashLeast.substring(p1)
                hashLeast=''
            }
            elements=part.split('?')
            handlerName=elements[0]
            if(handlerName in routeHandlers) {
                params={}
                if(elements.length==2){
                    paramElements=elements[1].split('&')
                    for(i in paramElements){
                        param=paramElements[i].split('=')
                        if(param.length==2){
                            params[param[0]]=param[1]
                        }
                    }
                }
                routeHandlers[handlerName](params)
            }
        } 
    } while(p1!=-1)
}

function onload(){
    executeHashHandlers(window.location.hash)
}

function onhashchange(){
    executeHashHandlers(window.location.hash)
}

function registerRouteHandler (routeHashElement,callback){
    routeHandlers[routeHashElement]=callback
}

function unregisterRouteHandler (routeHashElement){
    if (routeHashElement in routeHandlers) {
        delete routeHandlers[routeHashElement]
    }
}

window.addEventListener("load", onload)
window.addEventListener("hashchange", onhashchange)

exports.onhashchange=onhashchange
exports.registerRouteHandler=registerRouteHandler
exports.unregisterRouteHandler=unregisterRouteHandler
})(window,'router')

