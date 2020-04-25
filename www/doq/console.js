/* jshint asi:true, -W100, forin:false, sub:true */
doq.module('doq.console', ['doq.router'], function(){
    var buttonShown=false,
        clientSelector,
        pageSelector,
        apiLoggerURL='../../api/doq/logger.php'
        
    
    
    function upClientsList(){
        var response, i,de,arr,n,
            url= apiLoggerURL+'?action=clients'
            
        doq.log('doq.console', "Читаем список клиентов из "+url)
        doq.postJSON(url, {}, function(e){
            response=e.target.response, de, i, n
            if(!response){
                doq.log('doq.console', "Не прочитался список клиентов "+url)
                return
            }
            clientSelector.innerHTML=''
            if('clients' in response){
                arr=response['clients']
                for (i in arr){
                    n=arr[i]
                    de=document.createElement('option')
                    de.className='tree-list-item'
                    de.value=n
                    de.innerText=n
                    clientSelector.appendChild(de)
                }
            }
        }, 'json');
        
    }
    
    function upPageloadsSelector(){
        var response, i, de, arr, n,
        url= apiLoggerURL+'?action=pageloads'
        
        doq.log('doq.console', "Читаем список загрузок страниц клиента  "+doq.cfg.clientToken+" из "+url)
        doq.postJSON(url, {clientToken:doq.cfg.clientToken}, function(e){
            response=e.target.response
            
            if(!response){
                doq.log('doq.console', "Не прочитался список загрузок страниц из "+url)
                return
            }
            pageloadsSelector.innerHTML=''
            
            de=document.createElement('option')
            de.innerText='Выберите загрузку'
            pageloadsSelector.appendChild(de)
            
            if('pageloadTokens' in response){
                arr=response['pageloadTokens']
                for (i in arr){
                    v=arr[i]
                    de=document.createElement('option')
                    de.className='tree-list-item'
                    de.value=i
                    if(i==doq.cfg.pageloadToken){
                        de.setAttribute('selected','selected')
                    }
                    de.innerText='('+v.time+') '+v.script
                    pageloadsSelector.appendChild(de)
                }
            }
        }, 'json');        
    }
    
    function upPageSelector(){
        var response, i, de, arr, n,
            url= apiLoggerURL+'?action=pages'
            
            doq.log('doq.console', "Читаем список загрузок страниц в рамках "+doq.cfg.pageloadToken+" из "+url)
            doq.postJSON(url, {pageloadToken:doq.cfg.pageloadToken}, function(e){
            response=e.target.response
            
            if(!response){
                doq.log('doq.console', "Не прочитался список страниц из "+url)
                return
            }
            pageSelector.innerHTML=''
            if('pages' in response){
                arr=response['pages']
                for (i in arr){
                    v=arr[i]
                    de=document.createElement('option')
                    de.className='tree-list-item'
                    de.value=i
                    de.innerText=v.script
                    pageSelector.appendChild(de)
                }
            }
        }, 'json');        
    }
    
    function hide(){
        var d=document.getElementById("logger_console"),s;
        if (d){
            d.style.display='none';
        }
    }
    
    function makeConsoleMenu(name, tabs, parentElement){
        var s='',i,mi,radio,label;
        for (i in tabs){
            mi=tabs[i]
            radio=document.createElement('input')
            radio.id=name+'@'+i+'_r'
            radio.setAttribute('type','radio')
            radio.setAttribute('name',name)
            if(!i) 
                radio.setAttribute('checked','1')
            parentElement.appendChild(radio)
            
            label=document.createElement('label')
            label.id=name+'@'+i
            label.innerText=mi.label;
            
            (function(menuItem){
            label.addEventListener('click',function(){
                var r=document.getElementById(this.id+'_r')
                r.checked=true
                if(!!menuItem.onclick){
                    menuItem.onclick(menuItem, this)
                }
                console.log(this.innerText+' '+menuItem.label)
            })
            })(mi)
            parentElement.appendChild(label)
        }
        return s
    }
    
    function show(){
        var s, d=document.getElementById("logger_console"), llw
        if(!d) {
            d=document.createElement('div')
            d.id='doq-console-desk'
            document.body.appendChild(d);
            
            var dt=document.createElement('div');
            dt.innerHTML='<table width="100%" height="100%" cellspacing=0 cellpadding=0 border=1>'+
            '<tr valign="top"><td width="200px">'+
            '<div class="doq-console-menu" id="doq-console-menu1"></div><div id="doq-console-selectors"></div></td>'+
            '<td><div>Right panel'+
            '<span style="color:white; float:right; padding:0px 10px;cursor:pointer" onclick="location.href=\'#logger?do=hidePanel\'">X</span></div>'+
            '<div id="logger_right_window" style="box-sizing: border-box; display:block; float:right; padding:2px; background:#eeeeee; overflow:auto; height:250px; width:80%">'+
            '<div style="border:solid #222222 2px; height:1000px;">kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk<br>kjkljlk1<br>kjkljlk2<br></div>'+
            '</div></td></tr></table>'
            
            d.appendChild(dt);
            var pe=document.getElementById('doq-console-menu1')
            
            makeConsoleMenu('console-left',[
                {label:'Clients',onclick:function(mi, el){alert(mi.label) }},
                {label:'Loads',onclick:function(){}},
                {label:'Pages',onclick:function(){}},
                ],pe)

            llw=document.getElementById('doq-console-selectors')
            clientSelector=document.createElement('select')
            clientSelector.style.width='100%'
            llw.appendChild(clientSelector)
            
            pageloadsSelector=document.createElement('select')
            pageloadsSelector.style.width='100%'
            llw.appendChild(pageloadsSelector)
            
            pageSelector=document.createElement('select')
            pageSelector.style.width='100%'
            pageSelector.setAttribute('size',6)
            
            pageSelector.style.height='100%'
            llw.appendChild(pageSelector)

            // Производим первое чтение
            upClientsList()
            upPageloadsSelector()
            upPageSelector()

        } else {
            d.style.display='block'
        }
    }

    function showTab(tabName){
        switch(tabName){
            case 'errors':
                showConsole()
                document.getElementById("m1_0").checked=true;
                break;
            case 'info':
                showConsole()
                document.getElementById("m1_1").checked=true;
                
                break;
            case 'hide':
                hideConsole()
            }
    }

    function onRoute(params){
        if('tab' in params){
            showTab(params.tab)
        }
        if('do' in params){
            switch(params.do){
                case 'showPanel':showConsole(); break;
                case 'hidePanel':hideConsole(); break;
            }
        }
    }

    function init(){
        showButton()
    }

    function showButton(){
        if(buttonShown)
            return
        
        buttonShown=true
        var b=document.createElement('button')
        b.innerText="Console"
        b.style.position='fixed'
        b.style.bottom='30px'
        b.style.right='30px'
        b.style.backgroundColor='#2020ff'
        b.style.borderRadius='5px'
        b.style.padding='5px'
        b.style.opacity='20%'
        b.style.color='white'
        b.addEventListener('click',show)
        document.body.appendChild(b)
    }
    return {
        functions:[init, hide, show, showButton],
        css:{
            vars:{
                    '@console-menu-bgcolor':'#666688',
            },
            '#doq-console-desk':'position:fixed; height:300px; bottom:0px; left:0px; background:#aaaaaa; border:solid black 1px; border-radius:3px; width:100%; padding:4px; box-sizing:border-box;',
            '.doq-console-menu':'font-family:sans; font-size:9pt; color:#222222; background:@console-menu-bgcolor; ',
            '.doq-console-menu label' : 'margin:0 2px 0 2px; border-radius: 2px 2px 0 0; padding:2px 5px 2px 5px; background:#555555; color:white; ',
            '.doq-console-menu label:hover' : 'color:#ff2222; cursor:pointer;' ,
            '.doq-console-menu input' : 'display:none;' ,
            '.doq-console-menu input:checked + label ':'background:#ffffff; color:#0022aa;'
        }
    }
})



