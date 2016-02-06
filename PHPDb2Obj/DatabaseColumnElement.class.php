<?php
/* 
    This file is part of PHPDb2Obj.

    PHPDb2Obj is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    PHPDb2Obj is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with PHPDb2Obj.  If not, see <http://www.gnu.org/licenses/>.
 */
require_once dirname(__FILE__).'/DatabaseConnector.class.php';
require_once dirname(__FILE__).'/DatabaseTable.class.php';

/**
 * DatabaseColumnElement is an object to represent a column in a database table.
 * DatabaseColumnElement accepts properties in its constructor to ensure that
 * it is only accessed when it should be.
 */
class DatabaseColumnElement{
    /** Column value cannot be retrieved by normal classes */
    const COLUMN_EXCLUDE_GET_VALUE = 1;
    /** Column value cannot be set by normal classes */
    const COLUMN_EXCLUDE_SET_VALUE = 2;
    /** Column will not update when you call the update() function */
    const COLUMN_EXCLUDE_UPDATE = 4;
    /** Column is the unique identifier of this table */
    const COLUMN_UNIQUE_ID = 8;
    /** Column should serialize the data before updating and inserting */
    const COLUMN_SERIALIZE_VALUE = 16;
    
    
    private $col_name;
    private $props;
    private $value;
    private $value_needs_set;
    private $relationalClass;
    private $relationColumn;
    
    public $valueChanged;
    
    /**
     * Construct a DatabaseColumnElement with a column name and properties
     * @param string $name
     * @param byte $props: DatabaseColumnElement::$COLUMN_PRIVATE | DatabaseColumnElement::$COLUMN_NO_UPDATE;
     * @param string $relation ClassName of the DatabaseTable that this element corresponds to
     */
    public function __construct($column_name, $props = 0, $relation = ""){
        $this->setColumnName($column_name);
        $this->setProperties($props);
        $this->setRelation($relation);
        $this->value_needs_set = true;
        $this->valueChanged = false;
    }
    
    //Getters
    /**
     * Get column name of the corresponding column in the table
     * @return string
     */
    public function getColumnName(){
        return $this->col_name;
    }
    /**
     * Get the properties that the class has requested to use
     * @return byte
     */
    public function getProperties(){
        return $this->props;
    }
    /**
     * Get the value of this column from the row in the database
     * @param bool $ignore_properties Ignore properties when getting this value
     * @return mixed
     */
    public function getValue($ignore_properties = false){
        if(!$ignore_properties && $this->getProperties() & DatabaseColumnElement::COLUMN_SERIALIZE_VALUE && $this->value != NULL){
            return DatabaseColumnElement::DeserializeArray($this->value);
        }
        return $this->value;
    }
    /**
     * Get the class relation that this column has
     * @return string Class Name
     */
    public function getRelation(){
        return $this->relationalClass;
    }
    /**
     * Get the relation object from this column
     * @return DatabaseTable
     */
    public function getRelationObject(){
        if($this->getRelation() == NULL || empty($this->getRelation())){
            return NULL;
        }
        $obj = new $this->relationalClass();
        if(!$obj instanceof DatabaseTable){
            return NULL;
        }
        if(strlen($this->relationColumn) == 0){
            if(!$obj->loadFromColumnElement($this)){
                return NULL;
            }
        }
        else{
            $obj->setColumnValue($this->relationColumn, $this->getValue());
            if(!$obj->loadFromColumn($this->relationColumn)){
                return NULL;
            }
        }
        
        return $obj;
    }
    
    /**
     * Get if value needs to be set or not
     * @return bool
     */
    public function valueNeedsSet(){
        return $this->value_needs_set;
    }
    
    //Setters
    /**
     * Set column name
     * @param string $col_name Column Name
     */
    private function setColumnName($col_name){
        $this->col_name = $col_name;
    }
    /**
     * Set the properties of this column
     * @param byte $props
     */
    private function setProperties($props){
        $this->props = $props;
    }
    /**
     * Set the value of this column
     * @param mixed $val Value
     * @param bool $ignore_properties Ignore properties and use default set value
     */
    public function setValue($val, $ignore_properties = false){
        // we can mark this column as changed to ensure proper update.
        if($val !== $this->getValue()){
            $this->valueChanged = true;
        }
        if(
            !$ignore_properties &&
            $this->getProperties() & DatabaseColumnElement::COLUMN_SERIALIZE_VALUE
        ){
            $this->value = DatabaseColumnElement::SerializeArray($val);
        }
        else{
            $this->value = $val;
        }
        $this->value_needs_set = false;
    }
    
    /**
     * Set the relation of this class to a class. This helps with having a relational
     * ID to another row in a seperate table.
     *  Note: To load from custom column in the database, must set relational column
     * 
     * @param string $className the name of a class that is a descendant of
     * DatabaseTable.
     */
    public function setRelation($className = ""){
        $this->relationalClass = $className;
    }
    
    /**
     * Set a custom column for a relational object.
     *  Note: Use this when you wish to load from a column that is not listed
     *  as the COLUMN_UNIQUE_ID.
     * @param string $column_name Column Name in other table
     */
    public function setRelationalColumn($column_name){
        $this->relationColumn = $column_name;
    }
    
    
    /*========================================*/
    /*           Static Functions             */
    /*========================================*/
        
    /**
     * Serialize an array so that it may be saved as text in the database.
     * @param array $arr
     * @return string Serialized Array
     */
    public static function SerializeArray($arr){
        return is_array($arr) ? base64_encode(json_encode($arr)) : NULL;
    }
    
    /**
     * Deserialize an array that has been serialized for database storage
     * @param string $serialized_array
     * @return array
     */
    public static function DeserializeArray($serialized_array){
        return is_string($serialized_array) ? json_decode(base64_decode($serialized_array), true) : NULL;
    }
}
?>
