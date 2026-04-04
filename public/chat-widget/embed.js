(function () {
  var script = document.currentScript;
  var baseUrl = "";
  var apiBaseUrl = "";

  if (script && script.src) {
    baseUrl = script.src.replace(/\/chat-widget\/embed\.js(\?.*)?$/, "");
  }

  if (script) {
    apiBaseUrl = script.getAttribute("data-api-base-url") || "";
  }

  function boot() {
    if (document.querySelector("totalfilter-chat-widget")) return;
    var widget = document.createElement("totalfilter-chat-widget");
    widget.setAttribute("base-url", baseUrl);
    if (apiBaseUrl) {
      widget.setAttribute("api-base-url", apiBaseUrl.replace(/\/$/, ""));
    }
    document.body.appendChild(widget);
  }

  var widgetScript = document.createElement("script");
  widgetScript.src = baseUrl + "/chat-widget/widget.js";
  widgetScript.defer = true;
  widgetScript.onload = boot;
  document.head.appendChild(widgetScript);
})();
