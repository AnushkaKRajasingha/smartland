/**
 * Created by anushkar on 2/26/19.
 */
function btnImportCallback($this) {
    console.log('btnImportCallback');
    jQuery($this).attr("disabled", "disabled");
    jQuery($this).val('Please wait...');
    var jqxhr = jQuery.ajax({
        url: ajaxurl,
        type: 'post',
        data: {
            'action':'doappfosync'
        }})
        .done(function(result) {
            console.log( "success" );
            _message = JSON.parse(result);
            alert(_message.Message);

            jQuery($this).val('Import');jQuery($this).removeAttr('disabled');
        })
        .fail(function(error) {
            console.log( "error" );
        })
        .always(function() {
            console.log( "complete" );

            jQuery($this).val('Import');jQuery($this).removeAttr('disabled');
        });
}

(function($){
    $(document).ready(function(){
        console.log('sunrise-custom.js');
    });


})(jQuery);