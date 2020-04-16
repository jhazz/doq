/* jshint asi:true, -W100, forin:false, sub:true */
doq.module('doq.logger', ['doq.router'], function(){

    function hideLoggerPanel(){
        var d=document.getElementById("debug_console"),s;
        if (d){
            d.style.display='none';
        }
    }

    function showLoggerPanel(){
        var d=document.getElementById("logger_console"),s;
        if(!d) {
            d=document.createElement('div')
            d.id="logger_console"
            s=d.style
            s.position='fixed'
            s.height='300px'
            s.bottom='0px'
            s.left='0px'
            s.background='#aaaaaa'
            s.border='solid black 1px'
            s.borderRadius='3px'
            s.width='100%'
            s.padding='4px'
            s.boxSizing='border-box'

            document.body.appendChild(d);
            var dt=document.createElement('div');
            dt.innerHTML='<table width="100%" height="100%" cellspacing=0 cellpadding=0 border=1>'+
            '<tr><td width="200px"><div id="logger_left_window" style="box-sizing: border-box;  background:#ff8080; float:right; width:20%; height:250px">Список</div></td>'+
            '<td><div class="menu-tabs">'+
            makeTabs('m1',[{href:'#logger?tab=errors',label:'Errors'}, {href:'#logger?tab=info',label:'Info'}] )+
            '<span style="color:white; float:right; padding:0px 10px;cursor:pointer" onclick="location.href=\'#logger?do=hidePanel\'">X</span></div>'+
            '<div id="logger_right_window" style="box-sizing: border-box; display:block; float:right; padding:2px; background:#eeeeee; overflow:auto; height:250px; width:80%">'+
            '<div style="border:solid #222222 2px; height:1000px;">kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk1<br>kjkljlk2<br></div>'+
            '</div></td></tr></table>'
            d.appendChild(dt);

            // Производим первое чтение

            // 
            postJSON('../../api/doq/logger.php?action=browse',{},function(e){
                var r,lrw=document.getElementById('logger_right_window'),llw=document.getElementById('logger_left_window')
                //lrw.innerText=
                r=e.target.response;
                var de;
                if(!r){
                    console.log("Не прочиталось")
                    return
                }
                for (var pageTokenNames in r['pageTokens']){
                    de=document.createElement('div')
                    de.className='tree-list-item'
                    de.innerText=pageTokenNames
                    llw.appendChild(de)
                }
            }, 'json');

            /*
            var dd=document.createElement('div');
            d.appendChild(dd);
            dd.style.border='solid #ffffff 2px';
            dd.style.height='100%'*/
        } else {
            d.style.display='block'
        }
    }

    function showTab(tabName){
        switch(tabName){
            case 'errors':
                showLoggerPanel()
                document.getElementById("m1_0").checked=true;
                break;
            case 'info':
                showLoggerPanel()
                document.getElementById("m1_1").checked=true;
                
                break;
            case 'hide':
                hideLoggerPanel()
            }
    }

    function onroute(params){
        if('tab' in params){
            showTab(params.tab)
        }
        if('do' in params){
            switch(params.do){
                case 'showPanel':showLoggerPanel(); break;
                case 'hidePanel':hideLoggerPanel(); break;
            }
        }
    }

    function init(){
        doq.router.addRouteHandler('#logger',onroute)
    }

    return {
        functions:[hideLoggerPanel,showLoggerPanel],
        init:init
    }
})



