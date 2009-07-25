<?php
/*
File Name: Taquilla - Import Class (see main file taquilla.php)
Plugin URI: 
Description: This plugin allows you to add box office movies and results in your WordPress posts.
Version: 0.1
Author: Claudio Baccigalupo
Author URI: 
*/

// should be included by Taquilla_Admin!
class Taquilla_Import {

    // 
    var $import_class_version = '0.1';

    // possible import formats
    var $import_formats = array();

    // used if file uploaded
    var $filename = '';
    var $tempname = '';
    var $mimetype = '';

    // filled before import
    var $import_format = '';
    var $import_from = '';
    var $wp_table_id = '';
    
    // return values
    var $error = false;
    var $imported_data = array();

    // constructor class
    function Taquilla_Import() {
        $this->import_formats = array(
            'xls' => __( 'XLS - Excel Spreadsheet', TAQUILLA_DOMAIN ),
            // don't have this show up in list, as handled in separate table
            // 'wp_table' => 'wp-Table plugin database'
        );
    }

    function import_movie() {
        switch( $this->import_format ) {
            case 'xls':
                $this->import_xls();
                break;
            default:
                $this->imported_table = array();
        }
    }

    // ###################################################################################################################
    function import_xls() {

        require_once 'excel_reader.php';
        $reader = new Spreadsheet_Excel_Reader();
        $reader->setOutputEncoding('CP1251');
        $reader->read($this->tempname);
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
            $result['position'] = (integer)$row[1];
            $result['title_edi'] = $row[2];
            $result['title_original_edi'] = $row[3];
            $result['studio_code_edi'] = $row[4];
            $result['copies'] = (integer)$row[5];
            $result['weeks'] = (integer)$row[6];
            $result['gross'] = (integer)$row[7];
            $result['copies_edi'] = (integer)$row[8];
            $result['gross_mean'] = (integer)$row[9];
            $result['gross_delta'] = (integer)$row[10];
            $result['audience'] = (integer)$row[11];
            $result['audience_mean'] = (integer)$row[12];
            $result['audience_delta'] = (integer)$row[13];
            $result['gross_cume'] = (integer)$row[14];
            $result['audience_cume'] = (integer)$row[15];
            $data['results'][] = $result;
        }
        $this->imported_data = $data;
    }

    // ###################################################################################################################
    function unlink_uploaded_file() {
        unlink( $this->tempname );
    }

    // ###################################################################################################################
    // make sure array is rectangular with $max_cols columns in every row
    function pad_array_to_max_cols( $array_to_pad ){
        $rows = count( $array_to_pad );
        $max_columns = $this->count_max_columns( $array_to_pad );
        // array_map wants arrays as additional parameters (so we create one with the max_cols to pad to and one with the value to use (empty string)
        $max_columns_array = array_fill( 1, $rows, $max_columns );
        $pad_values_array =  array_fill( 1, $rows, '' );
        return array_map( 'array_pad', $array_to_pad, $max_columns_array, $pad_values_array );
    }

    // ###################################################################################################################
    // find out how many cols the longest row has
    function count_max_columns( $array ){
        $max_cols = 0 ;
        if ( is_array( $array ) && 0 < count( $array ) ) {
                foreach ( $array as $row_idx => $row ) {
                    $cols  = count( $row );
                    $max_cols = ( $cols > $max_cols ) ? $cols : $max_cols;
                }
        }
        return 	$max_cols;
    }

    // ###################################################################################################################
    function add_slashes( $array ) {
        return array_map( 'addslashes', $array );
    }
    
    // ###################################################################################################################
    function get_table_meta() {
        $table['name'] = $this->filename;
        $table['description'] = $this->filename;
        $table['description'] .= ( false == empty( $this->mimetype ) ) ? ' (' . $this->mimetype . ')' : '';
        return $table;
    }

    // ###################################################################################################################
    function create_class_instance( $class, $file) {
        if ( !class_exists( $class ) ) {
            include_once ( TAQUILLA_ABSPATH . 'php/' . $file );
            if ( class_exists( $class ) )
                return new $class;
        }
    }

} // class Taquilla_Import

?>