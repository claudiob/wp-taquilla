<?php
/*
File Name: Taquilla - Period Class
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

if (!class_exists("Item"))
    include_once ("item.class.php");

class Period extends Item {

    function name($item = null) { 
        return $this->get("week", $item) . "/" . $this->get("year", $item); 
    }

    function Period($period_id = null) {
        $this->class = "period";
        $this->columns = array('id', 'post_id', 'country_id', 'year', 'week', 'date_from', 'date_to');
        $this->setup($period_id);
    }

    function create_table() {
    // create the tables in the MySql database
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        if($wpdb->get_var("show tables like '$this->table'") != $this->table) {
          $sql = "CREATE TABLE " . $this->table . " (
              id mediumint(9) NOT NULL auto_increment,
              post_id bigint(20) unsigned,
              country_id mediumint(9),
              year year(4) default NULL,
              week smallint(5) unsigned default NULL,
              date_from date NOT NULL,
              date_to date NOT NULL,
              UNIQUE KEY id (id),
              UNIQUE KEY post_id (post_id),
              KEY country_id (country_id),
              UNIQUE KEY unique3 (date_from,date_to),
              UNIQUE KEY unique2 (year,week,country_id)
      	    );";
        dbDelta($sql);       
        }
        return $this->table;
    }

}


?>