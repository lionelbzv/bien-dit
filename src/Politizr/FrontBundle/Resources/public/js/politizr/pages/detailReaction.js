// beta
$(function() {
    stickySidebar();
    // $('.cycle-slideshow').cycle('goto', 1);
});


$("body").on("click", "[action='showMenuAllFamily']", function() {
    $('.answerMenuAllFamily').toggle();
    $('.answerShowMenuAllFamily').hide();
    $('.answerHideMenuAllFamily').show();
    
});

$("body").on("click", "[action='hideMenuAllFamily']", function() {
    $('.answerMenuAllFamily').toggle();
    $('.answerHideMenuAllFamily').hide();
    $('.answerShowMenuAllFamily').show();
}); 
