<?php
/*
File Name: Taquilla - Item Class (base class for objects, abstract)
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

if (!class_exists("Inflect"))
    include_once ("inflect.class.php");

class Item {
    // Note: most of this function should be declared as ABSTRACT
    // but this is not compatible for PHP_VERSION < 5 

    var $values = array();

    ####   Properties  ####################################################                                                     

    function name() {return $this->safe_output($this->values['name']);}
    function id() {return $this->safe_output($this->values['id']);}

    var $class = "";
    var $classes = "";
    var $Class = "";
    var $Classes = "";
    var $table = "";
        
    ####   Constructor function  ##########################################                                                     

    function Item($item_id = null) {
        $this->class = "item";
        $this->setup($item_id);
    }

    function setup($item_id) {
        if (!defined('ITEM_ABSPATH'))
            define('ITEM_ABSPATH', WP_PLUGIN_DIR . '/' .
             basename(dirname(__FILE__)) . '/');

        $inflect = new Inflect();
        $this->classes = $inflect->pluralize($this->class);
        unset($inflect);

        $this->Class = ucwords($this->class);
        $this->Classes = ucwords($this->classes);

        global $wpdb;
        $this->table = $wpdb->prefix . "tq_" . $this->classes;

        if(!($item_id == null))
            $this->load($item_id);
    }

    ####   MySql functions  ###############################################                                                     

    function load($item_id) {
        global $wpdb;
        $select = "SELECT " . implode(",",array_keys($this->values)) . 
        " FROM " . $this->table .
#      " LEFT JOIN ".$wpdb->prefix."tq_studios ON studio_id = tq_studios.id ".
        " WHERE id = " . $item_id . " LIMIT 1"; 
        $this->values = $wpdb->get_row($select, ARRAY_A);
    }

    function save() {
        global $wpdb;
        $insert = "INSERT INTO " . $this->table . 
        "(" . implode(",",array_keys($this->values)) . ") VALUES " .
        "(\"" . implode("\",\"", $this->values) . "\") ";
        // TODO Add $wpdb->escape 
        $wpdb->query($insert);
        $this->values['id'] = mysql_insert_id($wpdb->dbh);
    }

    function update() {
        global $wpdb;
        $update = "UPDATE " . $this->table . 
        " SET " . implode(",", $this->array_keys_and_values($this->values)) .
        " WHERE id = " . $this->id();
        $wpdb->query($update);
        return $this->id();
    }

    function delete() {
        global $wpdb;
        $delete = "DELETE FROM " . $this->table . 
        " WHERE id = " . $this->id();
        $wpdb->query($delete);
        return true;
    }

    ####   Admin (print) function  ########################################                                                     

    function admin_edit($action) {

        $header = __(ucwords($action) ." ". $this->Class, TAQUILLA_DOMAIN);
        if (!($this->name() == null))
            $header = sprintf('%s &ldquo;%s&rdquo;', $header, $this->name());
        $this->print_page_header($header);

        $this->print_submenu_navigation($action);
        ?>
        <div style="clear:both;"><p>
        <p><?php _e('You can ' . $action . ' a ' . $this->class . ' here...', TAQUILLA_DOMAIN); ?></p>
		<?php # echo sprintf(__('If you want to show a movie, use this shortcode: <strong>[movie id=%s /]</strong>')); ?> 
		</p></div>
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field($this->get_nonce($action)); ?>

        <div class="postbox">
        <h3 class="hndle">
        <span><?php _e($this->Class . ' Information', TAQUILLA_DOMAIN); ?></span>
        </h3>
        <div class="inside">
        <table class="taquilla-options">
        <?php foreach($this->values as $k => $v) {
                switch($action) {
                    case "edit":
        ?>
            <tr valign="top">
            <th scope="row">
            <label for="<?php echo $this->class . "[" . $k . "]"; ?>"><?php _e($this->Class . ' ' . $k, TAQUILLA_DOMAIN); ?>:</label>
            </th>
            <td>
            <input type="text" name="<?php echo $this->class . "[" . $k . "]"; ?>" id="<?php echo $this->class . "[" . $k . "]"; ?>" value="<?php echo $this->safe_output($v); ?>" style="width:250px" <?php if ($k == "id" || substr($k, -3) == "_id") echo 'readonly="true"'; ?> />
            </td>  
            </tr>
        <?php       break;
                    case "add":
                        if (!($k == "id" || substr($k, -3) == "_id")) {   
        ?>
            <tr valign="top">
            <th scope="row"><label for="<?php echo $this->class . "[" . $k . "]"; ?>"><?php _e($this->Class . ' ' . $k, TAQUILLA_DOMAIN); ?>:</label></th>
            <td><input type="text" name="<?php echo $this->class . "[" . $k . "]"; ?>" id="<?php echo $this->class . "[" . $k . "]"; ?>" value="<?php _e('Enter ' . $this->Class . ' ' . $k, TAQUILLA_DOMAIN); ?>" style="width:250px" /></td>  
            </tr>
        <?php    
                        }
                    break;
                }
            }
        ?>
        </table>
        </div>
        </div>
        <p class="submit">
        <?php switch($action) {
            case "edit":
        ?>
            <input type="hidden" name="<?php echo $this->class; ?>[id]" value="<?php echo $this->id(); ?>" />
            <input type="hidden" name="action" value="edit" />
            <input type="submit" name="submit[update]" class="button-primary" value="<?php _e('Update Changes', TAQUILLA_DOMAIN); ?>" />
            <input type="submit" name="submit[save_back]" class="button-primary" value="<?php _e('Save and go back', TAQUILLA_DOMAIN); ?>" />
        <?php
            $list_url = $this->get_action_url(array('action' => 'list'));
            echo " <a class=\"button-primary\" href=\"{$list_url}\">" . __('Cancel', TAQUILLA_DOMAIN) . "</a>";
            $delete_url = $this->get_action_url(array('action' => 'delete', $this->class . '_id' => $this->id(), 'item' => $this->class), true);
            echo " <a class=\"button-secondary delete_" . $this->class . "_link\" href=\"{$delete_url}\">" . __('Delete ' . $this->Class, TAQUILLA_DOMAIN) . "</a>";
            break;

            case "add":
        ?>
            <input type="hidden" name="action" value="add" />
            <input type="submit" name="submit" class="button-primary" value="<?php _e('Add ' . $this->Class, TAQUILLA_DOMAIN); ?>" />
        <?php
            break;
        }
        ?>
        </p>
        </form>
        <?php
        $this->print_page_footer();
    }

    function admin_list_header()  {        
        $header = __('List of ' . $this->Classes, TAQUILLA_DOMAIN);
        $this->print_page_header($header);
        $this->print_submenu_navigation('list', $this->class);
        ?>
        <div style="clear:both;"><p><?php _e('This is a list of all available ' . $this->classes, TAQUILLA_DOMAIN); ?> <?php _e('You may add, edit or delete ' . $this->classes . ' here.', TAQUILLA_DOMAIN); ?><br />
		<?php _e('If you want to show a ' . $this->class . ' in your pages, posts or text-widgets, use the shortcode <strong>[' . $this->class . ' id=&lt;the_' . $this->class . '_ID&gt; /]</strong> or click the button "' . $this->Class . '" in the editor toolbar.', TAQUILLA_DOMAIN); ?></p></div>
		<?php
	}
	
	function admin_list_table_header() {
        ?>
        <div style="clear:both;">
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field($this->get_nonce('bulk_edit')); ?>
        <table class="widefat">
            <tr>
            <th class="check-column" scope="col"><input type="checkbox" /></th>
                <?php
                foreach(array_keys($this->values) as $key) {
                ?>
                <th scope="col"><?php _e($key, TAQUILLA_DOMAIN); ?></th>
                <?php } ?>
            <th scope="col"><?php _e('Action', TAQUILLA_DOMAIN); ?></th>
            </tr>
        <?php
    }
    
    function admin_list_item($bg_style_index = 0) {
        $bg_style = (0 == ($bg_style_index % 2)) ? ' class="alternate"' : '';
        $edit_url = $this->get_action_url(array('action' => 'edit', $this->class . '_id' => $this->id()), false);
        $delete_url = $this->get_action_url(array('action' => 'delete', $this->class . '_id' => $this->id(), 'item' => $this->class), true);
        echo "<tr{$bg_style}>\n";
        echo "\t<th class=\"check-column\" scope=\"row\"><input type=\"checkbox\" name=\"" . $this->class . "s[]\" value=\"" . $this->id() . "\" /></th>";
        foreach($this->values as $value)
            echo "<td>{$value}</td>";
        echo "<td><a href=\"{$edit_url}\">" . __('Edit', TAQUILLA_DOMAIN) . "</a>" . " | ";
        echo "<a class=\"delete_" . $this->class . "_link delete\" href=\"{$delete_url}\">" . __('Delete', TAQUILLA_DOMAIN) . "</a></td>\n";
        echo "</tr>\n";
    }

	function admin_list_table_footer() {
        ?>
        </table>
        <input type="hidden" name="action" value="bulk_edit" />
        <p class="submit"><?php _e('Bulk actions:', TAQUILLA_DOMAIN); ?>  <input type="submit" name="submit[delete]" class="button-primary bulk_delete_<?php echo $this->class; ?>s" value="<?php _e('Delete ' . $this->classes, TAQUILLA_DOMAIN); ?>" />
        </p>
        </form></div>
        <?php
    }

	function admin_list_table_empty() {
        $add_url = $this->get_action_url(array('action' => 'add'), false);
        $import_url = $this->get_action_url(array('action' => 'import'), false);
        echo "<div style=\"clear:both;\"><p>" . __('No ' . $this->classes . ' found.', TAQUILLA_DOMAIN) . '<br/>' . sprintf(__('You might <a href="%s">add</a> or <a href="%s">import</a> one!', TAQUILLA_DOMAIN), $add_url, $import_url) . "</p></div>";
    }

    function admin_list_footer() {        
        $this->print_page_footer();
    }

    ####   Auxiliary functions  ###############################################                                                     

    function array_keys_and_values($arr) {
        $carr = array();
        foreach($arr as $k => $v)
            $carr[] = $k . " = \"" . $v . "\"";
        return $carr;
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
            <li><a <?php if ('list' == $action) echo 'class="current" '; ?>href="<?php echo $this->get_action_url(array('action' => 'list')); ?>"><?php _e('List of ' . $this->Classes, TAQUILLA_DOMAIN); ?></a> | </li>
            <li><a <?php if ('add' == $action) echo 'class="current" '; ?>href="<?php echo $this->get_action_url(array('action' => 'add')); ?>"><?php _e('Add new ' . $this->class, TAQUILLA_DOMAIN); ?></a> | </li>
            <li><a <?php if ('import' == $action) echo 'class="current" '; ?>href="<?php echo $this->get_action_url(array('action' => 'import')); ?>"><?php _e('Import Results', TAQUILLA_DOMAIN); ?></a> | </li>
            <li>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</li>
            <li><a <?php if ('options' == $action) echo 'class="current" '; ?>href="<?php echo $this->get_action_url(array('action' => 'options')); ?>"><?php _e('Plugin Options', TAQUILLA_DOMAIN); ?></a> | </li>
            <li><a <?php if ('info' == $action) echo 'class="current" '; ?>href="<?php echo $this->get_action_url(array('action' => 'info')); ?>"><?php _e('About the Plugin', TAQUILLA_DOMAIN); ?></a></li>
        </ul>
        <br class="clear" />
        <?php
    }

    function get_nonce($action, $item = false) {
        $nonce_base = 'taquilla-nonce';
        return (false !== $item) ? $nonce_base . '_' . $action . '_' . $item : $nonce_base . '_' . $action;
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
}

class Collection {

    ####   Properties  ########################################################                                                     

    var $Kind = "";
    var $nil = null;
    
    var $table = "";
    var $columns = "";
    var $limit = 20;
    var $offset = 0;
    var $where = "TRUE";

    var $items = array();

    var $plugin_version = '0.1';
    var $allowed_actions = array('list', 'add', 'edit', 'bulk_edit', 'delete', 'insert', 'import', 'options', 'uninstall', 'info'); 
    
    ####   Constructor function  ##############################################                                                     
        
    function Collection() {
        $this->Kind = "Item";
        $this->setup();
    }

    function setup() {
        $this->nil = new $this->Kind; // empty item to use static variables
        $this->table = $this->nil->table;
        $this->columns = array_keys($this->nil->values);
        add_action('admin_menu', array(&$this, 'add_manage_page'));
    }

    
    ####   MySql functions  ###################################################                                                     

    function select() {
        global $wpdb;
        $Kind = "Item";
        $select = "SELECT " . implode(", ",$this->columns) . 
        " FROM " . $this->table .
        " WHERE " . $this->where . 
        " LIMIT " . $this->offset . "," . $this->limit; 
        $results = $wpdb->get_results($select, ARRAY_A);
        if($results != null)
            foreach($results as $result) {
                $item = new $this->Kind;
                $item->values = $result;
                $this->items[] = $item;
            }
    }

    // TODO: should take the column values from $this->columns !
    function create_table() {
    // create the tables in the MySql database
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        if (!$this->table_exists()) {
            $sql = "CREATE TABLE " . $this->table . " (
                    id mediumint(9) NOT NULL auto_increment,
                    name varchar(80) default NULL,
                    UNIQUE KEY id (id)
          	        );";
            dbDelta($sql);       
        }
        return $this->table;
    }

    function table_exists() {
    // check whether the table exists in the MySql database
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $exists = $wpdb->get_var("show tables like '$this->table'"); 
        return $exists == $this->table;
    }

    
    ####   Admin actions  #####################################################                                                     

    function do_action_list()  {
        $this->select();
        $this->nil->admin_list_header();
        if (0 == count($this->items))
            $this->nil->admin_list_table_empty();
        else {
            $this->nil->admin_list_table_header();
            $bg_style_index = 0;
            // TODO add paginate and arrows for pagination
            foreach($this->items as $item) {
                $bg_style_index++;
                $item->admin_list_item($bg_style_index);
            }
            $this->nil->admin_list_table_footer();
        }
        $this->nil->admin_list_footer();
    }

    function do_action_add() {
        if (isset($_POST['submit']) && isset($_POST[$this->nil->class])) {
            check_admin_referer($this->nil->get_nonce('add'));
            
            $this->nil->values = $_POST[$this->nil->class];
            // TODO Return false on error and according error message
            $this->nil->save();

            $this->nil->print_success_message(sprintf(__('%s "%s" added successfully.', TAQUILLA_DOMAIN), $this->nil->Class, $this->nil->name()));
            $this->nil->admin_edit("edit");
        } else {
            $this->nil->admin_edit("add");
        }
    }

    function do_action_edit() {
        if (isset($_POST['submit']) && isset($_POST[$this->nil->class])) {
            check_admin_referer($this->nil->get_nonce('edit'));

            // TODO Return false on error and according error message
            $this->nil->load($_POST[$this->nil->class]['id']);
            $this->nil->values = $_POST[$this->nil->class];
            $item_id = $this->nil->update();
            
            $this->nil->print_success_message(sprintf(__('%s "%s" edited successfully.', TAQUILLA_DOMAIN), $this->nil->Class, $this->nil->name()));
            $subactions = array_keys($_POST['submit']);
            if ('save_back' == $subactions[0]) {
                $this->do_action_list();
            } else {
                $this->nil->admin_edit("edit");
            }
        } elseif (isset($_GET[$this->nil->class . '_id'])) {
            $this->nil->load($_GET[$this->nil->class . '_id']);
            $this->nil->admin_edit("edit");
        } else {
            $this->do_action_list();
        }
    }

    function do_action_delete() {
        if (isset($_GET[$this->nil->class . '_id']) && isset($_GET['item'])) {
            check_admin_referer($this->nil->get_nonce('delete', $_GET['item']));

            $this->nil->load($_GET[$this->nil->class . '_id']);
            $this->nil->delete();

            $this->nil->print_success_message(sprintf(__('%s "%s" deleted successfully.', TAQUILLA_DOMAIN), $this->nil->Class, $this->nil->name()));
            $this->do_action_list();
        } else {
            $this->do_action_list();
        }
    }


    ####   Admin Tab Functions  ###############################################                                                     

    function add_manage_page() {
    // add page, and what happens when page is loaded or shown
        $min_capability = 'publish_posts'; // user needs at least this

        $this->hook = add_posts_page($this->nil->Classes, $this->nil->Classes, $min_capability, 'list_' . $this->nil->classes, array(&$this, 'show_manage_page')); 
        add_action('load-' . $this->hook, array(&$this, 'load_manage_page'));

        $this->hook = add_posts_page($this->nil->Classes, 'Add ' . $this->nil->class, $min_capability, 'add_' . $this->nil->class, array(&$this, 'do_action_add')); 
        add_action('load-' . $this->hook, array(&$this, 'load_manage_page'));
    }

    function load_manage_page() {
    // only load the scripts, stylesheets and language by hook
        //$this->add_manage_page_js();
        add_action('admin_footer', array(&$this, 'add_manage_page_js')); 
        // can be put in footer, jQuery will be loaded anyway
        $this->add_manage_page_css();

        // init language support
        $this->init_language_support();
        
        if (true == function_exists('add_contextual_help')) // if WP ver >= 2.7
            add_contextual_help($this->hook, $this->get_contextual_help_string());
    }

    function show_manage_page() {
    // get and check action parameter from passed variables
        $action = (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) ? $_REQUEST['action'] : 'list';
        // check if action is allowed and method callable, if yes, call it
        if (in_array($action, $this->allowed_actions) && is_callable(array(&$this, 'do_action_' . $action)))
            call_user_func(array(&$this, 'do_action_' . $action));
        else
            call_user_func(array(&$this, 'do_action_list'));
    }

    function add_manage_page_js() {
    // TODO Change reference to "movie" with $this->nil->class
    // enqueue javascript-file, with some jQuery stuff
        $jsfile = 'admin-script.js';
        if (file_exists(TAQUILLA_ABSPATH . 'js/' . $jsfile)) {
            wp_register_script('taquilla-admin-js', TAQUILLA_URL . 'js/' . $jsfile, array('jquery'), $this->plugin_version);
            // add all strings to translate here
            wp_localize_script('taquilla-admin-js', 'Taquilla_Admin', array(
	  	        'str_UninstallCheckboxActivation' => __('Do you really want to activate this? You should only do that right before uninstallation!', TAQUILLA_DOMAIN),
	  	        'str_DataManipulationLinkInsertURL' => __('URL of link to insert', TAQUILLA_DOMAIN),
	  	        'str_DataManipulationLinkInsertText' => __('Text of link', TAQUILLA_DOMAIN),
	  	        'str_DataManipulationLinkInsertExplain' => __('To insert the following link into a cell, just click the cell after closing this dialog.', TAQUILLA_DOMAIN),
	  	        'str_DataManipulationImageInsertURL' => __('URL of image to insert', TAQUILLA_DOMAIN),
	  	        'str_DataManipulationImageInsertAlt' => __("''alt'' text of the image", TAQUILLA_DOMAIN),
	  	        'str_DataManipulationImageInsertExplain' => __('To insert the following image into a cell, just click the cell after closing this dialog.', TAQUILLA_DOMAIN),
	  	        'str_BulkDeleteMoviesLink' => __('The selected movies and all content will be erased. Do you really want to delete them?', TAQUILLA_DOMAIN),
	  	        'str_BulkImportwpMovieMoviesLink' => __('Do you really want to import the selected movies from the wp-Movie plugin?', TAQUILLA_DOMAIN),
	  	        'str_DeleteMovieLink' => __('The complete movie and all content will be erased. Do you really want to delete it?', TAQUILLA_DOMAIN),
	  	        'str_DeleteRowLink' => __('Do you really want to delete this row?', TAQUILLA_DOMAIN),
	  	        'str_DeleteColumnLink' => __('Do you really want to delete this column?', TAQUILLA_DOMAIN),
	  	        'str_ImportwpMovieLink' => __('Do you really want to import this movie from the wp-Movie plugin?', TAQUILLA_DOMAIN),
	  	        'str_UninstallPluginLink_1' => __('Do you really want to uninstall the plugin and delete ALL data?', TAQUILLA_DOMAIN),
	  	        'str_UninstallPluginLink_2' => __('Are you really sure?', TAQUILLA_DOMAIN),
	  	        'str_ChangeMovieID' => __('Do you really want to change the ID of the movie?', TAQUILLA_DOMAIN)
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

    function get_contextual_help_string() {
        return __('More information can be found on the <a href="">plugin\'s website</a>.', TAQUILLA_DOMAIN) . '<br/>' . __('See the <a href="">documentation</a> or find out how to get <a href="">support</a>.', TAQUILLA_DOMAIN);
    }

    function init_language_support() {
        $language_directory = basename(dirname(__FILE__)) . '/languages';
        load_plugin_textdomain(TAQUILLA_DOMAIN, 'wp-content/plugins/' . $language_directory, $language_directory);
    }

}



?>