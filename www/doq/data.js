doq.module('doq.data', ['doq.evaluate'], function(){
    var CONST_1='123'


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
      if (popOnExit) closePath(scopeStack)
      return [datanode.type, datanode.value]
    }


    /**
    * Класс узла данных
    * @constructor
    * @param {string} path путь к узлу
    * @param {Datanode} parentNode родительский узел
    */
    function Datanode(path, parentNode) {
        doq.log('doq.data','Module data: expression constructor is called')
        this.path = path
        this.parentNode = parentNode
        this['#'] = {}
    }

  
    /** @deprecated  НЕ ТЕСТИРОВАЛОСЬ, плохо продумано и непонятно нужно ли
     *
     **/
    Datanode.prototype.callInherited = function(methodName, ars) {
      try {
        var generalClass = this['#']['class']['data']
        if (methodName in generalClass['methods']) {
          return generalClass['methods'][methodName].apply(this, ars)
        }
      } catch (e) {}
    }

    Datanode.prototype.doLater = function(methodName, params) {
      var self = this
      if ((!!self.path) && (methodName in self.methods))
        doq.doLaterOnce(self.path + '!' + methodName, self, self.methods[methodName], params)
    }

    Datanode.prototype.updateCategory = function(categoryName) {
      var self = this
      if (!self.updatingCategories)
        self.updatingCategories = {}
      self.updatingCategories[categoryName] = 1
      self.doLater('update')
    }

    Datanode.prototype.checkoutCategory = function(categoryName) {
      var self = this
      if('all' in self.updatingCategories){
        if(categoryName in self.updatingCategories)
          delete self.updatingCategories[categoryName]
        return true
      }
      if(categoryName in self.updatingCategories){
        delete self.updatingCategories[categoryName]
        return true
      } else
        return false
    }

    Datanode.prototype.resetUpdateCategories = function() {
      this.updatingCategories={}
    }

    Datanode.prototype.getAttributeAsString = function(attrName, defaultResult) {
      var d, format, r, t
      if (attrName in this['#']) {
        var attr = this['#'][attrName]
        if ('data' in attr){
          t = attr['type'] || doq.C.T_STRING
          d = attr['data']
          format = attr['format']
        }
        if (d !== undefined){
          r = mgui.convert(t, d, doq.C.T_STRING, format)
          if (r[0] === doq.C.CR_GOOD)
            return r[1]
        }
      }
      return defaultResult
    }

    Datanode.prototype.getAttributeAsNumber = function(attrName, failResult) {
      if (attrName in this['#']) {
        var attr = this['#'][attrName]
        if ('data' in attr)
          var r, t = attr['type'],
            d = attr['data']
        if (d === undefined)
          return failResult
        else {
          r = mgui.convert(t, d, doq.C.T_NUMBER, format)
          if (r[0] === doq.C.CR_GOOD)
            return r[1]
          else
            mgui.error('getAttributeAsNumber is failed [' + attrName + '] ' + r[2])
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

    Datanode.prototype.setAttribute = function(attrName, options) {
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
          res = mgui.parseExpression(attrExpression)
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
              binding = mgui.subscribe(pubPath + '#' + pubAttrName, doq.C.EV_CHANGE,
                thisScopeAttrPath + '$' + i, _changeEvaluatedBind)
              binding.paramNo = i
              
              binding.subDatanode = this
              binding.pubDatanode = n
              if (!!options.handlers)
                binding.handlers = options.handlers
              mgui.closePath(contextScopeStack)

              binding = mgui.subscribe(thisScopeAttrPath, doq.C.EV_PULL, thisScopeAttrPath, _pullEvaluatedBind)
              binding.subDatanode = this
              // Затем подписываемся на вычисление общего результата выражения и его публикацию сами к себе
              binding = mgui.subscribe(thisScopeAttrPath, doq.C.EV_UPDATE, thisScopeAttrPath, _updateEvaluatedBind)
              
              binding.subDatanode = this
              if (!!options.handlers)
                binding.handlers = options.handlers
            } // for each bindParams
            mgui.emit(this.path, attrName, doq.C.EV_PULL)
          } else {
            mgui.error(res[1])
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
          binding = mgui.subscribe(pubPath + '#' + pubAttrName, doq.C.EV_CHANGE, thisScopeAttrPath, _changeDirectBind)
          binding.subDatanode = this
          binding.pubDatanode = n
          if (!!options.handlers)
            binding.handlers = options.handlers
          mgui.closePath(contextScopeStack)

          binding = mgui.subscribe(thisScopeAttrPath, doq.C.EV_UPDATE, thisScopeAttrPath, _updateDirectBind)
          binding.subDatanode = this
          if (!!options.handlers)
            binding.handlers = options.handlers

          binding = mgui.subscribe(thisScopeAttrPath, doq.C.EV_PULL, thisScopeAttrPath, _pullDirectBind)
          binding.subDatanode = this
          if (!!options.handlers)
            binding.handlers = options.handlers
          mgui.emit(this.path, attrName, doq.C.EV_PULL) // сначала вытягиваем данные из источников
          break

        default: //no bind, change itself
          binding = this['#'][attrName]['changeBinding']
          if (!binding) {
            this['#'][attrName]['changeBinding'] = binding = mgui.subscribe(thisScopeAttrPath, doq.C.EV_CHANGE, thisScopeAttrPath, _changeItself)
            binding.subDatanode = this
          }
          if (!!options.handlers)
            binding.handlers = options.handlers

          binding = this['#'][attrName]['updateBinding']
          if (!binding) {
            this['#'][attrName]['updateBinding'] = binding = mgui.subscribe(thisScopeAttrPath, doq.C.EV_UPDATE, thisScopeAttrPath, _updateItself)
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
            return mgui.emit(this.path, attrName, doq.C.EV_CHANGE, params)
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
            mgui.error("При вычисляемом изменении " + binding.subPathAttr + " ошибка:" + params.error)
            return
          }
        }
        thisAttr.state = doq.C.US_VALIDATED
        mgui.postEmit(binding.subPathNode, subAttrName, doq.C.EV_UPDATE)
      }

      function _pullEvaluatedBind() {
        mgui.postEmit(this.subPathNode, this.subPathAttr, doq.C.EV_UPDATE)
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
          r = mgui.expressionElementHandlers[cmd](arg, stack, thisAttr.linkage)
          if ((r !== undefined) && (r[0] === false)) {
            mgui.error(r[1] + ' in ' + ne[2])
          }
        }
        if (stack.length === 1) {
          r = mgui.convert(stack[0][0], stack[0][1], thisAttr.type)
          if (r[0] === doq.C.CR_ERROR) {
            thisAttr['state'] = doq.C.US_EVALUATE_ERROR
            thisAttr['error'] = r[2]
            mgui.error("При вычисляемом обновлении " + binding.subPathAttr + " ошибка:" + r[2])
            return
          }
          params.newData = r[1]

          if ((!!binding.handlers) && (!!(c = binding.handlers['evaluate']))) {
            thisAttr['state'] = doq.C.US_EVALUATING
            r = c.call(thisDatanode, binding, thisAttr, params)
            if (r === false) {
              thisAttr['state'] = doq.C.US_EVALUATE_ERROR
              thisAttr['error'] = params.error
              mgui.error("При вычисляемом обновлении " + binding.subPathAttr + " ошибка:" + params.error)
              return
            }
          }
          // ЗАПИСЫВАЕМ НОВОЕ ВЫЧИСЛЕННОЕ ЗНАЧЕНИЕ!
          thisAttr['data'] = params.newData
        } else {
          thisAttr['state'] = doq.C.US_EVALUATE_ERROR
          thisAttr['error'] = "Формула содержит ошибки. В стеке осталось " + stack.length + " значений. Формула:" + thisAttr.linkage.formula
          mgui.error(thisAttr.error)
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
          r = mgui.convert(thisAttr.linkage.bindPubDataAttr['type'],
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
          if (r[0] == doq.C.US_VALIDATE_ERROR){
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
        mgui.postEmit(binding.subPathNode, binding.subPathAttr, doq.C.EV_UPDATE)
      
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
        mgui.postEmit(binding.subPathNode, binding.subPathAttr, doq.C.EV_UPDATE)
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

        doq.log('doq.DataNode','_changeItself[' + binding.subPath + ' (' + thisAttr['type'] + ')' + thisAttr['data'] +
          ' <= (' + params.newDataType + ')' + params.newData + ']')

        if (targetDataType === undefined) {
          thisAttr['type'] = (params.newDataType === undefined) ? doq.C.T_VARIANT : params.newDataType
        }

        if (params.newData !== undefined) {
          if (params.newDataType === undefined)
            params.newDataType = targetDataType
          r2 = mgui.convert(params.newDataType, params.newData, targetDataType, thisAttr) // r2[0]=status, r2[1]-convValue. r2[2]-errorMessage
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
            thisAttr['data']=params.newData
            r = [doq.C.US_VALIDATED]
          }
          if (r[0] == doq.C.US_VALIDATE_CORRECTED) {
            thisAttr['data'] = r[1] // значение изменено
            if (!!r[2]) 
              thisAttr['type'] = r[2]
          }
        } else {
          r = [doq.C.US_VALIDATED]
          thisAttr['data']=params.newData
        }

        thisAttr['state'] = r[0]
        mgui.postEmit(binding.subPathNode, binding.subPathAttr, doq.C.EV_UPDATE)
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
        
    
    function forEachChild (scopeStack, callback) {
      var i, scope = scopeStack.top
      if (!('@' in scope.datanode)) return false
      for (i in scope.datanode['@']) {
        callback(scopeStack, i)
      }
    }

    function copyObject (obj) {
      var i, r = {},
        n = Object.getOwnPropertyNames(obj),
        nn
      for (i in n)
        nn = n[i], r[nn] = obj[nn]
      return r
    }

    function setAttribute (thisScopeStack, attrName, options) {
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

        schemaClassName=schema['#nodeClass']
        if (!!schemaClassName) {
          if (typeof schemaClassName === 'object')
          schemaClassName=schemaClassName['data']
          if (schemaClassName in mgui.classes)
            schemaClass = mgui.classes[schemaClassName]
          if ('schema' in schemaClass)
            _buildNodeBySchema(datanode, schemaClass['schema'])
        }
        if((!!nodeClassName)&&(nodeClassName in mgui.classes)){
           nodeClass=mgui.classes[nodeClassName]
        }
        for (tAttrName in schema) {
          if (tAttrName.indexOf('#') === 0) {
            tAttrDefs = schema[tAttrName]
            // название атрибута без # в начале
            tcAttrName = tAttrName.substr(1)
            if ((!!nodeClass) && ('handlers' in nodeClass) && (tcAttrName in nodeClass['handlers'])) {
              handlers = mgui.copyObject(nodeClass['handlers'][tcAttrName])
            }
            if (typeof tAttrDefs === 'object') {
              tBindType = tAttrDefs['bind']
              if (tBindType !== undefined)
                tBindType = (tBindType in doq.C.TYPE_MAP) ? doq.C.TYPE_MAP[tBindType] : doq.C.BT_AUTO
              mgui.setAttribute(digScopeStack, tcAttrName, {
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
              mgui.setAttribute(digScopeStack, tcAttrName, {
                'data': tAttrData,
                'type': typeof tAttrData,
                'contextScopeStack': contextScopeStack,
                'handlers': handlers
              })
            }
          }
        }
      }
      
      var newPath=params.newPath, 
        createIfNE=params.createIfNE, 
        scopeStack=params.scopeStack, 
        failIfExist=params.failIfExist, 
        strictCreate=params.strictCreate, 
        contextScopeStack=params.contextScopeStack,
        newSchema=params.newSchema,
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
        datanode = mgui.model
        if (datanode === undefined) {
          datanode = mgui.model = new mgui.Datanode('/')
        }
        schemaNode = mgui.schema // может быть undefined
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
              schemaNode: schemaNode, path: path })
          } else {
            if (!createIfNE)
              return [false, "Узел '" + s + "' отсутствует в пространстве данных '" + path + "'"]
            // Узел отсутствует, но если указан флаг createIfNE, то создаем этот узел
            // с шаблонами класса из схемы, передавая datanode в качестве родительского
            datanode = datanode['@'][s] = new mgui.Datanode(path, datanode)
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
                if (cn in mgui.classes) {
                  nodeClass = mgui.classes[cn]
                  datanode['nodeClassName'] = cn
                  if ((nodeClass !== undefined) && ('methods' in nodeClass)) {
                    datanode.methods = nodeClass['methods']
                    if('create' in datanode.methods){
                      datanode.methods['create'].call(datanode, digScopeStack, contextScopeStack)
                    }
                  }
                } else {
                  mgui.error('Unknown Datanode class: ' + cn)
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
     * @param {Array} текущий стек пространства имен, включая ссылку на текущую схему
     * @param {boolean} выход с ошибкой, если узел уже есть
     * @param {boolean} создавать только в соответствии со схемой
     * @param {array} - контекст данных стек пространства имен модели с которой связываются формулы внутри узла
     * @param {object} - новая схема
     * @returns ScopeStack||Array[false,errorText]
     **/
    function openPath (newPath, createIfNE, scopeStack, failIfExist, strictCreate, contextScopeStack, newSchema) {
      var nodeClass, digScopeStack, schemaNode, i, scope, s, datanode, path,
        recordPath, j,  aPath = newPath.split('/')

      function _buildNodeBySchema(datanode, schema) {
        // extern vars: digScopeStack, contextScopeStack
        var tAttrName, tAttrDefs, tBindType, tcAttrName, tAttrData,
          schemaClass, schemaClassName,
          nodeClass, nodeClassName = datanode.nodeClassName,
          handlers = {}
        if (!('#' in datanode))
          datanode['#'] = {}

        schemaClassName=schema['#nodeClass']
        if (!!schemaClassName) {
          if (typeof schemaClassName === 'object')
          schemaClassName=schemaClassName['data']
          if (schemaClassName in mgui.classes)
            schemaClass = mgui.classes[schemaClassName]
          if ('schema' in schemaClass)
            _buildNodeBySchema(datanode, schemaClass['schema'])
        }
        if((!!nodeClassName)&&(nodeClassName in mgui.classes)){
           nodeClass=mgui.classes[nodeClassName]
        }
        for (tAttrName in schema) {
          if (tAttrName.indexOf('#') === 0) {
            tAttrDefs = schema[tAttrName]
            // название атрибута без # в начале
            tcAttrName = tAttrName.substr(1)
            if ((!!nodeClass) && ('handlers' in nodeClass) && (tcAttrName in nodeClass['handlers'])) {
              handlers = mgui.copyObject(nodeClass['handlers'][tcAttrName])
            }
            if (typeof tAttrDefs === 'object') {
              tBindType = tAttrDefs['bind']
              if (tBindType !== undefined)
                tBindType = (tBindType in doq.C.TYPE_MAP) ? doq.C.TYPE_MAP[tBindType] : doq.C.BT_AUTO
              mgui.setAttribute(digScopeStack, tcAttrName, {
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
              mgui.setAttribute(digScopeStack, tcAttrName, {
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
        datanode = mgui.model
        if (datanode === undefined) {
          datanode = mgui.model = new mgui.Datanode('/')
        }
        schemaNode = mgui.schema // может быть undefined
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
              schemaNode: schemaNode, path: path })
          } else {
            if (!createIfNE)
              return [false, "Узел '" + s + "' отсутствует в пространстве данных '" + path + "'"]
            // Узел отсутствует, но если указан флаг createIfNE, то создаем этот узел
            // с шаблонами класса из схемы, передавая datanode в качестве родительского
            datanode = datanode['@'][s] = new mgui.Datanode(path, datanode)
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
                if (cn in mgui.classes) {
                  nodeClass = mgui.classes[cn]
                  datanode['nodeClassName'] = cn
                  if ((nodeClass !== undefined) && ('methods' in nodeClass)) {
                    datanode.methods = nodeClass['methods']
                    if('create' in datanode.methods){
                      datanode.methods['create'].call(datanode, digScopeStack, contextScopeStack)
                    }
                  }
                } else {
                  mgui.error('Unknown Datanode class: ' + cn)
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
    mgui.closePath = function(scopeStack) {
      var l = scopeStack.length
      if (l > 0) scopeStack.pop()
      if (l > 1) scopeStack.top = scopeStack[l - 2]
      else scopeStack.top = undefined
    }

mgui.unbindByPub = function(pubPath, referencingSub, ignoreBindery) {
  var pubPathNode = pubPath,
    pubPathAttr = 'value',
    binding, r, i, j, k, e, n1, n2, allAttrs = true,
    m, node = mgui.bindery.byPub,
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
                mgui.unbindBySub(binding.subPath, true)
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

mgui.unbindBySub = function(subPath, ignoreBindery) {
  var subPathNode = subPath,
    subPathAttr = 'value',
    binding, r, i, j, k, e, n1, allAttrs = true,
    node = mgui.bindery.bySub,
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
          mgui.unbindByPub(binding.pubPath, binding.subPath, true)
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

mgui.parseExpression = function(str) {
  var C = mgui.C,
    symClasses = { '=': C.CC_OPERATOR, ',': C.CC_OPERATORCHAR, "'": C.CC_QUOTE, '"': C.CC_QUOTE, '-': C.CC_OPERATOR, '+': C.CC_OPERATOR, '*': C.CC_OPERATOR, '&': C.CC_OPERATOR, '{': C.CC_QUOTE, '(': C.CC_OPERATORCHAR, ')': C.CC_OPERATORCHAR, ' ': C.CC_SPACE, '\t': C.CC_SPACE, '\n': C.CC_SPACE, '\r': C.CC_SPACE },
    priorities = { '{': 0, '(': 0, '=': 2, ',': 1, '+': 5, '-': 5, '*': 7, '/': 7, '}': 9, ')': 9 },
    stack = [],
    out = [],
    bindings = [],
    res = _tokenize(str)
  if (res === true) {
    return [true, out, bindings]
  } else {
    return [false, 'Ошибка: ' + res[1] + ' в колонке ' + res[2] + '<br>' + str.substring(0, res[2] - 1) +
      '<font color="red"><b>' + str.substr(res[2] - 1, 1) + '</b></font>' + str.substr(res[2])
    ]
  }

  function _tokenize(src) {
    var r, pos = 0,
      l = src.length,
      ntChar, ctStart, C = mgui.C,
      ctClass = C.CC_NONE,
      ntClass, token, ctQuoteChar, prevTokenClass
    do {
      ntClass = C.CC_NONE
      ntChar = (pos < l) ? src.charAt(pos) : ''
      if (ctClass == C.CC_QUOTE) {
        if (ntChar == ctQuoteChar) {
          if (ntChar == '}') ctClass = C.CC_BIND
          else ctClass = C.CC_TEXT
        }
      } else {
        if (ntChar == '') ntClass = C.CC_EOT
        else if (ntChar in symClasses) ntClass = symClasses[ntChar]
        else if (((ntChar >= '0') && (ntChar <= '9')) || (ntChar == '.')) ntClass = C.CC_NUMBER
        else ntClass = C.CC_SYMBOL
        if (ntClass == C.CC_QUOTE) ctQuoteChar = (ntChar == '{') ? '}' : ntChar
        if ((ntClass != ctClass) || (ctClass == C.CC_OPERATORCHAR)) {
          if ((ctClass !== C.CC_NONE) && (ctClass !== C.CC_SPACE)) {
            token = ((ctClass == C.CC_TEXT) || (ctClass == C.CC_BIND)) ? src.substring(ctStart + 1, pos - 1) : src.substring(ctStart, pos)
            r = pushToRPN(ctClass, token, prevTokenClass, pos)
            if (r !== undefined) return r
          }
          if (ctClass !== C.CC_SPACE) prevTokenClass = ctClass
          ctClass = ntClass
          ctStart = pos
        }
      }
      pos++
    } while (ntChar != '')

    while (stack.length > 0) {
      r = stack.pop()
      if (r[0] == C.E_OPENEVAL) return [false, 'Лишняя открывающая скобка', r[2]]
      out.push(r)
    }
    return true
  }

  function pushToRPN(tokenClass, token, prevTokenClass, srcPos) {
    var paramNo, opener, p1, p2, topStackOperator, C = mgui.C
    if ((tokenClass == C.CC_SYMBOL) || (tokenClass == C.CC_TEXT) || (tokenClass == C.CC_BIND) || (tokenClass == C.CC_NUMBER)) {
      if (tokenClass == C.CC_TEXT) {
        out.push([C.E_TEXT, token, srcPos])
      } else if (tokenClass == C.CC_BIND) {
        //openPath(token,true,mScopeStack)
        //out.push([C.E_BIND,mScopeStack.top.path,srcPos])
        //mgui.closePath(mScopeStack)
        paramNo = bindings.indexOf(token)
        if (paramNo == -1) {
          bindings.push(token)
          paramNo = bindings.length - 1
        }
        out.push([C.E_BIND, [token, paramNo], srcPos])

      } else if (tokenClass == C.CC_NUMBER) {
        out.push([C.E_NUMBER, Number(token), srcPos])
      } else {
        out.push([C.E_SYMBOL, token, srcPos])
      }
    } else if ((tokenClass == C.CC_OPERATOR) || (tokenClass == C.CC_OPERATORCHAR)) {
      if (token == '(') {
        //if ((out.length>0)&&(out[out.length-1][0]==C.E_SYMBOL)) {
        if (prevTokenClass == C.CC_SYMBOL) {
          stack.push([C.E_OPENFUNC, token, srcPos])
        } else {
          stack.push([C.E_OPENEVAL, token, srcPos])
        }
      } else if (token == ')') {
        while ((stack.length > 0) && (stack[stack.length - 1][1] !== '(')) {
          out.push(stack.pop())
        }
        if (stack.length == 0) {
          return [false, 'Лишняя закрывающая скобка', srcPos]
        }
        opener = stack.pop()
        if (opener[0] == C.E_OPENFUNC) {
          out.push([C.E_CALLFUNC, 'popAndExecIt!'])
        }
      } else {
        p1 = priorities[token]
        while (stack.length > 0) {
          topStackOperator = stack[stack.length - 1]
          p2 = topStackOperator[3]
          if (p1 <= p2) {
            out.push(stack.pop())
          } else break;
        }
        stack.push([C.E_OPERATOR, token, srcPos, priorities[token]])
      }
    } else {
      return [false, "Неправильное выражение", srcPos]
    }
    return
  }

  function outAsText() {
    var i, e, s, r = []
    for (i in out) {
      e = out[i]
      s = '<td>' + e[0] + '</td><td>' + e[1] + '</td><td>#' + e[2] + '</td>'
      if (!!e[3]) s += '<td>^' + e[3] + '</td>'
      r.push('<tr>' + s + '</tr>')
    }
    return '<table cellpadding=5 border=1>' + r.join('\n') + '</table>'
  }
}

/** @param srcType - тип из которого делается преобразование
 * @param srcValue int - исходное значение
 * @param dstType - целевой тип
 * @param stringFormat - хэш параметров форматирования результата (обычно это хэш целевого атрибута)
 * @param inputMode - указывает, что преобразование происходит для отображения
 *   в поле ввода. Соответственно, игнорируются  лишние форматирующие символы
 * @return [status, convertedValue, errorMessage]
 */
mgui.convert = function(srcType, srcValue, dstType, stringFormat, inputMode) {
  if (dstType == srcType)
    return [doq.C.CR_GOOD, srcValue]

  var r, v, ci, c2, lim, c, s, els, j, i, k, y, m, d, da, daLength, parts, t, tmpDate,
    formatLength, isNullable = 0,
    separator, scale = 0,
    thousandsSeparator, decimalSeparator

  thousandsSeparator = mgui.lang.base['THOUSANDS_SEPARATOR']
  decimalSeparator = mgui.lang.base['DECIMAL_SEPARATOR']
  if (stringFormat !== undefined) {
    if ('nullable' in stringFormat)
      isNullable = Boolean(stringFormat['nullable'])
    if ('thousandsSeparator' in stringFormat)
      thousandsSeparator = stringFormat['thousandsSeparator']
    if ('decimalSeparator' in stringFormat)
      decimalSeparator = stringFormat['decimalSeparator']
  }

  if (srcType === doq.C.T_VARIANT)
    srcType = (typeof(srcValue) == 'number') ? doq.C.T_NUMBER : srcType = doq.C.T_STRING

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
            mgui.error('Ошибка внутреннего представления даты: ' + srcValue)
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


mgui.getOperandValue = function(operand) {
  var datanodeAttr, value, type = operand[0]
  if (type == doq.C.T_BINDREF) {
    datanodeAttr = operand[1]
    if ('value' in datanodeAttr) {
      value = datanodeAttr['value']
    } else {
      if ('data' in datanodeAttr)
        value = datanodeAttr['data']
      else
        value = doq.C.T_UNDEFINED
    }
    type = datanodeAttr['type']
  } else {
    value = operand[1]
  }
  return [type, value]
}


mgui.expressionElementHandlers = {}
mgui.expressionElementHandlers[doq.C.E_BIND] = function(arg, stack, linkage) {
  var bindPubDatanodes = linkage.bindPubDatanodes,
    paramNo = arg[1] //paramNo
  var node = bindPubDatanodes[paramNo]
  if (!node)
    stack.push([doq.C.T_UNDEFINED, '(' + arg[0] + ' is undefined)'])
  else
    stack.push([doq.C.T_BINDREF, node])

}
mgui.expressionElementHandlers[doq.C.E_TEXT] = function(arg, stack) {
  stack.push([doq.C.T_STRING, arg])
}
mgui.expressionElementHandlers[doq.C.E_OPERATOR] = function(arg, stack) {
  var op1, op2, r1, r2, value, r, rtype
  switch (arg) {
    case ',':
      op2 = stack.pop()
      op1 = stack.pop()
      if (op1[0] == doq.C.T_TUPLE) {
        op1[1].push(op2)
        stack.push(op1)
      } else {
        op1 = [op1, op2]
        stack.push([doq.C.T_TUPLE, op1])
      }
      return
    case '=':
      if (stack.length < 2) return [false, 'Для операции сравнения = необходимо два аргумента']
      op2 = stack.pop() // не проверяем типы. Пусть javascript сам приводит типы
      op1 = stack.pop()
      stack.push([doq.C.T_NUMBER, (op1[1] == op2[1]) ? 1 : 0])
      return
    case '+':
      if (stack.length < 2) return [false, 'Для операции + необходимо два аргумента']
      op2 = stack.pop()
      op1 = stack.pop()
      // preffered - желаемый тип результата;
      // если операнд слева - строка, то r1[0] будет строкой и сложение будет уже
      // предпочтительно строковым
      r1 = mgui.getOperandValue(op1)
      r2 = mgui.getOperandValue(op2)
      rtype = r1[0] // обычно сумма двух значений должна приводиться к типу операнда слева

      if ((r1[0] == doq.C.T_STRING) || (r2[0] == doq.C.T_STRING)) {
        // Если левый или правый операнд - текст, то приводим к строковому типу
        rtype = doq.C.T_STRING
        if (r1[0] == doq.C.T_DATE) {
          if (!!r1[1]) {
            r = mgui.convert(doq.C.T_DATE, r1[1], doq.C.T_STRING)
            if (r[0] == doq.C.CR_GOOD)
              value = r[1]
            else
              value = '[' + r[2] + ']'
          } else value = ''
        } else if (r1[0] == doq.C.T_NUMBER) {
          value = mgui.convert(doq.C.T_NUMBER, r1[1], doq.C.T_STRING)
        } else if (r2[0] == doq.C.T_INT) {
          value = '' + r1[1]
        } else
          value = r1[1]
        if (r2[0] == doq.C.T_DATE) {
          if (!!r2[1]) {
            r = mgui.convert(doq.C.T_DATE, r2[1], doq.C.T_STRING)
            if (r[0] == doq.C.CR_GOOD)
              value += r[1]
            else
              value += '[' + r[2] + ']'
          } // else добавляем пустую дату, то есть, ничего
        } else if (r2[0] == doq.C.T_NUMBER) {
          r = mgui.convert(doq.C.T_NUMBER, r2[1], doq.C.T_STRING)
          if (r[0] == doq.C.CR_GOOD)
            value += r[1]
          else
            value += '[' + r[2] + ']'
        } else if (r2[0] == doq.C.T_INT) {
          value += '' + r2[1]
        } else
          value += r2[1]
      } else {
        // Если ни тот, ни другой операнд не являются текстом, то просто складываем значения
        value = r1[1] + r2[1]
      }
      stack.push([rtype, value])
      return
  }
}


mgui.expressionElementHandlers[doq.C.E_SYMBOL] = function(arg, stack) {
  stack.push([doq.C.T_STRING, arg])
}

mgui.expressionElementHandlers[doq.C.E_CALLFUNC] = function(arg, stack) {
  var fnArgs = stack.pop()
  if (!fnArgs) return [false, 'Вызов непонятной функции']
  var fn = stack.pop()
  var fnName = fn[1]
  if (fnName in mgui.expressionFunctions) {
    return mgui.expressionFunctions[fnName](fnArgs, stack)
  } else {
    return [false, 'Неизвестная функция ' + fnName]
  }
}

mgui.expressionElementHandlers[doq.C.E_NUMBER] = function(arg, stack) {
  stack.push([doq.C.T_NUMBER, arg])
}

mgui.expressionFunctions = {}

mgui.expressionFunctions['iif'] = function(fnArgs, stack) {
  var r
  if (fnArgs[0] != doq.C.T_TUPLE) return [false, 'Функция iif должна иметь три аргумента']
  var tuple = fnArgs[1]
  if (tuple.length != 3) return [false, 'Функция iif имеет ' + tuple.length + ' аргументов, а надо всего три']
  r = mgui.getOperandValue(tuple[0], doq.C.T_NUMBER)
  if (r[1] === 0) {
    stack.push(tuple[2])
  } else {
    stack.push(tuple[1])
  }
}

mgui.expressionFunctions['sum'] = function(fnArgs, stack) {
  if (fnArgs[0] == doq.C.T_TUPLE) {
    var i, v, tuple = fnArgs[1]
    v = (tuple[0][0] == doq.C.T_NUMBER) ? 0 : ''
    for (i in tuple) {
      if ((tuple[i][0] == doq.C.T_NUMBER) || (tuple[i][0] == doq.C.T_INT))
        v += tuple[i][1]
      else
        v += Number(tuple[i][1])
    }
    stack.push([doq.C.T_NUMBER, v])
  } else {
    stack.push([doq.C.T_NUMBER, Number(fnArgs[1])])
  }
}


    mgui.showPage = function(pageId, callbackOnReady) {
      var page, r,
        vmScopeStack = openPath('/vmodel/pager', true),
        mScopeStack = openPath('/', true),
        pageNode = vmScopeStack.top.datanode,
        el = pageNode.htmlElement
      if (!el)
        el = pageNode.htmlElement = mgui.guiContainer
      page = mgui.pages[pageId]
      if (!!page) {
        r = mgui.openPath(pageId, 1, vmScopeStack, 1, 1, mScopeStack, page)
        if (!r[0])
          mgui.error(r[1])
      }
      if (!!callbackOnReady)
        mgui.taskDoneCallbacks.push(callbackOnReady)

    }

    
    
    return {
        functions:[Datanode, forEachChild,copyObject,setAttribute,openContext, openPath],
        exports:{CONST_1:CONST_1},
        css: {
            uses:['a','li','#panel'],
            vars:{
                '@inputCcolor':'#ff00a0',
                '@okColor': 'red'
            },
            '.btn': 'color:red; font-size:15pt;',
            '.inputs': {
            _ : 'color:@inputColor; font-size:10pt',
            'a' : 'text-decoration:none; font-weight:bold; color:@okColor',
            'a:hover': 'text-decoration:underline'
            }
        }
    }
})
