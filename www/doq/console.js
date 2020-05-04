/* jshint asi:true, -W100, forin:false, sub:true */
doq.module('doq.console', ['doq.router'], function(){
    var buttonShown=false,
        resizeTimeout,
        drawer={appends:[],height:480,visible:false,borderSize:3,el:null,
            panel1:{width:350, className:'doq-console-panel',
                panel1menu:{
                    render:renderTabMenu,
                    items:[
                        {label:'Clients',activate:putPanelContent, id:'clients',  params:{panel:'panel1', update:updateClientsSelector}},
                        {label:'Loads',  activate:putPanelContent, id:'pageloads',params:{panel:'panel1', update:updatePageloadsSelector}},
                        {label:'Pages',  activate:putPanelContent, id:'pages',    params:{panel:'panel1', update:updatePageSelector}},
                    ]
                },
            }, 
            panel2:{className:'doq-console-panel',
                panel2menu:{
                    render:renderTabMenu,
                    items:[
                        {label:'PHP logs', activate:putPanelContent, id:'phplogs',params:{panel:'panel2',update:updatePHPLogs}},
                        {label:'Environment', activate:putPanelContent, id:'env', onupdate:updatePageloadsSelector},
                        {label:'PHP Data queries', activate:putPanelContent, id:'phpdata', onupdate:updatePageSelector},
                        {label:'JS logs', activate:putPanelContent, id:'jslogs', onupdate:updatePageSelector},
                        {label:'Settings', activate:putPanelContent, id:'close', onupdate:updatePageSelector}
                    ]
                },
            },
            splitter1:{className:'doq-console-vsplitter', mousedown:splitter1mousedown},
            splitter2:{className:'doq-console-vsplitter', mousedown:splitter1mousedown},
            details:{width:350, className:'doq-console-panel'},
            controls:{className:'doq-console-topbar',
                btnExpand:{className:'doq-console-drawerctrls',text:'[ ]'},
                btnClose: {click:hide, className:'doq-console-drawerctrls',text:'X'}
            }

        },
        splitterBorder=1,
        splitterHandleSize=7,
        drag={mode:0},
        apiLoggerURL='../../api/doq/console.php',
        debugScope={
            clientToken:null,
            pageloadToken:null,
            pageToken:null
        }

    
    function hide(){
        if (!!drawer.el){
            drawer.el.style.display='none';
        }
        drawer.visible=false
    }
    
    function arrange(){
        if (!drawer.visible) return

        var p1=drawer.panel1, s1=p1.el.style,
            p2=drawer.panel2, s2=p2.el.style,
            cs1=p1.el.style,
            cs2=p2.el.style,
            p1w=p1.width,
            docHeight=document.body.clientHeight, 
            docWidth=document.body.clientWidth
            dw=docWidth-drawer.borderSize*2,
            dh=drawer.height-drawer.borderSize*2,
            h1=p1.panel1menu.el.offsetHeight
        
        if(drawer.el.offsetHeight!=drawer.height){
           drawer.el.style.height=drawer.height+'px'
        }
        s1.width=p1w+'px'
        s2.width=(dw-p1w-splitterBorder)+'px'
        s2.transform='translateX('+(p1w+splitterBorder)+'px)'
        drawer.splitter1.el.style.height=p2.el.style.height=p1.el.style.height=dh+'px'
        drawer.splitter1.el.style.transform='translateX('+(p1w + splitterBorder-(splitterHandleSize-1)/2)+'px)'
        cs1.height = cs2.height=(dh-h1)+'px'
        
        if(!!drawer.splitter2.el){
            var d=drawer.details, dw=d.width, cs3=d.style
            drawer.splitter2.el.style.transform='translate('+(docWidth-dw+splitterBorder-(splitterHandleSize-1)/2)+'px,'+h1+'px)'
            d.el.style.transform='translate('+(docWidth-dw)+'px,'+h1+'px)'
            d.el.style.width=dw+'px'
            drawer.splitter2.el.style.height=d.el.style.height=(dh-h1)+'px'
        }
    }
    
    function makeControl(parent,childId){
        var ctrl=parent[childId], handlers=['mousedown','click'],i,j
        ctrl.id=childId
        if((!!ctrl.el) && (!!ctrl.destroy))
            ctrl.destroy.call(ctrl)
        
        if(!!ctrl.render){
            ctrl.render.call(ctrl)
        } else {
            ctrl.el=document.createElement('div')
            ctrl.el.id=childId
            ctrl.el.className=ctrl.className
            if(!!ctrl.text)
                ctrl.el.innerText=ctrl.text
            for (i in handlers){
                if(handlers[i] in ctrl){
                    ctrl.el.addEventListener(handlers[i],ctrl[handlers[i]])
                }
            }
        }
        drawer.appends.push([parent,ctrl])
        return ctrl
    }
    
    function appendControls(){
        for(var i;i=drawer.appends.shift();){
            i[0].el.appendChild(i[1].el)
        }
    }
    
    function show(){
        drawer.visible=true
        if(!!drawer.el) {
            drawer.el.style.display='block'
            return
        }
        window.addEventListener('resize',function onWindowResize(){
            if(resizeTimeout) 
                window.clearTimeout(resizeTimeout)
            resizeTimeout=window.setTimeout(arrange,66)
        })
        drawer.el=document.createElement('div')
        drawer.el.id='doq-console-desk'
        makeControl(drawer,'panel1')
        makeControl(drawer.panel1,'panel1menu')
        makeControl(drawer,'panel2')
        makeControl(drawer.panel2,'panel2menu')
        makeControl(drawer,'splitter1')
        var c=makeControl(drawer,'controls')
        makeControl(c,'btnExpand')
        makeControl(c,'btnClose')
        appendControls()
        document.body.appendChild(drawer.el)
        drawer.visible=true
        arrange()
        drawer.panel1.panel1menu.select('pages')
    }

    function putPanelContent(){
        // this: menuitem
        var panel=drawer[this.params.panel], child
        if(panel.updating)
            return
        
        panel.updating=true
        child=document.createElement('div')
        child.id=this.id
        if(this.params.update){
            this.params.update.call(this, panel, child)
        }
        if(!!panel.currentChild)
            panel.el.replaceChild(child, panel.currentChild)
        else 
            panel.el.appendChild(child)
        panel.currentChild=child
        return true
    }

    function showDetail(viewerFunction, rowData){
        if(!drawer.splitter2.el){
            makeControl(drawer,'details')
            makeControl(drawer,'splitter2')
            appendControls()
        }
        arrange()
        viewerFunction(drawer.details, rowData)
    }
    
    function phpDetailViewer(el, row){
        drawer.details.el.innerHTML="DETAILS: "+row.data
    }
    
    function updatePHPLogs(panel, targetEl){ //this - menuitem
        var response,
            url= apiLoggerURL+'?action=phplogs',
            pageToken=debugScope.pageToken
            
        if (!pageToken){
            return
        }
        doq.postJSON(url, {
                pageToken:debugScope.pageToken, 
                clientToken:debugScope.clientToken, 
                pageloadToken:debugScope.pageloadToken
                }, function(e){
            response=e.target.response
            if(!response){
                doq.log('doq.console', "Не прочитался лог из "+url+' по странице '+pageToken, doq.C.L_ERROR)
            } else if('entries' in response){
                renderTable(targetEl,{
                    id:'logPhplog',
                    caption:'<b>PHP log of: </b>'+response.url,
                    rows:response.entries,
                    columns:[
                        {header:'Type', size:'40', field:'typeName'},
                        {header:'Category', size:'40', field:'category'},
                        {header:'Data', size:'250', field:'data'},
                        {header:'Source file', size:'250', field:'file'},
                        {header:'Line', size:'40', field:'line'},
                        {header:'Time', size:'80', field:'utime', formatter:utimeFormat},
                        {header:'TypeNo', size:'30', field:'type'}
                    ],
                    onrowclass:function(rowdata){
                        if(rowData.type==1) 
                            return 'error'
                        if(rowData.type==2)
                            return 'warning'
                    },
                    onclick:function(rowData){
                        showDetail(phpDetailViewer, rowData)
                    }
                    
                })
            }
            panel.updating=false
        }, 'json');                
        
    }
    
    function utimeFormat(v){
        var t=v.split(' '), 
            dt=parseFloat(t[1])+parseFloat(t[0]),
            d=new Date(dt*1000)
        return d.getHours()+':'+d.getMinutes()+':'+d.getSeconds()+'.'+d.getMilliseconds() 
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
                        drawer.panel1.panel1menu.select('pageloads')
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
                        drawer.panel1.panel1menu.select('pages')
                    }
                    //sortBy:'timestamp_float'
                })
            }
            panel.updating=false
        }, 'json');        
    }
    

    
    function updatePageSelector(panel, targetEl){ // this-menuitem
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
                    onclick:function(rowData){
                        debugScope.pageToken=rowData.pageToken
                        debugScope.clientToken=clientToken
                        debugScope.pageloadToken=pageloadToken
                        drawer.panel2.panel2menu.select('phplogs')
                    }//,sortBy:'timestamp_float'
                })
            }
            panel.updating=false
        }, 'json');
    }

    /*function renderBtn(){
        this.el=document.createElement('div')
        this.el.className=this.className //
        this.el.innerText=this.text
    }*/
    
    function renderTabMenu(){
        var i,item,menuItem, radio,label
        if(!!this.destroy)
            this.destroy()
        this.el=document.createElement('div')
        this.el.className='doq-console-menu'
        this.select=function(menuItemId){
            menuId=this.id
            var sitem=this.items[menuItemId]
            if(!!sitem){
                if(!!this.activeItemEl)
                    this.activeItemEl.classList.remove('selected')
                this.activeItemEl=sitem.labelEl
                sitem.labelEl.classList.add('selected')
                if(sitem.activate)
                    sitem.activate.call(sitem)
            }
        }
        
        for (i in this.items){
            item=this.items[i]
            label=document.createElement('div')
            label.style.display='inline-block'
            label.className='doq-console-menuitem'
            label.innerText=item.label
            menuItem={labelEl:label, id:item.id, activeItemEl:null}
            if (!!item.activate) 
                menuItem.activate = item.activate
            if (!!item.params) 
                menuItem.params=item.params
            
            void(function(amenu, aitemId){
                label.addEventListener('click',function(){
                    amenu.select.call(amenu, aitemId)
                })
            })(this, menuItem.id)
            this.el.appendChild(label)
            this.items[menuItem.id]=menuItem 
        }
        return this
    }



    function renderTable (targetEl,params){
        var i, cnt, cell, item, inner ,w, table, s, st, addCl,col,
            table={
                el:document.createElement('table'),
                id:params.id,
                rows:params.rows,
                columns:params.columns,
                selectedRowEl:null,
                destroy:function(){
                    delete this.rows
                    delete this.columns
                }
            },
            thead = table.el.createTHead(),
            row = thead.insertRow()
        
        table.el.className='doq-console-table'
        cnt=params.columns.length
        for (i=0;i<cnt;i++) {
            cell = document.createElement('th');
            w=params.columns[i].size
            inner=document.createElement('div')
            st=inner.style
            st.width=w
            st.overflow='hidden'
            st.whiteSpace='nowrap'
            inner.innerText=params.columns[i].header
            cell.appendChild(inner)
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
            row=table.el.insertRow();
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
                col=params.columns[j]
                cell=row.insertCell()
                w=col.size
                if(!!w) 
                    cell.width=w
                inner=document.createElement('div')
                st=inner.style
                st.width=w+'px'
                st.overflow='hidden'
                st.whiteSpace='nowrap'
                s=rowData[col.field]
                if(s==undefined){
                    inner.innerHTML='&nbsp;-'
                    cell.classList.add('disabled')
                } else {
                    if(!!col.formatter)
                        s=col.formatter(s)
                    inner.innerText=s
                }
                cell.appendChild(inner)
            }
        }
        
        if(!!params.caption){
            var p=document.createElement('p')
            p.className='doq-console-p'
            p.innerHTML=params.caption
            targetEl.appendChild(p)
        }
        targetEl.appendChild(table.el)
    }

    function splitter2mousedown(e){
        document.body.addEventListener('mousemove',splitter2mousemove)
        document.body.addEventListener('mouseup',splitter2mouseup)
        document.body.addEventListener('mouseleave',splitter2mouseup)
        drag.startPageX=e.pageX
        drag.startObjX=drawer.panel1.width
        drag.mode=1
        drag.target=e.target
        
    }

    function splitter1mousedown(e){
        document.body.addEventListener('mousemove',splitter1mousemove)
        document.body.addEventListener('mouseup',splitter1mouseup)
        document.body.addEventListener('mouseleave',splitter1mouseup)
        drag.startPageX=e.pageX
        drag.startObjX=drawer.panel1.width
        drag.mode=1
        drag.target=e.target
        
        function splitter1mousemove(e){
            var newPageX=e.pageX, 
                maxWidth=document.body.clientWidth
                
            if(drag.mode==1){
                if(Math.abs(newPageX-drag.startPageX)<5) 
                    return
                if(drag.target.id=='splitter1')
                    drag.mode=2
                if(drag.target.id=='splitter2')
                    drag.mode=3
            }
            
            if(drag.mode==2){
                drawer.panel1.width=drag.startObjX+(newPageX-drag.startPageX)
                if(drawer.panel1.width<50)
                    drawer.panel1.width=50
                if(drawer.panel1.width>(maxWidth-100)) 
                    drawer.panel1.width=maxWidth-100
                arrange()
            }
            
            if(drag.mode==3){
                drawer.details.width=drag.startObjX-(newPageX-drag.startPageX)
                if(drawer.details.width<50)
                    drawer.details.width=50
                if(drawer.details.width>(maxWidth-100)) 
                    drawer.details.width=maxWidth-100
                arrange()
                
            }
        }
        
        function splitter1mouseup(e){
            drag.mode=0
            document.body.removeEventListener('mousemove',splitter1mousemove)
            document.body.removeEventListener('mouseup',splitter1mouseup)
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
            '@console-text-font':'sans',
            '@console-error-color':'#dd2222',
            '@console-error-bgcolor':'#ffeeee',
            '@console-splitter-handleSize':splitterHandleSize+'px'
            },
            '#doq-console-desk':'font-family:@console-text-font; font-size:@console-text-size; position:fixed; height:300px; bottom:0px; left:0px; background:@console-bgcolor; border-radius:3px; width:100%; padding:3px; overflow:hidden; box-sizing:border-box;',
            '.doq-console-panel':'position:absolute;background:@console-bgcolor; box-sizing:border-box; overflow:auto;',
            '.doq-console-vsplitter':'width:@console-splitter-handleSize; position:absolute; cursor:col-resize; user-select:none',
            '.doq-console-vsplitter:hover':'background:@console-bgcolor-hilight; opacity:20%;',
            '.doq-console-menu':'background:@console-menu-bgcolor; white-space: nowrap; user-select: none; padding:2pt 2pt 0 2pt;',
            '.doq-console-menuitem':'padding:2pt 5pt; color:@console-text-inverse',
            '.doq-console-menuitem:hover':'background:@console-text-color',
            '.doq-console-menuitem.selected': 'background:@console-bgcolor; color:@console-text-color;',
            '.doq-console-table':'border-spacing:1;',
            '.doq-console-table th': 'font-family: @console-text-font; font-size:@console-text-size; background:@console-border-color; font-weight:normal;',
            '.doq-console-table tr': 'cursor:default;font-family: @console-text-font; font-size:@console-text-size; color:@console-text-color;',
            '.doq-console-table tr > td': ' border-bottom:solid 1px @console-border-color;',
            '.doq-console-table .disabled':'background:@console-border-color;',
            '.doq-console-table tr.error':'background:@console-error-bgcolor;color:@console-error-color;',
            '.doq-console-table tr:hover': 'background:@console-bgcolor-hilight; color:@console-text-color',
            '.doq-console-table tr.selected': 'background:@console-bgcolor-inverse; color:@console-text-inverse;',
            '.doq-console-table tr.active': 'background:@console-bgcolor-hilight; color:@console-text-color;',
            '.doq-console-drawerctrls':'display:inline-block; margin:2pt; overflow:hidden; width:10pt; height:10pt; font-size:8pt; color:@console-text-inverse; text-align:center; background-color:@console-bgcolor-inverse;',
            '.doq-console-drawerctrls:hover':'background-color:@console-bgcolor-hilight; color:@console-text-color;',
            '.doq-console-topbar':'position:absolute; right:2pt; background: @console-menu-bgcolor;',
            
            '.doq-console-openbutton':'font-family:sans; font-size:7pt; position:fixed; bottom:20pt; right:20pt; background-color:#2020ff; border-radius:5px; padding:5px; opacity:20%; color:white;',
            '.doq-console-p':'color:@console-label-color; padding:3pt 3pt; margin:2pt 0pt; border-bottom:solid 1px @console-border-color;',
            '.doq-console-row-selected':'background:@console-bgcolor-inverse; color:@console-text-inverse;'
        }
    }
})



