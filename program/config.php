<?php
/**
 * Created by PhpStorm.
 * User: TheLonadels
 * Date: 11.01.2018
 * Time: 6:46
 */

class config {
    private $db;
    private $data = [];
    private $oldData = [];

    public function __construct( SQLite3 $db ) {
        $this->db = $db;

        $res = $this->db->query( "SELECT * FROM `settings`" );
        while( $row = $res->fetchArray(SQLITE3_ASSOC) )
            $this->data[ $row[ 'param' ] ] = $row[ 'value' ];

        $this->oldData = $this->data;
    }

    public function __set( $p, $v ) {
        if( property_exists($this, $p) )
            $this->$p = $v;
        else
            $this->data[$p] = $v;
    }

    public function __get( $p ) {
        if( property_exists($this, $p) )
            return $this->$p;
        elseif( isset($this->data[$p]) )
            return $this->data[$p];
        else
            return null;
    }

    public function save() {
        foreach( $this->data as $param => $value ) {
            $param = $this->db->escapeString( $param );
            $value = $this->db->escapeString( $value );
            if( isset( $this->oldData[ $param ] ) )
                $query[] = "UPDATE `settings` SET `value` = '$value' WHERE `param` = '$param'; ";
            else
                $query[] = "INSERT INTO `settings` VALUES ('$param', '$value'); ";
        }

        if( ! empty($query) )
            $this->db->query( implode( "\n", $query ) );

        $this->oldData = $this->data;
    }
}