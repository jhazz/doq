/**
 * @fileoverview The main module file
 * Loads in the topmost window and carry all libraries using by the child frames
 * @author Vlad Zbitnev
 *
 *
 * TODO NEXT
 * 1. Публикация событий
 * 2. Добавить подписку на удаление панели (destroy) к сплитерам, чтобы чувствовали удаление
 * 3. Добавить сплитер к обычной, не 'A'-панели, То есть до docSet'a
 * 4. Сделать аналогичное для набора панелей (A-docSet'a)
 *
 *
 */

/* jslint eqeqeq:false, indent: 4, maxerr: 230, white: true, browser: true, evil: true, nomen: true, plusplus: true, sloppy: true */
/* jshint eqeqeq:false, curly:true */



/**
 * @type Boolean Overridden to true by the compiler when --closure_pass
 *     or --mark_as_compiled is specified.
 */
var COMPILED = true;
/**
 * @define {boolean} May be exluded by compiler
 */
var CREATE_CONSOLE = true;

/**
 * @type Boolean Compilation directive to leave debug messages in code
 */
var DEBUG = 1;


/** @type {Object} */
var jsf;
/**  @namespace */
var jsa = {
  /** @type {Object} */
  modules: {},
  /** @type {Object}*/
  moduleLoaders: {},
  /** @type {Object}*/
  classesByName: {},
  /** @type Array.<Object>*/
  stages: [],
  /** @type Array.<Function>*/
  actionByCode: [],
  /** @type {Object} */
  actionByName: {},
  /** @type {number|null} */
  time: 0,
  /** @type {Object|integer} */
  isAppWindow: 1,
  /** @type {Window} */
  win: window,
  /** @type {Document} */
  doc: document,
  /** @type {Object} dependencies */
  deps: {},
  /** @type Array.<Object> registered IFrames by registerIFrame */
  frames: {},
  /** @type {Object}*/
  actions: {},
  /** @type {string} Path to the library*/
  LIB_URL: "src/",
  /** @type {number} *Default time interval for newest timelines in msec (100ms=10fps) */
  STAGE_TIMER_INTERVAL: 2000,
  /** @type {number} adds random number to url query */
  UNCACHEABLE_URLS: 1,
  /** @type {number} uid */
  lastUID: 0,
  nullFunction: function() {},
  name: 'jsa',
  subscribers: {},
  /**
   * Executes on class ready after load or inline
   * @param {Object} classDef Class object in Ext manner
   * @param {String} classDef.clsName class name
   * @param {Array} classDef.deps array of module urls/names the class depends on
   * @param {Function} classDef.inherits
   * @param {Function} classDef.constructor
   * @return {Object}
   * ВНИМАНИЕ! define определяет как модули так и классы
   */
  define: function(classDef) {
    if (!classDef.constructor) {
      classDef.constructor = function() {
      };
    }
    if (classDef.methods) {
      for(var m in classDef.methods) {
        classDef.constructor.prototype[m] = classDef.methods[m];
      }
    }
    if (classDef.inherits) {
      jsa.inherits(classDef.constructor, classDef.inherits.constructor);
    }
    if (DEBUG && (!classDef.clsName)) {
      throw new Error("Class should has a .clsName value defining a class namespace");
    }
    return classDef.constructor;
  },
  /**
   * @param {!Object} childConstructor that parentConstructor prototype applying to
   * @param {!Object} parentConstructor gives prototype methods to child
   * to call inherited do this:
   *  {myClass}.superClass.{inheritedMethod}.call(this);
   */
  inherits: function(childConstructor, parentConstructor) {
    function TempConstructor() {
    }
    TempConstructor.prototype = parentConstructor.prototype;
    childConstructor.superClass = parentConstructor.prototype;
    childConstructor.prototype = new TempConstructor();
    childConstructor.prototype.constructor = childConstructor;
  },
  /**
   * Copy hash from source to destination
   * @param {any} destination hash that values to be copied to
   * @param {any} source hash
   */
  copy: function(destination, source) {
    var i;
    if (typeof source == 'object') {
      for (i in source) {
        if (source.hasOwnProperty(i)) {
          destination[i] = source[i];
        }
      }
    } else {
      destination = source;
    }
    return destination;
  },
  /**
   * Add event dispatcher to any DOM object
   * @param {HTMLElement} target element listener assigns to
   * @param {string} eventName name (i.e. 'load','click','mouseover')
   * @param {function} callback recieves event
   */
  on: function(target, eventName, callback) {
    eventName = eventName.toLowerCase();
    if (!target) {
      target = document;
    }
    if (!!target.addEventListener) {
      target.addEventListener(eventName, callback, true);
    } else {
      target.attachEvent('on' + eventName, callback);
    }
  },
  /**
   * Loads js file
   * @param {string} jsPath to the loading script
   * @param {string} name of the module
   * @param {Object} doNext Actions runs after loading module if success or fail
   * @param {(string|Array)=} doNext.fail Action that runs on fail the loading
   * @param {(string|Array)=} doNext.run Action that runs on successful loading
   * @return {Object} loader record
   */
  loadJS: function(jsPath /* @String */, name, doNext) {
    var s, doc = jsa.doc, jsElement = doc.createElement("script"), loader;

    if (!name) {
      name = jsPath;
    }
    jsElement.type = 'text/javascript';
    jsElement.onload = jsElement.onreadystatechange = function() {
      /** @this {Element} */
      if (loader.loading && (!this.readyState || this.readyState == "loaded" || this.readyState == "complete")) {
        this.onreadystatechange = this.onload = "";
        loader.loading = 0;
        loader.success = 1;
        if (!!loader.run) {
          jsa.run(loader.run);
        }
      }
    };
    doc.getElementsByTagName("head")[0].appendChild(jsElement);
    s = jsPath + ((jsa.UNCACHEABLE_URLS) ? ((jsPath.indexOf('?') + 1) ? '&' : '?') + '~=' + Math.random() : "");
    jsElement.src = s;

    /** @this {Element} */
    loader = jsa.moduleLoaders[name] = {
      name: name,
      jsDOMElement: jsDomElement,
      jsPath: jsPath,
      loading: 1,
      success: 0
    };
    jsa.mixin(loader, doNext, 1);
    return loader;
  },
  getUID: function(prefix) {
    return (prefix || "id") + (jsa.lastUID++);
  },
  /**
   * Push act object to the stage
   * @param {Object} act What should to run next tick
   * @param {jsa.Stage} act._stage timeline aggregator
   */
  pushToStage: function(act) {
    var stageId, stage;
    if (act._stage) {
      stage = act._stage;
    } else {
      stageId = act._stageId;
      if (!stageId) {
        act._stageId = stageId = 'Stage1';
      }
      stage = jsa.stages[stageId];
      if (!stage) {
        stage = jsa.createStage(stageId);
      }
    }
    act.stage = stage;

    // Действия делятся на те, которые выполняются в конце (after) и остальные в обычном временной линии
    if (act.aidAfter) {
      stage.timelineAfter[act.aidAfter] = act;
    } else {
      if (!act.aid) {
        act.aid = jsa.getUID("a");
      }
      stage.timeline[act.aid] = act;
    }


    if (!stage.hTimer) {
      jsa.runStage(stage);
    }
    return act;
  },
  /**
   * Put log data in debug environment
   * @param {String} stageId Stage identificator
   * @param {Number=} timerInterval timing interval between refreshment
   * @param {HTMLElement} targetHtmlElement main html element the stage will be exposed in
   * @returns {jsa.Stage}
   */
  createStage: function(stageId, timerInterval, targetHtmlElement) {

    return jsa.stages[stageId] =
      /** @lends jsa.Stage.prototype */
        {
          stageId: stageId,
          hTimer: 0,
          targetHtmlElement: targetHtmlElement || jsa.doc,
          timerInterval: timerInterval || jsa.STAGE_TIMER_INTERVAL,
          timeline: {},
          timelineAfter: {} // для вызова различных обновлений в конце отрисовки кадра
        };
    },
  /**
   *
   * @param {jsa.Stage} stage
   */
  runStage: function(stage) {
    stage.hTimer = jsa.win.setInterval(function() {
      jsa.stageTick(stage);
    }, stage.timerInterval);
  },
  runStageId: function(stageId) {
    var stage = jsa.stages[stageId];
    if ((!!stage) && (!stage.hTimer)) {
      jsa.runStage(stage);
    }
  },
  stageTick: function(stage) {
    var act, n = 0, i, r, eliminate = [];
    if (stage.targetHtmlElement) {
      for (i in stage.timeline) {
        act = stage.timeline[i];
        n++;
        if (typeof (act.f) == 'function') {
          //try{
          r = (act.f)(act);
          //}catch(e){window.status='Error in action '+e.message;}
          if (r != 'continue') {
            if (!!act.next) {
              jsa.run(act.next, r); // передаю аргументом то, что получил до этого
            }
            eliminate.push(act);
          }
        }
      }
      n = eliminate.length;
      if (n) {
        while ((act = eliminate.pop())) {
          delete stage.timeline[act.aid];
          n--;
        }
      }
      for (i in stage.timelineAfter) {
        act = stage.timelineAfter[i];
        n++;
        if (typeof (act.f) == 'function') {
          r = (act.f)(act);
          if (r != 'continue') {
            if (!!act.next) {
              jsa.run(act.next, r); // передаю аргументом то, что получил до этого
            }
            eliminate.push(act);
          }
        }
      }
      n = eliminate.length;
      if (n) {
        while ((act = eliminate.pop())) {
          delete stage.timelineAfter[act.aidAfter];
          n--;
        }
      }
    }
    if (!n) {
      jsa.win.clearInterval(stage.hTimer);
      stage.hTimer = 0;
    }
  },
  /**
   * Asynchrous call the action or actions. If action is a set of actions run
   * fires these actions independently without control. If action class
   * unavailable it enqueue action to the timeline
   *
   * @param {(string|Array|number|Function)} action name (i.e. 'ui.control.Button.hide') or action[]
   * @param action Arguments of the action that pushes to the actions chain
   *   @param {Number=} action.start Delay to deferred start in msec
   *   @param {Number=} action.timeout Maximum delay after start to break the process
   *       and follow the fail chain
   *   @param {(string|Array|null)} action.fail Action if fail (if error or timeout occured)
   *   @param {(string|Array|null)} action.next Action if action is done. Action may
   *       repeatedly call itself and goes next after a while
   *   @param {Number=} act.f action reference to the calling Function
   *   @param {Number=} act.c action numeric code (only if act.n is not set)
   *   @param {String=} act.n action name using (only if act.c is not set)
   *   @param {String=} act.aid means that action must be called only once per tick! [aid] is unique tag of action process. If [aid] already exist on the stage action will not repushed
   *   @param {String=} act.aidAfter means that action must be called only once per tick after all act.aid
   *   @param {Object=} act.jsf - frame the run called from
   *   @param {String=} act.stageId stage of timelines with its own framerate and
   *       timer. Be aware from multiple stages
   * @this {jsa}
   * 	@returns {0|1|2} 0-fail, 1-deferred load, 2-enqueued
   */
  run: function(action, target) {
    var ac, an, act = jsa.copy({target: target}, action), f = act.f, s;
    if (act.start !== undefined) {
      act._startTime = jsa.time + act.start;
    }
    if (!f) {
      if ((ac = act.c)) {
        act.f = f = jsa.actionByCode[ac];
      } else {
        if ((an = act.n)) {
          act.f = f = jsa.actionByName[an];
          if (!f) {
            var parts = an.split('.'),
              method = parts.pop(),
              moduleName = parts.join('.'),
              jsPath = parts.join('/').toLowerCase();
            if (!jsa.modules[moduleName]) {
              // Deferred call
              // Module with this namespace like 'ui.control.Button' has not been loaded
              // Let's check if it does not loading  '{libURL}/ui/control/button.js'
              if (!jsa.moduleLoaders[moduleName]) {
                // if module file didn't enqueued to loading let's load it
                act.stillLoading = 1;
                s = jsa.LIB_URL + jsPath + ".js";
                if (DEBUG) {
                  jsa.console.log("Loading script " + s);
                }
                jsa.loadJS(s, moduleName, {run: act, fail: act.fail});
                return 1;
              }
            } else {
              if (DEBUG) {
                jsa.console.log('jsa.run error: Module ' + moduleName + ' has been loaded but action named as ' + an + ' is undefined');
              }
              return 0;
            }
          }

        } else {
          if (DEBUG) {
            jsa.console.log('run: Undefined action');
          }
          return 0;
        }
      }
    }
    jsa.pushToStage(act);
    return 2;
  },
  /**
   * @param {String} tpl html template with {} expressions
   * @param {Object=} scopeObject object that provide its vars or methods
   */
  parsedHTML: function(tpl, scopeObject) {
    return tpl.replace(/\{([^}]+)\}/g, function(j, i) {
      /** @ignore - google closure warns about using keyword with() */
      /** don't use jslint */
      with (scopeObject) {
        try {
          return eval('(' + i + ')');
        } catch (x) {
          return "{" + i + " " + x.message + "}";
        }
      }
    });
  },
  /**
   * @param {String} id name of the tag, i.e. div
   * @param {Object=} attrs list of tag attributes
   * @param {HTMLElement=} into HTML element to put newly created element inside
   * @param {String=} tpl html template
   * @param {Object=} scopeObject object that provide its vars or methods. If object is none the tpl kept unparsed
   * @returns undefined
   */
  createDiv: function(id, attrs, into, tpl, scopeObject) {
    var s, i, j, c = ((!!into) ? into.ownerDocument : jsa.doc).createElement('div');
    c.setAttribute('id', id);
    if (attrs) {
      for (i in attrs) {
        s = attrs[i];
        if (i == 'style') {
          for (j in s) {
            c.style[j] = s[j];
          }
        } else {
          c.setAttribute(i, s);
        }
      }
    }
    if (!!tpl) {
      c.innerHTML = (!scopeObject) ? tpl : jsa.parsedHTML(tpl, scopeObject);
    }
    if (!!into) {
      into.appendChild(c);
    }
    return c;
  },
  registerFrame: function(window, frameName) {
    if (!jsa.frames[frameName]) {
      return new jsa.Frame(window, frameName);
    } else {
      jsa.console.error("jsa.registerFrame: repeated resistration of frame " + frameName);
      return false;
    }
  },
  /**
   * Subscription of subObj.subMethod to pubObj.<pubEvent>
   * fills jsa.subscribers[pubObjNames][subObjNames][eventNames]=[subObj,subMethod]
   * @param {object} pubObj publisher Object that emits events
   * @param {string} eventName shortened event name
   * @param {object} subObj subscriber object that subscribing to publishing event notify
   * @param {function} subMethod subscriber object method activating by callback
   * @return {boolean} success
   */
  sub: function(pubObj, eventName, subObj, subMethod) {
    var v, evs, subs;
    if (!pubObj.id) {
      jsa.console.error('sub: pubObj has no id');
      return false;
    }
    if (!subObj.id) {
      jsa.console.error('sub: subObj has no id');
      return false;
    }
    subs = jsa.subscribers[pubObj.id];
    if (!subs) {
      subs = jsa.subscribers[pubObj.id] = {};
    }
    evs = subs[subObj.id];
    if (!evs) {
      evs = subs[subObj.id] = {};
    }
    v = evs[eventName];
    if (!v) {
      v = evs[eventName] = [subObj, subMethod];
    }
    return true;
  },
  pub: function(pubObjId, eventName, eargs) {
    var sObjName, evs, subs = jsa.subscribers[pubObjId], v;
    if (subs) {
      for (sObjName in subs) {
        evs = subs[sObjName];
        v = evs[eventName];
        if (!!v) {
          try {
            (v[0])[v[1]](eargs);
          } catch (e) {
            jsa.console.error("jsa.pub(): trying to execute unhandled subscriber " + sObjName + " for event" + eventName);
            debugger;
          }

          // jsa.run({_:v[0],f:v[1],args:eargs})
        }
      }
    }
  },
  eventPublisher: function(e) {
    var t = e.type, el = srcel = (e.target || e.srcElement), id = el.getAttribute('jsa_id');
    while ((id == null) && (el.nodeName != 'BODY') && (el.nodeName != 'HTML')) {
      el = el.parentElement;
      id = el.getAttribute('jsa_id');
    }
    if (id != null) {
      jsa.pub(id, e.type, e);
    }
  }
};


/**
 * @class jsa.GridDataProvider
 */
jsa.GridDataProvider = function() {
  this.name = jsa.getUID('dgp');
  this.data = {};
};
jsa.GridDataProvider.prototype = {
  pasteData: function(destRange, data) {
    var r, c, srcRow, ic, row, dr = destRange[0], dc = destRange[1];
    for (r in data) { //0..rowMax-1
      srcRow = data[r];
      row = this.data[dr];
      if (!row) {
        row = this.data[dr] = {};
      }
      for (c in srcRow) {
        ic = parseInt(c);
        row[dc + ic] = {value: srcRow[c]};
      }
      dr++;
    }
    jsa.pub(this.id, 'afterChange', {range: destRange});
  }
};

/*
 * @class jsa.Frame
 * Environment
 */
jsa.Frame = jsa.define({
  clsName: 'jsa.Frame',
  constructor: function(window, name) {
    var me = this;
    me.jsa = window.top.jsa;
    me.name = name;
    me.win = window;
    me.doc = window.document;
    me.jsa.frames[name] = me;
    me.ownedObjects = {};

    jsa.on(me.doc, 'selectstart', function(e) {
      if ((e.target || e.srcElement).getAttribute('selectable') === null) {
        e.cancelBubble = true;
        return false;
      }
      return true;
    });


    jsa.on(me.doc, 'mousedown', jsa.eventPublisher);
    jsa.on(me.doc, 'mouseup', jsa.eventPublisher);
    //jsa.on(me.doc,'mousemove', jsa.eventPublisher);
    /*
     function(e){
     var srcId=e.srcElement.getAttribute('id');
     if(!!srcId) {
     jsa.console.log('Pub event mouseDown from '+srcId);
     jsa.pub(srcId,'mouseDown',e);
     }
     });
     */

  },
  methods: {
    /*
     replaceConsole:function(){
     if(CREATE_CONSOLE){
     console=jsa.console;
     }
     },
     */
    run: function(act) {
      /** @this jsa.Frame */

      act.jsf = this;
      return jsa.run(act);
    },
    find: function(id) {
      /** @this jsa.Frame */
      return this.doc.getElementById(id);
    }
  }
});


if (CREATE_CONSOLE) {
  /** @class jsa.Console */
  jsa.Console = function() {
    /** @type {Array} **/
    this.logData = [];
    this.lastAdded = 0;
    this.name = "jsa.console";
  };
  jsa.Console.prototype = {
    init: function(aTargetWindow, container) {
      var _ = this;
      _.win = aTargetWindow || window;
      _.doc = _.win.document;
      _.options = {mainMenuHeight: 20, monitorSize: 0.3, logSize: 0.4, pBorderSize: 10, pSpacing: 5};
      _.container = container || _.doc.body;
      _.timers = {};
      _.curGroupEntry = 0;
      _.curGroupIndent = 0;
      _.consoleMonitor =
        jsa.createDiv('consoleMonitor', {
          style: {
            position: 'absolute',
            left: '70%',
            top: 0,
            width: '30%',
            height: '400px',
            overflow: 'hidden',
            backgroundColor: '#e0ffe0'
          }
        },
        _.container);


      var tmpl = {
        selectable: 1,
        style: {
          position: 'absolute',
          left: 0,
          top: 0,
          overflow: 'auto',
          width: '50px',
          height: '50px',
          backgroundColor: '#ffffe0'
        }
      };
      _.consoleWatch = jsa.createDiv('consoleWatch', tmpl, _.consoleMonitor, 'Console watch');
      _.consoleLog = jsa.createDiv('consoleLog', tmpl, _.consoleMonitor, 'Console log');
      jsa.on(_.win, 'resize', function() {
        _.rearrange(_);
        //jsa.run({_: _, f: _.rearrange, aid: jsa.c.ACTION_JSA_CONSOLE_REARRANGE});
      });
      //jsa.run({_: _, f: _.rearrange, aid: jsa.c.ACTION_JSA_CONSOLE_REARRANGE});
      debugger;
      _.rearrange(_);

    },
    group: function(groupName) {
      this.addLog(arguments, 5); // 5-group opened
    },
    groupCollapsed: function(groupName) {
    },
    time: function(timerName) {
      this.timers[timerName] = new Date();
    },
    timeEnd: function(timerName) {
      if (this.timers[timerName]) {
        this.info(timerName + ": " + Number(new Date() - this.timers[timerName]) / 1000 + " sec");
      }
    },
    log: function() {
      this.addLog(arguments, 1); // 1-info
    },
    info: function() {
      this.addLog(arguments, 1); // 1-info
    },
    warn: function() {
      this.addLog(arguments, 2); // 2-warning
    },
    error: function() {
      this.addLog(arguments, 3); // 3-error
    },
    groupEnd: function(groupNameOptional) {
      var _ = this, i = _.curGroupEntry;
      if (!i) {
        _.warn("Called Console.groupEnd() without Console.group()");
      } else {
        _.curGroupEntry = _.logData[i][2];
        _.curGroupIndent--;
      }
    },
    dump: function(o) {
      var i, s = "", c = 0;
      for (i in o) {
        c++;
        if (c > 100) {
          s += "[...]";
          break;
        }
        s += "." + i + "=" + o[i] + " ";
      }
      return s;
    },
    /**
     * Push array of args from log wrapper function to the log
     * @param {Array} args
     * @param {integer} mode
     */
    addLog: function(args, mode) {
      var i, v = [], _ = this, logEntry = [];
      for (i = 0; i < args.length; i++) {
        if (typeof args[i] == 'object') {
          v.push(this.dump(args[i]));
        } else if (args[i] === undefined) {
          v.push('undefined');
        } else {
          v.push(args[i].toString());
        }
      }
      logEntry = [v, mode, _.curGroupEntry, _.curGroupIndent];
      if (mode == 5) {
        _.curGroupEntry = _.logData.length;
        _.curGroupIndent++;
      }
      _.logData.push(logEntry);
      if (!_.hIntervalRegen) {
        _.hIntervalRegen=window.setInterval(function(){_.regenerate(_);},200);
      }
      //jsa.run({f: _.regenerate, _: _, aidAfter: jsa.c.ACTION_JSA_CONSOLE_REGENERATE});
    },

    regenerate: function(_) {
      _.hIntervalRegen=0;
      var e, df, d = _.doc, logEntry, n = _.logData.length, i, j, v, s, trc;
      if (!_.consoleMonitor) {
        return 'continue';
      }
      if (_.lastAdded < n) {
        df = d.createDocumentFragment();

        for (i = _.lastAdded; i < n; i++) {

          logEntry = _.logData[i];
          e = d.createElement("div");
          v = logEntry[0];
          s = trc = "";
          for (j = 0; j < v.length; j++) {
            s += "<td>" + v[j] + "</td>";
          }
          if (logEntry[1] == 2) {
            s = "<td>WARNING!</td>" + s;
            trc = " style='background-color:#ffffe0;'";
          }
          if (logEntry[1] == 3) {
            s = "<td>ERROR!</td>" + s;
            trc = " style='background-color:#ffe0e0;'";
          }
          if (logEntry[3] > 0) {
            s = "<td width='1%'>&nbsp;</td>" + s;
          }

          s = "<table frame=0 bordercolor='#e0e0e0' cellspacing=0 width='100%' cellpadding=1 border=1><tr class='log'" + trc + ">" + s + "</tr></table>";
          e.innerHTML = s;
          df.appendChild(e);
        }
        _.lastAdded = i;
        _.consoleLog.appendChild(df);
        if (_.consoleLog.scrollHeight) {
          _.consoleLog.scrollTop = _.consoleLog.scrollHeight;
        }
      }
      return false;
    },

    rearrange: function(_) {
      console.log("rearranging");
      var b = _.container, w = b.clientWidth, h = b.clientHeight, pw, ph, v, ls,
        stMonitor = _.consoleMonitor.style, lab = _.labIFrame, stLab, stLog = _.consoleLog.style,
        stWatch = _.consoleWatch.style, o = _.options, borderSize = o.pBorderSize, spacing = o.pSpacing, mmHeight = o.mainMenuHeight;
      // _.options={monitorSize:0.3, logSize:0.4,pBorderSize:1,pSpacing:2};

      if (lab) {
        stLab = lab.style;
      }
      if (w > h) {
        pw = Math.round(w * o.monitorSize);
        ph = h;
        v = ph - mmHeight - borderSize * 2;
        if (v < 0) {
          v = 0;
        }
        stMonitor.height = v + "px";
        stMonitor.left = (w - pw - borderSize) + "px";
        stMonitor.top = borderSize + mmHeight + "px";
        stMonitor.width = pw + "px";
        if (!!lab) {
          stLab.left = borderSize + "px";
          stLab.top = mmHeight + borderSize + "px";
          stLab.width = (w - pw - borderSize * 2 - spacing) + "px";
          v = (h - borderSize * 2 - mmHeight);
          if (v < 0) {
            v = 0;
          }
          stLab.height = v + "px";
        }

        ls = Math.round(o.logSize * h);
        stWatch.width = stLog.width = pw + "px";
        stLog.height = ls;
        stLog.top = "0px";
        stWatch.left = stLog.left = "0px";

        v = ph - ls - o.pSpacing - mmHeight;
        if (v < 0){
          v = 0;
        }
        stWatch.height = v;
        stWatch.top = ls + spacing;

      } else {
        ph = Math.round(h * o.monitorSize);
        pw = w;
        stMonitor.height = ph + "px";
        stMonitor.top = (h - ph - borderSize) + "px";
        stMonitor.left = borderSize + "px";
        stMonitor.width = w - borderSize * 2 + "px";
        if (!!lab) {
          stLab.left = borderSize + "px";
          stLab.top = mmHeight + borderSize + "px";
          stLab.width = w - borderSize * 2 + "px";
          stLab.height = (h - mmHeight - ph - borderSize * 2 - spacing * 2) + "px";
        }
        ls = Math.round(o.logSize * w);
        stLog.width = ls + "px";
        stWatch.height = stLog.height = ph + "px";
        stWatch.top = stLog.top = "0px";
        stLog.left = "0px";
        stWatch.left = ls + spacing + "px";
        stWatch.width = pw - spacing - ls + "px";
      }
    }
  };
  jsa.console = new jsa.Console();
} // if CREATE_CONSOLE




/** @class jsa.Control */
(jsa.Control = function(){}).prototype =
  /** @lends jsa.Control.prototype */
  {
  clsName: 'jsa.Control',
  /**
   * Factory method for all Controls
   * @param {object}      a arguments
   * @param {Object}      a.target target control to put the newest control inside
   * @param {Number}      a.x x coordinate
   * @param {Number}      a.y y coordinate
   * @param {object}      a.owner owner the jsf container that has ownedObjects{}
   * @param {jsa.Frame}   a.jsf environment
   * @param {HTMLElement} a.he htmlElement that will containing created control
   * @param {Number}		isFirst means 1=first control that will call .arrangeKids
   **/
  put: function(a, isFirst) {
    var me = this, viewModel = a.vm, htmlTag, element, doc, parentCtrl = a.target, s, j;
    if (!a.jsf) {
      if(!me.jsf) {
        if (!!parentCtrl.jsf) {
          me.jsf = parentCtrl.jsf;
        }
        else {
          jsa.console.info('jsa.Control.put({}) without jsf');
          return;
        }
      }
    } else {
      me.jsf=a.jsf;
    }

    if(!a.owner) {
      if (!parentCtrl.owner){
        me.owner=me.jsf;
      } else {
        me.owner=parentCtrl.owner;
      }
    } else {
      me.owner=a.owner;
    }

    htmlTag = viewModel.tag || 'div';

    if (!me.jsf.doc) {
      jsa.console.info('jsa.Control.put({jsf}) has no document reference in attribute .doc');
      return;
    }
    doc = me.jsf.doc;
    me.element = element = ((!a.he) ? doc : a.he.ownerDocument).createElement(htmlTag);
    me.x = a.x || 0;
    me.y = a.y || 0;
    me.isVisible = a.isVisible || true;
    me.id = viewModel.id || jsa.getUID(me.clsName);
    me.element.setAttribute('id', me.id);
    me.element.setAttribute('jsa_id', me.id);

    if (!!me.owner) {
      me.owner.ownedObjects[me.id] = me;
    } else {
      if (DEBUG) {
        jsa.console.warn("Created " + (me.clsName) + " without reference to owner. It means memory leaks");
      }
    }
    me.viewModel = viewModel;
    me.parentCtrl = parentCtrl;
    me.dataProvider = a.dataProvider;
    me.minWidth = viewModel.minWidth || 50;
    me.minHeight = viewModel.minHeight || 40;
    me.borderSize = viewModel.borderSize || 0;
    me.padding = viewModel.padding || 0;
    me.width = viewModel.width || 200;
    me.height = viewModel.height || 200;
    me.kids = [];
    if (!!viewModel.html) {
      element.innerHTML = viewModel.html;
    }
    if (!!viewModel.thtml) {
      element.innerHTML = jsa.parsedHTML(viewModel.thtml, me);
    }
    if (!!(s = viewModel.a)) {
      for (j in s) {
        element.setAttribute(j, s[j]);
      }
    }
    if (!!(s = viewModel.s)) {
      for (j in s) {
        element.style[j] = s[j];
      }
    }

    if ((!parentCtrl) && (!a.he)) {
      me.topHtmlContainer = (doc.compatMode == 'CSS1Compat') ? doc.documentElement : doc.body;
    }
  },
  destroy: function() {
    var i, c;
    for (i in this.ownedObjects) {
      c = this.ownedObjects[i];
      c.destroy();
      delete this.ownedObjects[i];
    }
    if (c.element) {

    }
  },
  setPosSizeVisible: function() {
    var e = this.element, es, offset = (this.borderSize + this.padding) * 2;
    if (e) {
      es = e.style;
      if ((this.w <= 0) || (this.h <= 0)){
        this.isVisible = 0;
      }
      if (!this.isVisible) {
        es.display = 'none';
      } else {
        es.position = 'absolute';
        es.left = this.x + 'px';
        es.top = this.y + 'px';
        es.width = (this.w - offset) + 'px';
        es.height = (this.h - offset) + 'px';
        es.display = 'block';
      }
    }
  }



};

(jsa.Splitter = function() {
}).prototype = new jsa.Control();
jsa.Splitter.prototype.clsName = 'jsa.Splitter';
jsa.Splitter.prototype.superClass = jsa.Control.prototype;
jsa.Splitter.prototype.put = function(a) {
  jsa.console.log('.Splitter.put called', a);
  this.superClass.put.call(this, a);
  this.mode = a.mode;
  this.stretchControl1 = a.stretchControl1;
  this.stretchControl2 = a.stretchControl2;
  // TODO add destroy publisher
//    jsa.sub(this.stretchControl1,'destroy',this,function(){
//      jsa.console.log('Control destroyed. So splitter should destroyed too');
//        })
  jsa.sub(this, 'mousedown', this, 'mousedown');
  jsa.sub(this, 'mouseup', this, 'mouseup');
  jsa.sub(this, 'mousemove', this, 'mousemove');

  this.parentCtrl.element.appendChild(this.element);
};
jsa.Splitter.prototype.mousedown = function(e) {
  jsa.console.log('Splitter mouse down');
};
jsa.Splitter.prototype.mouseup = function(e) {
  jsa.console.log('Splitter mouse up');
};
jsa.Splitter.prototype.mouseout = function(e) {
  jsa.console.log('Splitter mouse out');
};
jsa.Splitter.prototype.size = function() {
  var w = this.width, h = this.height;
  this.sizeChanged = ((w != this.w) || (h != this.h));
  if (this.sizeChanged) {
    this.w = w;
    this.h = h;
  }
};
jsa.c.SPLITTER_MODE_STRETCH = 1;
jsa.c.SPLITTER_MODE_RESIZE_DOCK = 2;
//jsa.c.SPLITTER_MODE_RESIZE_CONTROL=3;

(jsa.DockPanel = function() {
}).prototype = new jsa.Control();
jsa.DockPanel.prototype.superClass = jsa.Control.prototype;

jsa.DockPanel.prototype.put = function(a, doArrangeAfterPut) {
  // viewModel is a JSON template. {t:'div',width:100,position:'absolute',height:200,idp:'idprefix',before:"evalCodeBeforeChild",_:[t:'ul',a:{type:'circle'}],after:"evalCodeAfterCreate"}
  var me = this,
    viewModel = a.vm,
    dataProvider = a.dp,
    htmlContainer = a.he,
    parentCtrl = a.target,
    childElementDef,
    s, j,
    doc = a.jsf.doc,
    element;

  me.side = viewModel.side;
  me.superClass.put.call(this, a);
  element = me.element;
  me.size();
  if (!!(s = viewModel._)) {
    for (j in s) { // array of child element definitions
      childElementDef = s[j];
      // TODO: не хочу использовать Path и его выстраивание контролами.
      // Хочется позвать dataProvider и сообщить, что я вхожу в дочерние узлы, чтобы он сам расставил бинды
      jsa.put({owner: a.jsf, jsf: a.jsf, vm: childElementDef, dp: dataProvider, he: element, target: me});
    }
  }

  if (!!parentCtrl) {
    parentCtrl.kids.push(me);
    if (doArrangeAfterPut) {
      parentCtrl.arrangeKids();
    }
  }
  if (!!htmlContainer) {
    htmlContainer.appendChild(element);
  } else {
    doc.body.appendChild(element);
    me.setPosSizeVisible();
    jsa.on(a.jsf.win, 'resize', function() {
      jsa.run({aidAfter: me.id + "winresize", f: function(act) {
          var t = act.target;
          jsa.console.log("DockPanel " + t.id + " rearranging");
          t.size();
          t.setPosSizeVisible();
          t.arrangeKids();
        }}, me);
    });
  }
};


/**
 * updates (this.w, this.h) <= (this.width, this.height) that described in percents
 * If any dimension had changed set this.sizeChanged to true
 * Note: this.viewModel contains initial size
 **/
jsa.DockPanel.prototype.size = function() {
  var w = this.w, h = this.h, l, htmlContainer = this.htmlContainer, s, parentWidth,
    parentHeight, parentCtrl = this.parentCtrl;

  s = this.width;
  if (isFinite(s)) {
    this.w = s;
  } else {
    if (s.charAt((l = s.length - 1)) == '%') {
      if (!parentCtrl) {
        if (!htmlContainer) {
          parentWidth = this.topHtmlContainer.clientWidth;
        } else {
          parentWidth = htmlContainer.clientWidth;
        }
      } else {
        parentWidth = parentCtrl.w - (parentCtrl.borderSize + parentCtrl.padding) * 2;
      }
      this.w = parseInt(s.substr(0, l)) * parentWidth / 100;
    } else {
      this.w = parseInt(s);
    }
  }

  s = this.height;
  if (isFinite(s)) {
    this.h = s;
  } else {
    if (s.charAt((l = s.length - 1)) == '%') {
      if (!parentCtrl) {
        if (!htmlContainer) {
          parentHeight = this.topHtmlContainer.clientHeight;
        } else {
          parentHeight = htmlContainer.clientHeight;
        }
      } else {
        parentHeight = parentCtrl.h - (parentCtrl.borderSize + parentCtrl.padding) * 2;
      }
      this.h = parseInt(s.substr(0, l)) * parentHeight / 100;
    } else {
      this.h = parseInt(s);
    }
  }
  this.sizeChanged = ((w != this.w) || (h != this.h));
};

/**
 *
 * @param {Array} dockSet array of docking controls
 * @param {Object} boundary view limits
 * @param {Number} spOn allow add splitters
 * @param {Number} ss splitter size in pixels
 */
jsa.DockPanel.prototype._arrangeDockSet = function(dockSet, boundary, spOn, ss) {
  var me = this, doc = me.element.ownerDocument, justadded, needSplitter, mul, ws, j, stackPos, a, l, isLast, side, isVertical,
    amount, maxThick, tx, ty, tw, th, tv, sp;
  ss = ss || 5;
  if (!dockSet) {
    return;
  }

  l = dockSet.length;
  if (l > 1) {
    //debugger;
  }
  for (j = 0; j < l; j++) {
    a = dockSet[j];
    if (!j) {
      side = a.side;
      isVertical = (side == 'W') || (side == 'E') || (side == 'M');
      amount = 0;
      maxThick = 0;
    }
    if (isVertical) {
      if (a.width > maxThick) {
        maxThick = a.width;
      }
      amount += a.height;
    } else {
      if (a.height > maxThick) {
        maxThick = a.height;
      }
      amount += a.width;
    }
  }
  // window size
  ws = (isVertical) ? boundary.vy2 - boundary.vy1 : boundary.vx2 - boundary.vx1;
  mul = (ws < 1) ? 1 : amount / (ws - (l - 1) * ss);
  stackPos = (isVertical) ? boundary.vy1 : boundary.vx1;
  for (j = 0; j < l; j++) {
    a = dockSet[j];
    isLast = (j == (l - 1));
    needSplitter = (!isLast) && (spOn);
    if ((a.isVisible = (ws > 0))) {
      if (isVertical) {
        a.h = (isLast) ? ws : Math.floor(a.height / mul);
        if (a.h < a.minHeight) {
          a.h = a.minHeight;
        }
        a.w = maxThick;
        a.y = stackPos;
        stackPos += a.h + ss;
        ws -= a.h + ss;
      } else {
        a.x = stackPos;
        a.w = (isLast) ? ws : Math.floor(a.width / mul);
        if (a.w < a.minWidth) {
          a.w = a.minWidth;
        }
        a.h = maxThick;
        stackPos += a.w + ss;
        ws -= a.w + ss;
      }
      switch (side) {
        case 'N': // North - top
          a.y = boundary.vy1;
          break;
        case 'S':
          a.y = boundary.vy2 - maxThick;
          break;
        case 'E': // East - right
          a.x = boundary.vx2 - maxThick;
          break;
        case 'M':
          a.w = boundary.vx2 - boundary.vx1; // NO BREAK!
        case 'W':
          a.x = boundary.vx1;
      }
      if (needSplitter) {
        if (isVertical) {
          tx = a.x;
          ty = a.y + a.h;
          tw = a.w;
          th = ss;
          tv = 1;
        } else {
          tx = a.x + a.w;
          ty = a.y;
          tw = ss;
          th = a.h;
          tv = 0;
        }
      }
    } else {
      needSplitter = 0;
      jsa.console.info('not isVisible ' + a.viewModel.html + " w=" + a.w + ' h=' + a.h);
    }

    a.arrangeKids();
    a.setPosSizeVisible();
    if (needSplitter) {
      if (!(sp = a.stretchSplitter)) {
        jsa.console.info("jsa.put splitter");
        sp = jsa.put({
          target: me,
          jsf: me.jsf,
          using: 1,
          x: tx, // will be re-calculated during rearrange
          y: ty,
          mode: jsa.c.SPLITTER_MODE_STRETCH,
          dockSetPos: j,
          vm: {
            clsName: 'Splitter',
            width: tw,
            height: th,
            s: {
              backgroundColor: 'red',
              cursor: tv ? 'row-resize' : 'col-resize'
            }
          }
        });
        if (!sp) {
          jsa.console.error("jsa.put splitter returned nothing");
        }
        a.stretchSplitter = sp;
      } else {
        sp.x = tx;
        sp.y = ty;
      }
      if (sp) {
        jsa.console.info("splitter " + sp.id + " resized");
        sp.size();
        sp.setPosSizeVisible();
      }
    }
  }
  if (a.isVisible){
    switch (side) {
      case 'N':
        boundary.vy1 += maxThick + ss;
        break;
      case 'E':
        boundary.vx2 -= maxThick + ss;
        break;
      case 'W':
        boundary.vx1 += maxThick + ss;
        break;
      case 'S':
        boundary.vy2 -= maxThick + ss;
    }// M should be only last! Works like 'W'
  }
};

/**
 * Clears all neighbour dockSets inside docked controls
 **/
jsa.DockPanel.prototype.flushArrangedDock = function() {
  var me = this, i;
  for (i in me.dockSets) {
    delete me.dockSets[i];
  }
}


jsa.DockPanel.prototype.arrangeKids = function() {
  var me = this, kidCount, i, dockSet, dockSetStarted = 0, dockedControl, tmp, sp,
    boundary = {vx1: me.padding, vy1: me.padding, vx2: me.w - me.padding * 2, vy2: me.h - me.padding * 2};

//	if(!me.dockSets){
  me.dockSets = {};
  //}
  if (!!me.stretchSplitters) {
    for (i in me.stretchSplitters) {
      me.stretchSplitters[i].using = 0;
    }
  } else {
    me.stretchSplitters = [];
  }

  kidCount = me.kids.length;
  for (i = 0; i < kidCount; i++) {
    dockedControl = me.kids[i];
    if (dockedControl.side !== undefined) {
      if (dockedControl.side != 'A') { // not Attached to previous docked panel
        if (dockSetStarted) {
          // arrange previous collected dockSet
          me._arrangeDockSet(dockSet, boundary, 1, 5);
        }

        // check this control inside previously generated dockSets[control.id]
        if (!(dockSet = me.dockSets[dockedControl.id])) {
          dockSet = me.dockSets[dockedControl.id] = [dockedControl];
          dockSetStarted = 1;
        }
      } else {
        dockSet.push(dockedControl);
        me.dockSets[dockedControl.id] = dockSet;
      }
    }
  }
  if (dockSetStarted) {
    me._arrangeDockSet(dockSet, boundary, 1, 5);
  }

  if (!!me.stretchSplitters) {
    for (i = me.stretchSplitters.length - 1; i >= 0; i--) {
      sp = me.stretchSplitters[i];
      if (!sp.using) {

        sp.destroy();
        /*
         sp.htmlElement.parentNode.removeChild(sp.htmlElement);
         delete sp.htmlElement;
         if ((!!sp.control)&&(sp.control.stretchSplitter)) delete sp.control.stretchSplitters;
         delete me.stretchSplitters[i];
         */
      }
    }
  }
};

jsa.DockPanel.prototype.anchor = function(id) {
  return "[[" + id + "_" + jsa.getUID() + "]]";
};


jsa.on(window, 'load', function() {
  /** @this window */
  jsf = jsa.registerFrame(window, "AppTopWindow");
  if (CREATE_CONSOLE) {
    jsa.console.init();
    jsa.actionByCode[jsa.c.ACTION_JSA_CONSOLE_REGENERATE] = jsa.actionByName['.console.regenerate'] = jsa.console.regenerate;
    jsa.actionByCode[jsa.c.ACTION_JSA_CONSOLE_REARRANGE] = jsa.actionByName['.console.rearrange'] = jsa.console.rearrange;
    jsa.console.log('Console module starts');
  }
});

if (!COMPILED) {
  // This block will be rejected by compiler
  console.log("Non-compiled mode");
}


jsa.actionByCode[jsa.c.ACTION_JSA_PUT] =
  jsa.actionByName['put'] =
  jsa.put = function(a) {
    var ctrl, vm, clsName = '', classDef, nothing = false;
    vm = a.vm;
    if (!a.owner) {
      a.owner = a.jsf;
    }
    if (!vm) {
      if (DEBUG) {
        jsa.console.error('Put called without .vm parameter');
      }
      return nothing;
    }
    clsName = a.vm.clsName;
    if (!clsName) {
      if (DEBUG) {
        jsa.console.error('Cannot put control without .clsName attribute of ViewModel');
      }
      return nothing;
    }
    classDef = jsa[clsName];
    if (!!classDef) {
      ctrl = new (classDef)();
      ctrl.put(a, 1); // 1 - means to do rearrange after create
      return ctrl;
    } else {
      if (DEBUG) {
        jsa.console.error('Undefined class jsa.' + clsName);
      }
      return nothing;
    }
  };

