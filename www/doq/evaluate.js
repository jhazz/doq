doq.module('evaluate',function evaluate(){
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
        functions:[Expression]}
})
