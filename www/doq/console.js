/* jshint asi:true, -W100, forin:false, sub:true */
doq.module('doq.console', ['doq.router'], function(){
    var buttonShown=false,
        clientSelector,
        pageSelector,
        tabMenus={},
        components={},
        resizeTimeout,
        panelVisible=false,
        consoleDesk,
        panel1,
        panel2,
        panel1menu,
        panel1content,
        panel1Size=300,
        deskHeight=480,
        deskBorderSize=3,
        splitterBorder=1,
        splitterHandleSize=5,
        startDragX,
        dragStartPageX,
        dragStartX,
        dragMode,
        splitter1,
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
    

    
    function hide(){
        var d=document.getElementById("logger_console"),s;
        if (d){
            d.style.display='none';
        }
        panelVisible=false
    }
    
    function makeConsoleMenu(parentElement, name, items, istabs){
        var i,mi,radio,label,tabMenu,menuLine=document.createElement('div'), active
        if(istabs) tabMenu=tabMenus[name]={}
        menuLine.className='doq-console-menu'
        for (i in items){
            mi=items[i]
            label=document.createElement('div')
            label.style.display='inline-block'
            label.className='doq-console-item-unselected'
            label.innerText=mi.label
            void(function(menuItem,tm){
                label.addEventListener('click',function(){
                    if(!!menuItem.onclick){
                        if(menuItem.onclick(menuItem, this)===false)
                            return
                    }
                    if(tm!==undefined){
                        if(tm.active){
                            tm.active.className='doq-console-item-unselected'
                        }
                        tm.active=this
                    }
                    this.className='doq-console-item-selected'
                })
            })(mi,tabMenu)
            menuLine.appendChild(label)
        }
        parentElement.appendChild(menuLine)
        return menuLine
    }
    
    function arrange(){
        if (!panelVisible) return

        var docHeight=document.body.clientHeight, 
            docWidth=document.body.clientWidth
            w=docWidth-deskBorderSize*2,
            h=deskHeight-deskBorderSize*2
        
        if(consoleDesk.offsetHeight!=deskHeight){
           consoleDesk.style.height=deskHeight+'px'
        }
        panel1.style.width=panel1Size+'px'
        panel2.style.width=(w-panel1Size-splitterBorder)+'px'
        panel2.style.transform='translateX('+(panel1Size+splitterBorder)+'px)'
        splitter1.style.height=panel2.style.height=panel1.style.height=h+'px'
        splitter1.style.transform='translateX('+(panel1Size+splitterBorder-(splitterHandleSize-1)/2)+'px)'
        var h1=panel1menu.offsetHeight
        panel1content.style.height=(h-h1)+'px'
    }
    
    
    function show(){
        var s, llw, dt
        if(!!consoleDesk) {
            consoleDesk.style.display='block'
            return
        }
        window.addEventListener('resize',function onWindowResize(){
            if(resizeTimeout) 
                window.clearTimeout(resizeTimeout)
            resizeTimeout=window.setTimeout(arrange,66)
        })
        consoleDesk=document.createElement('div')
        consoleDesk.id='doq-console-desk'
        consoleDesk.style.overflow='auto'
        panel1=document.createElement('div')
        s=panel1.style
        s.position='absolute'
        panel2=document.createElement('div')
        s=panel2.style
        s.position='absolute'
        s.background='white'
        consoleDesk.appendChild(panel1)
        consoleDesk.appendChild(panel2)
        panel1menu=makeConsoleMenu(panel1, 'console-left',[
            {label:'Clients',onclick:putClientSelector},
            {label:'Loads',onclick:putPageloadsSelector},
            {label:'Pages',onclick:putPageSelector},
            ],true)
        panel1content=document.createElement('div')
        s=panel1content.style
        s.background=doq.css.vars['@console-bgcolor']
        s.overflow='auto'
        panel1.appendChild(panel1content)
        
        splitter1=document.createElement('div')
        s=splitter1.style
        s.width=splitterHandleSize+'px'
        s.position='absolute'
        s.cursor='col-resize'
        consoleDesk.appendChild(splitter1)
        splitter1.addEventListener('mousedown',splitter1mousedown)
        splitter1.addEventListener('selectstart',function(e){console.log('Select start'); e.preventDefault();} )
        
        document.body.appendChild(consoleDesk)
        panelVisible=true
        arrange()
    }

    function putClientSelector(mi, el){
        panel1content.innerHTML=''
        clientSelector=document.createElement('select')
        clientSelector.style.width='100%'
        panel1content.appendChild(clientSelector)
        upClientsList()
        return true
    }

    function putPageloadsSelector(mi, el){
        panel1content.innerHTML=''
        pageloadsSelector=document.createElement('select')
        pageloadsSelector.style.width='100%'
        panel1content.appendChild(pageloadsSelector)
        upPageloadsSelector()
        return true
    }

    function putPageSelector(mi, el){
        panel1content.innerHTML=''
        pageSelector=document.createElement('div')
        pageSelector.id='pageSelector'
        panel1content.appendChild(pageSelector)
        upPageSelector()
        return true
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
            if('pages' in response){
                placeTable(pageSelector,
                    'pageSelect',
                    'Client:'+doq.cfg.clientToken+'Pages under Pageload Token: '+doq.cfg.pageloadToken, 
                    response['pages'],
                    [
                    {header:'Script', size:'200', field:'script'},
                    {header:'Time', size:'50', field:'time'},
                    {header:'URL', size:'300', field:'url'},
                    ]
                )
            }
        }, 'json');
    }



    function placeTable(panelEl, componentId, caption, items, columns){
        var panelId=panelEl.id, i, cell, item, cnt,w, table, component
        if(!panelId) {
            console.error('console.placeTable to panel without id')
            return
        }
        
        if(componentId in components){
            component=components[componentId]
            if(!!component.clean){
                component.clean.call(components[name])
            }
        }

        var tableElement=document.createElement('table'),
            thead = tableElement.createTHead(),
            row = thead.insertRow()
        
        tableElement.className='doq-console-table'
        tableElement.setAttribute('cellspacing',1)
        
        table=components[componentId]={
            el:tableElement,
            componentId:componentId,
            items:items,
            columns:columns,
            selectedRowEl:null,
            panelEl:panelEl,
            destroy:function(){
                this.panelEl.removeChild(this.el)
                delete this.items
                delete this.columns
            }
        }
        
        for (i in columns) {
            cell = document.createElement('th');
            cell.innerText=columns[i].header
            row.appendChild(cell);
        }
        
        for (i in items) {
            item=items[i]
            row=tableElement.insertRow();
            (function(table){
                row.addEventListener('click',function(e){
                    if(!!table.selectedRowEl)
                        table.selectedRowEl.classList.remove('doq-console-row-selected')
                    this.classList.add('doq-console-row-selected')
                    table.selectedRowEl=this
            })
            
            })(table)
            
            for(j in columns){
                cell=row.insertCell()
                w=columns[j].size
                if(!!w) cell.width=w
                cnt=document.createElement('div')
                cnt.style.width=w
                cnt.style.overflow='hidden'
                cnt.style.whiteSpace='nowrap'
                cnt.innerText=item[columns[j].field]
                cell.appendChild(cnt)
            }
        }
        if(!!caption){
            var p=document.createElement('p')
            p.className='doq-console-p'
            p.innerText=caption
            panelEl.appendChild(p)
        }

        panelEl.appendChild(tableElement)
        
    }

    function splitter1mousedown(e){
        document.body.addEventListener('mousemove',splitter1mousemove)
        document.body.addEventListener('mouseup',splitter1mouseup)
        document.body.addEventListener('mouseleave',splitter1mouseup)
        dragStartPageX=e.pageX
        dragStartX=panel1Size
        dragMode=1
    }
    
    function splitter1mousemove(e){
        var newPageX=e.pageX
        if(dragMode==1){
            if(Math.abs(newPageX-dragStartPageX)<5) 
                return
            dragMode=2
        }
        if(dragMode==2){
            panel1Size=dragStartX+(newPageX-dragStartPageX)
            if(panel1Size<50)panel1Size=50
            if(panel1Size>600)panel1Size=600
            arrange()
        }
    }
    
    function splitter1mouseup(e){
        dragMode=0
        document.body.removeEventListener('mousemove',splitter1mousemove)
        document.body.removeEventListener('mouseup',splitter1mouseup)
    }
    
    function init(){
        showButton()
    }

    function showButton(){
        if(buttonShown)
            return
        
        buttonShown=true
        var b=document.createElement('button')
        b.className='doq-console-openbutton'
        b.innerText="Console"
        b.addEventListener('click',show)
        document.body.appendChild(b)
    }
    
    return {
        functions:[init, hide, show, showButton],
        css:{
            vars:{
            '@console-menu-bgcolor':'#666666',
            '@console-border-color':'#ddddee',
            '@console-bgcolor':'#eeeeee',
            '@console-bgcolor-inverse':'#777777',
            '@console-text-size':'8pt',
            '@console-text-color':'#555555',
            '@console-label-color':'#777777',
            '@console-text-inverse':'#eeeeee',
            '@console-text-font':'sans'
            },
            '#doq-console-desk':'font-family:@console-text-font; font-size:@console-text-size; position:fixed; height:300px; bottom:0px; left:0px; background:@console-bgcolor; border-radius:3px; width:100%; padding:3px; overflow:hidden; box-sizing:border-box;',
            '.doq-console-menu':'background:@console-menu-bgcolor; white-space: nowrap; user-select: none;',
            '.doq-console-menu > div':'padding:2pt 5pt',
            '.doq-console-item-unselected':'color:@console-text-inverse',
            '.doq-console-item-unselected:hover':'background:@console-text-color',
            '.doq-console-item-selected':'background:@console-bgcolor; color:@console-text-color;',
            '.doq-console-table th': 'font-family: @console-text-font; font-size:@console-text-size; background:@console-border-color; font-weight:normal;',
            '.doq-console-table td': 'font-family: @console-text-font; font-size:@console-text-size; border-bottom:solid 1px @console-border-color;',
            
            '.doq-console-openbutton':'font-family:sans; font-size:7pt; position:fixed; bottom:20pt; right:20pt; background-color:#2020ff; border-radius:5px; padding:5px; opacity:20%; color:white;',
            '.doq-console-p':'color:@console-label-color; padding:3pt 3pt; margin:2pt 0pt; border-bottom:solid 1px @console-border-color;',
            '.doq-console-row-selected':'background:@console-bgcolor-inverse; color:@console-text-inverse;'
        }
    }
})



