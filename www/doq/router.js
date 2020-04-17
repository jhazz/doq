doq.module('doq.router', [], function(){
/* jshint asi:true, -W100, forin:false, sub:true */

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

function onLoad(){
    executeHashHandlers(window.location.hash)
}

function onHashChange(){
    executeHashHandlers(window.location.hash)
}

function addRouteHandler (routeHashElement,callback){
    routeHandlers[routeHashElement]=callback
}

function removeRouteHandler (routeHashElement){
    if (routeHashElement in routeHandlers) {
        delete routeHandlers[routeHashElement]
    }
}

function init(){
    console.log('router.init')
    window.addEventListener("load", onLoad)
    window.addEventListener("hashchange", onHashChange)
}


return {
    functions:[onHashChange, addRouteHandler, removeRouteHandler, init],
}
})

