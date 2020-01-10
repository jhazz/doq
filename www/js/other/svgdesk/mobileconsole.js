document.write ('<div id="consoleWindow" style="position:fixed; overflow-y:auto; border: solid 1px red; background-color:#d0e0e0; left:0px; width:100%;">LOGWIN</div>');
document.write ('<div id="consoleWindowButton" style="position:fixed; left:10px; top:11px; width:20px; height:20px; background-color:#40a0F0;"></div>');

var console={
inited:false,
initDOMElements:function() {
  if (console.inited) return;
  console.consoleWindow=document.getElementById("consoleWindow");
  if (!console.consoleWindow) return;

  console.inited=true;
  console.consoleWindowButton=document.getElementById("consoleWindowButton");
  var c=console.consoleWindowButton;
  c.style.display='block';
  c.addEventListener('click',console.toggle);
  console.arrange();
},

toggle:function() {
  var c=console.consoleWindow.style;
  c.display=(c.display!=='none')?'none':'block';
},

arrange:function(){
  var size=console.getWindowSize();
  console.info(size);
  var c=console.consoleWindow.style;
  c.width=(size.width-30)+'px';
  c.height=(size.height-30)+'px';
  c.top=10+'px'; c.left=10+'px';
  c=console.consoleWindowButton.style;
  c.left=(size.width-39)+'px';
  return true;
},

info:function(data) {
 console.addLogRecord(console.dump(data,true),0);
},

log:function(data,status,onlyOwned,level){
  if(level==undefined)level=2; 
  console.addLogRecord(console.dump(data,onlyOwned,level));
},

error:function(data) {
  console.addLogRecord(console.dump(data,true),1);
},

addLogRecord:function(text,status) {
  if (!console.inited) {
    console.initDOMElements();
  }
  var div=document.createElement('div');
  div.style.border="1px solid #808080";
  div.innerHTML="<span class='log'>"+text+"</span>";
  if (status==1) {
    div.style.backgroundColor="#ffe0e0";
  }
  console.consoleWindow.appendChild(div);
},

dump:function(data,onlyOwned,level) {
  var v,i,s="",cn=0; 
  if(level==undefined) level=1;
  
  
  if (typeof(data)=="object"){
    if (data instanceof Array) {
      if(level<0) return '[...]';
      for (i in data) {
        v = data[i];
        if(!!s) s+=", ";
        s+=console.dump(v, onlyOwned,level-1 );
        cn++; if (cn>200) {s+="[..]"; break;}
      }
      s="["+s+"]";
    } else {
      if(level<0) return '{...}';
      for (i in data) {
        v = data[i];
        if (onlyOwned && (!data.hasOwnProperty(i))) continue;
        if(!!s) s+=", ";
        s=s+" <b>"+i+"</b>:"+console.dump(v,onlyOwned,level-1 );
        cn++; if (cn>50) {s+="[..]"; break;}
      }
      s="{"+s+"}";
    }
  } else {
    s=data;
  }
  return s;
},

getWindowSize:function () {
  var r={width:400,height:400};
  r.width=window.innerWidth;
  r.height=window.innerHeight;
  return r;
},

getWindowSize2:function () {
  var r={width:400,height:400};
  if (typeof (window.innerWidth) == 'number') {
    r.width = window.innerWidth;
    r.height = window.innerHeight;
  } else {
    if (document.documentElement && (document.documentElement.clientWidth || document.documentElement.clientHeight)) {
      r.width = document.documentElement.clientWidth;
      r.eight = document.documentElement.clientHeight;
    } else {
      if (document.body && (document.body.clientWidth || document.body.clientHeight)) {
        r.width = document.body.clientWidth;
        r.height = document.body.clientHeight;
      }
    }
  }
  return r;
}
};


window.addEventListener("load", console.initDOMElements);
window.addEventListener('resize',console.arrange);
window.onerror = function(msg, url, line, col, error) {
   // Note that col & error are new to the HTML 5 spec and may not be 
   // supported in every browser.  It worked for me in Chrome.
   var extra = !col ? '' : '\ncolumn: ' + col;
   extra += !error ? '' : '\nerror: ' + error;

   // You can view the information in an alert to see things working like this:
   console.error("Error: " + msg + "\nurl: " + url + "\nline: " + line + extra);

   // TODO: Report this error via ajax so you can keep track
   //       of what pages have JS issues

   var suppressErrorAlert = true;
   // If you return true, then error alerts (like in older versions of 
   // Internet Explorer) will be suppressed.
   return suppressErrorAlert;
};