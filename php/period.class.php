<?php
/*
File Name: Taquilla - Period Class
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

if (!class_exists("Item") || !class_exists("Collection"))
    include_once ("item.class.php");

class Period extends Item {

    var $values = array(
        'id' => null,
        'post_id' => null,
        'country_id' => null,
        'year' => null,
        'week' => null,
        'date_from' => null,
        'date_to' => null
    );

    function name() {
        return $this->values['week'] . '/' . $this->values['year'];
    }

    function Period($period_id = null) {
        $this->class = "period";
        $this->setup($period_id);
    }
}

class Periods extends Collection {

    function Periods() {
        $this->Kind = "Period";
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