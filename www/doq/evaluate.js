doq.module('doq.evaluate',function evaluate(){
/* jshint asi:true, -W100, forin:false, sub:true */
    console.log('Module evaluate: executed')
    /**
     * Класс вычислителя
     * @constructor
     */
    function Expression() {
        doq.log('Module evaluate: expression constructor is called')
        this.isValid=1
    }

    return {
        functions:[Expression]
    }
})