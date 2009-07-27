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

    var $columns = array();
    var $items = array();
    var $inflect = null;
    var $import = null;
    var $plugin_version = '0.1';
    var $limit = 20;
    var $offset = 0;
    var $where = "TRUE";
    
    ####   Properties  ####################################################                                                     

    function id($item = null) { return $this->get("id", $item); }
    function name($item = null) { return $this->get("name", $item); }

    var $classes = "";
    var $Class = "";
    var $Classes = "";
    var $table = "";

    var $class   = "item";
    var $columns = array('id','post_id','name');
    var $actions = array('list', 'add', 'edit', 'delete');  
    var $actions_item = array('edit', 'delete');
    var $actions_all  = array('list', 'add'); # import in Result
        
    ####   Constructor function  ##########################################                                                     

    function Item($item_id = null) {
        $this->setup($item_id);
    }

    function setup($item_id) {
        if (!defined('ITEM_ABSPATH'))
            define('ITEM_ABSPATH', WP_PLUGIN_DIR . '/' .
             basename(dirname(__FILE__)) . '/');

        add_action('admin_menu', array(&$this, 'add_manage_page'));

        $this->inflect = new Inflect();
        $this->classes = $this->inflect->pluralize($this->class);
        unset($inflect);

        $this->Class = ucwords($this->class);
        $this->Classes = ucwords($this->classes);

        global $wpdb;
        $this->table = $wpdb->prefix . "tq_" . $this->classes;

        if(!($item_id == null))
            $this->load($item_id);
    }
    
    function has_items() {
        return count($this->items) > 0;
    }

    function get($keyword = "id", $item = null) {
        if($item == null && !$this->has_items())
            return null;
        if($item == null)
            $item = $this->items[0];
        return $this->safe_output($item[$keyword]);
    }

    ####   MySql functions  ###############################################                                                     


    function save($add_post = true) {
        // TODO Include everything in foreach($this->items as $item)
        // But carefully, since $item['id'] = new value would not work!
        global $wpdb;
#       foreach($this->items as $item) {
            # First add a post for this item
            if($add_post) {
            $cat_id = wp_insert_category(array('cat_name' => $this->Class));
            $new_post = array('post_status' => 'publish', 'post_content' => '',
                 'post_title' => $wpdb->escape($this->name()),
                 'post_category' => $cat_id);
            $aa = wp_insert_post($new_post, true); 
            if ( is_wp_error($aa) )
               echo $aa->get_error_message();
            else            
                $this->items[0]['post_id'] = $aa;       
            }
            # Then add the item
            $valid = array_filter($this->items[0], 'strlen');// remove NULLs
            $columns = ' (' . implode(",", array_keys($valid)) . ')';
            $values =  ' VALUES ("' . implode('", "', $valid) . '")';
            $insert = "INSERT INTO " . $this->table . $columns . $values;
            $wpdb->query($insert);
            $this->items[0]['id'] = mysql_insert_id($wpdb->dbh);
#       }
    }

    function array_to_where($arr) {
        $where = array();
        foreach($arr as $k => $v)
            $where[] = $k . '="' . $v . '"';
        return $where;
    }
    
    function update() {
        global $wpdb;
        foreach($this->items as $item) {
            $multiple = $this->array_to_where($item);
            $set = " SET " . implode(", ", $multiple);
            $update = "UPDATE " . $this->table . $set . 
            " WHERE id = " . $this->id();
            $wpdb->query($update);
        }
        return count($this->items);
        
        #.# $update = "UPDATE " . $this->table . 
        #.# " SET " . implode(",", $this->array_keys_and_values($this->values)) .
        #.# " WHERE id = " . $this->id();
        #.# $wpdb->query($update);
        #.# return $this->id();
    }

    function delete() {
        global $wpdb;
        $delete = "DELETE FROM " . $this->table . 
        " WHERE id = " . $this->id();
        $wpdb->query($delete);
        return true;
    }

    function select() {
        global $wpdb;
        $Kind = "Item";
        $select = "SELECT " . implode(",", $this->columns) . 
        " FROM " . $this->table .
        " WHERE " . $this->where . 
        " LIMIT " . $this->offset . "," . $this->limit; 
        $results = $wpdb->get_results($select, ARRAY_A);
        if($results != null)
            foreach($results as $result)
                $this->items[] = $result;
    }


    function find($data) {
        global $wpdb;
        $multiple = $this->array_to_where($data);
        $cond =  $this->where . " AND (" . implode(") AND (", $multiple) . ")";
        $select = "SELECT " . implode(",",$this->columns) . 
        " FROM " . $this->table . " WHERE " . $cond . " LIMIT 1";
        // TODO Add the possibility to have JOINs
        $output =  $wpdb->get_row($select, ARRAY_A);
        if(!empty($output)) $this->items[] = $output; 
    }

    function load($item_id) {
        $this->find(array('id' => $item_id));
    }

    function find_or_create($data, $save = true) {
        $this->items = array();        
        $this->find($data);
        if (!$this->has_items()) {
            $this->items[] = $data;
            if($save) $this->save();
        }
    }


    function table_exists() {
    // check whether the table exists in the MySql database
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $exists = $wpdb->get_var("show tables like '$this->table'"); 
        return $exists == $this->table;
    }

    // TODO: should take the column values from $this->columns !
    function create_table() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        if (!$this->table_exists()) {
            $sql = "CREATE TABLE " . $this->table . " (
                    id mediumint(9) NOT NULL auto_increment,
                    post_id bigint(20) unsigned,
                    name varchar(80) default NULL,
                    UNIQUE KEY id (id),
                    UNIQUE KEY post_id (post_id)
          	        );";
            dbDelta($sql);       
        }
        return $this->table;
    }


    ####   Actions functions  #################################################                                                     

    function do_action_list()  {
        $this->select();
        $this->print_action_list();
    }

    function do_action_edit() {
    // Return true if the list of items has to be shown afterwards
        if (isset($_POST['submit']) && isset($_POST[$this->class])) {
            check_admin_referer($this->get_nonce('edit'));

            $this->load($_POST[$this->class]['id']);
            #.# $this->values = $_POST[$this->class];
            if ($this->has_items()) {
                $this->items[0] = $_POST[$this->class];      
                $this->update();
                $this->print_success_message(sprintf(__('%s "%s" edited successfully.', TAQUILLA_DOMAIN), $this->Class, $this->name()));
            } else
                $this->print_success_message(sprintf(__('%s not found (id = %s).', TAQUILLA_DOMAIN), $this->Class, $_POST[$this->class]['id']));
            $subactions = array_keys($_POST['submit']);
            if ('save_back' == $subactions[0]) {
                return true;
            } else {
                $this->print_action_edit("edit");
                return false;
            }
        } elseif (isset($_GET[$this->class . '_id'])) {
            $this->load($_GET[$this->class . '_id']);
            $this->print_action_edit("edit");
            return false;
        } else
            return true;
    }

    function do_action_add() {
    // Return true if the list of items has to be shown afterwards
        if (isset($_POST['submit']) && isset($_POST[$this->class])) {
            check_admin_referer($this->get_nonce('add'));
            
            #.# $this->values = $_POST[$this->class];
            $this->items[] = $_POST[$this->class];
            // TODO Return false on error and according error message
            $this->save();

            $this->print_success_message(sprintf(__('%s "%s" added successfully.', TAQUILLA_DOMAIN), $this->Class, $this->name()));
            $this->print_action_edit("edit");
        } else {
            # $this->print_action_edit("add");
            $this->print_action_add();
        }
        return false;
    }

    function do_action_delete() {
    // Return true if the list of items has to be shown afterwards
        if (isset($_GET[$this->class . '_id']) && isset($_GET['item'])) {
            check_admin_referer($this->get_nonce('delete', $_GET['item']));

            $this->load($_GET[$this->class . '_id']);
            $this->delete();

            $this->print_success_message(sprintf(__('%s "%s" deleted successfully.', TAQUILLA_DOMAIN), $this->Class, $this->name()));
        }
        return true;
    }


    ####   Admin (print) function  ########################################                                                     

    function print_action_list() {

        $header = __('List of ' . $this->Classes, TAQUILLA_DOMAIN);
        $this->print_page_header($header);
        $this->print_submenu_navigation('list', $this->class);
        ?>
        <div style="clear:both;"><p><?php _e('This is a list of all available ' . $this->classes, TAQUILLA_DOMAIN); ?> <?php _e('You may add, edit or delete ' . $this->classes . ' here.', TAQUILLA_DOMAIN); ?><br />
    	<?php _e('If you want to show a ' . $this->class . ' in your pages, posts or text-widgets, use the shortcode <strong>[' . $this->class . ' id=&lt;the_' . $this->class . '_ID&gt; /]</strong> or click the button "' . $this->Class . '" in the editor toolbar.', TAQUILLA_DOMAIN); ?></p></div>
    	<?php if (!$this->has_items()) {
            $add_url = $this->get_action_url(array('action' => 'add'), false);
            $import_url = $this->get_action_url(array('action' => 'import'), false);
            echo "<div style=\"clear:both;\"><p>" . __('No ' . $this->classes . ' found.', TAQUILLA_DOMAIN) . '<br/>' . sprintf(__('You might <a href="%s">add</a> or <a href="%s">import</a> one!', TAQUILLA_DOMAIN), $add_url, $import_url) . "</p></div>";
    	    } else { ?>
            <div style="clear:both;">
            <form method="post" action="<?php echo $this->get_action_url(); ?>">
            <?php wp_nonce_field($this->get_nonce('bulk_edit')); ?>
            <table class="widefat">
                <tr>
                <th class="check-column" scope="col"><input type="checkbox" /></th>
                    <?php
                    foreach($this->columns as $key) {
                        if(substr($key, -3) == "_id")
                            $key = ucwords(substr($key, 0, -3));
                    ?>
                    <th scope="col"><?php _e($key, TAQUILLA_DOMAIN); ?></th>
                    <?php } ?>
                <th scope="col"><?php _e('Action', TAQUILLA_DOMAIN); ?></th>
                </tr>
            <?php
            $bg_style_index = 0;
            // TODO add paginate and arrows for pagination
            foreach($this->items as $item) {
                $bg_style_index++;
                $bg_style = (0 == ($bg_style_index % 2)) ? ' class="alternate"' : '';
                $edit_url = $this->get_action_url(array('action' => 'edit', $this->class . '_id' => $this->id($item)), false);
                $delete_url = $this->get_action_url(array('action' => 'delete', $this->class . '_id' => $this->id($item), 'item' => $this->class), true);
                echo "<tr{$bg_style}>\n";
                echo "\t<th class=\"check-column\" scope=\"row\"><input type=\"checkbox\" name=\"" . $this->class . "s[]\" value=\"" . $this->id($item) . "\" /></th>";
                foreach($item as $key => $value) {            
                    if(substr($key, -3) == "_id" && $key != "post_id") {
                        $join_class = substr($key, 0, -3);
                        $join_class = ucwords($join_class);    
                        $join = new $join_class($value);
                        $value = $join->name();
                        unset($join);
                    }
                    echo "<td>" . $value . "</td>";
                }
                echo "<td><a href=\"{$edit_url}\">" . __('Edit', TAQUILLA_DOMAIN) . "</a>" . " | ";
                echo "<a class=\"delete_" . $this->class . "_link delete\" href=\"{$delete_url}\">" . __('Delete', TAQUILLA_DOMAIN) . "</a></td>\n";
                echo "</tr>\n";
            }
            ?>
            </table>
            <input type="hidden" name="action" value="bulk_edit" />
            <p class="submit"><?php _e('Bulk actions:', TAQUILLA_DOMAIN); ?>  <input type="submit" name="submit[delete]" class="button-primary bulk_delete_<?php echo $this->class; ?>s" value="<?php _e('Delete ' . $this->classes, TAQUILLA_DOMAIN); ?>" />
            </p>
            </form></div>
            <?php
        }
        $this->print_page_footer();
    }
    
    function print_action_edit($action = "edit") {

        $header = __(ucwords($action) ." ". $this->Class, TAQUILLA_DOMAIN);
        if ($this->name() != null)
            $header = sprintf('%s &ldquo;%s&rdquo;', $header, $this->name());
        $this->print_page_header($header);

        $this->print_submenu_navigation($action);
        ?>
        <div style="clear:both;"><p>
        <p><?php _e('You can ' . $action . ' a ' . $this->class . ' here...', TAQUILLA_DOMAIN); ?></p>
		<?php # echo sprintf(__('If you want to show a movie, use this shortcode: <strong>[movie id=%s /]</strong>')); ?> 
		</p></div>
		<?php foreach($this->items as $item) { ?>
        <form method="post" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field($this->get_nonce($action)); ?>

        <div class="postbox">
        <h3 class="hndle">
        <span><?php _e($this->Class . ' Information', TAQUILLA_DOMAIN); ?></span>
        </h3>
        <div class="inside">
        <table class="taquilla-options">
        <?php foreach($item as $k => $v) {
            if($k == "id" || $k == "post_id")
                continue;
            $name = $this->class . "[" . $k . "]";
        ?>
            <tr valign="top">
            <th scope="row">
            <label for="<?php echo $name; ?>"><?php _e($this->Class . ' ' . $k, TAQUILLA_DOMAIN); ?>:</label>
            </th>
            <td>
            <?php
            if(substr($k, -3) == "_id") {
                // if the name ends in _id, show a <select> if table exists
                echo '<select id="' . $name . '" name="' . $name . '">';
                $join_class = substr($k, 0, -3);
                $join_class = ucwords($join_class);    
                $join = new $join_class();
                // TODO cannot paginate here, so use AJAX autocomplete
                $join->select();
                foreach ($join->items as $item)
                    echo '<option' . (($join->id($item) == $v) ? ' selected="selected"': '') . ' value="' . $join->id($item) . '" style="width:200px">' . $join->name($item) . '</option>';
                unset($join);    
                echo '</select>';
#                echo '<input type="hidden" name="' . $this->class . '[' . $k . ']" id="' . $this->class . '[' . $k . ']" value="' . $this->safe_output($v) . '" />';
#                echo '<input type="text" name="' . $this->class . '_join[' . $k . ']" id="' . $this->class . '[' . $k . ']" value="' . $elem->name() . '" style="width:250px" />';
            } elseif ($k != "id")
                echo '<input type="text" name="' . $this->class . '[' . $k . ']" id="' . $this->class . '[' . $k . ']" value="' . $this->safe_output($v) . '" style="width:250px" />';
            }
            ?>
            </td>  
            </tr>
        </table>
        </div>
        </div>
        <p class="submit">
            <input type="hidden" name="action" value="edit" />
            <input type="hidden" name="<?php echo $this->class; ?>[id]" value="<?php echo $this->id(); ?>" />
        <input type="hidden" name="<?php echo $this->class; ?>[post_id]" value="<?php echo $item['post_id']; ?>" />
            <input type="submit" name="submit[update]" class="button-primary" value="<?php _e('Update Changes', TAQUILLA_DOMAIN); ?>" />
            <input type="submit" name="submit[save_back]" class="button-primary" value="<?php _e('Save and go back', TAQUILLA_DOMAIN); ?>" />
        <?php
            $list_url = $this->get_action_url(array('action' => 'list'));
            echo " <a class=\"button-primary\" href=\"{$list_url}\">" . __('Cancel', TAQUILLA_DOMAIN) . "</a>";
            $delete_url = $this->get_action_url(array('action' => 'delete', $this->class . '_id' => $this->id(), 'item' => $this->class), true);
            echo " <a class=\"button-secondary delete_" . $this->class . "_link\" href=\"{$delete_url}\">" . __('Delete ' . $this->Class, TAQUILLA_DOMAIN) . "</a>";
        ?>
        </p>
        </form>
	    <?php } //foreach item ?>
        <?php
        $this->print_page_footer();
    }

    // TODO: merge with the print_action_edit
    function print_action_add($action = "add") {
        $header = __(ucwords($action) ." ". $this->Class, TAQUILLA_DOMAIN);
        if ($this->name() != null)
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
        <?php foreach($this->columns as $k) {
            if($k == "id" || $k == "post_id")
                continue;
            $name = $this->class . "[" . $k . "]";
        ?>
            <tr valign="top">
            <th scope="row">
            <label for="<?php echo $name; ?>"><?php _e($this->Class . ' ' . $k, TAQUILLA_DOMAIN); ?>:</label>
            </th>
            <td>
            <?php
            if(substr($k, -3) == "_id") {
                // if the name ends in _id, show a <select> if table exists
                echo '<select id="' . $name . '" name="' . $name . '">';
                $join_class = substr($k, 0, -3);
                $join_class = ucwords($join_class);    
                $join = new $join_class();
                // TODO cannot paginate here, so use AJAX autocomplete
                $join->select();
                foreach ($join->items as $item)
                    echo '<option value="' . $join->id($item) . '" style="width:200px">' . $join->name($item) . '</option>';
                unset($join);    
                echo '</select>';
#                echo '<input type="hidden" name="' . $this->class . '[' . $k . ']" id="' . $this->class . '[' . $k . ']" value="' . $this->safe_output($v) . '" />';
#                echo '<input type="text" name="' . $this->class . '_join[' . $k . ']" id="' . $this->class . '[' . $k . ']" value="' . $elem->name() . '" style="width:250px" />';
            } elseif ($k != "id")
                echo '<input type="text" name="' . $this->class . '[' . $k . ']" id="' . $this->class . '[' . $k . ']" value="" style="width:250px" />';
            }
            ?>
            </td>  
            </tr>
        </table>
        </div>
        </div>
        <p class="submit">
        <input type="hidden" name="action" value="add" />
        <input type="submit" name="submit" class="button-primary" value="<?php _e('Add ' . $this->Class, TAQUILLA_DOMAIN); ?>" />
        </p>
        </form>
        <?php
        $this->print_page_footer();
    }


    ####   Show page functions  ###############################################                                                     

    function show_manage_page() {
    // get and check action parameter from passed variables
        $action = (isset($_REQUEST['action']) && !empty($_REQUEST['action'])) ? $_REQUEST['action'] : 'list';
        // check if action is allowed and method callable, if yes, call it
        $show_list = true;
        if (in_array($action, array_merge($this->actions_item, $this->actions_all)) && is_callable(array(&$this, 'do_action_' . $action)))
            $show_list = call_user_func(array(&$this, 'do_action_' . $action));
        if($show_list)
            call_user_func(array(&$this, 'do_action_list'));
    }


 ####   Admin Tab Functions  ###############################################                                                     

 function add_manage_page() {
 // add page, and what happens when page is loaded or shown
     $min_capability = 'publish_posts'; // user needs at least this
     $menu_position = 7;
     $menu_page = exists_menu_page($menu_position);
     if ($menu_page  == null) {
         $menu_page = 'list_' . $this->classes;
         $this->hook = my_add_menu_page($this->Classes, $this->Classes, $min_capability, $menu_page, array(&$this, 'show_manage_page'), '', $menu_position);
     }
     else
         $this->hook = add_submenu_page($menu_page, $this->Classes, $this->Classes, $min_capability, 'list_' . $this->classes, array(&$this, 'show_manage_page')); 
     add_action('load-' . $this->hook, array(&$this, 'load_manage_page'));

     // This works, it's just commented to save space
     // $this->hook = add_submenu_page($menu_page, $this->Classes, 'Add ' . $this->class, $min_capability, 'add_' . $this->class, array(&$this, 'do_action_add')); 
     // add_action('load-' . $this->hook, array(&$this, 'load_manage_page'));
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

 function add_manage_page_js() {
 // TODO Change reference to "movie" with $this->class
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

    ####   Auxiliary functions  ###############################################                                                     

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
            <?php 
            $elems = array();
            foreach($this->actions_all as $curr_action) { 
                $current = ($curr_action == $action ? 'class="current" ' : '');
                $elems[] = '<a ' . $current . 'href="' . $this->get_action_url(array('action' => $curr_action)) . '">' . ucwords($curr_action) . ' ' . $this->Classes . '</a>';
            }
            echo "<li>" . implode(' | </li><li>', $elems) . "</li>";     
            ?>  
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

###############################################################################
####                                                                       ####
####   External functions                                                  ####
####                                                                       ####
###############################################################################

# Copied from wp-admin/menu.php, adding the position of the menu
function my_add_menu_page($page_title, $menu_title, $access_level, $file, $function = '', $icon_url = '', $position=99) {
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

function exists_menu_page($position) {
    global $menu;
    if (!isset($menu[$position]))
        return null;
    else
        return $menu[$position][2];
}



?>