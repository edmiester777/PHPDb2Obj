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
require_once dirname(__FILE__).'/DatabaseColumnElement.class.php';

/**
 * DatabaseTable is an abstracted class that controls data from a table in the
 * database.
 */
abstract class DatabaseTable{
    private $table_name;
    private $uniqueCol;
    private $columns;
    
    /**
     * Construct an empty DatabaseTable
     */
    public function __construct(){
        $this->setTableName("");
        $this->columns = array();
    }
    
    /**
     * Supporting method overloading and dynamic accessors/mutators
     * @param type $name
     * @param type $arguments
     */
    public function __call($name, $arguments) {
        if($name == "addColumnElement" && count($arguments) == 1 && $arguments[0] instanceof DatabaseColumnElement){
            return call_user_func_array(array($this, 'addColumnElementWithColumnElement'), $arguments);
        }
        else if($name == "addColumnElement" && is_string($arguments[0]) && count($arguments) < 4){
            return call_user_func_array(array($this, 'addColumnElementNoClass'), $arguments);
        }
        else if($name == 'addColumnElement' && is_string($arguments[0]) && count($arguments) == 4){
            return call_user_func_array(array($this, 'addColumnElementNoClassCustomRelation'), $arguments);
        }
        else if($name == 'loadFromColumn' && is_string($arguments[0])){
            return call_user_func_array(array($this, 'loadFromColumnElementString'), $arguments);
        }
        
        // checking for accessors and mutators
        $name = strtolower($name);
        $func_start = substr($name, 0, 3);
        $func_end   = substr($name, 3);
        $func_end   = preg_replace("/[^A-Za-z0-9 ]/", '', $func_end);
        foreach($this->columns as $column){
            $col_name = $column->getColumnName();
            if($func_end == preg_replace("/[^A-Za-z0-9 ]/", '', $col_name)){
                switch($func_start){
                    case "get":
                        return $this->getColumnValue($col_name); // call DatabaseTable function incase get override has taken place
                        break;
                    case "set":
                        $this->setColumnValue($col_name, $arguments[0]); // call DatabaseTable function incase set override has taken place
                        return;
                        break;
                }
            }
        }
    }
    
    /**
     * Dynamic get variables
     * @param string $name
     */
    public function __get($name){
        return $this->getColumnValue($name);
    }
    
    /**
     * Dynamic set functions
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value) {
        $this->setColumnValue($name, $value);
    }
    
    /**********************************/
    /*         Common Methods         */
    /**********************************/
    
    /**
     * Reset the tables column elements to null values.
     */
    protected function resetColumnValues(){
        foreach($this->columns as $column){
            $column->setValue(NULL);
        }
    }
    
    /**
     * Get the name of the table
     * @return string
     */
    protected function getTableName(){
        return $this->table_name;
    }
    
    /**
     * Get the column element representing
     * the unique id column in database.
     * @return DatabaseColumnElement
     */
    protected function getUniqueColumn(){
        return $this->uniqueCol;
    }
    
    /**
     * Get an array of the registered database column elements
     * @return DatabaseColumnElement[]
     */
    protected function getColumnElements(){
        return $this->columns;
    }
    
    /**
      * Get value from column
      * @param string $column_name
      * @return mixed Value
      * @throws Exception Properties of column prevent values from being accessed
      */
    public function getColumnValue($column_name){
        if(!isset($this->columns[$column_name]))
            return NULL;
        if($this->columns[$column_name]->getProperties() & DatabaseColumnElement::COLUMN_EXCLUDE_GET_VALUE){
            // element is private, values cannot be set
            return NULL;
        }
        return $this->columns[$column_name]->getValue();
    }
    /**
     * Get the relation object of this column
     * @param string $column_name
     * @return DatabaseTable Relational table
     */
    public function getColumnRelation($column_name){
        if(!isset($this->columns[$column_name])){
            return NULL;
        }
        return $this->columns[$column_name]->getRelationObject();
    }
    
    /**
     * Set the name of this table
     * @param string $table_name
     */
    protected function setTableName($table_name){
        $this->table_name = $table_name;
    }
    
    /**
     * Set the unique id column
     * @param DatabaseColumnElement $col
     */
    protected function setUniqueColumn(DatabaseColumnElement $col){
        $this->uniqueCol = $col;
    }
    
    /**
     * Set the value of the column regardless of the properties registered to it
     * @param string $column_name Column Name
     * @param mixed $value Value
     * @param bool $ignore_properties Ignore Properties when setting (good for database loading)
     */
    protected function forceSetColumnValue($column_name, $value, $ignore_properties = false){
        if(!isset($this->columns[$column_name]))
            return;
        $this->columns[$column_name]->setValue($value, $ignore_properties);
    }
    
    /**
     * Set value of a column
     * @param string $column_name Name of column
     * @param mixed $value Value of current row
     * @throws Exception Properties of column prevent values from being set
     */
    public function setColumnValue($column_name, $value){
        if(!isset($this->columns[$column_name]))
            return;
        if($this->columns[$column_name]->getProperties() & DatabaseColumnElement::COLUMN_EXCLUDE_SET_VALUE && !$this->columns[$column_name]->valueNeedsSet()){
            // element is private, values cannot be set
            return;
        }
        $this->columns[$column_name]->setValue($value);
    }
    
    /**
     * Generate an associative array from this DatabaseTable
     * @return array
     */
    public function toAssocArray(){
        $arr = array();
        foreach($this->columns as $col){
            $arr[$col->getColumnName()] = $col->getValue();
        }
        return $arr;
    }
    
    /**
     * Load data from a query represented as an associative array
     * @param associative array $arr Array retrieved from query
     */
    public function loadFromAssocArray($arr){
        foreach($arr as $col_name => $val){
            if(isset($this->columns[$col_name])){
                $this->forceSetColumnValue($col_name, $val, true);
            }
        }
    }
    /**
     * Query data based on a column being equal to a certain value.
     *  Note: Limit 1 result
     * @global DatabaseConnector $dbConn
     * @param DatabaseColumnElement $column
     * @return bool success
     */
    public function loadFromColumnElement(DatabaseColumnElement $column){
        global $dbConn;
        
        // query setup
        $column_names = array();
        foreach($this->columns as $col){
            $column_names[]= $col->getColumnName();
        }
        
        $select = implode(', ', $column_names);
        // actual query
        $query = $dbConn->executeQuery(
            "
            SELECT {$select}
            FROM {$this->getTableName()}
            WHERE {$column->getColumnName()} = :val
            LIMIT 1
            ",
            array(
                ":val" => $column->getValue()
            )
        );
        if(is_array($query) && count($query) > 0){
            $this->loadFromAssocArray($query[0]);
            return true;
        }
        return false;
    }
    /**
     * Load data from the unique identifier of this table
     * @global DatabaseConnector $dbConn
     * @param mixed $id
     * @return bool success
     */
    public function loadFromUniqueID($id){
        $uniq_col = $this->getUniqueColumn();
        if($uniq_col == NULL)
            return false;
        $uniq_col->setValue($id);
        return $this->loadFromColumnElement($uniq_col);
    }
    
    /**
     * Update all columns that can be updated in the database.
     * @global DatabaseConnector $dbConn
     * @return bool success
     * @throws Exception
     */
    public function update(){
        global $dbConn;
        $uniq_id = NULL;
        $setStuff = array();
        $valsArray = array();
        foreach($this->columns as $key => $col){
            if($col->getProperties() & DatabaseColumnElement::COLUMN_UNIQUE_ID){
                // column is registered as the unique id
                $uniq_id = $col;
            }
            if(
                !($col->getProperties() & DatabaseColumnElement::COLUMN_EXCLUDE_UPDATE)
            ){
                $setStuff[]= "{$col->getColumnName()} = :{$col->getColumnName()}";
                $valsArray[":{$col->getColumnName()}"]= $col->getValue(true);
            }
        }
        if($uniq_id == NULL){
            throw new Exception("Could not locate a column with the COLUMN_UNIQUE_ID property");
        }
        if(count($setStuff) == 0){
            throw new Exception("Nothing to update");
        }
        $valsArray[':uniq_oop_registered_id'] = $uniq_id->getValue(true);
        $setString = implode(", ", $setStuff);
        
        $ret = $dbConn->executeNonQuery(
            "
            UPDATE {$this->getTableName()}
            SET {$setString}
            WHERE {$uniq_id->getColumnName()} = :uniq_oop_registered_id
            ",
            $valsArray
        );
        return $ret;
    }
    
    /**
     * Update single column if complete update is not necessary
     * @global DatabaseConnector $dbConn
     * @param string $column_name
     * @return boolean Success
     */
    public function updateSingleColumn($column_name){
        if(!isset($this->columns[$column_name]))
            return false;
        global $dbConn;
        $uniq_id = $this->getUniqueColumn();
        $update_col = $this->columns[$column_name];
        if($update_col->getProperties() & DatabaseColumnElement::COLUMN_EXCLUDE_UPDATE)
            return false;
        return $dbConn->executeNonQuery(
            "
            UPDATE {$this->getTableName()}
            SET {$update_col->getColumnName()} = :{$update_col->getColumnName()}
            WHERE {$uniq_id->getColumnName()} = :{$uniq_id->getColumnName()}
            ",
            array(
                ":{$update_col->getColumnName()}" => $update_col->getValue(),
                ":{$uniq_id->getColumnName()}"    => $uniq_id->getValue()
            )
        );
    }
    
    /**
     * Insert the current row and all values into the database
     * @global DatabaseConnector $dbConn
     * @return bool success
     */
    public function insert(){
        global $dbConn;
        $uniq_id = $this->getUniqueColumn();
        $column_names = array();
        $param_names = array();
        $valsArray = array();
        foreach($this->columns as $key => $col){
            if($col->getValue() != NULL){
                $column_names[]= $col->getColumnName();
                $param_names[]= ":{$col->getColumnName()}";
                $valsArray[":{$col->getColumnName()}"]= $col->getValue(true);
            }
        }
        $insert = implode(", ", $column_names);
        $values = implode(", ", $param_names);
        
        $ret = $dbConn->executeNonQuery(
            "
            INSERT INTO {$this->getTableName()}({$insert})
            VALUES({$values})
            ",
            $valsArray
        );
        if($uniq_id != NULL)
            $this->forceSetColumnValue($uniq_id->getColumnName(), $dbConn->getLastInsertId());
        return $ret;
    }
    
    /**
     * Delete the row in the database with the current unique id
     *  Note: Unique Identifier will be updated after this row is inserted
     * @global DatabaseConnector $dbConn
     * @return bool success
     * @throws Exception COLUMN_UNIQUE_ID property not found
     */
    public function delete(){
        global $dbConn;
        $uniq_id = $this->getUniqueColumn();
        if($uniq_id == NULL){
            throw new Exception("Could not locate a column with the COLUMN_UNIQUE_ID property");
        }
        
        $ret = $dbConn->executeNonQuery(
            "
            DELETE FROM {$this->getTableName()}
            WHERE {$uniq_id->getColumnName()} = :uniq_id
            ",
            array(
                ":uniq_id" => $uniq_id->getValue()
            )
        );
        $this->resetColumnValues();
        return $ret;
    }
    
    /**
     * Perform custom query.
     * @param string $where
     * @param array $values Values to bind
     * @return DatabaseTable[] Result
     */
    public static function Where($where, $values = array()){
        global $dbConn;
        $tmp = get_called_class();
        $tmp = new $tmp();
        $db_column_elements = $tmp->getColumnElements();
        $column_names = array();
        foreach($db_column_elements as $column){
            $column_names[]= $column->getColumnName();
        }
        $cols = implode(", ", $column_names);
        $result = $dbConn->executeQuery(
            "
            SELECT {$cols}
            FROM {$tmp->getTableName()}
            WHERE {$where}
            ",
            $values
        );
        $class = get_called_class();
        $results = array();
        foreach($result as $res){
            $tmp = new $class();
            $tmp->loadFromAssocArray($res);
            $results[]= $tmp;
        }
        return $results;
    }
    
    /**
     * Query data based on a column being equal to a certain value.
     *  Note: Limit 1 result
     * @param string $col_name Name of column
     * @param mixed $val Value to be loaded
     * @return DatabaseTable
     */
    public static function FromColumn($col_name, $val){
        $class = get_called_class();
        $tmp = new $class();
        if(!$tmp instanceof DatabaseTable)
            return NULL;
        $tmp->setColumnValue($col_name, $val);
        return $tmp->loadFromColumn($col_name) ? $tmp : NULL;
    }
    
    /**
     * Load data from the unique id of this table
     * @param int $id Unique identifier
     * @return DatabaseTable row selected
     */
    public static function FromUniqueId($id){
        $class = get_called_class();
        $tmp = new $class();
        if(!$tmp instanceof DatabaseTable)
            return NULL;
        return $tmp->loadFromUniqueID($id) ? $tmp : NULL;
    }
    
    /**
     * Get all the rows in the database as an array of the current class.
     * @global DatabaseConnector $dbConn
     * @return DatabaseTable[] Array of results
     */
    public static function LoadAllRows(){
        global $dbConn;
        
        // query setup
        $class = get_called_class();
        $obj = new $class();
        $column_names = array();
        foreach($obj->getColumnElements() as $col){
            $column_names[]= $col->getColumnName();
        }
        
        $select = implode(', ', $column_names);
        // actual query
        $query = $dbConn->executeQuery(
            "
            SELECT {$select}
            FROM {$obj->getTableName()}
            "
        );
        $results = array();
        if(is_array($query) && count($query) > 0){
            foreach($query as $row){
                $tmp = new $class();
                $tmp->loadFromAssocArray($row);
                $results[]= $tmp;
            }
            return $results;
        }
        return array();
    }
    
    /**
     * Get the count of rows in this table.
     * @global DatabaseConnector $dbConn
     * @return int count
     */
    public static function Count(){
        global $dbConn;
        
        $class = get_called_class();
        $obj = new $class();
        $uniq = $obj->getUniqueColumn();
        
        $ret = $dbConn->executeQuery(
            "
            SELECT COUNT({$uniq->getColumnName()}) AS total
            FROM {$obj->getTableName()}
            "
        );
        if(is_array($ret) && count($ret) == 0)
            return 0;
        return $ret[0]['total'];
    }
    
    /**
     * Starts a custom linear query that
     * can be executed at any time.
     * @global DatabaseConnector $dbConn
     * @param type $sql
     * @param type $params
     * @return boolean success
     */
    public static function StartCustomLinearQuery($sql, $params=array()){
        global $dbConn;
        if($dbConn->getIsLinearFetchStarted()){
            $dbConn->endLinearFetch();
        }
        return $dbConn->startLinearFetch($sql, $params);
    }
    
    /**
     * Start loading all rows in a linear query
     * @global DatabaseConnector $dbConn
     * @return boolean success
     */
    public static function StartLoadAllRowsLinear(){
        global $dbConn;
        $class = get_called_class();
        $obj = new $class();
        $column_names = array();
        foreach($obj->getColumnElements() as $col){
            $column_names[]= $col->getColumnName();
        } 
        $select = implode(', ', $column_names);
        return DatabaseTable::StartCustomLinearQuery(
            "
            SELECT {$select}
            FROM {$obj->getTableName()}
            "
        );
    }
    
    /**
     * Execute a WHERE query in linear fashion.
     * @global DatabaseConnector $dbConn
     * @param string $where where conditions
     * @param array $params parameters to be bound
     * @return boolean success
     */
    public static function StartLinearWhere($where, $params=array()){
        global $dbConn;
        $tmp = get_called_class();
        $tmp = new $tmp();
        $db_column_elements = $tmp->getColumnElements();
        $column_names = array();
        foreach($db_column_elements as $column){
            $column_names[]= $column->getColumnName();
        }
        $cols = implode(", ", $column_names);
        return DatabaseTable::StartCustomLinearQuery(
            "
            SELECT {$cols}
            FROM {$tmp->getTableName()}
            WHERE {$where}
            ",
            $params
        );
    }
    
    /**
     * End loading all rows in linear fashion.
     * @global DatabaseConnector $dbConn
     */
    public static function EndLinearQuery(){
        global $dbConn;
        $dbConn->endLinearFetch();
    }
    
    /**
     * Get the next row in prepared query.
     * Used to balance RAM as a substitute for
     * functions like LoadAllRows().
     * @global DatabaseConnector $dbConn
     * @return DatabaseTable response
     */
    public static function GetNextRow($load_from_unique_id){
        global $dbConn;
        if(!$dbConn->getIsLinearFetchStarted())
            return NULL;
        $result = $dbConn->getNextRow();
        if($result == NULL)
            return NULL;
        $class = get_called_class();
        $tmp = new $class();
        if($load_from_unique_id){
            $uniq = $tmp->getUniqueColumn()->getColumnName();
            $tmp->loadFromUniqueID($result[$uniq]);
        }
        else{
            $tmp->loadFromAssocArray($result);
        }
        return $tmp;
    }
    
    
    /*=============================================*/
    /*             Overload Functions              */
    /*=============================================*/
    
    /**
     * Add DatabaseColumnElement to the list of columns in our table
     * @param DatabaseColumnElement $column
     */
    private function addColumnElementWithColumnElement(DatabaseColumnElement $column){
        $this->columns[$column->getColumnName()] = $column;
        if($column->getProperties() & DatabaseColumnElement::COLUMN_UNIQUE_ID){
            $this->setUniqueColumn($column);
        }
    }
    
    /**
     * Add a column element from just constructor parameters
     * @param type $name Column Name
     * @param type $props Column Properties
     * @param type $relation Relational class with unique id loading
     */
    private function addColumnElementNoClass($name, $props = 0, $relation = ""){
        $this->addColumnElement(new DatabaseColumnElement($name, $props, $relation));
    }
    
    /**
     * Add a column element from just constructor parameters with a custom column
     * to be loaded when relation is called
     * @param type $name
     * @param type $props
     * @param type $relation
     * @param type $custom_column
     */
    private function addColumnElementNoClassCustomRelation($name, $props = 0, $relation = "", $custom_column = ""){
        $column = new DatabaseColumnElement($name, $props, $relation);
        $column->setRelationalColumn($custom_column);
        $this->addColumnElement($column);
    }
    
    /**
     * Perform the loadFromColumnElement function with only a string column_name
     * @param type $column_name Column Name
     * @return boolean success
     */
    private function loadFromColumnElementString($column_name){
        // validation
        if(strlen($column_name) == 0){
            return false;
        }
        if(!isset($this->columns[$column_name])){
            return false;
        }
        return $this->loadFromColumnElement($this->columns[$column_name]);
    }
}
?>
