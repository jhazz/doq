/* jshint asi:true, -W100, forin:false, sub:true */
doq.module('doq.console', ['doq.router'], function(){
    var buttonShown=false,
        resizeTimeout,
        drawer={appends:[],height:200,visible:false,borderSize:3,el:null,
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
                        {label:'Data queries', activate:putPanelContent, id:'queries', params:{panel:'panel2',update:updateDatalogs}},
                        {label:'Environment', activate:putPanelContent, id:'env', onupdate:updatePageloadsSelector},
                        {label:'JS logs', activate:putPanelContent, id:'jslogs', onupdate:updatePageSelector},
                        {label:'Settings', activate:putPanelContent, id:'close', onupdate:updatePageSelector}
                    ]
                },
            },
            splitter1:{className:'doq-console-vsplitter', mousedown:splitter1mousedown, wire:{className:'doq-console-vsplitterw'}},
            splitter2:{className:'doq-console-vsplitter', mousedown:splitter1mousedown, wire:{className:'doq-console-vsplitterw'}},
            controls:{className:'doq-console-topbar',
                //btnExpand:{className:'doq-console-drawerctrls',text:'[ ]'},
                btnClose: {click:hide, className:'doq-console-drawerctrls',text:'X'}
            },
            panel3:{width:350, className:'doq-console-panel',
                bar:{className:'doq-console-menu',
                    btnClose: {click:hidePanel3, className:'doq-console-drawerctrls',text:'X'}
                },
                place:{className:'doq-console-scrollbox'}
            }

        },
        splitterBorder=1,
        splitterHandleSize=7,
        splitterHandleHalfSize=3,
        drag={mode:0},
        apiLoggerURL='../../api/doq/console.php',
        debugScope={
            clientToken:null,
            pageloadToken:null,
            pageToken:null
        }




    function arrange(){
        if (!drawer.visible) return

        var p1=drawer.panel1, p1s=p1.el.style,
            p2=drawer.panel2, p2s=p2.el.style,
            p1w=p1.width,
            docHeight=document.body.clientHeight, 
            docWidth=document.body.clientWidth
            dw=docWidth-drawer.borderSize*2,
            dh=drawer.height-drawer.borderSize*2,
            h1=p1.panel1menu.el.offsetHeight
        
        if(drawer.el.offsetHeight!=drawer.height){
           drawer.el.style.height=drawer.height+'px'
        }
        p1s.width=p1w+'px'
        p2s.width=(dw-p1w-splitterBorder)+'px'
        if(p2.currentChild)
            p2.currentChild.style.width='100%'
        p2s.transform='translateX('+(p1w+splitterBorder)+'px)'
        drawer.splitter1.el.style.height=p2.el.style.height=p1.el.style.height=dh+'px'
        drawer.splitter1.el.style.transform='translateX('+(p1w - splitterHandleHalfSize)+'px)'
        p1s.height = p2s.height=dh+'px'
        
        if(drawer.panel3.visible){
            var p3=drawer.panel3, p3w=p3.width, p3s=p3.el.style
            drawer.splitter2.el.style.transform='translate('+(dw-p3w-splitterHandleHalfSize)+'px,'+h1+'px)'
            p3s.transform='translate('+(dw-p3w)+'px,'+h1+'px)'
            p3s.width=p3w+'px'
            drawer.splitter2.el.style.height=p3s.height=(dh-h1)+'px'
            if(p2.currentChild)
                p2.currentChild.style.width=(dw-p1w-p3w-splitterBorder*2)+'px'
                
            drawer.panel3.place.el.style.height=(dh-h1-p3.bar.el.offsetHeight-2)+'px'
        }
    }
    
    
    function hide(){
        if (!!drawer.el){
            drawer.el.style.display='none';
        }
        drawer.visible=false
    }
    

    
    function put(parent,childId){
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
        put(drawer,'panel1')
        put(drawer.panel1,'panel1menu')
        put(drawer,'panel2')
        put(drawer.panel2,'panel2menu')
        put(drawer,'splitter1')
        put(drawer.splitter1,'wire')
        var c=put(drawer,'controls')
        //put(c,'btnExpand')
        put(c,'btnClose')
        appendControls()
        document.body.appendChild(drawer.el)
        drawer.visible=true
        arrange()
        drawer.panel1.panel1menu.select('pages')
    }

    
    //@this menuItem
    function putPanelContent(){
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

    function showPanel3(viewerFunction, rowData){
        if(!drawer.splitter2.el){
            put(drawer,'panel3')
            put(drawer.panel3,'bar')
            put(drawer.panel3.bar,'btnClose')
            put(drawer.panel3,'place')
            put(drawer,'splitter2')
            put(drawer.splitter2,'wire')
            appendControls()
        } else {
            drawer.splitter2.el.style.display=drawer.panel3.el.style.display='block'
        }
        viewerFunction(drawer.panel3, rowData)
        drawer.panel3.visible=true
        arrange()
    }
    function hidePanel3(){
        if(drawer.panel3.visible){
            drawer.splitter2.el.style.display='none'
            drawer.panel3.el.style.display='none'
            drawer.panel3.visible=false
            arrange()
        }
    }
    
    function renderParams(targetEl, params,schema){
        var i,se,label,entry,v,item
        for (i=0;i<schema.length;i++){
            se=schema[i]
            if(se.id in params){
                v=params[se.id]
                entry=document.createElement('div')
                label=document.createElement('div')
                label.innerText=se.label
                label.className='doq-console-forml'
                
                item=document.createElement('div')
                item.innerText=v
                item.className='doq-console-formi'
                
                entry.appendChild(label)
                entry.appendChild(item)
                targetEl.appendChild(entry)
            }
            
        }
    }
    function detailViewDatalog(el,row){
        console.log('WELCOM!')
    }
    
    function detailViewPhplog(el, row){
        var placeEl=drawer.panel3.place.el, 
            f=document.createDocumentFragment(), 
            c=document.createElement('div'),fc
        placeEl.style.height='90%'
        c.className='doq-console-form'
        renderParams(c, row, [
            {label:'Type',id:'typeName'}, 
            {label:'Message',id:'data'}, 
            {label:'Category', id:'category'},
            {label:'Source file',id:'file'}, 
            {label:'at line', id:'line'},
            {label:'Occur time', id:'utime'}
            ])
        f.appendChild(c)
        
        fc=placeEl.firstChild
        if(!fc)
            placeEl.appendChild(f)
        else
            placeEl.replaceChild(f,fc)
        
    }
    
    function updateDatalogs(panel, targetEl){ //this - menuitem
        var response,
            url= apiLoggerURL+'?action=datalog',
            pageToken=debugScope.pageToken
        if (!pageToken) 
            return
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
                    id:'dataLog',
                    caption:'<b>Data log of: </b>'+response.url+'<br>Started at '+timeFormat(response.timestamp),
                    rows:response.entries,
                    columns:[
                        {header:'Type', size:'40', field:'type'},
                        {header:'Time', size:'80', field:'utime', formatter:utimeFormat},
                        {header:'Text', size:'150', field:'text'},
                        {header:'File', size:'80', field:'file'},
                        {header:'Line', size:'40', field:'line'},
                    ],
                    onrowclass:function(rowdata){
                        if(rowData.type=='queryString') 
                            return 'warning'
                    },
                    onclick:function(rowData){
                        showPanel3(detailViewDatalog, rowData)
                    }
                    
                })
            }
            panel.updating=false
        }, 'json');                
    }
    
    function updatePHPLogs(panel, targetEl){ //this - menuitem
        var response,
            url= apiLoggerURL+'?action=phplogs',
            pageToken=debugScope.pageToken
        if (!pageToken) 
            return
        
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
                    id:'Phplog',

                    caption:'<b>PHP log of: </b>'+response.url+'<br>Started at '+timeFormat(response.timestamp),
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
                        showPanel3(detailViewPhplog, rowData)
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
    function timeFormat(v){
        d=new Date(v*1000)
        return d.getMonth()+'.'+d.getDate()+'.'+d.getFullYear()+' '+ d.getHours()+':'+d.getMinutes()+':'+d.getSeconds()
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
                hidePanel3()
                renderTable(targetEl,{
                    id:'pageSelect',
                    caption:'<b>Client:&nbsp;</b>'+response.clientToken+' <b>Pageload:&nbsp;</b>'+response.pageloadToken,
                    rows:response.pages,
                    columns:[
                        {header:'Script', size:'150', field:'script'},
                        {header:'Time', size:'50', field:'time'},
                        {header:'URL', size:'250', field:'url'},
                        {header:'Date', size:'60', field:'date'},
                        {header:'Timestamp', size:'100', field:'timestamp_float'}
                    ],
                    onrowclass:function(rowdata,rowEl){
                        if(rowData.pageToken==response.firstPageToken){
                            debugScope.pageToken=rowData.pageToken
                            debugScope.clientToken=clientToken
                            debugScope.pageloadToken=pageloadToken
                            drawer.panel2.panel2menu.select('phplogs')
                            this.selectedRowEl=rowEl
                            return 'selected'
                        }
                    },
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
            scrollbox=document.createElement('div'),
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
        
        scrollbox.className='doq-console-scrollbox'
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
                    if(!!atable.selectedRowEl){
                        atable.selectedRowEl.classList.remove('selected')
                    }
                    this.classList.add('selected')
                    atable.selectedRowEl=this
                    if(params.onclick)
                        params.onclick(arowData)
                })
            })(table, rowData)
            
            if(!!params.onrowclass){
                addCl=params.onrowclass.call(table,rowData,row)
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
        scrollbox.appendChild(table.el)
        targetEl.appendChild(scrollbox)
        
    }

    function splitter1mousedown(e){
        document.body.addEventListener('mousemove',splitter1mousemove)
        document.body.addEventListener('mouseup',splitter1mouseup)
        document.body.addEventListener('mouseleave',splitter1mouseup)
        drag.startPageX=e.pageX
        drag.id=e.target.id
        drag.target=e.target
        if(drag.id=='splitter1'){
            drag.startObjX=drawer.panel1.width
            drag.mode=10
        } else if(drag.id=='splitter2'){
            drag.startObjX=drawer.panel3.width
            drag.mode=11
        }
        
        function splitter1mousemove(e){
            var newPageX=e.pageX, 
                maxWidth=document.body.clientWidth
                
            if((drag.mode==10)||(drag.mode==11)){
                if(Math.abs(newPageX-drag.startPageX)<splitterHandleSize) 
                    return
            }
            
            if(drag.mode==10){
                drawer.panel1.width=drag.startObjX+(newPageX-drag.startPageX)
                if(drawer.panel1.width<50)
                    drawer.panel1.width=50
                if(drawer.panel1.width>(maxWidth-100)) 
                    drawer.panel1.width=maxWidth-100
                arrange()
            }
            
            if(drag.mode==11){
                drawer.panel3.width=drag.startObjX-(newPageX-drag.startPageX)
                if(drawer.panel3.width<50)
                    drawer.panel3.width=50
                if(drawer.panel3.width>(maxWidth-100)) 
                    drawer.panel3.width=maxWidth-100
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
            '@console-border-color':'#ddddee',
            '@console-bgcolor-menu':'#666666',
            '@console-bgcolor':'#eeeeee',
            '@console-bgcolor-selected':'#7777dd',
            '@console-bgcolor-inverse':'#777777',
            '@console-bgcolor-hilight':'#ffffff',
            '@console-bgcolor-error':'#ffeeee',
            '@console-color':'#555555',
            '@console-color-label':'#777777',
            '@console-color-selected':'#eeeeee',
            '@console-color-error':'#dd2222',
            '@console-text-size':'8pt',
            '@console-head-size':'10pt',
            '@console-text-font':'sans, verdana, arial',
            '@console-splitter-handleSize':splitterHandleSize+'px',
            '@console-splitter-handleHalfSize':splitterHandleHalfSize+'px'
            },
            '#doq-console-desk':'font-family:@console-text-font; font-size:@console-text-size; position:fixed; bottom:0px; left:0px; background:@console-bgcolor; border-radius:3px; width:100%; padding:3px; overflow:hidden; box-sizing:border-box;',
            '.doq-console-scrollbox':'overflow:auto;',
            '.doq-console-panel':'position:absolute;background:@console-bgcolor; padding:1px;box-sizing:border-box; overflow:auto; user-select:none',
            '.doq-console-vsplitter':'width:@console-splitter-handleSize; position:absolute; cursor:col-resize; user-select:none',
            '.doq-console-vsplitterw':'width:1px; height:100%; background-color:@console-border-color; margin:0 @console-splitter-handleHalfSize; pointer-events: none;',
            '.doq-console-vsplitter:hover > div':'background:@console-bgcolor-hilight;',
            '.doq-console-menu':'background:@console-bgcolor-menu; white-space: nowrap; user-select: none; padding:2pt 2pt 0 2pt;',
            '.doq-console-menuitem':'padding:2pt 5pt; color:@console-color-selected',
            '.doq-console-menuitem:hover':'background:@console-color',
            '.doq-console-menuitem.selected': 'background:@console-bgcolor; color:@console-color;',
            '.doq-console-table':'border-spacing:1;',
            '.doq-console-table th': 'font-family: @console-text-font; font-size:@console-text-size; background:@console-border-color; font-weight:normal;',
            '.doq-console-table tr': 'cursor:default;font-family: @console-text-font; font-size:@console-text-size; color:@console-color;',
            '.doq-console-table tr > td': ' border-bottom:solid 1px @console-border-color;',
            '.doq-console-table .disabled':'background:@console-border-color;',
            '.doq-console-table tr.error':'background:@console-bgcolor-error;color:@console-color-error;',
            '.doq-console-table tr:hover': 'background:@console-bgcolor-hilight; color:@console-color',
            '.doq-console-table tr.selected': 'background:@console-bgcolor-selected; color:@console-color-selected;',
            '.doq-console-table tr.active': 'background:@console-bgcolor-hilight; color:@console-color;',
            '.doq-console-drawerctrls':'cursor:pointer;display:inline-block; margin:2pt; overflow:hidden; width:10pt; height:10pt; font-size:8pt; color:@console-color-selected; text-align:center; background-color:@console-bgcolor-menu;',
            '.doq-console-drawerctrls:hover':'background-color:@console-bgcolor-hilight; color:@console-color;',
            '.doq-console-topbar':'position:absolute; right:2pt;',
            '.doq-console-form':'padding:4pt;',
            '.doq-console-forml':'margin:3pt 0 2pt 0; color: @console-color-label',
            '.doq-console-formi':'user-select:text;background-color:@console-bgcolor-hilight; border:solid 1px @console-border-color; border-radius:2pt; padding:2pt; overflow:auto;',
            
            '.doq-console-openbutton':'font-family:sans; font-size:7pt; position:fixed; bottom:20pt; right:20pt; background-color:#2020ff; border-radius:5px; padding:5px; opacity:20%; color:white;',
            '.doq-console-p':'color:@console-color-label; padding:3pt 3pt; margin:2pt 0pt; border-bottom:solid 1px @console-border-color;',
            '.doq-console-row-selected':'background:@console-bgcolor-selected; color:@console-color-selected;'
        }
    }
})



