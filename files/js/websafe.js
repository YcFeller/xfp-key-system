//屏蔽右键菜单
document.oncontextmenu = function (event) {
  if (window.event) {
    event = window.event;
  }

  try {
    var the = event.srcElement;
    if (!((the.tagName == "INPUT" && the.type.toLowerCase() == "text") || the.tagName == "TEXTAREA")) {
      return false;
    }
    return true;
  } catch (e) {
    return false;
  }
}

//按f12卡死页面
function inlobase_noF12() { while (1) { } }
function inlojv_console() { if ((window.console && (console.firebug || console.table && /firebug/i.test(console.table()))) || (typeof opera == "object" && typeof opera.postError == "function" && console.profile.length > 0)) { inlobase_noF12() } if (typeof console.profiles == "object" && console.profiles.length > 0) { inlobase_noF12() } } inlojv_console(); window.onresize = function () { if ((window.outerHeight - window.innerHeight) > 200) { inlobase_noF12() } };

// 按下F12关闭当前页面
function fuckyou() {
  window.close(); //关闭当前窗口(防抽)  
  window.location = "about:blank"; //将当前窗口跳转置空白页  
}
function ck() {
  console.profile();
  console.profileEnd();
  //我们判断一下profiles里面有没有东西，如果有，肯定有人按F12了，没错！！  
  if (console.clear) { console.clear() };
  if (typeof console.profiles == "object") {
    return console.profiles.length > 0;
  }
}
function hehe() {
  if ((window.console && (console.firebug || console.table && /firebug/i.test(console.table()))) || (typeof opera == 'object' && typeof opera.postError == 'function' && console.profile.length > 0)) {
    fuckyou();
  }
  if (typeof console.profiles == "object" && console.profiles.length > 0) {
    fuckyou();
  }
}
hehe();
window.onresize = function () {
  if ((window.outerHeight - window.innerHeight) > 200)
    //判断当前窗口内页高度和窗口高度，如果差值大于200，那么呵呵了！
    fuckyou();
}