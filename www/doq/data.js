/* jshint asi:true, -W100, forin:false, sub:true */
doq.module('doq.data', ['doq.evaluate'], function () {
    var CONST_1 = '123'
    var classes={}
    doq.log('Module doq.data: executed!')

    /**
     * Возвращает существующий узел данных
     * @param string path путь к узлу
     */
    function getDatanode(scopeStack, path) {
        var r, popOnExit = 0
        if ((path !== '') && (path !== '.')) {
            r = openPath(path, false, scopeStack)
            if (r[0] === false) return r
            popOnExit = 1
        }
        var datanode = scopeStack.top.datanode
        if (popOnExit) 
            closePath(scopeStack)
        return [datanode.type, datanode.value]
    }


    /**
    * Класс узла данных
    * @constructor
    * @param {string} path путь к узлу
    * @param {Datanode} parentNode родительский узел
    */
    function Datanode(path, parentNode) {
        doq.log('doq.data', 'Module data: expression constructor is called')
        this.path = path
        this.parentNode = parentNode
        this['#'] = {}
    }


    /** @deprecated  НЕ ТЕСТИРОВАЛОСЬ, плохо продумано и непонятно нужно ли
     *
     **/
    Datanode.prototype.callInherited = function (methodName, ars) {
        try {
            var generalClass = this['#']['class']['data']
            if (methodName in generalClass['methods']) {
                return generalClass['methods'][methodName].apply(this, ars)
            }
        } catch (e) { }
    }

    Datanode.prototype.doLater = function (methodName, params) {
        var self = this
        if ((!!self.path) && (methodName in self.methods))
            doq.doLaterOnce(self.path + '!' + methodName, self, self.methods[methodName], params)
    }

    Datanode.prototype.updateCategory = function (categoryName) {
        var self = this
        if (!self.updatingCategories)
            self.updatingCategories = {}
        self.updatingCategories[categoryName] = 1
        self.doLater('update')
    }

    Datanode.prototype.checkoutCategory = function (categoryName) {
        var self = this
        if ('all' in self.updatingCategories) {
            if (categoryName in self.updatingCategories)
                delete self.updatingCategories[categoryName]
            return true
        }
        if (categoryName in self.updatingCategories) {
            delete self.updatingCategories[categoryName]
            return true
        } else
            return false
    }

    Datanode.prototype.resetUpdateCategories = function () {
        this.updatingCategories = {}
    }

    Datanode.prototype.getAttributeAsString = function (attrName, defaultResult) {
        var d, format, r, t
        if (attrName in this['#']) {
            var attr = this['#'][attrName]
            if ('data' in attr) {
                t = attr['type'] || doq.C.T_STRING
                d = attr['data']
                format = attr['format']
            }
            if (d !== undefined) {
                r = convert(t, d, doq.C.T_STRING, format)
                if (r[0] === doq.C.CR_GOOD)
                    return r[1]
            }
        }
        return defaultResult
    }

    Datanode.prototype.getAttributeAsNumber = function (attrName, failResult) {
        var r,d,t
        if (attrName in this['#']) {
            var attr = this['#'][attrName]
            if ('data' in attr) {
                t = attr['type']
                d = attr['data']
            }
            if (d === undefined)
                return failResult
            else {
                r = convert(t, d, doq.C.T_NUMBER, format)
                if (r[0] === doq.C.CR_GOOD)
                    return r[1]
                else
                    doq.error('getAttributeAsNumber is failed [' + attrName + '] ' + r[2])
                return failResult
            }
        }
        return failResult
    }




    /**
     * Устанавливает значение атрибута вместе с дополнительными параметрами
     * @param {string} attrName название устанавливаемого атрибута
     * @param {Object} options опции функции
     * @param {string=} options.type строковое описание типа данных int,string,number
     * @param {string=} options.data данные
     * @param {string=} options.dataType внутренний тип данных doq.C.T_xxxx
     * @param {boolean=} options.nullable атрибут допускает пустое значение
     * @param {string=} options.bindType тип связки
     * @param {boolean=} options.doCreateRefNodes создавать узлы, на которые указывает связь. По-умолчанию true
     * @param {doq.Datanode=} options.contextScopeStack область контекста данных на которые указывают ссылки по-умолчанию
     * @param {object=} options.handlers обработчики событий
     *
     **/

    Datanode.prototype.setAttribute = function (attrName, options) {
        if (options === undefined)
            options = {}
        var thisAttr, attrExpression, res, r, n, s, schemaAttrName, params,
            expressionRPN, bindParams, i, pubPath, pubAttrName, sNode, binding, isChanged = 0,
            // Путь к данному атрибуту узла
            thisScopeAttrPath = this.path + '#' + attrName,
            // По-умолчанию, создаем все связанные формулами узлы
            doCreateRefNodes = (options.doCreateRefNodes === undefined) ? true : options.doCreateRefNodes,
            newData, hasNewData = 0,
            typeName, newDataType = options['dataType'],
            bindType = options['bindType'],
            // стек контекстной области видимости
            contextScopeStack = options.contextScopeStack

        if ('data' in options) {
            newData = options['data']
            hasNewData = 1
        }

        if ('type' in options) {
            typeName = options['type']
            if (typeName in doq.C.TYPE_MAP) {
                newDataType = doq.C.TYPE_MAP[typeName]
            } else
                newDataType = doq.C.T_VARIANT
        }

        if (attrName in this['#']) {
            thisAttr = this['#'][attrName]
        } else {
            thisAttr = this['#'][attrName] = {}
            if (!newDataType)
                newDataType = thisAttr.type = doq.C.T_VARIANT
        }

        if (hasNewData) {
            // ЕСЛИ новый тип связи данных автоматически определяемый, то определяем его из строки newdata
            if ((bindType === doq.C.BT_AUTO) || ((bindType === undefined) && (typeof newData === 'string'))) {
                if ((newData.charAt(0) === '{') && (newData.substr(-1) === '}')) {
                    if (newData.charAt(1) === '=') {
                        bindType = doq.C.BT_FORMULA
                    } else {
                        bindType = doq.C.BT_BIND_INOUT
                    }
                } else {
                    bindType = doq.C.BT_NONE
                }
            }
        }

        switch (bindType) {
            case doq.C.BT_FORMULA:
                attrExpression = newData.substring(2, newData.length - 1)
                res = doq.evaluate.parseExpression(attrExpression)
                if (res[0] === true) {
                    expressionRPN = res[1]
                    bindParams = res[2]
                    // linkage - прямая связь с атрибутами которые публикуют часть 
                    // параметров для вычисляемого формулой выражения 
                    thisAttr.linkage = {
                        expressionRPN: expressionRPN,
                        bindParams: bindParams,
                        bindPubDatanodes: [],
                        formula: attrExpression
                    }
                    for (i in bindParams) {
                        pubPath = bindParams[i]
                        // По-умолчанию, если атрибут издателя не указан, то  атрибутом отправителем является #value
                        // {/env/clock/localDate} означает {/env/clock/localDateYMD#value}
                        // Но этот способ нежелателен, так как создает неявное действие
                        pubAttrName = 'defaultValue'
                        if (pubPath.indexOf('#') >= 0) {
                            r = pubPath.split('#')
                            pubPath = r[0]
                            pubAttrName = r[1]
                        }

                        r = openPath(pubPath, doCreateRefNodes, contextScopeStack)
                        if (r[0] === false) return r
                        n = contextScopeStack.top.datanode
                        if (!('#' in n)) {
                            if (!doCreateRefNodes) return [false, 'Узел ' + pubPath + ' не содержит атрибутов']
                            n['#'] = {}
                        }
                        if (!(pubAttrName in n['#'])) {
                            if (!doCreateRefNodes) return [false, 'Узел ' + pubPath + ' не содержит атрибута ' + pubAttrName]
                            r = n['#'][pubAttrName] = { 'data': '' }
                            s = contextScopeStack.top.schemaNode
                            // но! если есть схема данных на данный узел, то подтягиваем из него все определения
                            if (!!s) {
                                schemaAttrName = '#' + pubAttrName
                                if (schemaAttrName in s) {
                                    sNode = s[schemaAttrName]
                                    if ('data' in sNode) r['data'] = sNode['data']
                                    if ('type' in sNode) r['type'] = sNode['type']
                                }
                            }
                        } else r = n['#'][pubAttrName]

                        // Сначала подписываемся на наличие изменений в параметрах выражения
                        thisAttr.linkage.bindPubDatanodes[i] = r
                        binding = doq.subscribe(pubPath + '#' + pubAttrName, doq.C.EV_CHANGE,
                            thisScopeAttrPath + '$' + i, _changeEvaluatedBind)
                        binding.paramNo = i

                        binding.subDatanode = this
                        binding.pubDatanode = n
                        if (!!options.handlers)
                            binding.handlers = options.handlers
                        closePath(contextScopeStack)

                        binding = doq.subscribe(thisScopeAttrPath, doq.C.EV_PULL, thisScopeAttrPath, _pullEvaluatedBind)
                        binding.subDatanode = this
                        // Затем подписываемся на вычисление общего результата выражения и его публикацию сами к себе
                        binding = doq.subscribe(thisScopeAttrPath, doq.C.EV_UPDATE, thisScopeAttrPath, _updateEvaluatedBind)

                        binding.subDatanode = this
                        if (!!options.handlers)
                            binding.handlers = options.handlers
                    } // for each bindParams
                    doq.emit(this.path, attrName, doq.C.EV_PULL)
                } else {
                    doq.error(res[1])
                    return [false, res[1]]
                }
                break

            case doq.C.BT_BIND_INOUT:
                // Если формулы нет, а есть прямой биндинг к атрибуту узла в модели
                // по адресу названия узла {pubPath} или {pubPath#anyAttr}
                attrExpression = newData.substring(1, newData.length - 1)
                pubPath = attrExpression
                if ((i = pubPath.indexOf('#')) >= 0) {
                    pubAttrName = pubPath.substring(i + 1)
                    pubPath = pubPath.substring(0, i)
                } else
                    pubAttrName = 'value'
                r = openPath(pubPath, doCreateRefNodes, contextScopeStack)
                if (r[0] === false) return r
                n = contextScopeStack.top.datanode
                if (!(pubAttrName in n['#'])) {
                    if (!doCreateRefNodes) return [false, 'Узел ' + pubPath + ' не содержит атрибута ' + pubAttrName]
                    r = n['#'][pubAttrName] = { 'data': '' }
                    s = contextScopeStack.top.schemaNode
                    if (!!s) {
                        schemaAttrName = '#' + pubAttrName
                        if (schemaAttrName in s) {
                            sNode = s[schemaAttrName]
                            if ('data' in sNode) r['data'] = sNode['data']
                            if ('type' in sNode) r['type'] = sNode['type']
                        }
                    }
                } else {
                    r = n['#'][pubAttrName]
                }
                thisAttr.linkage = {
                    pubPath: pubPath,
                    pubAttrName: pubAttrName,
                    bindPubDataAttr: r,
                    bindPubDatanode: n
                }
                binding = doq.subscribe(pubPath + '#' + pubAttrName, doq.C.EV_CHANGE, thisScopeAttrPath, _changeDirectBind)
                binding.subDatanode = this
                binding.pubDatanode = n
                if (!!options.handlers)
                    binding.handlers = options.handlers
                closePath(contextScopeStack)

                binding = doq.subscribe(thisScopeAttrPath, doq.C.EV_UPDATE, thisScopeAttrPath, _updateDirectBind)
                binding.subDatanode = this
                if (!!options.handlers)
                    binding.handlers = options.handlers

                binding = doq.subscribe(thisScopeAttrPath, doq.C.EV_PULL, thisScopeAttrPath, _pullDirectBind)
                binding.subDatanode = this
                if (!!options.handlers)
                    binding.handlers = options.handlers
                doq.emit(this.path, attrName, doq.C.EV_PULL) // сначала вытягиваем данные из источников
                break

            default: //no bind, change itself
                binding = this['#'][attrName]['changeBinding']
                if (!binding) {
                    this['#'][attrName]['changeBinding'] = binding = doq.subscribe(thisScopeAttrPath, doq.C.EV_CHANGE, thisScopeAttrPath, _changeItself)
                    binding.subDatanode = this
                }
                if (!!options.handlers)
                    binding.handlers = options.handlers

                binding = this['#'][attrName]['updateBinding']
                if (!binding) {
                    this['#'][attrName]['updateBinding'] = binding = doq.subscribe(thisScopeAttrPath, doq.C.EV_UPDATE, thisScopeAttrPath, _updateItself)
                    binding.subDatanode = this
                }
                if (!!options.handlers)
                    binding.handlers = options.handlers
                params = {}
                if (newDataType !== undefined) {
                    if (thisAttr['type'] !== newDataType)
                        params.newDataType = newDataType, isChanged = 1
                }
                if (thisAttr['data'] !== newData)
                    params.newData = newData, isChanged = 1
                if (options.nullable !== undefined) {
                    if (thisAttr['nullable'] !== options.nullable)
                        thisAttr['nullable'] = options.nullable, isChanged = 1
                }
                if (isChanged)
                    return doq.emit(this.path, attrName, doq.C.EV_CHANGE, params)
                else
                    return [false, 'Данные совпадают со старыми. Изменения не производятся']
        } // switch of bindType


        // params здесь не используется, поскольку новые данные вычисляются
        function _changeEvaluatedBind(params) {
            var r, c, binding = this,
                thisDatanode = binding.subDatanode,
                paramIndex, paramIndexPos,
                thisAttr, subAttrName = binding.subPathAttr

            if (!params) params = {}
            paramIndexPos = subAttrName.indexOf('$')
            if (paramIndexPos > 0)
                paramIndex = subAttrName.substring(paramIndexPos + 1),
                    subAttrName = subAttrName.substring(0, paramIndexPos)
            thisAttr = thisDatanode['#'][subAttrName]
            if ((!!binding.handlers) && (!!(c = binding.handlers['validate']))) {
                thisAttr.state = doq.C.US_VALIDATING
                r = c.call(thisDatanode, binding, thisAttr)
                if (r === false) {
                    thisAttr.state = doq.C.US_VALIDATE_ERROR
                    thisAttr.error = params.error
                    doq.error("При вычисляемом изменении " + binding.subPathAttr + " ошибка:" + params.error)
                    return
                }
            }
            thisAttr.state = doq.C.US_VALIDATED
            doq.postEmit(binding.subPathNode, subAttrName, doq.C.EV_UPDATE)
        }

        function _pullEvaluatedBind() {
            doq.postEmit(this.subPathNode, this.subPathAttr, doq.C.EV_UPDATE)
        }

        function _updateEvaluatedBind() {
            var binding = this,
                thisDatanode = binding.subDatanode,
                i, ne, cmd, arg, stack = [],
                c, r,
                thisAttr = thisDatanode['#'][binding.subPathAttr],
                params = {}
            for (i in thisAttr.linkage.expressionRPN) {
                ne = thisAttr.linkage.expressionRPN[i]
                cmd = ne[0]
                arg = ne[1]
                r = evaluate.expressionElementHandlers[cmd](arg, stack, thisAttr.linkage)
                if ((r !== undefined) && (r[0] === false)) {
                    doq.error(r[1] + ' in ' + ne[2])
                }
            }
            if (stack.length === 1) {
                r = convert(stack[0][0], stack[0][1], thisAttr.type)
                if (r[0] === doq.C.CR_ERROR) {
                    thisAttr['state'] = doq.C.US_EVALUATE_ERROR
                    thisAttr['error'] = r[2]
                    doq.error("При вычисляемом обновлении " + binding.subPathAttr + " ошибка:" + r[2])
                    return
                }
                params.newData = r[1]

                if ((!!binding.handlers) && (!!(c = binding.handlers['evaluate']))) {
                    thisAttr['state'] = doq.C.US_EVALUATING
                    r = c.call(thisDatanode, binding, thisAttr, params)
                    if (r === false) {
                        thisAttr['state'] = doq.C.US_EVALUATE_ERROR
                        thisAttr['error'] = params.error
                        doq.error("При вычисляемом обновлении " + binding.subPathAttr + " ошибка:" + params.error)
                        return
                    }
                }
                // ЗАПИСЫВАЕМ НОВОЕ ВЫЧИСЛЕННОЕ ЗНАЧЕНИЕ!
                thisAttr['data'] = params.newData
            } else {
                thisAttr['state'] = doq.C.US_EVALUATE_ERROR
                thisAttr['error'] = "Формула содержит ошибки. В стеке осталось " + stack.length + " значений. Формула:" + thisAttr.linkage.formula
                doq.error(thisAttr.error)
                return
            }

            if ((!!binding.handlers) && (!!(c = binding.handlers['present']))) {
                thisAttr['state'] = doq.C.US_PRESENTING
                r = c.call(thisDatanode, binding, thisAttr, params)
                if (r === false) {
                    thisAttr['state'] = doq.C.US_PRESENT_ERROR
                    thisAttr['error'] = params.error
                    return
                }
            }
            thisAttr['state'] = doq.C.US_PRESENTED
        }

        function _changeDirectBind(params) {
            var r, c, binding = this,
                thisDatanode = binding.subDatanode,
                thisAttr = thisDatanode['#'][binding.subPathAttr]
            if (!params) params = {}
            if (params.newData === undefined) {
                r = convert(thisAttr.linkage.bindPubDataAttr['type'],
                    thisAttr.linkage.bindPubDataAttr['data'],
                    thisAttr['type'])
                if (r[0] === doq.C.CR_ERROR) {
                    thisAttr['state'] = doq.C.US_VALIDATE_ERROR
                    thisAttr['error'] = r[2]
                    return
                }
                params.newData = r[1]
            }

            if ((!!binding.handlers) && (!!(c = binding.handlers['validate']))) {

                thisAttr['state'] = doq.C.US_VALIDATING

                // TODO!   Не сходятся результаты вызова в direct и itself
                //  вроде поправил с телефона


                r = c.call(thisDatanode, binding, thisAttr, params)
                if (r[0] == doq.C.US_VALIDATE_ERROR) {
                    thisAttr['state'] = doq.C.US_VALIDATE_ERROR
                    thisAttr['error'] = "get error from r"
                    return
                }

                if (r[0] == doq.C.US_VALIDATE_CORRECTED) {
                    thisAttr['data'] = r[1]
                    if (!!r[2]) thisAttr['type'] = r[2]
                }

                if (r === undefined) {
                    thisAttr['state'] = doq.C.US_VALIDATE_ERROR
                    thisAttr['error'] = params.error
                    return
                }
            }

            thisAttr['state'] = doq.C.US_VALIDATED
            if (params.newData !== undefined)
                thisAttr['data'] = params.newData
            if (params.newDataType !== undefined)
                thisAttr['type'] = params.newDataType
            doq.postEmit(binding.subPathNode, binding.subPathAttr, doq.C.EV_UPDATE)

        }

        // Вытянуть данные из источниов прямого связывания. Используется при начальной
        // инициализации атрибута, связанного с каким-то другим атрибутом
        function _pullDirectBind(params) {
            var pubAttrRef, binding = this,
                subPathAttr = binding.subPathAttr,
                thisDatanode = binding.subDatanode,
                expr = thisDatanode['#'][subPathAttr].linkage,
                pubDatanode = expr.bindPubDatanode,
                pubAttrName = expr.pubAttrName

            if (('#' in pubDatanode) && (pubAttrName in pubDatanode['#'])) {
                pubAttrRef = pubDatanode['#'][pubAttrName]
                if ('data' in pubAttrRef) {
                    thisAttr['data'] = pubAttrRef['data']
                    thisAttr['type'] = pubAttrRef['type']
                    thisAttr['state'] = doq.C.US_PULLED
                }
            }
            doq.postEmit(binding.subPathNode, binding.subPathAttr, doq.C.EV_UPDATE)
        }

        // params не должен ничего содержать! Данные уже должны быть записаны в модель
        function _updateDirectBind(params) {
            var binding = this,
                r, c, thisDatanode = binding.subDatanode,
                thisAttr = thisDatanode['#'][binding.pubPathAttr]
            thisAttr['state'] = doq.C.US_EVALUATING
            if ((!!binding.handlers) && (!!(c = binding.handlers['evaluate']))) {
                r = c.call(thisDatanode, binding, thisAttr, params)
                if (r === false) {
                    thisAttr['state'] = doq.C.US_EVALUATE_ERROR
                    thisAttr['error'] = params.error
                    return
                }
            }
            // обновляем данные!
            //thisAttr['data']=params.newData

            if ((!!binding.handlers) && (!!(c = binding.handlers['present']))) {
                thisAttr['state'] = doq.C.US_PRESENTING
                r = c.call(thisDatanode, binding, thisAttr, params)
                if (r === false) {
                    thisAttr['state'] = doq.C.US_PRESENT_ERROR
                    thisAttr['error'] = params.error
                    return
                }
            }
            thisAttr['state'] = doq.C.US_PRESENTED
        }

        /**
         * Вызывается через подписку на данные EV_CHANGE целевого узла
         * @param {string} params.newData новые данные
         * @param {string} params.newDataType тип новых данных
         * @return [enum UpdateState, any newData] .0-тип результата обработки изменения состояния, .1-измененное значение или ошибка
         */
        function _changeItself(params) {
            var r, r2, c, binding = this,
                thisDatanode = binding.subDatanode,
                thisAttr = thisDatanode['#'][binding.pubPathAttr],
                targetDataType = thisAttr['type']

            doq.log('doq.DataNode', '_changeItself[' + binding.subPath + ' (' + thisAttr['type'] + ')' + thisAttr['data'] +
                ' <= (' + params.newDataType + ')' + params.newData + ']')

            if (targetDataType === undefined) {
                thisAttr['type'] = (params.newDataType === undefined) ? doq.C.T_VARIANT : params.newDataType
            }

            if (params.newData !== undefined) {
                if (params.newDataType === undefined)
                    params.newDataType = targetDataType
                r2 = convert(params.newDataType, params.newData, targetDataType, thisAttr) // r2[0]=status, r2[1]-convValue. r2[2]-errorMessage
                params.newDataType = targetDataType
                switch (r2[0]) {
                    case doq.C.CR_GOOD:
                        params.newData = r2[1];
                        break
                    case doq.C.CR_ERROR:
                        r[0] = doq.C.US_VALIDATE_ERROR
                        r[1] = r2[2] // возвращаем ошибку
                        return r;
                    case doq.C.CR_CORRECTED:
                        r[0] = doq.C.US_VALIDATE_CORRECTED
                        params.newData = r2[1] // в данные записываем расчищенную версию данных
                        r[1] = r2[1] // возвращаем измененную версию
                        break
                }
            }

            if ((!!binding.handlers) && (!!(c = binding.handlers['validate']))) {
                thisAttr['state'] = doq.C.US_VALIDATING
                // при вызове handler возвращает [результат, новое_значение, новый тип]
                r = c.call(thisDatanode, binding, thisAttr, params)
                if (r == undefined) {
                    thisAttr['data'] = params.newData
                    r = [doq.C.US_VALIDATED]
                }
                if (r[0] == doq.C.US_VALIDATE_CORRECTED) {
                    thisAttr['data'] = r[1] // значение изменено
                    if (!!r[2])
                        thisAttr['type'] = r[2]
                }
            } else {
                r = [doq.C.US_VALIDATED]
                thisAttr['data'] = params.newData
            }

            thisAttr['state'] = r[0]
            doq.postEmit(binding.subPathNode, binding.subPathAttr, doq.C.EV_UPDATE)
            return r
        }

        function _updateItself(params) {
            var r, c, binding = this,
                thisDatanode = binding.subDatanode,
                thisAttr = thisDatanode['#'][binding.pubPathAttr]

            if ((!!binding.handlers) && (!!(c = binding.handlers['present']))) {
                thisAttr['state'] = doq.C.US_PRESENTING
                r = c.call(thisDatanode, binding, thisAttr, params)
                if (r === false) {
                    thisAttr['state'] = doq.C.US_PRESENT_ERROR
                    thisAttr['error'] = params.error
                }
            }
        }
    }


    function forEachChild(scopeStack, callback) {
        var i, scope = scopeStack.top
        if (!('@' in scope.datanode)) return false
        for (i in scope.datanode['@']) {
            callback(scopeStack, i)
        }
    }

    function copyObject(obj) {
        var i, r = {}, nn,
            n = Object.getOwnPropertyNames(obj)
        for (i in n)
            nn = n[i], r[nn] = obj[nn]
        return r
    }

    function setAttribute(thisScopeStack, attrName, options) {
        return thisScopeStack.top.datanode.setAttribute(attrName, options)
    }


    function openContext(params) {

        function _buildNodeBySchema(datanode, schema) {
            // extern vars: digScopeStack, contextScopeStack
            var tAttrName, tAttrDefs, tBindType, tcAttrName, tAttrData,
                schemaClass, schemaClassName,
                nodeClass, nodeClassName = datanode.nodeClassName,
                handlers = {}
            if (!('#' in datanode))
                datanode['#'] = {}

            schemaClassName = schema['#сlass']
            if (!!schemaClassName) {
                if (typeof schemaClassName === 'object')
                    schemaClassName = schemaClassName['data']
                if (schemaClassName in doq.data.classes)
                    schemaClass = doq.data.classes[schemaClassName]
                if ('schema' in schemaClass)
                    _buildNodeBySchema(datanode, schemaClass['schema'])
            }
            if ((!!nodeClassName) && (nodeClassName in doq.data.classes)) {
                nodeClass = doq.data.classes[nodeClassName]
            }
            for (tAttrName in schema) {
                if (tAttrName.indexOf('#') === 0) {
                    tAttrDefs = schema[tAttrName]
                    // название атрибута без # в начале
                    tcAttrName = tAttrName.substr(1)
                    if ((!!nodeClass) && ('handlers' in nodeClass) && (tcAttrName in nodeClass['handlers'])) {
                        handlers = doq.copyObject(nodeClass['handlers'][tcAttrName])
                    }
                    if (typeof tAttrDefs === 'object') {
                        tBindType = tAttrDefs['bind']
                        if (tBindType !== undefined)
                            tBindType = (tBindType in doq.C.TYPE_MAP) ? doq.C.TYPE_MAP[tBindType] : doq.C.BT_AUTO
                        doq.setAttribute(digScopeStack, tcAttrName, {
                            'data': tAttrDefs['data'],
                            'nullable': tAttrDefs['nullable'],
                            'bindType': tBindType,
                            'type': tAttrDefs['type'],
                            'contextScopeStack': contextScopeStack,
                            'handlers': handlers
                        })
                    } else {
                        tBindType = doq.C.BT_AUTO
                        tAttrData = tAttrDefs
                        setAttribute(digScopeStack, tcAttrName, {
                            'data': tAttrData,
                            'type': typeof tAttrData,
                            'contextScopeStack': contextScopeStack,
                            'handlers': handlers
                        })
                    }
                }
            }
        }

        var newPath = params.newPath,
            createIfNE = params.createIfNE,
            scopeStack = params.scopeStack,
            failIfExist = params.failIfExist,
            strictCreate = params.strictCreate,
            contextScopeStack = params.contextScopeStack,
            newSchema = params.newSchema,
            nodeClass, digScopeStack, schemaNode,
            i, j, scope, s,
            recordPath, datanode, path,
            aPath = newPath.split('/')

        if ((aPath.length > 1) && (aPath[0] === '')) {
            aPath.shift()
        } else {
            if ((scopeStack !== undefined) && (scopeStack.length > 0)) {
                scope = scopeStack.top
                if (newPath === '') {
                    scopeStack.push(scopeStack.top = scope)
                    return scopeStack
                }
            }
        }
        if (scope !== undefined) {
            datanode = scope.datanode
            schemaNode = scope.schemaNode
            path = scope.path
        } else {
            datanode = doq.model
            if (datanode === undefined) {
                datanode = doq.model = new Datanode('/')
            }
            schemaNode = doq.schema // может быть undefined
            path = '/'
        }

        if (newSchema !== undefined)
            schemaNode = newSchema

        digScopeStack = []
        digScopeStack.push(digScopeStack.top = {
            datanode: datanode,
            schemaNode: schemaNode,
            path: path
        })

        for (i in aPath) {
            s = aPath[i]
            if (s === '') continue
            if (s.charAt(0) === '!') {
                // Если путь выглядит /env/config/server/!1234 - это путь типа 1,
                //   тогда 1234-это идентификатор записи
                // Если путь выглядит /env/config/server/!12-3 - это путь типа 2,
                //   тогда 12-номер страницы, 3 - номер строки (номера начинаются с 1)
                isRecordPath = 1
                recordPath = s.substr(1).trim()
                if ((j = recordPath.indexOf('-')) !== -1) {
                    isRecordPath = 2
                    pageNo = parseInt(recordPath.substr(0, j))
                    pageRowNo = parseInt(recordPath.substr(j + 1))
                }
            } else {
                isRecordPath = 0
                path += (path.charAt(path.length - 1) !== '/') ? '/' + s : s
                if (newSchema === undefined) {
                    // если продолжение блуждания по схеме, то используем ключ s Для входа в следующий узел
                    if (schemaNode !== undefined) {
                        if (s in schemaNode) schemaNode = schemaNode[s]
                        else schemaNode = undefined
                    }
                } else {
                    // Чтобы не было перехода по верхнему условию
                    newSchema = undefined
                }
                if (!('@' in datanode)) {
                    if (!createIfNE) return [false, "Узел '" + path + "' не содержит дочерних элементов"]
                    if (strictCreate) {
                        if (schemaNode === undefined) return [false, "Узел '" + path + "' не определен в схеме"]
                    }
                    datanode['@'] = {}
                }
                if (s in datanode['@']) {
                    if (failIfExist)
                        return [false, "Узел '" + s + "' уже есть в пространстве данных '" + path + "'"]
                    datanode = datanode['@'][s]
                    digScopeStack.push(digScopeStack.top = {
                        datanode: datanode,
                        schemaNode: schemaNode, path: path
                    })
                } else {
                    if (!createIfNE)
                        return [false, "Узел '" + s + "' отсутствует в пространстве данных '" + path + "'"]
                    // Узел отсутствует, но если указан флаг createIfNE, то создаем этот узел
                    // с шаблонами класса из схемы, передавая datanode в качестве родительского
                    datanode = datanode['@'][s] = new Datanode(path, datanode)
                    if (!!contextScopeStack) {
                        datanode.contextPath = contextScopeStack.top.path
                        datanode.contextDatanode = contextScopeStack.top.datanode
                        datanode.contextSchemaNode = contextScopeStack.top.schemaNode
                    }

                    digScopeStack.push(digScopeStack.top = {
                        datanode: datanode, schemaNode: schemaNode, path: path
                    })

                    if (schemaNode !== undefined) {
                        var cn = schemaNode['#nodeClass']
                        if (!!cn) {
                            if (typeof cn === 'object') cn = cn['data']
                            if (cn in doq.data.classes) {
                                nodeClass = doq.data.classes[cn]
                                datanode['nodeClassName'] = cn
                                if ((nodeClass !== undefined) && ('methods' in nodeClass)) {
                                    datanode.methods = nodeClass['methods']
                                    if ('create' in datanode.methods) {
                                        datanode.methods['create'].call(datanode, digScopeStack, contextScopeStack)
                                    }
                                }
                            } else {
                                doq.error('Unknown Datanode class: ' + cn)
                            }
                        }
                        _buildNodeBySchema(datanode, schemaNode)
                    }
                }
            }
        }
        if (scopeStack === undefined) scopeStack = []
        scopeStack.push(scopeStack.top = {
            datanode: digScopeStack.top.datanode,
            path: digScopeStack.top.path,
            schemaNode: digScopeStack.top.schemaNode
        })
        return scopeStack

    }

    /**
     * Открывает узел видимости данных по адресу newPath, а если узла нет, то при createIfNE создает сам узел и промежуточные
     * @param {string} newPath новый путь
     * @param {boolean} создать узел, если отсутствует
     * @param {Array} текущий стек пространства имен, включая ссылку на текущую схему (обычно это viewmodel)
     * @param {boolean} выход с ошибкой, если узел уже есть
     * @param {boolean} создавать только в соответствии со схемой
     * @param {array} - контекст данных стек пространства имен модели с которой связываются формулы внутри узла (обычно это model)
     * @param {object} - новая схема
     * @returns ScopeStack||Array[false,errorText]
     **/
    function openPath(newPath, createIfNE, scopeStack, failIfExist, strictCreate, contextScopeStack, newSchema) {
        var nodeClass, digScopeStack, schemaNode, i, scope, s, datanode, path,
            recordPath, j, aPath = newPath.split('/')

        function _buildNodeBySchema(datanode, schema) {
            // extern vars: digScopeStack, contextScopeStack
            var tAttrName, tAttrDefs, tBindType, tcAttrName, tAttrData,
                schemaClass, schemaClassName,
                nodeClass, nodeClassName = datanode.nodeClassName,
                handlers = {}
            if (!('#' in datanode))
                datanode['#'] = {}

            schemaClassName = schema['#class']
            if (!!schemaClassName) {
                if (typeof schemaClassName === 'object')
                    schemaClassName = schemaClassName['data']
                if (schemaClassName in doq.data.classes)
                    schemaClass = doq.data.classes[schemaClassName]
                if ('schema' in schemaClass)
                    _buildNodeBySchema(datanode, schemaClass['schema'])
            }
            if ((!!nodeClassName) && (nodeClassName in doq.data.classes)) {
                nodeClass = doq.data.classes[nodeClassName]
            }
            for (tAttrName in schema) {
                if (tAttrName.indexOf('#') === 0) {
                    tAttrDefs = schema[tAttrName]
                    // название атрибута без # в начале
                    tcAttrName = tAttrName.substr(1)
                    if ((!!nodeClass) && ('handlers' in nodeClass) && (tcAttrName in nodeClass['handlers'])) {
                        handlers = copyObject(nodeClass['handlers'][tcAttrName])
                    }
                    if (typeof tAttrDefs === 'object') {
                        tBindType = tAttrDefs['bind']
                        if (tBindType !== undefined) {
                            tBindType = (tBindType in doq.C.TYPE_MAP) ? doq.C.TYPE_MAP[tBindType] : doq.C.BT_AUTO
                        }
                        setAttribute(digScopeStack, tcAttrName, {
                            'data': tAttrDefs['data'],
                            'nullable': tAttrDefs['nullable'],
                            'bindType': tBindType,
                            'type': tAttrDefs['type'],
                            'contextScopeStack': contextScopeStack,
                            'handlers': handlers
                        })
                    } else {
                        tBindType = doq.C.BT_AUTO
                        tAttrData = tAttrDefs
                        setAttribute(digScopeStack, tcAttrName, {
                            'data': tAttrData,
                            'type': typeof tAttrData,
                            'contextScopeStack': contextScopeStack,
                            'handlers': handlers
                        })
                    }
                }
            }
        }

        if ((aPath.length > 1) && (aPath[0] === '')) {
            aPath.shift()
        } else {
            if ((scopeStack !== undefined) && (scopeStack.length > 0)) {
                scope = scopeStack.top
                if (newPath === '') {
                    scopeStack.push(scopeStack.top = scope)
                    return scopeStack
                }
            }
        }
        if (scope !== undefined) {
            datanode = scope.datanode
            schemaNode = scope.schemaNode
            path = scope.path
        } else {
            datanode = doq.model
            if (datanode === undefined) {
                datanode = doq.model = new Datanode('/')
            }
            schemaNode = doq.schema // может быть undefined
            path = '/'
        }

        if (newSchema !== undefined)
            schemaNode = newSchema

        digScopeStack = []
        digScopeStack.push(digScopeStack.top = {
            datanode: datanode,
            schemaNode: schemaNode,
            path: path
        })

        for (i in aPath) {
            s = aPath[i]
            if (s === '') continue
            if (s.charAt(0) === '!') {
                // Если путь выглядит /env/config/server/!1234 - это путь типа 1,
                //   тогда 1234-это идентификатор записи
                // Если путь выглядит /env/config/server/!12-3 - это путь типа 2,
                //   тогда 12-номер страницы, 3 - номер строки (номера начинаются с 1)
                isRecordPath = 1
                recordPath = s.substr(1).trim()
                if ((j = recordPath.indexOf('-')) !== -1) {
                    isRecordPath = 2
                    pageNo = parseInt(recordPath.substr(0, j))
                    pageRowNo = parseInt(recordPath.substr(j + 1))
                }
            } else {
                isRecordPath = 0
                path += (path.charAt(path.length - 1) !== '/') ? '/' + s : s
                if (newSchema === undefined) {
                    // если продолжение блуждания по схеме, то используем ключ s Для входа в следующий узел
                    if (schemaNode !== undefined) {
                        if (s in schemaNode) schemaNode = schemaNode[s]
                        else schemaNode = undefined
                    }
                } else {
                    // Чтобы не было перехода по верхнему условию
                    newSchema = undefined
                }
                if (!('@' in datanode)) {
                    if (!createIfNE) return [false, "Узел '" + path + "' не содержит дочерних элементов"]
                    if (strictCreate) {
                        if (schemaNode === undefined) return [false, "Узел '" + path + "' не определен в схеме"]
                    }
                    datanode['@'] = {}
                }
                if (s in datanode['@']) {
                    if (failIfExist)
                        return [false, "Узел '" + s + "' уже есть в пространстве данных '" + path + "'"]
                    datanode = datanode['@'][s]
                    digScopeStack.push(digScopeStack.top = {
                        datanode: datanode,
                        schemaNode: schemaNode, path: path
                    })
                } else {
                    if (!createIfNE)
                        return [false, "Узел '" + s + "' отсутствует в пространстве данных '" + path + "'"]
                    // Узел отсутствует, но если указан флаг createIfNE, то создаем этот узел
                    // с шаблонами класса из схемы, передавая datanode в качестве родительского
                    datanode = datanode['@'][s] = new Datanode(path, datanode)
                    if (!!contextScopeStack) {
                        datanode.contextPath = contextScopeStack.top.path
                        datanode.contextDatanode = contextScopeStack.top.datanode
                        datanode.contextSchemaNode = contextScopeStack.top.schemaNode
                    }

                    digScopeStack.push(digScopeStack.top = {
                        datanode: datanode, schemaNode: schemaNode, path: path
                    })

                    if (schemaNode !== undefined) {
                        var cn = schemaNode['#nodeClass']
                        if (!!cn) {
                            if (typeof cn === 'object') cn = cn['data']
                            if (cn in doq.data.classes) {
                                nodeClass = doq.data.classes[cn]
                                datanode['nodeClassName'] = cn
                                if ((nodeClass !== undefined) && ('methods' in nodeClass)) {
                                    datanode.methods = nodeClass['methods']
                                    if ('create' in datanode.methods) {
                                        datanode.methods['create'].call(datanode, digScopeStack, contextScopeStack)
                                    }
                                }
                            } else {
                                doq.error('Unknown Datanode class: ' + cn)
                            }
                        }
                        _buildNodeBySchema(datanode, schemaNode)
                    }
                }
            }
        }
        if (scopeStack === undefined) scopeStack = []
        scopeStack.push(scopeStack.top = {
            datanode: digScopeStack.top.datanode,
            path: digScopeStack.top.path,
            schemaNode: digScopeStack.top.schemaNode
        })
        return scopeStack
    }

    /** Выход из открытой области видимости */
    function closePath (scopeStack) {
        var l = scopeStack.length
        if (l > 0) scopeStack.pop()
        if (l > 1) scopeStack.top = scopeStack[l - 2]
        else scopeStack.top = undefined
    }



    /** @param srcType - тип из которого делается преобразование
     * @param srcValue int - исходное значение
     * @param dstType - целевой тип
     * @param stringFormat - хэш параметров форматирования результата (обычно это хэш целевого атрибута)
     * @param inputMode - указывает, что преобразование происходит для отображения
     *   в поле ввода. Соответственно, игнорируются  лишние форматирующие символы
     * @return [status, convertedValue, errorMessage]
     */
    function convert  (srcType, srcValue, dstType, stringFormat, inputMode) {
        if (dstType == srcType)
            return [doq.C.CR_GOOD, srcValue]

        var r, v, ci, c2, lim, c, s, els, j, i, k, y, m, d, da, daLength, parts, t, tmpDate,
            formatLength, isNullable = 0,
            separator, scale = 0,
            thousandsSeparator, decimalSeparator

        thousandsSeparator = doq.lang.base['THOUSANDS_SEPARATOR']
        decimalSeparator = doq.lang.base['DECIMAL_SEPARATOR']
        if (stringFormat !== undefined) {
            if ('nullable' in stringFormat)
                isNullable = Boolean(stringFormat['nullable'])
            if ('thousandsSeparator' in stringFormat)
                thousandsSeparator = stringFormat['thousandsSeparator']
            if ('decimalSeparator' in stringFormat)
                decimalSeparator = stringFormat['decimalSeparator']
        }

        if (srcType === doq.C.T_VARIANT)
            srcType = (typeof (srcValue) == 'number') ? doq.C.T_NUMBER : srcType = doq.C.T_STRING

        switch (dstType) {
            case doq.C.T_NUMBER:
                if (srcValue === undefined) {
                    if (!isNullable)
                        v = 0
                } else {
                    if (srcType === doq.C.T_STRING) {
                        t = srcValue
                        if (decimalSeparator !== '')
                            t = t.replace(new RegExp('\\' + decimalSeparator, "g"), '.')
                        if (thousandsSeparator !== '')
                            t = t.replace(new RegExp('\\' + thousandsSeparator, "g"), '')
                        v = Number(t)
                    } else if ((srcType === doq.C.T_NUMBER) || (srcType === doq.C.T_INT))
                        v = srcValue
                    else
                        return [doq.C.CR_ERROR, srcValue, mgui.lang.base['ERROR_UNKNOWN_NUMBER_SRCTYPE']]

                    if (isNaN(v))
                        return [doq.C.CR_ERROR, srcValue, mgui.lang.base['ERROR_BAD_NUMBER']]
                }

                if (stringFormat !== undefined) {
                    if ('max' in stringFormat) {
                        lim = stringFormat['max']
                        if (v > lim)
                            v = lim
                    }
                    if ('min' in stringFormat) {
                        lim = stringFormat['min']
                        if (v < lim)
                            v = lim
                    }
                }
                r = [doq.C.CR_GOOD, v]
                break

            case doq.C.T_INT:
                if (srcValue === undefined) {
                    if (!isNullable)
                        v = 0
                } else {
                    switch (srcType) {
                        case doq.C.T_INT:
                            v = srcValue;
                            break
                        case doq.C.T_STRING:
                            v = parseInt(srcValue, 10);
                            break
                        case doq.C.T_NUMBER:
                            v = Math.ceil(srcValue);
                            break
                        default:
                            return [doq.C.CR_ERROR, srcValue, mgui.lang.base['ERROR_UNKNOWN_NUMBER_SRCTYPE']]
                    }
                    if (isNaN(v))
                        return [doq.C.CR_ERROR, srcValue, mgui.lang.base['ERROR_BAD_NUMBER']]
                }
                if (stringFormat !== undefined) {
                    if ('max' in stringFormat) {
                        lim = stringFormat['max']
                        if (v > lim)
                            v = lim
                    }
                    if ('min' in stringFormat) {
                        lim = stringFormat['min']
                        if (v < lim)
                            v = lim
                    }
                    if ('bitMask' in stringFormat) {
                        lim = stringFormat['bitMask']
                        v &= lim
                    }
                    if ('bitSize' in stringFormat) {
                        lim = (1 << stringFormat['bitSize']) - 1
                        v &= lim
                    }
                    r = [doq.C.CR_GOOD, v]
                }
                break

            case doq.C.T_DATE: // dstType - T_DATE
                if ((srcValue == '') || (srcValue == undefined)) {
                    r = (isNullable) ? [doq.C.CR_GOOD] : [doq.C.CR_ERROR, undefined, mgui.lang.base['ERROR_BAD_DATE']]
                } else {
                    if (srcType == doq.C.T_DATE)
                        return [doq.C.CR_GOOD, srcValue]
                    else if (srcType == doq.C.T_STRING) {
                        da = srcValue.split(mgui.lang.base['DATE_SEPARATOR'], 4)
                        daLength = da.length
                        if (daLength !== 3) {
                            return [doq.C.CR_ERROR, undefined, mgui.lang.base['ERROR_NOT_DATE']]
                        } else {
                            els = mgui.lang.base['DATE_FORMAT_SHORT']
                            if ((stringFormat !== undefined) && ('format' in stringFormat))
                                els = stringFormat['format']
                            formatLength = els.length
                            j = k = 0
                            for (i = 0; i < formatLength; i++) {
                                c = els.charAt(i)
                                for (s = c, i++; i < formatLength; i++) {
                                    ci = els.charAt(i)
                                    if (ci !== c)
                                        break
                                    s += ci
                                }
                                switch (s) {
                                    case 'y':
                                        y = parseInt(da[k++], 10)
                                        if (y < 100) y += 2000
                                        break
                                    case 'Y':
                                        y = parseInt(da[k++], 10)
                                        break
                                    case 'm':
                                        m = parseInt(da[k++], 10)
                                        break
                                    case 'M':
                                        m = mgui.lang['SHORT_MONTHS'][da[k++].trim()]
                                        break
                                    case 'd':
                                        d = parseInt(da[k++], 10)
                                }
                            }
                            if (!(isNaN(y) || isNaN(m) || isNaN(d))) {
                                tmpDate = new Date(y, m - 1, d, 0, 0, 0, 0)
                                if ((tmpDate.getDate() == d) && ((tmpDate.getMonth() + 1) == m) && (tmpDate.getFullYear() == y))
                                    r = [doq.C.CR_GOOD, y + '-' + m + '-' + d]
                                else
                                    r = [doq.C.CR_ERROR, undefined, mgui.lang.base['ERROR_BAD_DATE']]
                            } else
                                r = [doq.C.CR_ERROR, undefined, mgui.lang.base['ERROR_BAD_DATE_NUM']]
                        }
                    } else
                        r = [doq.C.CR_ERROR, undefined, mgui.lang.base['ERROR_UNKNOWN_DATE_SRCTYPE']]
                }
                break

            default: // dstType is T_STRING
                if (srcType == doq.C.T_DATE) {
                    // date=>string
                    if ((srcValue === undefined) || (srcValue === '')) {
                        return (isNullable) ? [doq.C.CR_GOOD, undefined] : [doq.C.CR_ERROR, undefined, mgui.lang.base['ERROR_BAD_DATE']]
                    } else {
                        da = srcValue.split('-', 4)
                        if (da.length !== 3) {
                            doq.error('Ошибка внутреннего представления даты: ' + srcValue)
                            return [mgui.CR_ERROR, srcValue, mgui.lang.base['ERROR_BAD_DATE']]
                        } else {
                            els = mgui.lang.base['DATE_FORMAT_SHORT']
                            separator = mgui.lang.base['DATE_SEPARATOR']
                            if (stringFormat !== undefined) {
                                if (!inputMode) {
                                    if ('format' in stringFormat) els = stringFormat['format']
                                    if (els == 'short') els = mgui.lang.base['DATE_FORMAT_SHORT']
                                    if (els == 'long') els = mgui.lang.base['DATE_FORMAT_LONG']
                                }
                                if ('dateSeparator' in stringFormat)
                                    separator = stringFormat['dateSeparator']
                            }
                            formatLength = els.length
                            v = []
                            for (i = 0; i < formatLength; i++) {
                                c = els.charAt(i)
                                switch (c) {
                                    case 'y':
                                        v.push(da[0]);
                                        break
                                    case 'm':
                                        v.push(da[1]);
                                        break
                                    case 'd':
                                        v.push(da[2]);
                                        break
                                    case 'M':
                                        v.push(mgui.lang['SHORT_MONTHS'][Number(da[1]) - 1]);
                                        break
                                    case '/':
                                        v.push(separator)
                                }
                            }
                            v = v.join('')
                        }
                    }
                } else if (srcType == doq.C.T_NUMBER) {
                    // number=>string
                    v = +srcValue
                    if ((!inputMode) && (stringFormat !== undefined)) {
                        // scale - число цифр после запятой
                        if ('scale' in stringFormat) {
                            scale = stringFormat['scale']
                            v = srcValue.toFixed(scale)
                        }
                        v += ''
                        parts = v.split('.')
                        if (!!thousandsSeparator) {
                            t = parts[0]
                            j = t.length
                            m = j % 3
                            if (!m) m = 3
                            els = []
                            for (i = 0; i < j; i += m) {
                                els.push(t.substr(i, m))
                                m = 3
                            }
                            els = [els.join(thousandsSeparator)]
                        } else
                            els = [v]
                        if ('prefix' in stringFormat)
                            els.unshift(stringFormat['prefix'])
                        if (('scale' in stringFormat) && (parts.length == 2)) {
                            els.push(decimalSeparator)
                            t = parts[1]
                            j = stringFormat['scale']
                            m = t.length
                            if (m < j) {
                                if ('repeat' in String.prototype)
                                    t += '0'.repeat(j - m)
                                else
                                    for (i = j - m; i; i--)
                                        t += '0'
                            }
                            els.push(t)
                        }
                        if ('suffix' in stringFormat)
                            els.push(stringFormat['suffix'])
                        v = els.join()
                    }
                } else
                    v = srcValue + ''
                r = [doq.C.CR_GOOD, v]
                break
        }
        return r
    }


    return {
        exports: [Datanode, forEachChild, copyObject, setAttribute, 
                getDatanode, openContext, openPath, closePath,
                {classes:classes}],
        css: {
            uses: ['a', 'li', '#panel'],
            vars: {
                '@inputCcolor': '#ff00a0',
                '@okColor': 'red'
            },
            '.btn': 'color:red; font-size:15pt;',
            '.inputs': {
                _: 'color:@inputColor; font-size:10pt',
                'a': 'text-decoration:none; font-weight:bold; color:@okColor',
                'a:hover': 'text-decoration:underline'
            }
        }
    }
})
