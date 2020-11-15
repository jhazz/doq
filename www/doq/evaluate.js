/* jshint asi:true, -W100, forin:false, sub:true */

doq.module('doq.evaluate',function(){
    var expressionFunctions = {}
    var expressionElementHandlers = {}

    console.log('Module evaluate: executed')

    /**
     * Transfrom expression atring to RPN elements and bindings to parameters
     * @param {String} str evaluating string of any expression
     * @return [ok, expressionRPN, bindParams]
     */
    function parseExpression (str) {
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

    
    function getOperandValue (operand) {
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


    
    expressionElementHandlers[doq.C.E_BIND] = function (arg, stack, linkage) {
        var bindPubDatanodes = linkage.bindPubDatanodes,
            paramNo = arg[1] //paramNo
        var node = bindPubDatanodes[paramNo]
        if (!node)
            stack.push([doq.C.T_UNDEFINED, '(' + arg[0] + ' is undefined)'])
        else
            stack.push([doq.C.T_BINDREF, node])

    }

    expressionElementHandlers[doq.C.E_TEXT] = function (arg, stack) {
        stack.push([doq.C.T_STRING, arg])
    }


    expressionElementHandlers[doq.C.E_OPERATOR] = function (arg, stack) {
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
                r1 = getOperandValue(op1)
                r2 = getOperandValue(op2)
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


    expressionElementHandlers[doq.C.E_SYMBOL] = function (arg, stack) {
        stack.push([doq.C.T_STRING, arg])
    }

    expressionElementHandlers[doq.C.E_CALLFUNC] = function (arg, stack) {
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

    expressionElementHandlers[doq.C.E_NUMBER] = function (arg, stack) {
        stack.push([doq.C.T_NUMBER, arg])
    }


    expressionFunctions['iif'] = function (fnArgs, stack) {
        var r
        if (fnArgs[0] != doq.C.T_TUPLE) return [false, 'Функция iif должна иметь три аргумента']
        var tuple = fnArgs[1]
        if (tuple.length != 3) return [false, 'Функция iif имеет ' + tuple.length + ' аргументов, а надо всего три']
        r = getOperandValue(tuple[0], doq.C.T_NUMBER)
        if (r[1] === 0) {
            stack.push(tuple[2])
        } else {
            stack.push(tuple[1])
        }
    }

    expressionFunctions['sum'] = function (fnArgs, stack) {
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

    return {
        functions:[parseExpression, expressionElementHandlers]
    }
})
