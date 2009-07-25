<?php
/*
File Name: Taquilla - Studio Class
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

if (!class_exists("Item") || !class_exists("Collection"))
    include_once ("item.class.php");

class Studio extends Item {

    var $values = array(
        'id' => null,
        'name' => null,
        'code_edi' => null,
        'code_mojo' => null
    );

    function Studio($studio_id = null) {
        $this->class = "studio";
        $this->setup($studio_id);
    }
}

class Studios extends Collection {

    function Studios() {
        $this->Kind = "Studio";
        $this->setup();
    }

    function create_table() {
    // create the tables in the MySql database
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        if($wpdb->get_var("show tables like '$this->table'") != $this->table) {
          $sql = "CREATE TABLE " . $this->table . " (
              id mediumint(9) NOT NULL auto_increment,
              name varchar(80) default NULL,
              code_edi varchar(32) default NULL,
              code_mojo varchar(32) default NULL,
              UNIQUE KEY id (id),
              UNIQUE KEY code_edi (code_edi),
              UNIQUE KEY code_mojo (code_mojo)
      	    );";
        dbDelta($sql);       
        }
        return $this->table;
    }
}

?>