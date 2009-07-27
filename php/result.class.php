<?php
/*
File Name: Taquilla - Result Class
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

if (!class_exists("Item"))
    include_once ("item.class.php");

class Result extends Item {

        

    function name($item = null) { 
        return $this->get("movie_id", $item) . "/" . $this->get("movie_id", $item); 
    }


    function Result($result_id = null) {
        $this->class = "result";
        $this->columns = array('id', 'post_id', 'movie_id', 'period_id', 'periods', 'copies', 'gross', 'gross_mean', 'gross_cume', 'gross_delta', 'audience', 'audience_mean', 'audience_cume', 'audience_delta');
        $this->actions_all[] = 'import'; 
        $this->setup($result_id);
    }

    function create_table() {
    // create the tables in the MySql database
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        if($wpdb->get_var("show tables like '$this->table'") != $this->table) {
          $sql = "CREATE TABLE " . $this->table . " (
              id mediumint(9) NOT NULL auto_increment,
              post_id bigint(20) unsigned,
              movie_id mediumint(9),
              period_id mediumint(9),
              position smallint(5) unsigned default NULL,
              periods smallint(5) unsigned default NULL,
              copies int(20) unsigned default NULL,
              gross int(20) unsigned default NULL,
              gross_mean int(20) unsigned default NULL,
              gross_cume int(20) unsigned default NULL,
              gross_delta decimal(8,2) default NULL,
              audience int(20) unsigned default NULL,
              audience_mean int(20) unsigned default NULL,
              audience_cume int(20) unsigned default NULL,
              audience_delta decimal(8,2) default NULL,
              UNIQUE KEY id (id),
              UNIQUE KEY post_id (post_id),
              KEY movie_id (movie_id),
              KEY period_id (period_id),
              UNIQUE KEY unique2 (movie_id,period_id),
              UNIQUE KEY unique3 (period_id, position)
      	    );";
        dbDelta($sql);       
        }
        return $this->table;
    }


    function load_from_excel($tmp_file) {

        require_once 'excel_reader.php';
        $reader = new Spreadsheet_Excel_Reader();
        $reader->setOutputEncoding('CP1251');
        if(!$reader->read($tmp_file))
            return null;
            
        $cells = $reader->sheets[0]['cells'];
        error_reporting(E_ALL ^ E_NOTICE);

        $data = array();
        # Look for period
        # // TODO Do not read from XLS, let the user specify with a calendar
        # // TODO Sometimes is G4 rather than G7
        $data['period'] = array();
        $periods = explode("-", $cells[7][7]);
        if (!$periods)
            $periods = explode("-", $cells[4][7]);
        $from_re = preg_match('/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{1,2})/',
             $periods[0],$matches);
        $data['period']['date_from'] = $matches[3] . "-" . $matches[2] . "-" . $matches[1];    
        $to_re = preg_match('/([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{1,2})/',
             $periods[1],$matches);
        $data['period']['date_to'] = $matches[3] . "-" . $matches[2] . "-" . $matches[1];    

        # Look for rows with valid rankings
        $first_row = null;
        for ($i = 1; $i <= 100; $i++) {
        	if (is_numeric($cells[$i][1]))
        		{$first_row = $i; break;}
        }

        $data['results'] = array();
        for ($count = 0; $count < 50; $count++) {
            $row = $cells[$first_row + $count];
            if (!is_numeric($row[1]))
                break;
            $result = array();
            $result['result']['position'] = (integer)$row[1];
            $result['movie']['title_edi'] = $row[2];
            $result['movie']['title_original_edi'] = $row[3];
            $result['movie']['studio_code_edi'] = $row[4];
            $result['result']['copies'] = (integer)$row[5];
            $result['result']['periods'] = (integer)$row[6];
            $result['result']['gross'] = (integer)$row[7];
            $result['result']['copies'] = (integer)$row[8];
            $result['result']['gross_mean'] = (integer)$row[9];
            $result['result']['gross_delta'] = (integer)$row[10];
            $result['result']['audience'] = (integer)$row[11];
            $result['result']['audience_mean'] = (integer)$row[12];
            $result['result']['audience_delta'] = (integer)$row[13];
            $result['result']['gross_cume'] = (integer)$row[14];
            $result['result']['audience_cume'] = (integer)$row[15];
            $data['results'][] = $result;
        }
        unlink($tmp_file);        
        return $data;
    }

    function do_action_import() {
        // Return true if the list of items has to be shown afterwards

        $imported_data = null;
        // TODO: add && isset($_POST[$this->class])) as in do_action_edit
        // if (isset($_POST['submit']) && isset($_POST[$this->class])) {
        if (isset($_POST['submit'])) {
            check_admin_referer($this->get_nonce('import'));
            $tmp_file = $_FILES[$this->class . '_file']['tmp_name'];
            if ('import' == $_POST['action'] && false === empty($tmp_file))
                $imported_data = $this->load_from_excel($tmp_file);
            if ($imported_data == null) {
                $this->print_success_message(__($this->Class . ' could not be imported.', TAQUILLA_DOMAIN));
                $this->print_action_import();
                exit;
            }
            
            # Lookup country
            $country = new Country();
            $country->find_or_create(array('name' => 'Spain'));
            $country_id = $country->id();
            unset($country);
            
            # Lookup period
            $period = new Period();
            $period->find_or_create($imported_data['period']);
            $period_id = $period->id();
            unset($period);
            
            foreach($imported_data['results'] as $line) {
                
                # Lookup studio
                $studio = new Studio();
                $key = array(
                    'code_edi' => $line['movie']['studio_code_edi']
                );
                $studio->find_or_create($key);
                $studio_id = $studio->id();
                unset($studio);
                

                # Lookup movie
                $movie = new Movie();
                $key = array(
                   'studio_id' => $studio_id,
                   'title_edi' => $line['movie']['title_edi'],
                   'title_original_edi' => $line['movie']['title_original_edi']
                );
                $movie->find_or_create($key);
                $movie_id = $movie->id();
                unset($movie);

                $result = new Result();
                $key = array(
                   'period_id' => $period_id,
                   'movie_id' => $movie_id
                );
                $result->find_or_create($key, false);
                $line['result']['id'] = $result->id();                 
                $line['result']['period_id'] = $period_id;                 
                $line['result']['movie_id'] = $movie_id;                 
                $result->items[0] = $line['result'];
                $result->save();    
                unset($result);
            }

            $this->print_success_message(__('Results imported successfully.', TAQUILLA_DOMAIN));
            return true;
        } else {
            $this->print_action_import();
            return false;
        }
    }

    function print_action_import() {
        $action = "import";
        $header = __(ucwords($action) ." ". $this->Class, TAQUILLA_DOMAIN);
        $this->print_page_header($header);

        $this->print_submenu_navigation($action);
        ?>
        <div style="clear:both;"><p>
        <p><?php _e('You can ' . $action . ' a ' . $this->class . ' here...', TAQUILLA_DOMAIN); ?></p>
		</p></div>

        <form method="post" enctype="multipart/form-data" action="<?php echo $this->get_action_url(); ?>">
        <?php wp_nonce_field($this->get_nonce($action)); ?>

        <div class="postbox">
        <h3 class="hndle">
        <span><?php _e($this->Class . ' Information', TAQUILLA_DOMAIN); ?></span>
        </h3>
        <div class="inside">
        <table class="taquilla-options">
            <tr valign="top">
            <?php $name = $this->class . "_file"; ?>
            <th scope="row"><label for="<?php echo $name; ?>"><?php _e('Select File to Import', TAQUILLA_DOMAIN); ?>:</label></th>
            <td><input name="<?php echo $name; ?>" id="<?php echo $name; ?>" type="file" /></td>
            </tr>
        </table>
        </div>
        </div>
        <input type="hidden" name="action" value="import" />
        <p class="submit">
        <input type="submit" name="submit" class="button-primary" value="<?php _e('Import Table', TAQUILLA_DOMAIN); ?>" />
        </p>
        </form>
        <?php
        $this->print_page_footer();
    }

    function print_action_import2() {
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





}


?>