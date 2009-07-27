<?php
/*
File Name: Taquilla - Movie Class
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

if (!class_exists("Item"))
    include_once ("item.class.php");

class Movie extends Item {

    function Movie($movie_id = null) {
        $this->class = "movie";
        $this->columns = array('id','studio_id','post_id', 'title', 'title_edi', 'title_original', 'title_original_edi', 'minutes', 'year', 'budget');
        $this->setup($movie_id);
    }

    function name($item = null) { 
        return $this->get("title", $item) . "/" . $this->get("title_edi", $item); 
    }

    // TODO: should take the column values from $this->columns !
    function create_table() {
    // create the tables in the MySql database
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        if (!$this->table_exists()) {
            $sql = "CREATE TABLE " . $this->table . " (
                id mediumint(9) NOT NULL auto_increment,
                studio_id mediumint(9),
                post_id bigint(20) unsigned,
                title varchar(80) default NULL,
                title_edi varchar(80) default NULL,
                title_original varchar(80) default NULL,
                title_original_edi varchar(80) default NULL,
                minutes smallint(5) unsigned default NULL,
                year year(4) default NULL,
                budget mediumint(8) unsigned default NULL,
                UNIQUE KEY id (id),
                KEY studio_id (studio_id),
                KEY title_edi (title_edi),
                UNIQUE KEY post_id (post_id)
        	    );";
            dbDelta($sql);       
        }
        return $this->table;
    }

}


?>