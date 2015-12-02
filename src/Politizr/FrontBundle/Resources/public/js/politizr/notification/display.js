// on document ready
$(function() {
    notificationsLoading();
})


// ouverture/fermeture box notifications
$("body").on("click", "[action='linkNotifications']", function() {
    $('#notifications').slideToggle();
    $('body.css760 #headerCenter, body.css760 #menu').hide(); 
});

// close notif
$("body").on("click", ".notifClose", function() {
    $('#notifications').slideUp('fast');
});

// Regular function with arguments
function notificationsLoading(){
    var xhrPath = getXhrPath(
        ROUTE_NOTIF_LOADING,
        'notification',
        'notificationsLoad',
        RETURN_HTML
        );

    $.ajax({
        type: 'POST',
        dataType: 'json',
        url : xhrPath,
        // Fix #77
        // beforeSend: function ( xhr ) { xhrBeforeSend( xhr ); },
        // statusCode: { 404: function () { xhr404(); }, 500: function() { xhr500(); } },
        // error: function ( jqXHR, textStatus, errorThrown ) { xhrError(jqXHR, textStatus, errorThrown); },
        success: function(data) {
            $('#ajaxGlobalLoader').hide();

            // MAJ compteur
            note = parseInt(data['counterNotifs']);
            if (note > 0) {
                $('#notifCounter').html(data['counterNotifs']).show();
            } else {
                $('#notifCounter').html('-').hide();
            }

            // MAJ listing des notifs
            $('#notifList').html(data['html']);
        }
    });

    // Rappel toutes les 60 secondes
    setTimeout(function(){
        notificationsLoading();
    }, 60000);
}

// check notification
$("body").on("click", "i[action='notificationCheck']", function(e) {
    // console.log('*** click i notificationCheck');

    e.preventDefault();

    var localLoader = $(this).closest('.notifItem').find('.ajaxLoader').first();
    var xhrPath = getXhrPath(
        ROUTE_NOTIF_CHECK,
        'notification',
        'notificationCheck',
        RETURN_BOOLEAN
        );

    $.ajax({
        type: 'POST',
        dataType: 'json',
        url : xhrPath,
        context: $(this).closest('.notifItem'),
        data: { 'uuid': $(this).attr('uuid') },
        beforeSend: function ( xhr ) { xhrBeforeSend( xhr, localLoader ); },
        statusCode: { 404: function () { xhr404(localLoader); }, 500: function() { xhr500(localLoader); } },
        error: function ( jqXHR, textStatus, errorThrown ) { xhrError(jqXHR, textStatus, errorThrown, localLoader); },
        success: function(data) {
            localLoader.hide();

            $(this).find('.notifHighlight').removeClass();
            $(this).find('.iconCheck').remove();
            $(this).addClass('viewedNotif');

            note = parseInt($('#notifCounter').text()) - 1;
            if (note > 0) {
                $('#notifCounter').html(note);
            } else {
                $('#notifCounter').html('-').hide();
            }

            $('#ajaxGlobalLoader').hide();
        }
    });

})

$("body").on("click", "a[action='notificationCheck']", function(e) {
    // console.log('*** click a notificationCheck');

    var xhrPath = getXhrPath(
        ROUTE_NOTIF_CHECK,
        'notification',
        'notificationCheck',
        RETURN_BOOLEAN
        );

    e.preventDefault();
    $.ajax({
        type: 'POST',
        dataType: 'json',
        url : xhrPath,
        context: this,
        data: { 'uuid': $(this).closest('span').attr('uuid') },
        beforeSend: function ( xhr ) { xhrBeforeSend( xhr, 1 ); },
        statusCode: { 404: function () { xhr404(); }, 500: function() { xhr500(); } },
        error: function ( jqXHR, textStatus, errorThrown ) { xhrError(jqXHR, textStatus, errorThrown); },
        success: function(data) {
            window.location = $(this).attr('href');
        }
    });

})

// check toutes les notifications
$("body").on("click", "div[action='notificationCheckAll']", function(e) {
    // console.log('*** click notificationCheckAll');

    var xhrPath = getXhrPath(
        ROUTE_NOTIF_CHECK_ALL,
        'notification',
        'notificationsCheckAll',
        RETURN_BOOLEAN
        );

    $.ajax({
        type: 'POST',
        dataType: 'json',
        url : xhrPath,
        context: $(this).closest('table'),
        beforeSend: function ( xhr ) { xhrBeforeSend( xhr, 1 ); },
        statusCode: { 404: function () { xhr404(); }, 500: function() { xhr500(); } },
        error: function ( jqXHR, textStatus, errorThrown ) { xhrError(jqXHR, textStatus, errorThrown); },
        success: function(data) {
            // MAJ du style
            $('.notifItem').addClass('viewedNotif');
            $('.notifItem').find('.notifHighlight').removeClass();
            $('.notifItem').find('.iconCheck').remove();

            // MAJ du compteur
            $('#notifCounter').html('-').hide();

            $('#ajaxGlobalLoader').hide();
        }
    });

})