(function($){
  $('document').ready(function(){
    var body = $('body')[0];
    var bgdColor = getComputedStyle(body, null).getPropertyValue('background-color');
    var iconDiv = $('#dpo-icon-container');
    iconDiv.css('background-color', bgdColor);
  })
})(jQuery)
