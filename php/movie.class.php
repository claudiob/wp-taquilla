<?php
/*
File Name: Taquilla - Movie Class
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

if (!class_exists("Item") || !class_exists("Collection"))
    include_once ("item.class.php");

class Movie extends Item {

    var $values = array(
        'id' => null,
        'studio_id' => null,
        'post_id' => null,
        'title' => null,
        'title_edi' => null,
        'title_original' => null,
        'title_original_edi' => null,
        'minutes' => null,
        'year' => null,
        'budget' => null
    );

    function name() {
        return $this->safe_output($this->values['title']);
    }

    function Movie($movie_id = null) {
        $this->class = "movie";
        $this->setup($movie_id);
    }

    
    ####   Redefine MySql functions  ##########################################                                                     

    function save() {
        global $wpdb;
        # First add a post for this movie
		$post_category = array();
		$wp_cats = get_categories(array('hide_empty' => false));
		foreach($wp_cats as $cat) {
			if(strtolower($cat->name) == "movie") {
				array_push($post_category, $cat->term_id);
    			break;
			}
		}
		// TODO else add a new category called movie
        $new_post = array('post_status' => 'publish', 'post_content' => '',
         'post_title' => $wpdb->escape($this->name()),
         'post_category' => $post_category);
        // TODO add custom fields or tags with year, studio, etc.
        $this->values['post_id'] = wp_insert_post($new_post);

        # Then add the movie to the table
        $insert = "INSERT INTO " . $this->table . 
        "(" . implode(",",array_keys($this->values)) . ") VALUES " .
        "(\"" . implode("\",\"", $this->values) . "\") ";
        // TODO Add $wpdb->escape 
        $wpdb->query($insert);
        $this->values['id'] = mysql_insert_id($wpdb->dbh);
    }
}

class Movies extends Collection {

    function Movies() {
        $this->Kind = "Movie";
        $this->setup();
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