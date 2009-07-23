jQuery(document).ready(function($){

    // Taquilla_Admin object will contain all localized strings

    $("#export_format").change(function () {
        if ( 'csv' == $(this).val() )
            $(".tr-export-delimiter").css('display','movie-row');
        else
            $(".tr-export-delimiter").css('display','none');
        })
        .change();

    var movie_id = $(".taquilla-options #movie_id").val();
    $(".taquilla-options #movie_id").change(function () {
        if ( movie_id != $(this).val() ) {
            if ( confirm( Taquilla_Admin.str_ChangeMovieID ) )
                movie_id = $(this).val();
            else
                $(this).val( movie_id );
        }
    });

    $(".tr-import-addreplace input").click(function () {
        $('.tr-import-addreplace-movie').css('display','none');

        if( 'replace' == $('.tr-import-addreplace input:checked').val() ) {
            $('.tr-import-addreplace-movie').css('display','movie-row');
        }
    });
    $('.tr-import-addreplace input:checked').click();

    $(".tr-import-from input").click(function () {
        $('.tr-import-file').css('display','none');
        $('.tr-import-url').css('display','none');
        $('.tr-import-field').css('display','none');
        $('.tr-import-server').css('display','none');
      
        if( 'file-upload' == $('.tr-import-from input:checked').val() ) {
            $('.tr-import-file').css('display','movie-row');
        } else if( 'url' == $('.tr-import-from input:checked').val() ) {
            $('.tr-import-url').css('display','movie-row');
        } else if( 'form-field' == $('.tr-import-from input:checked').val() ) {
            $('.tr-import-field').css('display','movie-row');
        } else if( 'server' == $('.tr-import-from input:checked').val() ) {
            $('.tr-import-server').css('display','movie-row');
        }
    });
    $('.tr-import-from input:checked').click();

    $("#options_use_custom_css input").click(function () {
	  if( $('#options_use_custom_css input:checked').val() ) {
        $('#options_custom_css').removeAttr("disabled");
	  } else {
        $('#options_custom_css').attr("disabled", true);
	  }
      return true;
	});

    $("#options_use_movieheadline input").click(function () {
	  if( $('#options_use_movieheadline input:checked').val() && $('#moviesorter_enabled').val() ) {
        $('#options_use_moviesorter input').removeAttr("disabled");
	  } else {
        $('#options_use_moviesorter input').attr("disabled", true);
	  }
      return true;
	});

    $('.postbox h3, .postbox .handlediv').click( function() {
	$($(this).parent().get(0)).toggleClass('closed');
    } );

    $("#options_uninstall input").click(function () {
	  if( $('#options_uninstall input:checked').val() ) {
		return confirm( Taquilla_Admin.str_UninstallCheckboxActivation );
	  }
	});

    /*
    $("#movie_contents textarea").keypress(function () {
        var currentTextsize = $(this).val().split('\n').length;

        if ( 0 < currentTextsize ) {
            $(this).attr('rows', currentTextsize);
        }
	}).keypress();
    */

    var insert_html = '';

    function add_html() {
        var old_value = $(this).val();
        var new_value = old_value + insert_html;
        $(this).val( new_value );
        $("#movie_contents textarea").unbind('click', add_html);
    }

    $("#a-insert-link").click(function () {
        var link_url = prompt( Taquilla_Admin.str_DataManipulationLinkInsertURL + ':', 'http://' );
        if ( link_url ) {
            var link_text = prompt( Taquilla_Admin.str_DataManipulationLinkInsertText + ':', Taquilla_Admin.str_DataManipulationLinkInsertText );
            if ( link_text ) {
                insert_html = '<a href="' + link_url + '">' + link_text + '</a>';
                if ( confirm( Taquilla_Admin.str_DataManipulationLinkInsertExplain + '\n\n' + insert_html ) ) {
                    $("#movie_contents textarea").bind('click', add_html);
                }
            }
        }
		return false;
	});

    $("#a-insert-image").click(function () {
        var image_url = prompt( Taquilla_Admin.str_DataManipulationImageInsertURL + ':', 'http://' );
        if ( image_url ) {
            var image_alt = prompt( Taquilla_Admin.str_DataManipulationImageInsertAlt + ':', '' );
            // if ( image_alt ) { // won't check for alt, because there are cases where an empty one makes sense
                insert_html = '<img src="' + image_url + '" alt="' + image_alt + '" />';
                if ( true == confirm( Taquilla_Admin.str_DataManipulationImageInsertExplain + '\n\n' + insert_html ) ) {
                    $("#movie_contents textarea").bind('click', add_html);
                }
            // }
        }
		return false;
	});

    $("input.bulk_delete_movies").click(function () {
    	return confirm( Taquilla_Admin.str_BulkDeleteMoviesLink );
    });

    $("input.bulk_wp_movie_import_movies").click(function () {
    	return confirm( Taquilla_Admin.str_BulkImportwpMovieMoviesLink );
    });

    $("a.delete_movie_link").click(function () {
    	return confirm( Taquilla_Admin.str_DeleteMovieLink );
    });
    
    $("a.delete_row_link").click(function () {
    	return confirm( Taquilla_Admin.str_DeleteRowLink );
    });

    $("a.delete_column_link").click(function () {
    	return confirm( Taquilla_Admin.str_DeleteColumnLink );
    });

    $("a.import_wpmovie_link").click(function () {
    	return confirm( Taquilla_Admin.str_ImportwpMovieLink );
    });

    $("a.uninstall_plugin_link").click(function () {
        if ( confirm( Taquilla_Admin.str_UninstallPluginLink_1 ) ) { return confirm( Taquilla_Admin.str_UninstallPluginLink_2 ); } else { return false; }
    });

});