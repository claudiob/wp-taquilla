<?php
/*
Plugin Name: Taquilla - Class containing all the admin functions.
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

define('TAQUILLA_TEXTDOMAIN', 'taquilla');

class Taquilla_Admin { 

    var $plugin_version = '0.1';
    // nonce for security of links/forms, try to prevent "CSRF"
    var $nonce_base = 'taquilla-nonce';
    
    var $optionname = array(
        'options' => 'taquilla_options',
   );
    
    // default options values
    var $default_options = array(
        'installed_version' => '0',
#        'uninstall_upon_deactivation' => false,
   );

    // allowed actions in this class
    var $allowed_actions = array('list', 'add', 'edit', 'bulk_edit', 'delete', 'insert', 'import', 'options', 'uninstall', 'info'); // 'ajax_list', but handled separatly

    // init vars
    var $movies = array();
    var $options = array();
    
    // frontend vars
    var $shown_movies = array();
    var $shortcode = 'movie';
    

    ###########################################################################
    ####                                                                   ####
    ####   Initialization/Finalization functions                           ####
    ####                                                                   ####
    ###########################################################################

    function plugin_activation_hook() {
    // called when the plugin is activated
        $this->options = get_option($this->optionname['options']);
        if (false !== $this->options && isset($this->options['installed_version'])) {
            // check if update needed, or just reactivated the latest version of it
            if (version_compare($this->options['installed_version'], $this->plugin_version, '<')) {
                $this->plugin_update();
            } else {
                // just reactivating, but latest version of plugin installed
            }
        } else {
            // plugin has never been installed before
            $this->plugin_install();
        }
    }
    
    function plugin_install() {
    // write the current version and create the appropriate MySql tables    
        $this->options = $this->default_options;
        $this->options['installed_version'] = $this->plugin_version;
        $this->update_options();
        $this->create_table_movies();
    }

    function plugin_update() {
    // TODO 
    }


    function plugin_deactivation_hook() {
    // TODO
    }

    function init_language_support() {
        $language_directory = basename(dirname(__FILE__)) . '/languages';
        load_plugin_textdomain(TAQUILLA_TEXTDOMAIN, 'wp-content/plugins/' . $language_directory, $language_directory);
    }


    ###########################################################################
    ####                                                                   ####
    ####   Admin functions                                                 ####
    ####                                                                   ####
    ###########################################################################

    function Taquilla_Admin() { 

        if ( is_admin() ) {
            // ADMIN mode
            // load all movies 
    		$this->load_all_movies();
            // Create movies table if not existent
    		if (false === $this->movies)
                $this->plugin_install();

            add_action('admin_menu', array(&$this, 'add_manage_page'));

            // add JS to add button to editor on these pages
            $pages_with_editor_button = array('post.php', 'post-new.php', 'page.php', 'page-new.php');
            foreach ($pages_with_editor_button as $page)
                add_action('load-' . $page, array(&$this, 'add_editor_button'));

            // have to check for possible call by editor button to show list of movies
            if (isset($_GET['action']) && 'ajax_list' == $_GET['action']) {
                add_action('init', array(&$this, 'do_action_ajax_list'));
            }
        } else {
            // FRONTEND mode

            // add_filter('get_shortcode_regex', 'my_get_shortcode_regex', 1, 2);
            // function my_get_shortcode_regex() {
            // 	global $shortcode_tags;
            // 	$tagnames = array_keys($shortcode_tags);
            // 	$tagregexp = join( '|', array_map('preg_quote', $tagnames) );
            // 
            // 	return '(.?)|('.$tagregexp.')\b(.*?)(?:(\/))?|(?:(.+?)\[\/\2\])?(.?)';
            // }

            // shortcode for the_content, manual filter for widget_text
        	add_shortcode( $this->shortcode, array( &$this, 'handle_content_shortcode' ) );
            add_filter( 'widget_text', array( &$this, 'handle_widget_filter' ) );        	
        }
    } 
    

    function add_manage_page() {
    // add page, and what happens when page is loaded or shown
        $min_needed_capability = 'publish_posts'; // user needs at least this capability

        $this->hook = my_add_menu_page('Movies', 'Movies', $min_needed_capability, 'taquilla_manage_page', array(&$this, 'show_manage_page'), '', 8); 
        add_action('load-' . $this->hook, array(&$this, 'load_manage_page'));

        $this->hook = add_submenu_page('taquilla_manage_page', 'Taquilla', 'List movies', $min_needed_capability, 'taquilla_manage_page', array(&$this, 'show_manage_page')); 
        
#        $this->hook = add_management_page('Taquilla', 'Taquilla', $min_needed_capability, 'taquilla_manage_page', array(&$this, 'show_manage_page'));
    }
    
    function load_manage_page() {
    // only load the scripts, stylesheets and language by hook, if this admin page will be shown
    // all of this will be done before the page is shown by show_manage_page()
        // load js and css for admin
        //$this->add_manage_page_js();
        add_action('admin_footer', array(&$this, 'add_manage_page_js')); // can be put in footer, jQuery will be loaded anyway
        $this->add_manage_page_css();

        // init language support
        $this->init_language_support();
        
        if (true == function_exists('add_contextual_help')) // then WP version is >= 2.7
            add_contextual_help($this->hook, $this->get_contextual_help_string());
    }

    function show_manage_page() {
    // get and check action parameter from passed variables
        $action = (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) ? $_REQUEST['action'] : 'list';
        // check if action is in allowed actions and if method is callable, if yes, call it
        if (in_array($action, $this->allowed_actions) && is_callable(array(&$this, 'do_action_' . $action)))
            call_user_func(array(&$this, 'do_action_' . $action));
        else
            call_user_func(array(&$this, 'do_action_list'));
    }

    function add_manage_page_js() {
    // enqueue javascript-file, with some jQuery stuff
        $jsfile = 'admin-script.js';
        if (file_exists(TAQUILLA_ABSPATH . 'js/' . $jsfile)) {
            wp_register_script('taquilla-admin-js', TAQUILLA_URL . 'js/' . $jsfile, array('jquery'), $this->plugin_version);
            // add all strings to translate here
            wp_localize_script('taquilla-admin-js', 'Taquilla_Admin', array(
	  	        'str_UninstallCheckboxActivation' => __('Do you really want to activate this? You should only do that right before uninstallation!', TAQUILLA_TEXTDOMAIN),
	  	        'str_DataManipulationLinkInsertURL' => __('URL of link to insert', TAQUILLA_TEXTDOMAIN),
	  	        'str_DataManipulationLinkInsertText' => __('Text of link', TAQUILLA_TEXTDOMAIN),
	  	        'str_DataManipulationLinkInsertExplain' => __('To insert the following link into a cell, just click the cell after closing this dialog.', TAQUILLA_TEXTDOMAIN),
	  	        'str_DataManipulationImageInsertURL' => __('URL of image to insert', TAQUILLA_TEXTDOMAIN),
	  	        'str_DataManipulationImageInsertAlt' => __("''alt'' text of the image", TAQUILLA_TEXTDOMAIN),
	  	        'str_DataManipulationImageInsertExplain' => __('To insert the following image into a cell, just click the cell after closing this dialog.', TAQUILLA_TEXTDOMAIN),
	  	        'str_BulkDeleteMoviesLink' => __('The selected movies and all content will be erased. Do you really want to delete them?', TAQUILLA_TEXTDOMAIN),
	  	        'str_BulkImportwpMovieMoviesLink' => __('Do you really want to import the selected movies from the wp-Movie plugin?', TAQUILLA_TEXTDOMAIN),
	  	        'str_DeleteMovieLink' => __('The complete movie and all content will be erased. Do you really want to delete it?', TAQUILLA_TEXTDOMAIN),
	  	        'str_DeleteRowLink' => __('Do you really want to delete this row?', TAQUILLA_TEXTDOMAIN),
	  	        'str_DeleteColumnLink' => __('Do you really want to delete this column?', TAQUILLA_TEXTDOMAIN),
	  	        'str_ImportwpMovieLink' => __('Do you really want to import this movie from the wp-Movie plugin?', TAQUILLA_TEXTDOMAIN),
	  	        'str_UninstallPluginLink_1' => __('Do you really want to uninstall the plugin and delete ALL data?', TAQUILLA_TEXTDOMAIN),
	  	        'str_UninstallPluginLink_2' => __('Are you really sure?', TAQUILLA_TEXTDOMAIN),
	  	        'str_ChangeMovieID' => __('Do you really want to change the ID of the movie?', TAQUILLA_TEXTDOMAIN)
           ));
            wp_print_scripts('taquilla-admin-js');
        }
    }

    function add_manage_page_css() {
    // enqueue css-stylesheet-file for admin, if it exists
        $cssfile = 'admin-style.css';
        if (file_exists(TAQUILLA_ABSPATH . 'css/' . $cssfile)) {
            if (function_exists('wp_enqueue_style'))
                wp_enqueue_style('taquilla-admin-css', TAQUILLA_URL . 'css/' . $cssfile, array(), $this->plugin_version);
            else
                add_action('admin_head', array(&$this, 'print_admin_style'));
        }
    }

    function print_admin_style() {
    // print our style in wp-admin-head (only needed for WP < 2.6)
        $cssfile = 'admin-style.css';
        echo "<link rel='stylesheet' href='" . TAQUILLA_URL . 'css/' . $cssfile . "' type='text/css' media='' />\n";
    }



    
    ###########################################################################
    ####                                                                   ####
    ####   Print-support functions                                         ####
    ####                                                                   ####
    ###########################################################################

    function get_contextual_help_string() {
        return __('More information can be found on the <a href="">plugin\'s website</a>.', TAQUILLA_TEXTDOMAIN) . '<br/>' . __('See the <a href="">documentation</a> or find out how to get <a href="">support</a>.', TAQUILLA_TEXTDOMAIN);
    }

    function safe_output($string) {
        return htmlspecialchars(stripslashes($string));
    }

    function print_success_message($text) {
        echo "<div id='message' class='updated fade'><p><strong>{$text}</strong></p></div>";
    }

    function print_page_header($text = 'Taquilla') {
        echo <<<TEXT
<div class='wrap'>
<h2>{$text}</h2>
<div id='poststuff'>
TEXT;
    }

    function print_page_footer() {
        echo "</div></div>";
    }

    function print_submenu_navigation($action) {
        ?>
        <ul class="subsubsub">
            <li><a <?php if ('list' == $action) echo 'class="current" '; ?>href="<?php echo $this->get_action_url(array('action' => 'list')); ?>"><?php _e('List Movies', TAQUILLA_TEXTDOMAIN); ?></a> | </li>
            <li><a <?php if ('add' == $action) echo 'class="current" '; ?>href="<?php echo $this->get_action_url(array('action' => 'add')); ?>"><?php _e('Add new Movie', TAQUILLA_TEXTDOMAIN); ?></a> | </li>
            <li><a <?php if ('import' == $action) echo 'class="current" '; ?>href="<?php echo $this->get_action_url(array('action' => 'import')); ?>"><?php _e('Import a Movie', TAQUILLA_TEXTDOMAIN); ?></a> | </li>
            <li>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</li>
            <li><a <?php if ('options' == $action) echo 'class="current" '; ?>href="<?php echo $this->get_action_url(array('action' => 'options')); ?>"><?php _e('Plugin Options', TAQUILLA_TEXTDOMAIN); ?></a> | </li>
            <li><a <?php if ('info' == $action) echo 'class="current" '; ?>href="<?php echo $this->get_action_url(array('action' => 'info')); ?>"><?php _e('About the Plugin', TAQUILLA_TEXTDOMAIN); ?></a></li>
        </ul>
        <br class="clear" />
        <?php
    }


    ###########################################################################
    ####                                                                   ####
    ####   Print-related functions                                         ####
    ####                                                                   ####
    ###########################################################################
        
    function print_list_movies_form()  {
        // list all movies
        $this->print_page_header(__('List of Movies', TAQUILLA_TEXTDOMAIN));
        $this->print_submenu_navigation('list');
        ?>
        <div style="clear:both;"><p><?php _e('This is a list of all available movies.', TAQUILLA_TEXTDOMAIN); ?> <?php _e('You may add, edit or delete movies here.', TAQUILLA_TEXTDOMAIN); ?><br />
		<?php _e('If you want to show a movie in your pages, posts or text-widgets, use the shortcode <strong>[movie id=&lt;the_movie_ID&gt; /]</strong> or click the button "Movie" in the editor toolbar.', TAQUILLA_TEXTDOMAIN); ?></p></div>
		<?php
        if (0 < count($this->movies)) {
            ?>
        <div style="clear:both;">
            <form method="post" action="<?php echo $this->get_action_url(); ?>">
            <?php wp_nonce_field($this->get_nonce('bulk_edit')); ?>
            <table class="widefat">
            <thead>
                <tr>
                    <th class="check-column" scope="col"><input type="checkbox" /></th>
                    <th scope="col"><?php _e('ID', TAQUILLA_TEXTDOMAIN); ?></th>
                    <th scope="col"><?php _e('Movie Title', TAQUILLA_TEXTDOMAIN); ?></th>
                    <th scope="col"><?php _e('Original Title', TAQUILLA_TEXTDOMAIN); ?></th>
                    <th scope="col"><?php _e('Minutes', TAQUILLA_TEXTDOMAIN); ?></th>
                    <th scope="col"><?php _e('Year', TAQUILLA_TEXTDOMAIN); ?></th>
                    <th scope="col"><?php _e('Budget', TAQUILLA_TEXTDOMAIN); ?></th>
                    <th scope="col"><?php _e('Action', TAQUILLA_TEXTDOMAIN); ?></th>
                </tr>
            </thead>
            <?php
            echo "<tbody>\n";
            $bg_style_index = 0;
            // TODO pagination
            foreach ($this->movies as $movie) {
                $bg_style_index++;
                $bg_style = (0 == ($bg_style_index % 2)) ? ' class="alternate"' : '';

                // get name and description to show in list
                $id = $this->safe_output($movie['id']);
                $title = $this->safe_output($movie['title']);
                $title_original = $this->safe_output($movie['title_original']);
                $minutes = $this->safe_output($movie['minutes']);
                $year = $this->safe_output($movie['year']);
                $budget = $this->safe_output($movie['budget']);
                unset($movie);

                $edit_url = $this->get_action_url(array('action' => 'edit', 'movie_id' => $id), false);
                $delete_url = $this->get_action_url(array('action' => 'delete', 'movie_id' => $id, 'item' => 'movie'), true);

                echo "<tr{$bg_style}>\n";
                echo "\t<th class=\"check-column\" scope=\"row\"><input type=\"checkbox\" name=\"movies[]\" value=\"{$id}\" /></th>";
                echo "<th scope=\"row\">{$id}</th>";
                echo "<td>{$title}</td>";
                echo "<td>{$title_original}</td>";
                echo "<td>{$minutes}</td>";
                echo "<td>{$year}</td>";
                echo "<td>{$budget}</td>";
                echo "<td><a href=\"{$edit_url}\">" . __('Edit', TAQUILLA_TEXTDOMAIN) . "</a>" . " | ";
                echo "<a class=\"delete_movie_link delete\" href=\"{$delete_url}\">" . __('Delete', TAQUILLA_TEXTDOMAIN) . "</a></td>\n";
                echo "</tr>\n";

            }
            echo "</tbody>\n";
            echo "</table>\n";
        ?>
        <input type="hidden" name="action" value="bulk_edit" />
        <p class="submit"><?php _e('Bulk actions:', TAQUILLA_TEXTDOMAIN); ?>  <input type="submit" name="submit[delete]" class="button-primary bulk_delete_movies" value="<?php _e('Delete Movies', TAQUILLA_TEXTDOMAIN); ?>" />
        </p>

        </form>
        <?php
            echo "</div>";
        } else { // end if $movies
            $add_url = $this->get_action_url(array('action' => 'add'), false);
            $import_url = $this->get_action_url(array('action' => 'import'), false);
            echo "<div style=\"clear:both;\"><p>" . __('No movies found.', TAQUILLA_TEXTDOMAIN) . '<br/>' . sprintf(__('You might <a href="%s">add</a> or <a href="%s">import</a> one!', TAQUILLA_TEXTDOMAIN), $add_url, $import_url) . "</p></div>";
        }
        $this->print_page_footer();
    }


    function print_add_movie_form() {
        
        $this->print_page_header(__('Add new Movie', TAQUILLA_TEXTDOMAIN));
        $this->print_submenu_navigation('add');
        ?>
        <div style="clear:both;">
        <p><?php _e('You can add a new movie here. Just enter its title, and other optional arguments.<br/>You may add, insert or delete properties later.', TAQUILLA_TEXTDOMAIN); ?></p>
        </div>
		<div style="clear:both;">
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field($this->get_nonce('add')); ?>

        <table class="taquilla-options">
        <tr valign="top">
            <th scope="row"><label for="movie[title]"><?php _e('Title', TAQUILLA_TEXTDOMAIN); ?>:</label></th>
            <td><input type="text" name="movie[title]" value="<?php _e('Enter Movie Title', TAQUILLA_TEXTDOMAIN); ?>" style="width:250px;" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[title_original]"><?php _e('Original Title', TAQUILLA_TEXTDOMAIN); ?>:</label></th>
            <td><input type="text" name="movie[title_original]" value="<?php _e('Enter Original Title', TAQUILLA_TEXTDOMAIN); ?>" style="width:250px;" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[minutes]"><?php _e('Minutes', TAQUILLA_TEXTDOMAIN); ?>:</label></th>
            <td><input type="text" name="movie[minutes]" value="<?php _e('Enter Movie Minutes', TAQUILLA_TEXTDOMAIN); ?>" style="width:100px;" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[year]"><?php _e('Year', TAQUILLA_TEXTDOMAIN); ?>:</label></th>
            <td><input type="text" name="movie[year]" value="<?php _e('Enter Movie Year', TAQUILLA_TEXTDOMAIN); ?>" style="width:100px;" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[budget]"><?php _e('Budget', TAQUILLA_TEXTDOMAIN); ?>:</label></th>
            <td><input type="text" name="movie[budget]" value="<?php _e('Enter Movie Budget', TAQUILLA_TEXTDOMAIN); ?>" style="width:100px;" /></td>
        </tr>
        </table>

        <input type="hidden" name="action" value="add" />
        <p class="submit">
        <input type="submit" name="submit" class="button-primary" value="<?php _e('Add Movie', TAQUILLA_TEXTDOMAIN); ?>" />
        </p>

        </form>
        </div>
        <?php
        $this->print_page_footer();
    }

    function print_edit_movie_form2($movie_id) {
        $movie = $this->load_movie($movie_id);

        $this->print_page_header(sprintf(__('Edit Movie "%s"', TAQUILLA_TEXTDOMAIN), $this->safe_output($movie['title'])) . " (ID " . $this->safe_output($movie['id']) . ")" );
        $this->print_submenu_navigation('edit');
        ?>
        <div style="clear:both;"><p><?php _e('You may edit the properties of the movie here.', TAQUILLA_TEXTDOMAIN); ?><br />
		<?php echo sprintf(__('If you want to show a movie in your pages, posts or text-widgets, use this shortcode: <strong>[movie id=%s /]</strong>', TAQUILLA_TEXTDOMAIN), $this->safe_output($movie_id)); ?></p></div>
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field($this->get_nonce('edit')); ?>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e('Movie Information', TAQUILLA_TEXTDOMAIN); ?></span></h3>
        <div class="inside">
        <table class="taquilla-options">
        <tr valign="top">
            <th scope="row"><label for="movie_id"><?php _e('Movie ID', TAQUILLA_TEXTDOMAIN); ?>:</label></th>
            <td><?php echo $this->safe_output($movie['id']); ?></td>
            <input type="text" name="movie_id" id="movie_id" value="<?php echo $this->safe_output( $movie['id'] ); ?>" style="width:250px" />
            <input type="text" name="movie[id]" id="movie[id]" value="<?php echo $this->safe_output( $movie['id'] ); ?>" style="width:250px" />
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[title]"><?php _e('Movie Title', TAQUILLA_TEXTDOMAIN); ?>:</label></th>
            <td><input type="text" name="movie[title]" id="movie[title]" value="<?php echo $this->safe_output($movie['title']); ?>" style="width:250px" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[title_original]"><?php _e('Original Title', TAQUILLA_TEXTDOMAIN); ?>:</label></th>
            <td><input type="text" name="movie[title_original]" id="movie[title_original]" value="<?php echo $this->safe_output($movie['title_original']); ?>" style="width:250px" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[minutes]"><?php _e('Movie Minutes', TAQUILLA_TEXTDOMAIN); ?>:</label></th>
            <td><input type="text" name="movie[minutes]" id="movie[minutes]" value="<?php echo $this->safe_output($movie['minutes']); ?>" style="width:250px" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[year]"><?php _e('Movie Year', TAQUILLA_TEXTDOMAIN); ?>:</label></th>
            <td><input type="text" name="movie[year]" id="movie[year]" value="<?php echo $this->safe_output($movie['year']); ?>" style="width:250px" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[budget]"><?php _e('Movie Budget', TAQUILLA_TEXTDOMAIN); ?>:</label></th>
            <td><input type="text" name="movie[budget]" id="movie[budget]" value="<?php echo $this->safe_output($movie['budget']); ?>" style="width:250px" /></td>
        </tr>
        </table>
        </div>
        </div>

        <p class="submit">
        <input type="submit" name="submit[update]" class="button-primary" value="<?php _e('Update Changes', TAQUILLA_TEXTDOMAIN); ?>" />
        <input type="submit" name="submit[save_back]" class="button-primary" value="<?php _e('Save and go back', TAQUILLA_TEXTDOMAIN); ?>" />
        <?php
        $list_url = $this->get_action_url(array('action' => 'list'));
        echo " <a class=\"button-primary\" href=\"{$list_url}\">" . __('Cancel', TAQUILLA_TEXTDOMAIN) . "</a>";
        ?>
        <?php
        $delete_url = $this->get_action_url(array('action' => 'delete', 'movie_id' => $movie['id'], 'item' => 'movie'), true);
        echo " <a class=\"button-secondary delete_movie_link\" href=\"{$delete_url}\">" . __('Delete Movie', TAQUILLA_TEXTDOMAIN) . "</a>";
        ?>
        </p>
        </form>
        <?php
        $this->print_page_footer();
    }

    // ###################################################################################################################
    function print_edit_movie_form( $movie_id ) {

        $movie = $this->load_movie( $movie_id );

        $this->print_page_header( sprintf( __( 'Edit Movie "%s"', TAQUILLA_TEXTDOMAIN ), $this->safe_output( $movie['title'] ) ) . " (ID " . $this->safe_output( $movie['id'] ) . ")"  );
        $this->print_submenu_navigation( 'edit' );
        ?>
        <div style="clear:both;"><p><?php _e( 'You may edit the properties of the movie here.', TAQUILLA_TEXTDOMAIN ); ?><br />
		<?php echo sprintf( __( 'If you want to show a movie in your pages, posts or text-widgets, use this shortcode: <strong>[movie id=%s /]</strong>', TAQUILLA_TEXTDOMAIN ), $this->safe_output( $movie_id ) ); ?></p></div>
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field( $this->get_nonce( 'edit' ) ); ?>

        <div class="postbox">
        <h3 class="hndle"><span><?php _e( 'Movie Information', TAQUILLA_TEXTDOMAIN ); ?></span></h3>
        <div class="inside">
        <table class="taquilla-options">
        <tr valign="top">
            <th scope="row"><label for="movie[id]"><?php _e( 'Movie ID', TAQUILLA_TEXTDOMAIN ); ?>:</label></th>
            <td><?php echo $this->safe_output( $movie['id'] ); ?></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[title]"><?php _e( 'Movie Title', TAQUILLA_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="movie[title]" id="movie[title]" value="<?php echo $this->safe_output( $movie['title'] ); ?>" style="width:250px" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[title_original]"><?php _e( 'Original Title', TAQUILLA_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="movie[title_original]" id="movie[title_original]" value="<?php echo $this->safe_output( $movie['title_original'] ); ?>" style="width:250px" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[minutes]"><?php _e( 'Movie Minutes', TAQUILLA_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="movie[minutes]" id="movie[minutes]" value="<?php echo $this->safe_output( $movie['minutes'] ); ?>" style="width:250px" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[year]"><?php _e( 'Movie Year', TAQUILLA_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="movie[year]" id="movie[year]" value="<?php echo $this->safe_output( $movie['year'] ); ?>" style="width:250px" /></td>
        </tr>
        <tr valign="top">
            <th scope="row"><label for="movie[budget]"><?php _e( 'Movie Budget', TAQUILLA_TEXTDOMAIN ); ?>:</label></th>
            <td><input type="text" name="movie[budget]" id="movie[budget]" value="<?php echo $this->safe_output( $movie['budget'] ); ?>" style="width:250px" /></td>
        </tr>
        </table>
        </div>
        </div>

        <p class="submit">
        <input type="hidden" name="movie[id]" value="<?php echo $movie['id']; ?>" />
        <input type="hidden" name="action" value="edit" />
        <input type="submit" name="submit[update]" class="button-primary" value="<?php _e( 'Update Changes', TAQUILLA_TEXTDOMAIN ); ?>" />
        <input type="submit" name="submit[save_back]" class="button-primary" value="<?php _e( 'Save and go back', TAQUILLA_TEXTDOMAIN ); ?>" />
        <?php
        $list_url = $this->get_action_url( array( 'action' => 'list' ) );
        echo " <a class=\"button-primary\" href=\"{$list_url}\">" . __( 'Cancel', TAQUILLA_TEXTDOMAIN ) . "</a>";
        $delete_url = $this->get_action_url( array( 'action' => 'delete', 'movie_id' => $movie['id'], 'item' => 'movie' ), true );
        echo " <a class=\"button-secondary delete_movie_link\" href=\"{$delete_url}\">" . __( 'Delete Movie', TAQUILLA_TEXTDOMAIN ) . "</a>";
        ?>
        </p>
        </form>
        <?php
        $this->print_page_footer();
    }


    
    ###########################################################################
    ####                                                                   ####
    ####   MySql-related functions                                         ####
    ####                                                                   ####
    ###########################################################################
    
    function update_options() {
        update_option($this->optionname['options'], $this->options);
    }
        
    function create_table_movies() {
    // create the table wp_movies in the MySql database
    // See http://codex.wordpress.org/Creating_Tables_with_Plugins
        global $wpdb;
        $table_name = $wpdb->prefix . "tq_movies";
        if($wpdb->get_var("show tables like '$table_name'") != $table_name) {

          $sql = "CREATE TABLE " . $table_name . " (
              id mediumint(9) NOT NULL auto_increment,
              title varchar(80) default NULL,
              title_original varchar(80) default NULL,
              minutes smallint(5) unsigned default NULL,
              year year(4) default NULL,
              budget mediumint(8) unsigned default NULL,
              UNIQUE KEY `id` (`id`)
      	     );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);       
        }
        return $table_name;
    }
    
    function load_all_movies() {
        global $wpdb;
        $table_name = $wpdb->prefix . "tq_movies";
        $select = "SELECT * FROM " . $table_name; 
        // TODO pagination  
        $this->movies = $wpdb->get_results($select, ARRAY_A);
    }

    function load_movie($movie_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . "tq_movies";
        $select = "SELECT * FROM " . $table_name . 
        " WHERE id = " . $movie_id . " LIMIT 1"; 
        return $wpdb->get_row($select, ARRAY_A);
    }

    // TODO combine with load_movie in a unique function
    function load_movie_by_title($movie_title) {
        global $wpdb;
        $table_name = $wpdb->prefix . "tq_movies";
        $select = "SELECT * FROM " . $table_name . 
        " WHERE lower(title) = '" . strtolower($movie_title) . "' LIMIT 1"; 
        return $wpdb->get_row($select, ARRAY_A);
    }
    
    
    function save_movie($movie) {
        global $wpdb;
        // TODO call global $wpdb only once in the class
        $table_name = $wpdb->prefix . "tq_movies";
        // TODO take column names from array keys
        // TODO apply $wpdb->escape on each key in one shot
        $minutes = $wpdb->escape($movie['minutes']);
        $minutes = ($minutes == '' ? 'NULL' : $minutes);
        $budget = $wpdb->escape($movie['budget']);
        $budget = ($budget == '' ? 'NULL' : $budget);
        $year = $wpdb->escape($movie['year']);
        $year = ($year == '' ? 'NULL' : $year);
        $insert = "INSERT INTO " . $table_name .
        "(title, title_original, minutes, year, budget) VALUES ('" .
        $wpdb->escape($movie['title']) . "', '" . 
        $wpdb->escape($movie['title_original']) . "', " . 
        $minutes . ", " . $year . ", " . $budget . ")"; 
        $wpdb->query($insert);
        return mysql_insert_id ($wpdb->dbh);
    }

    function update_movie($movie) {
        global $wpdb;
        // TODO call global $wpdb only once in the class
        $table_name = $wpdb->prefix . "tq_movies";        
        // TODO take column names from array keys
        // TODO apply $wpdb->escape on each key in one shot
        $minutes = $wpdb->escape($movie['minutes']);
        $minutes = ($minutes == '' ? 'NULL' : $minutes);
        $budget = $wpdb->escape($movie['budget']);
        $budget = ($budget == '' ? 'NULL' : $budget);
        $year = $wpdb->escape($movie['year']);
        $year = ($year == '' ? 'NULL' : $year);

        // TODO Check how to user ->update with values that are NULL
        // $wpdb->update($table_name,
        //   array('title' => $wpdb->escape($movie['title']), 
        //         'title_original' => $wpdb->escape($movie['title_original']),
        //         'minutes' => $minutes,
        //         'year' => $year,
        //         'budget' => $budget), 
        //   array('id' => $movie['id'] ));

        // TODO prepare and protect from forgery
        $update = "UPDATE " . $table_name . " SET " .
         "title = '" . $wpdb->escape($movie['title']) . "', " .
         "title_original = '" . $wpdb->escape($movie['title_original']) . "', " .
         "minutes = " . $minutes  . ", year = " . $year . ", " .
         "budget = " . $budget . " WHERE id = " . $movie['id']; 
        $wpdb->query($update);
    }


    function delete_movie($movie_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . "tq_movies";
        $delete = "DELETE FROM " . $table_name . " WHERE ID = " . $movie_id;
        $result = $wpdb->query($delete);
        // TODO improve this next call, which can be heavy
		$this->load_all_movies();
		return $result;
    }

    ###########################################################################
    ####                                                                   ####
    ####   Add button to visual editor                                     ####
    ####                                                                   ####
    ###########################################################################
    
    function add_editor_button() {
        if (0 < count($this->movies)) {
            $this->init_language_support();
            add_action('admin_footer', array(&$this, 'add_editor_button_js'));
        }
    }

    function add_editor_button_js() {
    // print out the JS in the admin footer
        $params = array(
            'page' => 'taquilla_manage_page',
            'action' => 'ajax_list');
        $ajax_url = add_query_arg($params, dirname($_SERVER['PHP_SELF']) . '/tools.php');
        $ajax_url = wp_nonce_url($ajax_url, $this->get_nonce($params['action'], false));

        $jsfile = 'admin-editor-buttons-script.js';
        if (file_exists(TAQUILLA_ABSPATH . 'js/' . $jsfile)) {
            wp_register_script('taquilla-admin-editor-buttons-js', TAQUILLA_URL . 'js/' . $jsfile, array('jquery', 'thickbox'), $this->plugin_version);
            // add all strings to translate here
            wp_localize_script('taquilla-admin-editor-buttons-js', 'Taquilla_Admin', array(
	  	        'str_EditorButtonCaption' => __('Movie', TAQUILLA_TEXTDOMAIN),
	  	        'str_EditorButtonAjaxURL' => $ajax_url
           ));
            wp_print_scripts('taquilla-admin-editor-buttons-js');
        }
    }

    ###########################################################################
    ####                                                                   ####
    ####   URL support                                                     ####
    ####                                                                   ####
    ###########################################################################

    function get_nonce($action, $item = false) {
        return (false !== $item) ? $this->nonce_base . '_' . $action . '_' . $item : $this->nonce_base . '_' . $action;
    }

    function get_action_url($params = array(), $add_nonce = false) {
        $default_params = array(
                'page' => $_REQUEST['page'],
                'action' => false,
                'item' => false
       );
        $url_params = array_merge($default_params, $params);

        $action_url = add_query_arg($url_params, $_SERVER['PHP_SELF']);
        $action_url = (true == $add_nonce) ? wp_nonce_url($action_url, $this->get_nonce($url_params['action'], $url_params['item'])) : $action_url;
        return $action_url;
    }

    ###########################################################################
    ####                                                                   ####
    ####   Admin actions                                                   ####
    ####                                                                   ####
    ###########################################################################

    function do_action_list()  {
    // list all movies
        $this->print_list_movies_form();
    }

    // ###################################################################################################################
    function do_action_add() {
        if (isset($_POST['submit']) && isset($_POST['movie'])) {
            check_admin_referer($this->get_nonce('add'));

            $movie = array();

            $movie['title'] = $_POST['movie']['title'];
            $movie['title_original'] = $_POST['movie']['title_original'];
            $movie['minutes'] = $_POST['movie']['minutes'];
            $movie['year'] = $_POST['movie']['year'];
            $movie['budget'] = $_POST['movie']['budget'];

            $movie_id = $this->save_movie($movie);

            $this->print_success_message(sprintf(__('Movie "%s" added successfully.', TAQUILLA_TEXTDOMAIN), $this->safe_output($movie['title'])));
            $this->print_edit_movie_form($movie_id);
        } else {
            $this->print_add_movie_form();
        }
    }

    // ###################################################################################################################
    function do_action_edit() {
        if (isset($_POST['submit']) && isset($_POST['movie'])) {
            check_admin_referer($this->get_nonce('edit'));

            $subactions = array_keys($_POST['submit']);
            $subaction = $subactions[0];

            switch($subaction) {
            case 'update':
            case 'save_back':
                $movie = $_POST['movie'];   // careful here to not miss any stuff!!! (options, etc.)
                # $movie['options']['alternating_row_colors'] = isset($_POST['movie']['options']['alternating_row_colors']);
                $this->update_movie($movie);
                $message = sprintf(__( "Movie edited successfully.", WP_BOXOFFICE_TEXTDOMAIN ));
                break;
            default:
                $this->do_action_list();
            }

            $this->print_success_message($message);
            if ('save_back' == $subaction) {
                $this->do_action_list();
            } else {
                $this->print_edit_movie_form($movie['id']);
            }
        } elseif (isset($_GET['movie_id'])) {
            $this->print_edit_movie_form($_GET['movie_id']);
        } else {
            $this->do_action_list();
        }
    }

    // ###################################################################################################################
    function do_action_bulk_edit() {
#        if (isset($_POST['submit'])) {
#            check_admin_referer($this->get_nonce('bulk_edit'));
#
#            if (isset($_POST['movies'])) {
#
#                $subactions = array_keys($_POST['submit']);
#                $subaction = $subactions[0];
#
#                switch($subaction) {
#                case 'delete': // see do_action_delete for explanations
#                    foreach ($_POST['movies'] as $movie_id) {
#                        $this->delete_movie($movie_id);
#                    }
#                    $message = __ngettext('Movie deleted successfully.', 'Movies deleted successfully.', count($_POST['movies']), TAQUILLA_TEXTDOMAIN);
#                    break;
#                case 'wp_movie_import': // see do_action_import for explanations
#                    $this->import_instance = $this->create_class_instance('WP_Boxoffice_Import', 'taquilla-import.class.php');
#                    $this->import_instance->import_format = 'wp_movie';
#                    foreach ($_POST['movies'] as $movie_id) {
#                        $this->import_instance->wp_movie_id = $movie_id;
#                        $this->import_instance->import_movie();
#                        $imported_movie = $this->import_instance->imported_movie;
#                        $movie = array_merge($this->default_movie, $imported_movie);
#                        $movie['id'] = $this->get_new_movie_id();
#                        $this->save_movie($movie);
#                    }
#                    $message = __ngettext('Movie imported successfully.', 'Movies imported successfully.', count($_POST['movies']), TAQUILLA_TEXTDOMAIN);
#                    break;
#                default:
#                    break;
#                }
#
#            } else {
#                $message = __('You did not select any movies!', TAQUILLA_TEXTDOMAIN);
#            }
#            $this->print_success_message($message);
#        }
#        $this->do_action_list();
    }

    // ###################################################################################################################
    function do_action_delete() {
        if (isset($_GET['movie_id']) && isset($_GET['item'])) {
            check_admin_referer($this->get_nonce('delete', $_GET['item']));

            $movie_id = $_GET['movie_id'];
            $movie = $this->load_movie($movie_id);

            switch($_GET['item']) {
            case 'movie':
                $this->delete_movie($movie_id);
                $this->print_success_message(sprintf(__('Movie "%s" deleted successfully.', TAQUILLA_TEXTDOMAIN), $this->safe_output($movie['title'])));
                $this->do_action_list();
                break;
            # case 'result' or else...
            default:
                $this->print_success_message(__('Delete failed.', TAQUILLA_TEXTDOMAIN));
                $this->do_action_list();
            } // end switch
        } else {
            $this->do_action_list();
        }
    }

    // ###################################################################################################################
    function do_action_insert() {
#        if (isset($_GET['movie_id']) && isset($_GET['item']) && isset($_GET['element_id'])) {
#            check_admin_referer($this->get_nonce('insert', $_GET['item'] ));
#
#            $movie_id = $_GET['movie_id'];
#            $movie = $this->load_movie($movie_id);
#
#            switch($_GET['item']) {
#            case 'row':
#                $row_id = $_GET['element_id'];
#                $rows = count($movie['data']);
#                $cols = (0 < $rows) ? count($movie['data'][0]) : 0;
#                // init new empty row (with all columns) and insert it before row with key $row_id
#                $new_row = array(array_fill(0, $cols, ''));
#                array_splice($movie['data'], $row_id, 0, $new_row);
#                $this->save_movie($movie);
#                $message = __('Row inserted successfully.', TAQUILLA_TEXTDOMAIN);
#                break;
#            case 'col':
#                $col_id = $_GET['element_id'];
#                // init new empty row (with all columns) and insert it before row with key $row_id
#                $new_col = '';
#                foreach ($movie['data'] as $row_idx => $row)
#                    array_splice($movie['data'][$row_idx], $col_id, 0, $new_col);
#                $this->save_movie($movie);
#                $message = __('Column inserted successfully.', TAQUILLA_TEXTDOMAIN);
#                break;
#            default:
#                $message = __('Insert failed.', TAQUILLA_TEXTDOMAIN);
#            }
#            $this->print_success_message($message);
#            $this->print_edit_movie_form($movie_id);
#        } else {
#            $this->do_action_list();
#        }
    }

    // ###################################################################################################################
    function do_action_import() {
#        $this->import_instance = $this->create_class_instance('WP_Boxoffice_Import', 'taquilla-import.class.php');
#        if (isset($_POST['submit']) && isset($_POST['import_from'])) {
#            check_admin_referer($this->get_nonce('import'));
#
#            // do import
#            if ('file-upload' == $_POST['import_from'] && false === empty($_FILES['import_file']['tmp_name'])) {
#                $this->import_instance->tempname = $_FILES['import_file']['tmp_name'];
#                $this->import_instance->filename = $_FILES['import_file']['name'];
#                $this->import_instance->mimetype = $_FILES['import_file']['type'];
#                $this->import_instance->import_from = 'file-upload';
#                $this->import_instance->import_format = $_POST['import_format'];
#                $this->import_instance->import_movie();
#                $error = $this->import_instance->error;
#                $imported_movie = $this->import_instance->imported_movie;
#                $this->import_instance->unlink_uploaded_file();
#            } elseif ('server' == $_POST['import_from'] && false === empty($_POST['import_server'])) {
#                $this->import_instance->tempname = $_POST['import_server'];
#                $this->import_instance->filename = __('Imported Movie', TAQUILLA_TEXTDOMAIN);
#                $this->import_instance->mimetype = sprintf(__('from %s', TAQUILLA_TEXTDOMAIN), $_POST['import_server']);
#                $this->import_instance->import_from = 'server';
#                $this->import_instance->import_format = $_POST['import_format'];
#                $this->import_instance->import_movie();
#                $error = $this->import_instance->error;
#                $imported_movie = $this->import_instance->imported_movie;
#            } elseif ('form-field' == $_POST['import_from'] && false === empty($_POST['import_data'])) {
#                $this->import_instance->tempname = '';
#                $this->import_instance->filename = __('Imported Movie', TAQUILLA_TEXTDOMAIN);
#                $this->import_instance->mimetype = __('via form', TAQUILLA_TEXTDOMAIN);
#                $this->import_instance->import_from = 'form-field';
#                $this->import_instance->import_data = stripslashes($_POST['import_data']);
#                $this->import_instance->import_format = $_POST['import_format'];
#                $this->import_instance->import_movie();
#                $error = $this->import_instance->error;
#                $imported_movie = $this->import_instance->imported_movie;
#            } elseif ('url' == $_POST['import_from'] && false === empty($_POST['import_url'])) {
#                $this->import_instance->tempname = '';
#                $this->import_instance->filename = __('Imported Movie', TAQUILLA_TEXTDOMAIN);
#                $this->import_instance->mimetype = sprintf(__('from %s', TAQUILLA_TEXTDOMAIN), $_POST['import_url']);
#                $this->import_instance->import_from = 'url';
#                $url = clean_url($_POST['import_url']);
#                $this->import_instance->import_data = wp_remote_retrieve_body(wp_remote_get($url));
#                $this->import_instance->import_format = $_POST['import_format'];
#                $this->import_instance->import_movie();
#                $error = $this->import_instance->error;
#                $imported_movie = $this->import_instance->imported_movie;
#            } else { // no valid data submitted
#                $this->print_success_message(__('Movie could not be imported.', TAQUILLA_TEXTDOMAIN));
#                $this->print_import_movie_form();
#                exit;
#            }
#
#            $movie = array_merge($this->default_movie, $imported_movie);
#
#            if (isset($_POST['import_addreplace']) && isset($_POST['import_addreplace_movie']) && ('replace' == $_POST['import_addreplace']) && $this->movie_exists($_POST['import_addreplace_movie'])) {
#                $existing_movie = $this->load_movie($_POST['import_addreplace_movie']);
#                $movie['id'] = $existing_movie['id'];
#                $movie['title'] = $existing_movie['name'];
#                $movie['description'] = $existing_movie['description'];
#                $success_message = sprintf(__('Movie %s (%s) replaced successfully.', TAQUILLA_TEXTDOMAIN), $this->safe_output($movie['title']), $this->safe_output($movie['id']));
#                unset($existing_movie);
#            } else {
#                $movie['id'] = $this->get_new_movie_id();
#                $success_message = sprintf(__('Movie imported successfully.', TAQUILLA_TEXTDOMAIN));
#            }
#
#            $this->save_movie($movie);
#
#            if (false == $error) {
#                $this->print_success_message($success_message);
#                $this->print_edit_movie_form($movie['id']);
#            } else {
#                $this->print_success_message(__('Movie could not be imported.', TAQUILLA_TEXTDOMAIN));
#                $this->print_import_movie_form();
#            }
#        } elseif ( 'wp_movie' == $_GET['import_format'] && isset($_GET['wp_movie_id'])) {
#            check_admin_referer($this->get_nonce('import'));
#
#            // do import
#            $this->import_instance->import_format = 'wp_movie';
#            $this->import_instance->wp_movie_id = $_GET['wp_movie_id'];
#            $this->import_instance->import_movie();
#            $imported_movie = $this->import_instance->imported_movie;
#
#            $movie = array_merge($this->default_movie, $imported_movie);
#
#            $movie['id'] = $this->get_new_movie_id();
#
#            $this->save_movie($movie);
#
#            $this->print_success_message(__('Movie imported successfully.', TAQUILLA_TEXTDOMAIN));
#            $this->print_edit_movie_form($movie['id']);
#        } else {
#            $this->print_import_movie_form();
#        }
    }

    function do_action_options() {
    // TODO
    }

    function do_action_uninstall() {
        // TODO
    }

    function do_action_info() {
        // TODO
    }

    function do_action_ajax_list() {
        // TODO
    }


    ###########################################################################
    ####                                                                   ####
    ####   Frontend (Handle Shortcode) functions                           ####
    ####                                                                   ####
    ###########################################################################

    function handle_content_shortcode( $atts, $content = null ) {
    // handle [movie]title[/movie] in the_content()
    
        // check if movie exists
        $movie_title = $content;
        $movie = $this->load_movie_by_title($movie_title);
        if (false === $movie || null == $movie)
            return "[movie \"{$movie_title}\" not found /]<br />\n";

        // how often was movie displayed on this page yet? get its HTML ID
        $count = ( isset( $this->shown_movies[ $movie['id'] ] ) ) ? $this->shown_movies[ $movie['id'] ] : 0;
        $count = $count + 1;
        $this->shown_movies[ $movie['id'] ] = $count;
        $output_options['html_id'] = "taquilla-id-{$movie['id']}-no-{$count}";
        
        $output = $this->render_movie( $movie, $output_options );
        return $output;
    }


    function handle_content_shortcode_with_atts( $atts ) {
    // handle [movie id=<the_movie_id> /] in the_content()
        // parse shortcode attributs, only allow those specified
        $default_atts = array(
            'id' => 0,
            'title' => '',
        );
      	$atts = shortcode_atts( $default_atts, $atts );

        // check if movie exists
        $movie_id = $atts['id'];
        $movie = $this->load_movie( $movie_id );

        if (false === $movie || null == $movie)
            $movie = $this->load_movie_by_title($atts['title']);
            if (false === $movie || null == $movie)
                return "[movie \"{$movie_id}\" not found /]<br />\n";

        // determine options to use (if set in shortcode, use those, otherwise use options from "Edit Movie" screen)
        // $output_options = array();
        // foreach ( $atts as $key => $value ) {
        //     // have to check this, because strings 'true' or 'false' are not recognized as boolean!
        //     if ( 'true' == strtolower( $value ) )
        //         $output_options[ $key ] = true;
        //     elseif ( 'false' == strtolower( $value ) )
        //         $output_options[ $key ] = false;
        //     else
        //         $output_options[ $key ] = ( -1 !== $value ) ? $value : $movie['options'][ $key ] ;
        // }
        
        // how often was movie displayed on this page yet? get its HTML ID
        $count = ( isset( $this->shown_movies[ $movie_id ] ) ) ? $this->shown_movies[ $movie['id'] ] : 0;
        $count = $count + 1;
        $this->shown_movies[ $movie_id ] = $count;
        $output_options['html_id'] = "taquilla-id-{$movie_id}-no-{$count}";
        
        $output = $this->render_movie( $movie, $output_options );
        return $output;
    }


    function render_movie( $movie, $output_options ) {
    // echo content of array

        $output = '';
        $output .= '<span class="taquilla-movie-title">' . $this->safe_output( $movie['title'] ) . "</span> ";
        $output .= '<span class="taquilla-movie-year">(' . $this->safe_output( $movie['year'] ) . ")</span>\n";
        // TODO integrate custom CSS into output to render these classes
        return $output;
    }
} 


###############################################################################
####                                                                       ####
####   External functions                                                  ####
####                                                                       ####
###############################################################################

# Copied from wp-admin/menu.php, adding the position of the menu
function my_add_menu_page( $page_title, $menu_title, $access_level, $file, $function = '', $icon_url = '', $position=99 ) {
	global $menu, $admin_page_hooks, $_registered_pages;

	$file = plugin_basename( $file );

	$admin_page_hooks[$file] = sanitize_title( $menu_title );

	$hookname = get_plugin_page_hookname( $file, '' );
	if (!empty ( $function ) && !empty ( $hookname ))
		add_action( $hookname, $function );

	if ( empty($icon_url) )
		$icon_url = 'images/generic.png';
	elseif ( is_ssl() && 0 === strpos($icon_url, 'http://') )
		$icon_url = 'https://' . substr($icon_url, 7);

	$menu[$position] = array ( $menu_title, $access_level, $file, $page_title, 'menu-top ' . $hookname, $hookname, $icon_url );

	$_registered_pages[$hookname] = true;

	return $hookname;
}



?>