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
        panel1={width:350}, panel2={},
        deskHeight=480,
        deskBorderSize=3,
        splitterBorder=1,
        splitterHandleSize=7,
        startDragX,
        dragStartPageX,
        dragStartX,
        dragMode,
        splitter1,
        apiLoggerURL='../../api/doq/logger.php',
        debugScope={
            clientToken:null,
            pageloadToken:null,
            pageToken:null
        }
        

    
    function hide(){
        var d=document.getElementById("logger_console"),s;
        if (d){
            d.style.display='none';
        }
        panelVisible=false
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
        panel1.el.style.width=panel1.width+'px'
        panel2.el.style.width=(w-panel1.width-splitterBorder)+'px'
        panel2.el.style.transform='translateX('+(panel1.width+splitterBorder)+'px)'
        
        splitter1.style.height=panel2.el.style.height=panel1.el.style.height=h+'px'
        splitter1.style.transform='translateX('+(panel1.width+splitterBorder-(splitterHandleSize-1)/2)+'px)'
        var h1=panel1.tabMenu.menuLine.offsetHeight
        panel1.contentEl.style.height=(h-h1)+'px'
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
        panel1.el=document.createElement('div')
        s=panel1.el.style
        s.position='absolute'
        panel2.el=document.createElement('div')
        s=panel2.el.style
        s.position='absolute'
        s.background='white'
        consoleDesk.appendChild(panel1.el)
        consoleDesk.appendChild(panel2.el)
        
        panel1.tabMenu=renderTabMenu(panel1.el, {
            id:'panel1menu',
            items:[
                {label:'Clients', onselect:putSelector, id:'clients',onupdate:updateClientsSelector},
                {label:'Loads', onselect:putSelector, id:'pageloads', onupdate:updatePageloadsSelector},
                {label:'Pages', onselect:putSelector, id:'pages', onupdate:updatePageSelector},
            ]}
        )
        panel1.contentEl=document.createElement('div')
        s=panel1.contentEl.style
        s.background=doq.css.vars['@console-bgcolor']
        s.overflow='auto'
        panel1.el.appendChild(panel1.contentEl)
        
        splitter1=document.createElement('div')
        s=splitter1.style
        s.width=splitterHandleSize+'px'
        s.position='absolute'
        s.cursor='col-resize'
        s.userSelect='none'
        consoleDesk.appendChild(splitter1)
        splitter1.addEventListener('mousedown',splitter1mousedown)
        document.body.appendChild(consoleDesk)
        panelVisible=true
        arrange()
    }

    function putSelector(){
        var panel=panel1, child
        if(panel.updating)
            return
        panel.updating=true
        child=document.createElement('div')
        child.id=this.id
        if(this.onupdate){
            this.onupdate.call(this, panel, child)
        }
        if(!!panel.currentChild)
            panel.contentEl.replaceChild(child, panel.currentChild)
        else 
            panel.contentEl.appendChild(child)
        panel.currentChild=child
        return true
    }

    function updateClientsSelector(panel, targetEl){ //this - menuitem
        var response, i,de,arr,n, 
            clientToken=(debugScope.clientToken==null)? doq.cfg.clientToken: debugScope.clientToken,
            url= apiLoggerURL+'?action=clients'
            
        doq.log('doq.console', "Читаем список клиентов из "+url)
        doq.postJSON(url, {}, function(e){
            response=e.target.response, de, i, n
            if(!response){
                doq.log('doq.console', "Не прочитался список клиентов "+url)
            } else if('clients' in response){
                renderTable(targetEl,{
                    id:'loadSelect',
                    caption:'All clients',
                    rows:response.clients,
                    columns:[
                        {header:'Client token', size:'100', field:'clientToken'},
                        {header:'First attempt', size:'100', field:'date'},
                    ],
                    onrowclass:function(rowdata){
                        if(rowData.clientToken == doq.cfg.clientToken) 
                            return 'active'
                        if(rowData.clientToken == debugScope.clientToken)
                            return 'selected'
                    },
                    onclick:function(rowData){
                        debugScope.clientToken=rowData.clientToken
                        components['panel1menu'].select('pageloads')
                    }
                    
                })
            }
            panel.updating=false
        }, 'json');
    }


    function updatePageloadsSelector(panel, targetEl){
        var response, i, de, arr, n, 
        clientToken=(debugScope.clientToken==null)?doq.cfg.clientToken: debugScope.clientToken,
        url= apiLoggerURL+'?action=pageloads'
        
        doq.log('doq.console', "Читаем список загрузок клиента "+clientToken+" из "+url)
        doq.postJSON(url, {clientToken:clientToken}, function(e){
            response=e.target.response
            if(!response){
                doq.log('doq.console', "Не прочитался список загрузок из "+url, doq.C.L_ERROR)
            } else if('pageloadTokens' in response){
                renderTable(targetEl,{
                    id:'loadSelect',
                    caption:'<b>Client: </b>'+response.clientToken,
                    rows:response.pageloadTokens,
                    columns:[
                        {header:'Pageload token', size:'140', field:'pageloadToken'},
                        {header:'Script', size:'200', field:'script'},
                        {header:'Date', size:'120', field:'date'},
                        {header:'Timestamp', size:'100', field:'timestamp_float'}
                    ],
                    onrowclass:function(rowdata){
                        if(rowData.pageloadToken == doq.cfg.pageloadToken) 
                            return 'active'
                        if(rowData.pageloadToken == debugScope.pageloadToken)
                            return 'selected'
                    },
                    onclick:function(rowData){
                        debugScope.pageloadToken=rowData.pageloadToken
                        components['panel1menu'].select('pages')
                    }
                    //sortBy:'timestamp_float'
                })
            }
            panel.updating=false
        }, 'json');        
    }
    

    function updatePageSelector(panel, targetEl){
        var response, i, de, arr, n, 
            clientToken,pageloadToken, url= apiLoggerURL+'?action=pages'
            
        pageloadToken=debugScope.pageloadToken
        clientToken= debugScope.clientToken
        if(!pageloadToken) {
            pageloadToken=doq.cfg.pageloadToken
            clientToken=doq.cfg.clientToken
        }

        doq.log('doq.console', "Читаем список страниц по загрузке "+pageloadToken+" из "+url)
        doq.postJSON(url, {clientToken:clientToken, pageloadToken:pageloadToken}, function(e){
            response=e.target.response
            if(!response){
                doq.log('doq.console', "Не прочитался список страниц из "+url)
            } else if('pages' in response){
                renderTable(targetEl,{
                    id:'pageSelect',
                    caption:'<b>Client:&nbsp;</b>'+response.clientToken+' <b>Pageload:&nbsp;</b>'+response.pageloadToken,
                    rows:response.pages,
                    columns:[
                        {header:'Script', size:'200', field:'script'},
                        {header:'Time', size:'50', field:'time'},
                        {header:'URL', size:'300', field:'url'},
                        {header:'Timestamp', size:'100', field:'timestamp_float'}
                    ],
                    sortBy:'timestamp_float'
                })
            }
            panel.updating=false
        }, 'json');
    }

    
    function renderTabMenu(targetEl, params){
        var i,paramItem,menuItem, radio,label,tabMenu,
            componentId=params.id
        
        if(!componentId){
            doq.log('doq.console','renderTabMenu called without component id in params',doq.C.L_ERROR)
            return
        }
        if(componentId in components){
            component=components[componentId]
            if(!!component.destroy){
                component.destroy.call(component)
            }
        }

        tabMenu=components[componentId]={id:componentId}
        tabMenu.menuLine=document.createElement('div')
        tabMenu.menuLine.className='doq-console-menu'
        tabMenu.items={}
        tabMenu.select=function(menuItemId){
            menuId=this.id
            var item=this.items[menuItemId]
            if(!!item){
                if(!!this.activeItemEl){
                    this.activeItemEl.classList.remove('selected')
                }
                this.activeItemEl=item.labelEl
                item.labelEl.classList.add('selected')
                if(item.onselect){
                    item.onselect.call(item)
                }
            }
        }
        
        for (i in params.items){
            paramItem=params.items[i]
            label=document.createElement('div')
            label.style.display='inline-block'
            label.className='doq-console-menuitem'
            label.innerText=paramItem.label
            menuItem={labelEl:label, id:paramItem.id, activeItemEl:null}
            if (!!paramItem.onselect) 
                menuItem.onselect = paramItem.onselect
            if (!!paramItem.onupdate) 
                menuItem.onupdate= paramItem.onupdate
            
            void(function(amenu, aitemId){
                label.addEventListener('click',function(){
                    amenu.select.call(amenu, aitemId)
                    
                    /*
                    if(!!menuItem.onclick){
                        if(menuItem.onclick(menuItem, this)===false)
                            return
                    }
                    if(tm!==undefined){
                        if(tm.active){
                            tm.active.className='doq-console-menuitem-unselected'
                        }
                        tm.active=this
                    }
                    this.className='doq-console-menuitem-selected'
                    */
                })
            })(tabMenu, menuItem.id)
            tabMenu.menuLine.appendChild(label)
            tabMenu.items[menuItem.id]=menuItem
            
        }
        targetEl.appendChild(tabMenu.menuLine)
        return tabMenu
    }



    function renderTable (targetEl,params){
        var i, cnt, cell, item, inner ,w, table, component, 
            addCl, componentId=params.id
        if(!componentId){
            doq.log('doq.console','renderTable called without component id in params',doq.C.L_ERROR)
            return
        }
            
        if(componentId in components){
            component=components[componentId]
            if(!!component.destroy){
                component.destroy.call(component)
            }
        }

        var tableElement=document.createElement('table'),
            thead = tableElement.createTHead(),
            row = thead.insertRow()
        
        tableElement.className='doq-console-table'
        tableElement.setAttribute('cellspacing',1)
        
        table=components[componentId]={
            el:tableElement,
            id:componentId,
            rows:params.rows,
            columns:params.columns,
            selectedRowEl:null,
            parentEl:targetEl,
            destroy:function(){
                this.parentEl.removeChild(this.el)
                delete this.rows
                delete this.columns
            }
        }
        
        cnt=params.columns.length
        for (i=0;i<cnt;i++) {
            cell = document.createElement('th');
            cell.innerText=params.columns[i].header
            row.appendChild(cell);
        }
        
        cnt=params.rows.length
        
        if(!!params.sortBy){
            params.rows.sort(function(arow1, arow2){
                return arow1[params.sortBy] - arow2[params.sortBy]
            })
        }
        
        for (i=0;i<cnt;i++) {
            rowData=params.rows[i]
            row=tableElement.insertRow();
            (function(atable,arowData){
                row.addEventListener('click',function(e){
                    if(!!atable.selectedRowEl)
                        atable.selectedRowEl.classList.remove('selected')
                    this.classList.add('selected')
                    atable.selectedRowEl=this
                    if(params.onclick)
                        params.onclick(arowData)
                })
            })(table, rowData)
            
            if(!!params.onrowclass){
                addCl=params.onrowclass(rowData)
                if(!!addCl)
                    row.classList.add(addCl)
            }
                
            for(j in params.columns){
                cell=row.insertCell()
                w=params.columns[j].size
                if(!!w) cell.width=w
                inner=document.createElement('div')
                inner.style.width=w
                inner.style.overflow='hidden'
                inner.style.whiteSpace='nowrap'
                inner.innerText=rowData[params.columns[j].field]
                cell.appendChild(inner)
            }
        }
        
        if(!!params.caption){
            var p=document.createElement('p')
            p.className='doq-console-p'
            p.innerHTML=params.caption
            targetEl.appendChild(p)
        }
        
        targetEl.appendChild(tableElement)
                
        
    }


    function splitter1mousedown(e){
        document.body.addEventListener('mousemove',splitter1mousemove)
        document.body.addEventListener('mouseup',splitter1mouseup)
        document.body.addEventListener('mouseleave',splitter1mouseup)
        dragStartPageX=e.pageX
        dragStartX=panel1.width
        dragMode=1
    }
    
    function splitter1mousemove(e){
        var newPageX=e.pageX, maxWidth=document.body.clientWidth
        if(dragMode==1){
            if(Math.abs(newPageX-dragStartPageX)<5) 
                return
            dragMode=2
        }
        
        if(dragMode==2){
            panel1.width=dragStartX+(newPageX-dragStartPageX)
            if(panel1.width<50)panel1.width=50
            if(panel1.width>(maxWidth-100)) panel1.width=maxWidth-100
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
            '@console-bgcolor-hilight':'#ffffff',
            '@console-text-size':'8pt',
            '@console-text-color':'#555555',
            '@console-label-color':'#777777',
            '@console-text-inverse':'#eeeeee',
            '@console-text-font':'sans'
            },
            '#doq-console-desk':'font-family:@console-text-font; font-size:@console-text-size; position:fixed; height:300px; bottom:0px; left:0px; background:@console-bgcolor; border-radius:3px; width:100%; padding:3px; overflow:hidden; box-sizing:border-box;',
            '.doq-console-menu':'background:@console-menu-bgcolor; white-space: nowrap; user-select: none;',
            '.doq-console-menu > div':'padding:2pt 5pt',
            '.doq-console-menuitem':'color:@console-text-inverse',
            '.doq-console-menuitem:hover':'background:@console-text-color',
            '.doq-console-menuitem.selected': 'background:@console-bgcolor; color:@console-text-color;',
            '.doq-console-table th': 'font-family: @console-text-font; font-size:@console-text-size; background:@console-border-color; font-weight:normal;',
            '.doq-console-table tr': 'cursor:default;font-family: @console-text-font; font-size:@console-text-size; border-bottom:solid 1px @console-border-color; color:@console-text-color;',
            '.doq-console-table tr:hover': 'background:@console-bgcolor-hilight; color:@console-text-color',
            '.doq-console-table tr.selected': 'background:@console-bgcolor-inverse; color:@console-text-inverse;',
            '.doq-console-table tr.active': 'background:@console-bgcolor-hilight; color:@console-text-color;',
            
            '.doq-console-openbutton':'font-family:sans; font-size:7pt; position:fixed; bottom:20pt; right:20pt; background-color:#2020ff; border-radius:5px; padding:5px; opacity:20%; color:white;',
            '.doq-console-p':'color:@console-label-color; padding:3pt 3pt; margin:2pt 0pt; border-bottom:solid 1px @console-border-color;',
            '.doq-console-row-selected':'background:@console-bgcolor-inverse; color:@console-text-inverse;'
        }
    }
})



