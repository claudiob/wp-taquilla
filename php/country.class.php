<?php
/*
File Name: Taquilla - Country Class
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

if (!class_exists("Item"))
    include_once ("item.class.php");

class Country extends Item {

    function Country($country_id = null) {
        $this->class = "country";
        $this->setup($country_id);
    }

}


?>