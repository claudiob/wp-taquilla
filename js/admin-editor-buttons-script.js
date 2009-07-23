jQuery(document).ready(function($){

    var editor_toolbar = $("#ed_toolbar");

    edButtons[edButtons.length]=new edButton("ed_link","link","","</a>","a");

    if ( editor_toolbar ) {
        var theButton = document.createElement('input');
            theButton.type = 'button';
            theButton.value = Taquilla_Admin.str_EditorButtonCaption;
            theButton.className = 'ed_button';
            theButton.title = Taquilla_Admin.str_EditorButtonCaption;
            theButton.id = 'ed_button_taquilla';
            editor_toolbar.append( theButton );
            $("#ed_button_taquilla").click( taquilla_button_click );
    }

    function taquilla_button_click() {

        var title = 'Taquilla';
        var url = Taquilla_Admin.str_EditorButtonAjaxURL.replace(/&amp;/g, "&");
        url = url.replace(/&#038;/g, "&");

        tb_show( title, url, false);
        
        $("#TB_ajaxContent").width("100%").height("100%")
        .click(function(event) {
            var $target = $(event.target);
            if ( $target.is('a.send_movie_to_editor') ) {
                var movie_id = $target.attr('title');
                send_to_editor( '[movie id=' + movie_id + ' /]' );
            }
            return false;
        });

        return false;
    }

});