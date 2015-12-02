// on document ready
$(function() {
    $('.comments').hide();

    if(window.location.hash) {
       var paragraphId = window.location.hash.substr(1);
       openParagraph(paragraphId);
    }
});

// close comments
$("body").on("click", "[action='closeComments']", function(e) {
    // console.log('*** click closeComments');

    var paragraphElement = $(this).closest('.paragraph');

    context = paragraphElement.find('.comments');
    context.slideUp();
    context.html('');

    // scroll to paragraph
    $('html, body').animate({
        scrollTop: paragraphElement.offset().top
    }, '1000');    
});

// ajustement de la taille du textarea de saisie d'un commentaire en fonction de la saisie en cours
$("body").on('change keyup keydown paste cut', 'textarea', function () {
    $(this).height(0).height(this.scrollHeight);                    
}).find('textarea').change(); 

// affichage des commentaires par paragraphe
$("body").on("click", "[action='comments']", function(e) {
    // console.log('*** click comments');

    // Fermeture préalable de tous les paragraphes ouverts
    context = $('.comments').slideUp().html('');
    openParagraph($(this).closest('.paragraph').attr('id'));
});

// open paragraph id
function openParagraph(paragraphId)
{
    // console.log('*** openParagraph');
    // console.log(paragraphId);

    var context = $('#'+paragraphId);

    // close if already open
    if (context.find('.comments').is(':visible')) {
        context.find(".commentsClose").trigger( "click" );
        return;
    }

    var uuid = context.find('.commentsCounter').attr('uuid');
    var type = context.find('.commentsCounter').attr('type');
    var noParagraph = context.find('.commentsCounter').attr('noParagraph');

    var localLoader = context.find('.ajaxLoader').first();

    // console.log(uuid);
    // console.log(type);
    // console.log(noParagraph);

    // console.log(localLoader);

    var xhrPath = getXhrPath(
        ROUTE_COMMENTS,
        'document',
        'comments',
        RETURN_HTML
        );

    $.ajax({
        type: 'POST',
        url: xhrPath,
        context: context,
        data: { 'uuid': uuid, 'type': type, 'noParagraph': noParagraph },
        dataType: 'json',
        beforeSend: function ( xhr ) { xhrBeforeSend( xhr, localLoader ); },
        statusCode: { 404: function () { xhr404(localLoader); }, 500: function() { xhr500(localLoader); } },
        error: function ( jqXHR, textStatus, errorThrown ) { xhrError(jqXHR, textStatus, errorThrown, localLoader); },
        success: function(data) {
            localLoader.hide();
            if (data['error']) {
                $('#infoBoxHolder .boxError .notifBoxText').html(data['error']);
                $('#infoBoxHolder .boxError').show();
            } else {
                $(this).find('.comments').html(data['html']).slideDown();
                $(this).find('.counter').html(data['counter']);
                fullImgLiquid();

                // input text counter
                commentTextCounter();

                // scroll to comment
                $('html, body').animate({
                    scrollTop: $(this).find('.comments').offset().top
                }, '1000');

                // focus
                $('#comment_description').focus();
            }
        }
    });        
}

// création d'un commentaire
$("body").on("click", "input[action='createComment']", function(e) {
    // console.log('*** click createComment');
    
    var context = $(this).closest('.paragraph');
    var localLoader = $(this).prev('.ajaxLoader');
    var xhrPath = getXhrPath(
        ROUTE_COMMENT_CREATE,
        'document',
        'commentNew',
        RETURN_HTML
        );

    var textCount = $('.textCount').text();
    if (textCount < 5 || textCount > 500) {
        return false;
    }

    $.ajax({
        type: 'POST',
        url: xhrPath,
        context: context,
        data: $("#formCommentNew").serialize(),
        dataType: 'json',
        beforeSend: function ( xhr ) { xhrBeforeSend( xhr, localLoader ); },
        statusCode: { 404: function () { xhr404(localLoader); }, 500: function() { xhr500(localLoader); } },
        error: function ( jqXHR, textStatus, errorThrown ) { xhrError(jqXHR, textStatus, errorThrown, localLoader); },
        success: function(data) {
            localLoader.hide();
            if (data['error']) {
                $('#infoBoxHolder .boxError .notifBoxText').html(data['error']);
                $('#infoBoxHolder .boxError').show();
            } else {
                $(this).find('.comments').html(data['html']).slideDown();
                $(this).find('.counter').html(data['counter']);
                fullImgLiquid();

                // $("#formCommentNew").trigger("reset");
                $("#comment_description").val("");

                // input text counter
                commentTextCounter();
            }
        }
    });

});