<?php
/*
File Name: Taquilla - Result Class
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

if (!class_exists("Item") || !class_exists("Collection"))
    include_once ("item.class.php");

class Result extends Item {

    var $values = array(
        'id' => null,
        'post_id' => null,
        'movie_id' => null,
        'period_id' => null,
        'periods' => null,
        'copies' => null,
        'gross' => null,
        'gross_mean' => null,
        'gross_cume' => null,
        'gross_delta' => null,
        'audience' => null,
        'audience_mean' => null,
        'audience_cume' => null,
        'audience_delta' => null
    );

    function name() {
        return $this->values['movie_id'] . '/' . $this->values['period_id'];
    }


    function Result($result_id = null) {
        $this->class = "result";
        $this->setup($result_id);
    }
}

class Results extends Collection {

    function Results() {
        $this->Kind = "Result";
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
}

?>