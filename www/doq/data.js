doq.module('doq.data', ['doq.evaluate'], function(){
    var CONST_1='123'

    /**
    * Класс узла данных
    * @constructor
    * @param {string} path путь к узлу
    * @param {Datanode} parentNode родительский узел
    */
    function Datanode(path, parentNode) {
        doq.log('Module data: expression constructor is called')
        var s=this
        s.path = path
        s.parentNode = parentNode
        s['#'] = {}
    }

    Datanode.prototype.getNode=function(){
        return 'The node'
    }

    return {
        functions:[Datanode],
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
