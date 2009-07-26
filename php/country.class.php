<?php
/*
File Name: Taquilla - Country Class
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

if (!class_exists("Item") || !class_exists("Collection"))
    include_once ("item.class.php");

class Country extends Item {

    function Country($country_id = null) {
        $this->class = "country";
        $this->setup($country_id);
    }
}

class Countries extends Collection {

    function Countries() {
        $this->Kind = "Country";
        $this->setup();
    }

    function create_table() {
    // create the tables in the MySql database
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        if($wpdb->get_var("show tables like '$this->table'") != $this->table) {
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
}

?>