// beta

// init select2 & hide cities
$(function() {
    // console.log('*** init edit.js');

    $departmentUuid = $('.department_choice').val();
    // console.log($departmentUuid);
    if ($departmentUuid == '') {
        $('.control-group-city').hide();
    }

    $('select.select2_choice').select2({
        language: "fr"
    });
});


// department choice event
$("body").on("change", ".department_choice", function(e) {
    // console.log('*** click change department');

    var context = $('#main');
    var departmentUuid = $(this).val();

    if (departmentUuid == '') {
        $('.control-group-city').hide();
        $('.city_choice').html();

        return;
    }

    return initCities(context, departmentUuid);
});


/**
 * Refresh city selection by department
 *
 * @param contextZone
 * @param localLoader
 * @param departmentUuid
 */
function initCities(contextZone, departmentUuid)
{
    // console.log('*** initCities');
    // console.log(departmentUuid);

    var xhrPath = getXhrPath(
        xhrRouteCity,
        'localization',
        'citiesSelectList',
        RETURN_HTML
    );

    return xhrCall(
        contextZone,
        { 'departmentUuid': departmentUuid },
        xhrPath,
        null
    ).done(function(data) {
        $('#ajaxGlobalLoader').hide();
        if (data['error']) {
            $('#infoBoxHolder .boxError .notifBoxText').html(data['error']);
            $('#infoBoxHolder .boxError').show();
        } else {
            $('.city_choice').html(data['html']);
            $('select.city_choice').select2();
            $('.control-group-city').show();
        }
    });
}
