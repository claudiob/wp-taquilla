<?php
/*
Plugin Name: Taquilla - Class containing all the admin functions.
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

define('TAQUILLA_DOMAIN', 'taquilla');

class Taquilla_Admin { 

    var $plugin_version = '0.1';
    // nonce for security of links/forms, try to prevent "CSRF"
    
    var $optionname = array(
        'options' => 'taquilla_options',
  );
    // temp variables
    var $hook = '';
    var $import_instance;
    
    // default options values
    var $default_options = array(
        'installed_version' => '0',
#        'uninstall_upon_deactivation' => false,
  );

    // allowed actions in this class
    var $allowed_actions = array('list', 'add', 'edit', 'bulk_edit', 'delete', 'insert', 'import', 'options', 'uninstall', 'info'); // 'ajax_list', but handled separatly

    // init vars
    var $options = array();
    
    // frontend vars
    var $shown_movies = array();
    var $shortcode = 'movie';
    
    var $movie = null;
    var $studio = null;
    var $country = null;
    var $period = null;
    var $result = null;
    

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

        $this->country->create_table();
        $this->studio->create_table();
        $this->movie->create_table();
        $this->period->create_table();
        $this->result->create_table();

    }

    function plugin_update() {
    // TODO 
    }


    function plugin_deactivation_hook() {
    // TODO
    }

    function init_language_support() {
        $language_directory = basename(dirname(__FILE__)) . '/languages';
        load_plugin_textdomain(TAQUILLA_DOMAIN, 'wp-content/plugins/' . $language_directory, $language_directory);
    }

    function load_instance($class, $file) {
        if (!class_exists($class)) {
            include_once (TAQUILLA_ABSPATH . 'php/' . $file);
        }
        if (class_exists($class)) {
            return new $class;
        }
    }


    ###########################################################################
    ####                                                                   ####
    ####   Admin functions                                                 ####
    ####                                                                   ####
    ###########################################################################

    function Taquilla_Admin() { 
        // The order of these calls is the order of tabs in admin panel
        $this->movie = $this->load_instance('Movie', 'movie.class.php');
        $this->result = $this->load_instance('Result', 'result.class.php');
        $this->studio = $this->load_instance('Studio', 'studio.class.php');
        $this->period = $this->load_instance('Period', 'period.class.php');
        $this->country = $this->load_instance('Country', 'country.class.php');

        if (is_admin()) {
            
            // check if tables exist
            if (!$this->movie->table_exists())
                $this->plugin_install();

            // check if some redirection has to be performed
            
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

#            function taquilla_queryvars($qvars) {
#              $qvars[] = 'movie';
#              return $qvars;
#            }
#            add_filter('query_vars', 'taquilla_queryvars');
#
#            function taquilla_search_where($where) {
#                global $wp_query;
#                if(isset($wp_query->query_vars['movie'])) {
#                    echo "aaa"; exit;
#                    $where = preg_replace(
#                     "/\(\s*post_title\s+LIKE\s*(\'[^\']+\')\s*\)/",
#                     "(post_title LIKE \\1) OR (geotag_city LIKE \\1) OR (geotag_state LIKE \\1) OR (geotag_country LIKE \\1)", $where);
#                }
#              return $where;
#            }
#            add_filter('posts_where', 'taquilla_search_where');


            // add_filter('get_shortcode_regex', 'my_get_shortcode_regex', 1, 2);
            // function my_get_shortcode_regex() {
            // 	global $shortcode_tags;
            // 	$tagnames = array_keys($shortcode_tags);
            // 	$tagregexp = join('|', array_map('preg_quote', $tagnames));
            // 
            // 	return '(.?)|('.$tagregexp.')\b(.*?)(?:(\/))?|(?:(.+?)\[\/\2\])?(.?)';
            // }

            // shortcode for the_content, manual filter for widget_text
        	add_shortcode($this->shortcode, array(&$this, 'handle_content_shortcode'));
            add_filter('widget_text', array(&$this, 'handle_widget_filter'));        	
        }
    } 
    


    function print_import_movie_form() {
        // Begin Import Table Form
    	global $wp_version;
        $this->print_page_header(__('Import Results', TAQUILLA_DOMAIN));
        $this->print_submenu_navigation('import');
        ?>
        <div style="clear:both;">
        <p><?php _e('You may import results from Nielsen Edi data here.<br/>It needs be an Excel file with a certain structure though. Please consult the documentation.', TAQUILLA_DOMAIN); ?></p>
        </div>
        <div style="clear:both;">
        <form method="post" enctype="multipart/form-data" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field($this->get_nonce('import')); ?>
        <table class="taquilla-options">
        <tr valign="top" class="tr-import-addreplace">
            <th scope="row" style="min-width:350px;"><?php _e('Add or Replace Table?', TAQUILLA_DOMAIN); ?>:</th>
            <td>
            <input name="import_addreplace" id="import_addreplace_add" type="radio" value="add" <?php echo (isset($_POST['import_from']) && 'replace' != $_POST['import_from']) ? 'checked="checked" ': '' ; ?>/> <label for="import_addreplace_add"><?php _e('Add new Results', TAQUILLA_DOMAIN); ?></label>
            <input name="import_addreplace" id="import_addreplace_replace" type="radio" value="replace" <?php echo (isset($_POST['import_from']) && 'replace' == $_POST['import_from']) ? 'checked="checked" ': ''; ?>/> <label for="import_addreplace_replace"><?php _e('Replace existing Results', TAQUILLA_DOMAIN); ?></label>
            </td>
        </tr>
        <tr valign="top" class="tr-import-addreplace-table">
            <th scope="row"><label for="import_addreplace_table"><?php _e('Select existing Result to Replace', TAQUILLA_DOMAIN); ?>:</label></th>
            <td><select id="import_addreplace_table" name="import_addreplace_table">
        <?php
            foreach ($this->tables as $id => $tableoptionname) {
                // get name and description to show in list
                $table = $this->load_table($id);
                    $name = $this->safe_output($table['name']);
                    //$description = $this->safe_output($table['description']);
                unset($table);
                echo "<option" . (($id == $_POST['import_replace_table']) ? ' selected="selected"': '') . " value=\"{$id}\">{$name} (ID {$id})</option>";
            }
        ?>
        </select></td>
        </tr>
        <tr valign="top" class="tr-import-file">
            <th scope="row"><label for="import_file"><?php _e('Select File with Table to Import', TAQUILLA_DOMAIN); ?>:</label></th>
            <td><input name="import_file" id="import_file" type="file" /></td>
        </tr>
        <?php if (version_compare('2.7', $wp_version, '>=')) { ?>
        <tr valign="top" class="tr-import-url">
            <th scope="row"><label for="import_url"><?php _e('URL to import table from', TAQUILLA_DOMAIN); ?>:</label></th>
            <td><input type="text" name="import_url" id="import_url" style="width:400px;" value="<?php echo (isset($_POST['import_url'])) ? $_POST['import_url'] : 'http://' ; ?>" /></td>
        </tr>
        <?php } ?>
        </table>
        <input name="import_from" id="import_from_file" type="hidden" value="file-upload" />
        <input id="import_format" name="import_format" value="xls" type="hidden">        
        <input type="hidden" name="action" value="import" />
        <p class="submit">
        <input type="submit" name="submit" class="button-primary" value="<?php _e('Import Table', TAQUILLA_DOMAIN); ?>" />
        </p>
        </form>
        </div>
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
        


    // TODO combine with load_movie in a unique function
    function load_movie_by_title($movie_title) {
        global $wpdb;
        $table_name = $wpdb->prefix . "tq_movies";
        $select = "SELECT * FROM " . $table_name . 
        " WHERE lower(title) = '" . strtolower($movie_title) . "' LIMIT 1"; 
        return $wpdb->get_row($select, ARRAY_A);
    }

    // TODO combine with load_movie in a unique function
    function load_movie_by_title_edi($movie_title_edi) {
        global $wpdb;
        $table_name = $wpdb->prefix . "tq_movies";
        $select = "SELECT * FROM " . $table_name . 
        " WHERE title_edi = '" . $movie_title_edi . "' LIMIT 1"; 
        return $wpdb->get_row($select, ARRAY_A);
    }



    function load_period($period_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . "tq_periods";
        $select = "SELECT * FROM " . $table_name . 
        " WHERE date_from = '" . $period['date_from'] . 
        " AND date_to = '" . $period['date_to'] . "' LIMIT 1"; 
        return $wpdb->get_row($select, ARRAY_A);
    }
    
    function save_period($period_data) {
        global $wpdb;
        // TODO call global $wpdb only once in the class
        $table_name = $wpdb->prefix . "tq_periods";
        $insert = "INSERT INTO " . $table_name .
        "(date_from, date_to) VALUES ('" .
        $period_data['date_from'] . "', '" . 
        $period_data['date_to'] . "')"; 
        $wpdb->query($insert);
        return mysql_insert_id ($wpdb->dbh);
    }

    function find_or_create_period($period_data) {
        $period = load_period($period_data);
        if (false === $period || null == $period) {
            return save_period($period_data);
        }
        else
            return $period['id'];
    }

    function save_result($result_data, $period_id, $type = "edi") {
        global $wpdb;

        # here studio

        $movie = load_movie_by_title_edi($result_data['title_edi']);
        $table_name = $wpdb->prefix . "tq_movies";
        if (false === $movie || null == $movie) {
            # INSERT
            $insert = "INSERT INTO " . $table_name .
            "(studio_id, period_id, position, periods, copies, gross, " .
            "gross_mean, gross_cume, gross_delta, audience, audience_mean, " .
            "audience_cume, audience_delta) VALUES ('" .

            $period_data['date_from'] . "', '" . 
            $period_data['date_to'] . "')"; 
            $wpdb->query($insert);
            return mysql_insert_id ($wpdb->dbh);
        } else {
            $movie_id = $movie['id'];
        }

        $result = load_result_by_key($period_id, $result_data['position']);
        $table_name = $wpdb->prefix . "tq_periods";
        if (false === $result || null == $result) {
            # INSERT
            $insert = "INSERT INTO " . $table_name .
            "(movie_id, period_id, position, periods, copies, gross, " .
            "gross_mean, gross_cume, gross_delta, audience, audience_mean, " .
            "audience_cume, audience_delta) VALUES ('" .

            $period_data['date_from'] . "', '" . 
            $period_data['date_to'] . "')"; 
            $wpdb->query($insert);
            return mysql_insert_id ($wpdb->dbh);
        } else {
            # UPDATE
        }

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
	  	        'str_EditorButtonCaption' => __('Movie', TAQUILLA_DOMAIN),
	  	        'str_EditorButtonAjaxURL' => $ajax_url
          ));
            wp_print_scripts('taquilla-admin-editor-buttons-js');
        }
    }

    ###########################################################################
    ####                                                                   ####
    ####   Admin actions                                                   ####
    ####                                                                   ####
    ###########################################################################


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
#                    $message = __ngettext('Movie deleted successfully.', 'Movies deleted successfully.', count($_POST['movies']), TAQUILLA_DOMAIN);
#                    break;
#                case 'wp_movie_import': // see do_action_import for explanations
#                    $this->import_instance = $this->load_instance('WP_Boxoffice_Import', 'taquilla-import.class.php');
#                    $this->import_instance->import_format = 'wp_movie';
#                    foreach ($_POST['movies'] as $movie_id) {
#                        $this->import_instance->wp_movie_id = $movie_id;
#                        $this->import_instance->import_movie();
#                        $imported_movie = $this->import_instance->imported_movie;
#                        $movie = array_merge($this->default_movie, $imported_movie);
#                        $movie['id'] = $this->get_new_movie_id();
#                        $this->save_movie($movie);
#                    }
#                    $message = __ngettext('Movie imported successfully.', 'Movies imported successfully.', count($_POST['movies']), TAQUILLA_DOMAIN);
#                    break;
#                default:
#                    break;
#                }
#
#            } else {
#                $message = __('You did not select any movies!', TAQUILLA_DOMAIN);
#            }
#            $this->print_success_message($message);
#        }
#        $this->do_action_list();
    }



    // ###################################################################################################################
    function do_action_insert() {
#        if (isset($_GET['movie_id']) && isset($_GET['item']) && isset($_GET['element_id'])) {
#            check_admin_referer($this->get_nonce('insert', $_GET['item']));
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
#                $message = __('Row inserted successfully.', TAQUILLA_DOMAIN);
#                break;
#            case 'col':
#                $col_id = $_GET['element_id'];
#                // init new empty row (with all columns) and insert it before row with key $row_id
#                $new_col = '';
#                foreach ($movie['data'] as $row_idx => $row)
#                    array_splice($movie['data'][$row_idx], $col_id, 0, $new_col);
#                $this->save_movie($movie);
#                $message = __('Column inserted successfully.', TAQUILLA_DOMAIN);
#                break;
#            default:
#                $message = __('Insert failed.', TAQUILLA_DOMAIN);
#            }
#            $this->print_success_message($message);
#            $this->print_edit_movie_form($movie_id);
#        } else {
#            $this->do_action_list();
#        }
    }

    function do_action_import() {
        $this->import_instance = $this->load_instance('Taquilla_Import', 'taquilla-import.class.php');
        if (isset($_POST['submit']) && isset($_POST['import_from'])) {
            check_admin_referer($this->get_nonce('import'));

            // do import
            if ('file-upload' == $_POST['import_from'] && false === empty($_FILES['import_file']['tmp_name'])) {
                $this->import_instance->tempname = $_FILES['import_file']['tmp_name'];
                $this->import_instance->filename = $_FILES['import_file']['name'];
                $this->import_instance->mimetype = $_FILES['import_file']['type'];
                $this->import_instance->import_from = 'file-upload';
                $this->import_instance->import_format = $_POST['import_format'];
                $this->import_instance->import_movie();
                $error = $this->import_instance->error;
                $imported_data = $this->import_instance->imported_data;
                $this->import_instance->unlink_uploaded_file();
            } else { // no valid data submitted
                $this->print_success_message(__('Movie could not be imported.', TAQUILLA_DOMAIN));
                $this->print_import_movie_form();
                exit;
            }
            
            $period_id = find_or_create_period($imported_data['period']);
            foreach($imported_data['results'] as $result) {
                $result_id = save_result($result, $period_id, "edi");
            } 
            exit;

            $movie = array_merge($this->default_movie, $imported_movie);

            if (isset($_POST['import_addreplace']) && isset($_POST['import_addreplace_movie']) && ('replace' == $_POST['import_addreplace']) && $this->movie_exists($_POST['import_addreplace_movie'])) {
                $existing_movie = $this->load_movie($_POST['import_addreplace_movie']);
                $movie['id'] = $existing_movie['id'];
                $movie['title'] = $existing_movie['name'];
                $movie['description'] = $existing_movie['description'];
                $success_message = sprintf(__('Movie %s (%s) replaced successfully.', TAQUILLA_DOMAIN), $this->safe_output($movie['title']), $this->safe_output($movie['id']));
                unset($existing_movie);
            } else {
                $movie['id'] = $this->get_new_movie_id();
                $success_message = sprintf(__('Movie imported successfully.', TAQUILLA_DOMAIN));
            }

            $this->save_movie($movie);

            if (false == $error) {
                $this->print_success_message($success_message);
                $this->print_movie_form("edit", $movie['id']);
            } else {
                $this->print_success_message(__('Movie could not be imported.', TAQUILLA_DOMAIN));
                $this->print_import_movie_form();
            }
        } elseif (isset($_GET['import_format']) && 'wp_movie' == $_GET['import_format'] && isset($_GET['wp_movie_id'])) {
            check_admin_referer($this->get_nonce('import'));

            // do import
            $this->import_instance->import_format = 'wp_movie';
            $this->import_instance->wp_movie_id = $_GET['wp_movie_id'];
            $this->import_instance->import_movie();
            $imported_movie = $this->import_instance->imported_movie;

            $movie = array_merge($this->default_movie, $imported_movie);

            $movie['id'] = $this->get_new_movie_id();

            $this->save_movie($movie);

            $this->print_success_message(__('Movie imported successfully.', TAQUILLA_DOMAIN));
            $this->print_movie_form("edit", $movie['id']);
        } else {
            $this->print_import_movie_form();
        }
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

    function handle_content_shortcode($atts, $content = null) {
    // handle [movie]title[/movie] in the_content()
    
        // check if movie exists
        $movie_title = $content;
        $movie = $this->load_movie_by_title($movie_title);
        if (false === $movie || null == $movie)
            return "[movie \"{$movie_title}\" not found /]<br />\n";

        // how often was movie displayed on this page yet? get its HTML ID
        $count = (isset($this->shown_movies[ $movie['id'] ])) ? $this->shown_movies[ $movie['id'] ] : 0;
        $count = $count + 1;
        $this->shown_movies[ $movie['id'] ] = $count;
        $output_options['html_id'] = "taquilla-id-{$movie['id']}-no-{$count}";
        
        $output = $this->render_movie($movie, $output_options);
        return $output;
    }


    function handle_content_shortcode_with_atts($atts) {
    // handle [movie id=<the_movie_id> /] in the_content()
        // parse shortcode attributs, only allow those specified
        $default_atts = array(
            'id' => 0,
            'title' => '',
       );
      	$atts = shortcode_atts($default_atts, $atts);

        // check if movie exists
        $movie_id = $atts['id'];
        $movie = $this->load_movie($movie_id);

        if (false === $movie || null == $movie)
            $movie = $this->load_movie_by_title($atts['title']);
            if (false === $movie || null == $movie)
                return "[movie \"{$movie_id}\" not found /]<br />\n";

        // determine options to use (if set in shortcode, use those, otherwise use options from "Edit Movie" screen)
        // $output_options = array();
        // foreach ($atts as $key => $value) {
        //     // have to check this, because strings 'true' or 'false' are not recognized as boolean!
        //     if ('true' == strtolower($value))
        //         $output_options[ $key ] = true;
        //     elseif ('false' == strtolower($value))
        //         $output_options[ $key ] = false;
        //     else
        //         $output_options[ $key ] = (-1 !== $value) ? $value : $movie['options'][ $key ] ;
        // }
        
        // how often was movie displayed on this page yet? get its HTML ID
        $count = (isset($this->shown_movies[ $movie_id ])) ? $this->shown_movies[ $movie['id'] ] : 0;
        $count = $count + 1;
        $this->shown_movies[ $movie_id ] = $count;
        $output_options['html_id'] = "taquilla-id-{$movie_id}-no-{$count}";
        
        $output = $this->render_movie($movie, $output_options);
        return $output;
    }


    function render_movie($movie, $output_options) {
    // echo content of array

        $output = '';
        $output .= '<span class="taquilla-movie-title">' . $this->safe_output($movie['title']) . "</span> ";
        $output .= '<span class="taquilla-movie-year">(' . $this->safe_output($movie['year']) . ")</span>\n";
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
function my_add_menu_page2($page_title, $menu_title, $access_level, $file, $function = '', $icon_url = '', $position=99) {
	global $menu, $admin_page_hooks, $_registered_pages;

	$file = plugin_basename($file);

	$admin_page_hooks[$file] = sanitize_title($menu_title);

	$hookname = get_plugin_page_hookname($file, '');
	if (!empty ($function) && !empty ($hookname))
		add_action($hookname, $function);

	if (empty($icon_url))
		$icon_url = 'images/generic.png';
	elseif (is_ssl() && 0 === strpos($icon_url, 'http://'))
		$icon_url = 'https://' . substr($icon_url, 7);

	$menu[$position] = array ($menu_title, $access_level, $file, $page_title, 'menu-top ' . $hookname, $hookname, $icon_url);

	$_registered_pages[$hookname] = true;

	return $hookname;
}



?>