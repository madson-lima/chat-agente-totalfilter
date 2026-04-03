(function () {
  var script = document.currentScript;
  var baseUrl = "";

  if (script && script.src) {
    baseUrl = script.src.replace(/\/chat-widget\/embed\.js(\?.*)?$/, "");
  }

  function boot() {
    if (document.querySelector("totalfilter-chat-widget")) return;
    var widget = document.createElement("totalfilter-chat-widget");
    widget.setAttribute("base-url", baseUrl);
    document.body.appendChild(widget);
  }

  var widgetScript = document.createElement("script");
  widgetScript.src = baseUrl + "/chat-widget/widget.js";
  widgetScript.defer = true;
  widgetScript.onload = boot;
  document.head.appendChild(widgetScript);
})();
