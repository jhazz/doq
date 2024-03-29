/* jshint asi:true, -W100, forin:false, sub:true */
doq = {
    css: { activeTheme: 'light', themes: { "light": { vars: { '@inputColor': 'cyan' } } }, selectors: {}, usage: {}, vars: {} },
    datasources: {},
    //pages:{},
    model: undefined,
    schema: {},
    C: {
        EV_CHANGE: 'change',
        EV_UPDATE: 'update',
        EV_PULL: 'pull',
        MODULES_ROOT: '..',
        EVT_INSERTITEM: 'INSERTITEM',
        EVT_DELITEM: 'DELITEM',
        EVT_APPENDITEM: 'APPENDITEM',
        NT_DATASET: '[DATASET]',
        NT_SUBCOLUMNS: '[SUBCOLUMNS]',
        NT_COLUMN: '[COLUMN]',
        BT_AUTO: 'BT_AUTO',
        BT_FORMULA: 'BT_FORMULA',
        BT_NONE: 'BT_NONE',
        BT_BIND_IN: 'BT_BINDIN',
        BT_BIND_INOUT: 'BT_BIND_INOUT',
        T_INT: 'T_INT',
        T_VARIANT: 'T_VARIANT',
        T_STRING: 'T_STRING',
        T_NUMBER: 'T_NUMBER',
        T_TUPLE: 'T_TUPLE',
        T_UNDEFINED: 'T_UNDEFINED',
        T_BINDREF: 'T_BINDREF',
        T_DATE: 'T_DATE',
        US_VALIDATING: 'VALIDATING',
        US_VALIDATED: 'VALIDATED',
        US_VALIDATE_ERROR: 'VALIDATE_ERROR',
        US_VALIDATE_CORRECTED: 'VALIDATE_CORRECTED',
        US_EVALUATING: 'EVALUATING',
        US_EVALUATE_ERROR: 'EVALUATE ERROR',
        US_PRESENTING: 'PRESENTING',
        US_PRESENTED: 'PRESENTED',
        US_PRESENT_ERROR: 'PRESENT_ERROR',
        US_PULLED: 'PULLED',
        CR_GOOD: 1,
        CR_ERROR: 2,
        CR_CORRECTED: 3, // convert results
        US_INPUT: 'INPT',
        CC_NONE: 0,
        CC_UNKNOWN: 1,
        CC_QUOTE: 2,
        CC_OPERATORCHAR: 3,
        CC_OPERATOR: 4,
        CC_SPACE: 5,
        CC_SYMBOL: 6,
        CC_TEXT: 7,
        CC_EOT: 8,
        CC_BIND: 9,
        CC_NUMBER: 10,
        E_SYMBOL: 'SYM',
        E_BIND: 'BND',
        E_TEXT: 'TXT',
        E_NUMBER: 'NUM',
        E_OPERATOR: 'OPE',
        E_OPENFUNC: 'OFN',
        E_OPENEVAL: 'OEV',
        E_CALLFUNC: 'FN',
        L_ERROR: 1,
        L_INFO: 2,
        L_DEBUG: 4,
        LOG_TO_BROWSER: true,
        LOG_TYPE_FILTER: 7,
    }
}

doq.C.TYPE_MAP = {
    'int': doq.C.T_INT,
    'string': doq.C.T_STRING,
    'number': doq.C.T_NUMBER,
    'date': doq.C.T_DATE,
    'formula': doq.C.BT_FORMULA,
    'bindIn': doq.C.BT_BIND_IN,
    'bindInOut': doq.C.BT_BIND_INOUT
}

doq.cfg = {
    logTypeFilter: doq.C.L_ERROR | doq.C.L_DEBUG | doq.C.L_INFO,
    logToBrowser: 1,
    logPoolSize: 100,
    logSourcePosition: 1,
    defaultRowsPerPage: 10,
    jsModulesRoot: '..'
};

(function (_global) {
    var 
        bindery = { byPub: { '#': 0 }, bySub: { '#': 0 } },
        taskList = {},
        taskListSize = 0,
        taskQueueInterval = 0,
        taskDoneCallbacks = [],
        moduleLoaders = {},
        oldErrorHandler,
        logEndIndex = 0,
        logStartIndex = 0,
        logPool = [],
        logNo = 0,
        onlog,
        stringify = JSON.stringify,
        startExecutionTime = (new Date()).getTime(),
        reExtractPath = /^([a-zA-Z0-9_\/]*)((#(\w*))?)/,
        lang = {
            base: {
                THOUSANDS_SEPARATOR: ',',
                DECIMAL_SEPARATOR: '.',
                DATE_SEPARATOR: '.',
                TIME_SEPARATOR: ':',
                DATE_FORMAT_SHORT: 'd/m/y',
                DATE_FORMAT_LONG: 'd/M/Y',
                SHORT_MONTHS: ["янв", "фев", "мар", "апр", "май", "июн", "июл", "авг", "сен", "окт", "ноя", "дек"],
                ERROR_BAD_NUMBER: 'Некорректное число',
                ERROR_UNKNOWN_DATE_SRCTYPE: 'Неподдерживаемый тип данных из которого требуется получить дату',
                ERROR_UNKNOWN_NUMBER_SRCTYPE: 'Неподдерживаемый тип данных из которого требуется получить число',
                ERROR_BAD_DATE: 'Некорректная дата',
                ERROR_BAD_DATE_NUM: 'В дате указываются только цифры',
                ERROR_NOT_DATE: 'Значение не является датой'
            }
        }

    doq.expLog = logPool // debug

    if (_global == undefined) {
        _global = window
    }

    function initErrorHadnler() {
        oldErrorHandler = window.onerror
        window.onerror = globalErrorHandler
    }

    function require(moduleName, onAfterInit) {
        if (moduleName in moduleLoaders) {
            loader = moduleLoaders[moduleName]
            if (loader.inited) {
                onAfterInit()
                return moduleLoaders[moduleName]
            } else {
                loader.onAfterInit.push(onAfterInit)
            }
        }

        var modulePath = moduleName.replace(/\./g, '/'),
            jsPath = doq.cfg.jsModulesRoot + '/' + modulePath + '.js',
            jsElement = document.createElement("script"),
            loader

        jsElement.type = 'text/javascript';
        jsElement.onerror = function (e) {
            loader.loading = 0
            loader.error = e
            this.onreadystatechange = this.onload = undefined
            var errStr = 'Unable to load module ' + moduleName + ' from "' + jsPath + '".  Modules are halted by the lost module: [' + getDependentModulesList(moduleName).join(',') + '].'
            throw errStr
        }
        jsElement.onload = jsElement.onreadystatechange = function () {
            if (loader.loading) {
                if (!this.readyState || this.readyState == "loaded" || this.readyState == "complete") {
                    this.onreadystatechange = this.onload = undefined
                    loader.loading = 0
                    loader.loaded = 1
                    log('doq.require', 'Module file "' + modulePath + '" is loaded', doq.C.L_DEBUG)
                } else {
                    log('doq.require', 'Module file "' + modulePath + '" readyState= ' + this.readyState, doq.C.L_DEBUG)
                }
            }
        };
        document.getElementsByTagName("head")[0].appendChild(jsElement)
        jsElement.src = jsPath
        loader = moduleLoaders[moduleName] = new ModuleLoader(moduleName, modulePath, jsElement, jsPath, onAfterInit)
        return loader
    }

    function ModuleLoader(moduleName, modulePath, jsElement, jsPath, onAfterInit) {
        this.moduleName= moduleName
        this.modulePath= modulePath
        this.jsElement= jsElement
        this.jsPath= jsPath
        this.loading=1
        this.loaded=0
        this.inited=0
        if (onAfterInit !== undefined)
            this.onAfterInit = [onAfterInit]
    }

    function getDependentModulesList(moduleName) {
        var res = [], i, j, ml, r
        for (i in moduleLoaders) {
            ml = moduleLoaders[i]
            if ((!ml.inited) && (!!ml.requires)) {
                for (j in ml.requires) {
                    r = ml.requires[j]
                    if (r == moduleName) {
                        res.push(ml.moduleName)
                        break
                    }
                }
            }
        }
        return res
    }

    function module(moduleName, requires, moduleFunction) {
        var i, ml, deep, useMeIndex, hasInit
        loader = moduleLoaders[moduleName]
        if (!loader) {
            log('doq', 'Initing module ' + moduleName + ' has not been registered in loaders registry')
            return
        }
        if (typeof (requires) == 'function') {
            // no requires
            loader.moduleFunction = moduleFunction = requires
            requires = null
        } else {
            // module has requires, need to check that they are has been inited
            loader.requires = requires
            loader.moduleFunction = moduleFunction
        }
        _regLoadedModule(loader)
        if ((!requires) || ((!!requires) && _checkRequires(requires))) {
            _initModule(loader)
            for (deep = 0; deep < 10; deep++) {
                hasInit = false
                for (i in moduleLoaders) {
                    ml = moduleLoaders[i]
                    if ((!ml.inited) && (!!ml.requires)) {
                        if (_checkRequires(ml.requires)) {
                            _initModule(ml)
                            hasInit = true
                        }
                    }
                }
                if (!hasInit) break
            }
        }

        function _checkRequires(arequires) {
            var i, iModuleName, im, isReady = true
            for (i in arequires) {
                iModuleName = arequires[i]
                if (!(iModuleName in moduleLoaders)) {
                    require(iModuleName)
                    isReady = false
                } else {
                    im = moduleLoaders[iModuleName]
                    if (!im.inited)
                        isReady = false
                }
            }
            return isReady
        }

        function _regLoadedModule(aloader) {
            var i, j, e, t, targetNS = _global, moduleNames, n
            try {
                defs = aloader.moduleFunction()
            } catch (e) {
                console.error(e)
                return
            }
            if (aloader.moduleName) {
                moduleNames = aloader.moduleName.split('.')
                targetNS = _global
                for (i in moduleNames) {
                    n = moduleNames[i]
                    if (n in targetNS)
                        targetNS = targetNS[n]
                    else {
                        targetNS = (targetNS[n] = {})
                    }

                }
                //targetNS=_global[aloader.moduleName]={}
            }
            aloader.targetNS = targetNS

            if (!!defs.exports) {
                for (i in defs.exports) {
                    e = defs.exports[i]
                    if(typeof (e)==='function'){
                        targetNS[e.name] = e
                    } else {
                        for(j in e) {
                            targetNS[j]=e[j]
                        }
                    }
                }
            }
            if (!!defs.css)
                registerCSSSelector(aloader.moduleName, defs.css)
        }


        function _initModule(aloader) {
            var i
            if (aloader.targetNS.init !== undefined) {
                aloader.targetNS.init.call(aloader)
            }
            aloader.inited = 1
            applyCSSByOwnerId(aloader.moduleName)
            if (aloader.onAfterInit !== undefined) {
                for (i in aloader.onAfterInit) {
                    aloader.onAfterInit[i].call(aloader)
                }
            }
        }
    }

    function applyCSSByOwnerId(ownerId, doOverwrite) {
        var i, applyingStyleText, ss, sset, sels, rule, l, v, ruleSelector,
            targetSheet, overlaps = {}, activeTheme, val, varEntry

        //log('doq.css','-----apply css defined by "'+ownerId+'" ------')
        if ((!!doq.css.activeTheme) && (doq.css.activeTheme in doq.css.themes))
            activeTheme = doq.css.themes[doq.css.activeTheme]

        if (document.styleSheets) {
            for (i in document.styleSheets) {
                ss = document.styleSheets[i]
                if ((ss.disabled) || (!ss.cssRules))
                    continue
                if (!ss.href) {
                    targetSheet = ss
                }
                l = ss.cssRules.length
                for (j = 0; j < l; j++) {
                    rule = ss.cssRules[j]
                    ruleSelector = rule.selectorText
                    if (ruleSelector in doq.css.selectors) {
                        overlaps[ruleSelector] = rule
                    }
                }
            }
        }

        if (!targetSheet) {
            targetSheet = document.createElement('style')
            targetSheet.type = 'text/css'
            document.getElementsByTagName('head')[0].appendChild(targetSheet)
        }
        sels = doq.css.selectors
        for (ss in sels) {
            v = sels[ss]
            if (ownerId in v) {
                if (ss in overlaps) {
                    if (!doOverwrite) {
                        continue
                    }
                }
                applyingStyleText = v[ownerId].replace(/@[A-Za-z\-_]+/g, function (varName) {
                    var val
                    if (varName in doq.css.vars) {
                        varEntry = doq.css.vars[varName]
                        if ('value' in varEntry) {
                            val = varEntry.value
                        }

                    }
                    if (val == undefined) {
                        if ((!!activeTheme) && (varName in activeTheme.vars)) {
                            val = activeTheme.vars[varName]
                        } else {
                            if ((varEntry != undefined) && ('default' in varEntry)) {
                                val = varEntry.default
                            } else {
                                error('"' + ownerId + '" has CSS variable "' + varName + '" that is not defined')
                                return '/*BAD VARIABLE:' + varName + '!*/'
                            }
                        }
                    }
                    return val
                })
                targetSheet.addRule(ss, applyingStyleText)
            }
        }
    }

    function registerCSSSelector(ownerId, defs, prefix) {
        var k, v, t, n, s, se, i, u, doqVars = doq.css.vars,
            doqSelectors = doq.css.selectors, doqUsage = doq.css.usage
        for (k in defs) {
            t = typeof (v = defs[k])
            if (t == 'string') {
                if (k == '_') {
                    if (prefix in doqSelectors)
                        doqSelectors[prefix][ownerId] = v
                    else
                        doqSelectors[prefix] = {}
                    doqSelectors[prefix][ownerId] = v
                } else {
                    s = (prefix == undefined) ? k : prefix += ' ' + k
                    if (!(s in doqSelectors)) {
                        doqSelectors[s] = {}
                    }
                    doqSelectors[s][ownerId] = v
                }
            } else if (t == 'object') {
                if (k == '@media') {
                    error("Cannot work with media conditions")
                } else if (k == 'uses') {
                    for (i in v) {
                        u = v[i]
                        if (u in doqUsage)
                            docUsage[u].push(ownerId)
                        else
                            doqUsage[u] = [ownerId]
                    }
                } else if (k == 'vars') {
                    for (i in v) { // i-var name starting from @. u=v[i]-var value
                        if (i.charAt(0) != '@') {
                            error('Bad variable in css vars ' + i + ' ' + ' defined by ' + ownerId)
                            continue
                        }
                        u = v[i]
                        if (i in doqVars) {
                            doqVars[i].declaredBy.push(ownerId)
                        } else {
                            doqVars[i] = { declaredBy: [ownerId], default: u }
                        }
                    }
                } else {
                    registerCSSSelector(ownerId, v, (prefix == undefined) ? k : prefix += ' ' + k)
                }
            }
        }
    }


    function error(data, url, lineNumber, col) {
        log('Error', data, doq.C.L_ERROR, url, lineNumber, col)
    }

    function log(category, data, type, url, lineNumber, col) {
        var logEntry, msg, s, stack, last
        if (type == undefined) type = doq.C.L_DEBUG
        if (!(doq.cfg.logTypeFilter & type))
            return

        if (data === undefined) {
            data = category
            category = '(No category)'
        }

        msg = (typeof (data) == 'object') ? stringify(data) : '' + data

        if (url === undefined) {
            if (doq.cfg.logSourcePosition) {
                stack = (function () { try { throw Error() } catch (err) { return err; } })().stack.split('\n')
                if (stack.length && stack.length > 1) {
                    last = stack.pop()
                    if (!last)
                        last = stack.pop()
                    url = last.trim()
                }
            }
        } else {
            if (lineNumber !== undefined)
                url += ':' + lineNumber
            if (col !== undefined)
                url += ':' + col
        }

        var currentTime = (new Date()).getTime(),
            currentTimeOffset = currentTime - startExecutionTime

        logEntry = [logNo, currentTimeOffset, currentTime, category, '[#' + logNo + '] ' + msg, type, url]
        logNo++

        if (logPool.length < doq.cfg.logPoolSize) {
            logEndIndex = logPool.push(logEntry)
        } else {
            if (logEndIndex >= doq.cfg.logPoolSize) {
                logEndIndex = 0
            }
            logPool[logEndIndex++] = logEntry
            if (logStartIndex < logEndIndex) {
                logStartIndex = logEndIndex
                if (logStartIndex >= doq.cfg.logPoolSize) {
                    logStartIndex = 0
                }
            }
        }

        if (doq.cfg.logToBrowser) {
            s = category + ': ' + msg
            if (url !== undefined)
                s += ' ' + url
            if (type == doq.C.L_ERROR)
                console.error(s)
            else if (type == doq.C.L_INFO)
                console.info(s)
            else console.log(s)
        }

        if (onlog != undefined) {
            onlog(logPool, doq.cfg.logPoolSize)
        }
    }
    function getLog(offset, size) {
        var i, j, res = [], restSize
        if (!size) size = 100
        if (!offset) offset = 0
        restSize = size

        if (logEndIndex < logPool.length) {
            i = logStartIndex + offset
            if (i >= logPool.length) {
                i -= logPool.length
            }
        } else {
            i = offset
        }
        for (j = 0; j < restSize; j++) {
            res.push(logPool[i])
            i = (i < (doq.cfg.logPoolSize - 1)) ? i + 1 : 0
            if (i == logEndIndex) break
        }
        return res
    }

    function globalErrorHandler(errorMsg, url, lineNumber, col, eobj) {
        log('doq.globalError', errorMsg, doq.C.L_ERROR, url, lineNumber, col)
        if (!!oldErrorHandler)
            oldErrorHandler(errorMsg, url, lineNumber, col, eobj)
        return true
    }

    function getJSON(url, params, onload, responseType) {
        if (!responseType) {
            responseType = 'json'
        }
        var xhr = new XMLHttpRequest()
        xhr.open('GET', url)
        xhr.responseType = responseType
        xhr.setRequestHeader('Content-type', 'application/json; charset=utf-8')
        if (typeof params == 'object')
            xhr.send(new URLSearchParams(params).toString())
        else
            xhr.send(params)
    }

    function sendJSON(url, json, onload, responseType, method) {
        if (!responseType) {
            responseType = 'json'
        }
        var xhr = new XMLHttpRequest()
        if (!method) {
            method = 'POST'
        }
        xhr.open(method, url)
        xhr.responseType = responseType
        xhr.setRequestHeader('Content-type', 'application/json; charset=utf-8')
        if (typeof json == 'string')
            xhr.send(json)
        else
            xhr.send(stringify(json))
        //xhr.onload=onload

        xhr.onload = function (progress) {
            if (progress.target.status !== 200) {
                return onload({ error: 'Ошибка подключения ' + progress.target.status, url: url }, true)
            }
            if (progress.target.response === null) {
                return onload({ error: 'Ошибка обработки ответа от сервера', url: url, json: json }, true)
            }
            if ((progress.target.responseType == 'json') && ('error' in progress.target.response)) {
                return onload(progress.target.response, true)
            }
            return onload(progress.target.response, false)
        };

        return xhr
    }

    function bind(publisherNode, publisherAttr, eventType, subscriberNode, subscriberAttr, callback) {
        var binding, r, pe,
            pubPath = publisherNode + '#' + publisherAttr,
            subPath = subscriberNode + '#' + subscriberAttr

        function _digPath(pathElements, aNode, binding) {
            var prevNode = aNode, e, i
            for (i in pathElements) {
                e = pathElements[i]
                if (e === '') continue
                if (e in aNode) aNode = aNode[e]
                else {
                    if (prevNode !== undefined) prevNode['#']++
                    aNode = (aNode[e] = { '#': 0 })
                }
                prevNode = aNode
            }
            aNode['#'] = 1
            aNode['&'] = binding
            return aNode
        }

        binding = {
            pubPath: pubPath,
            subPath: subPath,
            eventType: eventType,
            subscriberNode: subscriberNode,
            subscriberAttr: subscriberAttr,
            publisherNode: publisherNode,
            publisherAttr: publisherAttr,
            callback: callback
        }

        pe = subscriberNode.split('/')
        pe.push('@')
        pe.push(subscriberAttr)
        pe.push(eventType)
        _digPath(pe, bindery.bySub, binding)

        pe = publisherNode.split('/')
        pe.push('@')
        pe.push(publisherAttr)
        pe.push(eventType)
        pe.push(subPath)
        _digPath(pe, bindery.byPub, binding)
        return binding
    }

    function unbindByPub(pubPath, referencingSub, ignoreBindery) {
        var pubPathNode = pubPath,
            pubPathAttr = 'value',
            binding, r, i, j, k, e, n1, n2, allAttrs = true,
            m, node = bindery.byPub,
            chain = [],
            pe, ce, nodeName
        if (pubPath.indexOf('#') >= 0) r = pubPath.split('#'), pubPathNode = r[0], pubPathAttr = r[1], allAttrs = false
        pe = pubPathNode.split('/')
        pe.push('@')
        for (i in pe) {
            e = pe[i]
            if (e === '') continue
            chain.push([e, node])
            if (e in node) node = node[e]
            else return [false, "Элемент " + e + ' не найден в связке подписчиков по пути ' + pubPath]
        }
        for (k in node) { // перебираем pubPathAttr
            if ((k == '#') || ((!allAttrs) && (k != pubPathAttr))) continue
            n1 = node[k]
            for (m in n1)
                if (m != '#') { // перебираем msgType
                    n2 = n1[m]
                    for (j in n2)
                        if (j != '#') { // перебирем subPath, удаляем только связанные с referensingSub
                            if ((referencingSub == undefined) || (referencingSub == j)) {
                                if (!ignoreBindery) {
                                    binding = n2[j]['&']
                                    unbindBySub(binding.subPath, true)
                                }
                                delete n2[j]
                                n2['#']--
                            }
                        }
                    if (!n2['#']) delete n1[m], n1['#']--
                }
            if (!n1['#']) delete node[k], node['#']--
        }
        for (j = chain.length - 1; j >= 0; j--) {
            ce = chain[j]
            node = ce[1]
            nodeName = ce[0]
            if (node[nodeName]['#'] != 0) break
            delete node[nodeName]
            node['#']--
        }
    }

    function unbindBySub(subPath, ignoreBindery) {
        var subPathNode = subPath,
            subPathAttr = 'value',
            binding, r, i, j, k, e, n1, allAttrs = true,
            node = bindery.bySub,
            chain = [],
            pe, ce, nodeName
        if (subPath.indexOf('#') >= 0) r = subPath.split('#'), subPathNode = r[0], subPathAttr = r[1], allAttrs = false
        pe = subPathNode.split('/')
        pe.push('@')
        for (i in pe) {
            e = pe[i]
            if (e === '') continue
            chain.push([e, node])
            if (e in node) node = node[e]
            else return [false, "Элемент " + e + ' не найден в связке подписчиков по пути ' + subPath]
        }
        for (k in node) {
            if ((k == '#') || ((!allAttrs) && (k != subPathAttr))) continue
            n1 = node[k]
            if (!ignoreBindery)
                for (j in n1)
                    if (j != '#') {
                        binding = n1[j]['&']
                        unbindByPub(binding.pubPath, binding.subPath, true)
                    }
            delete node[k]
            node['#']--
        }
        for (j = chain.length - 1; j >= 0; j--) {
            ce = chain[j]
            node = ce[1]
            nodeName = ce[0]
            if (node[nodeName]['#'] != 0) break
            delete node[nodeName]
            node['#']--
        }
    }

    /**
     * Связь между публикатором и подписчиком sub[node:attr] => pub[node:attr]
     */
    function Binding(pubPath, subPath, subPathAttr, pubPathAttr, pubPathNode, subPathNode, msgType, callback) {
        this.pubPath = pubPath
        this.subPath = subPath
        this.subPathAttr = subPathAttr
        this.pubPathAttr = pubPathAttr
        this.pubPathNode = pubPathNode
        this.subPathNode = subPathNode
        this.msgType = msgType
        this.callback = callback
    }

    /**
     * Подписка на сообщения от pub(публикатора) типа (msgType) и идентификатором подписчика (subpath)
     * @param {string} pubPath Путь к публикующему атрибуту
     * @param {any} msgType Тип сообщения на который формируется подписка
     * @param {string} subPath Путь к атрибуту подписчика
     * @param {function} callback Вызываемая функция
     * @returns {Binding}
     */
    function subscribe(pubPath, msgType, subPath, callback) {
        var binding, r, pe,
            pubExtraction = reExtractPath.exec(pubPath), //  pubExtraction[3] - название перед ':'. Пока не и
            pubPathNode = pubExtraction[1],
            pubPathAttr = pubExtraction[4],
            subExtraction = reExtractPath.exec(subPath),
            subPathNode = subExtraction[1],
            subPathAttr = subExtraction[4]

        function _digPath(pathElements, aNode, binding) {
            var prevNode = aNode,
                e, i
            for (i in pathElements) {
                e = pathElements[i]
                if (e === '') continue
                if (e in aNode) aNode = aNode[e]
                else {
                    if (prevNode !== undefined) prevNode['#']++
                    aNode = (aNode[e] = { '#': 0 })
                }
                prevNode = aNode
            }
            aNode['#'] = 1
            aNode['&'] = binding
            return aNode
        }

        binding = new Binding(pubPath, subPath, subPathAttr, pubPathAttr, pubPathNode, subPathNode, msgType, callback)
        pe = subPathNode.split('/')
        pe.push('@')
        pe.push(subPathAttr)
        pe.push(msgType)
        _digPath(pe, bindery.bySub, binding)

        pe = pubPathNode.split('/')
        pe.push('@')
        pe.push(pubPathAttr)
        pe.push(msgType)
        pe.push(subPath)
        _digPath(pe, bindery.byPub, binding)
        return binding
    }


    /**
     * Sends event from publisher to subscriber
     * @param {string} pubPath publisher path
     * @param {string} pubAttr publisher attribute
     * @param {EventType} eventType one of event types like EV_CHANGE/EV_UPDATE/EV_PULL
     * @param {object} params passing parameters to subscriber
     * */
    function emit(pubPath, pubAttr, eventType, params) {
        var e, pe, r, i, subPath, subscribers, result, binding, node = bindery.byPub, s, pp
        if (!pubAttr)
            pubAttr = "(Unknown attribute '" + pubPath + "' as emitter)"
        pe = pubPath.split('/')
        for (i in pe) {
            e = pe[i]
            if (e == "") continue
            if (e in node) {
                node = node[e]
            } else {
                return [false, "Пустая рассылка сообщения от узла на который никто не подписывался " + pubPath + '#' + pubAttr]
            }
        }

        if (!('@' in node))
            return [false, "Попытка отправить сообщение от узла у которого нет подписчиков '" + pubPath + "' (атрибут #" + pubAttr + "', сообщение '" + eventType + "')"]
        if (!(pubAttr in node['@']))
            return [false, "Попытка отправить сообщение '" + eventType + "' от атрибута '" + pubPath + '#' + pubAttr + "' на который никто не подписан"]
        if (!(eventType in node['@'][pubAttr]))
            return [false, "Попытка отправить незарегистрированное сообщение от атрибута '" + pubPath + '#' + pubAttr + "', тип сообщения '" + eventType + "'"]
        subscribers = node['@'][pubAttr][eventType]
        s = ''
        pp = '' // для отладки
        if ((!!params) && (!!params.newData)) pp = '=' + params.newData

        for (subPath in subscribers) {
            if (subPath != '#') {
                if ((!!params) && (!!params.caller) && (subPath == params.caller))
                    continue
                binding = subscribers[subPath]['&']
                r = binding.callback(params)
                if (r === false)
                    result = r
                else
                    if (result === undefined && r !== undefined)
                        result = r
                s += ((s !== '') ? ',' : '') + "'" + subPath + "'"
            }
        }
        log('doq.emit', "Сообытие доставлено '" + eventType + "' от атрибута '" + pubPath + '#' + pubAttr + "'=>[" + s + ']' + pp, '#80ff80')
        return [result]
    }

    function doLaterOnce(objId, obj, action, params) {
        taskListSize++
        if (!taskQueueInterval)
            taskQueueInterval = window.setInterval(taskRunner, 100)
        taskList[objId] = [taskListSize, obj, action, params]
    }

    function taskRunner() {
        if (!taskListSize) {
            if (taskQueueInterval)
                window.clearInterval(taskQueueInterval)
            taskQueueInterval = 0
            return
        }
        var taskListIterate, queue, m, loopTime, callback

        for (loopTime = 1; loopTime <= 4; loopTime++) {
            taskListIterate = taskList, queue = []
            taskList = {}
            taskListSize = 0
            for (m in taskListIterate)
                queue.push(taskListIterate[m])
            queue.sort(function (a, b) { return a[0] - b[0] })
            while (!!(m = queue.shift())) {
                if (typeof m[2] == 'function')
                    m[2].apply(m[1], m[3])
                else
                    if (m[2] in m[1]) {
                        log('doq.taskRunner', '--Executing' + m[2] + '--')
                        m[1][m[2]].apply(m[1], m[3])
                    }
            }
            if (!taskListSize) break
            log('doq.taskRunner', '-----loop ' + loopTime + '------')
        }
        log('doq.taskRunner', '-----tasks over ----')
        if (!!taskDoneCallbacks) {
            while (!!(callback = taskDoneCallbacks.pop())) {
                (callback)()
            }
        }
    }

    function postEmit(pubPath, pubAttr, eventType, params) {
        doLaterOnce(pubPath + '#' + pubAttr + '!' + eventType, doq, emit, [pubPath, pubAttr, eventType, params])
    }

    var i, j, f, fs = [module, require, log, stringify, getLog, error, emit, postEmit, doLaterOnce, taskRunner, sendJSON,
        bind, unbindByPub, unbindBySub, subscribe, {lang:lang}]
    for (i in fs){
        f = fs[i]
        if(typeof(f)==='function'){
            doq[f.name] = f
        } else {
            for (j in f){
                doq[j]=f[j]
            }
        }
        

    }
    initErrorHadnler()
})(window)

