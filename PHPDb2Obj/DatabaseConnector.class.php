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
require_once dirname(__FILE__).'/config.php';

/**
 * DatabaseConnector is a class that handles secure connections
 * and queries of a database.
 */
class DatabaseConnector{
    /** PDO Element for queries */
    private $pdo;
    /** Stores prepared statements */
    private $prepared = array();

    /**
     * Construct a DatabaseConnector with the global setup in config.
     * @global string $db_name Database name
     * @global string $db_user Database username
     * @global string $db_pass Database password
     * @global string $db_host Database host
     * @throws Exception
     */
    public function __construct(){
        global $db_name,
               $db_user,
               $db_pass,
               $db_host;
        // trying to make PDO connection
        try{
            $this->pdo = new PDO("mysql:host={$db_host};dbname={$db_name}", $db_user, $db_pass);
        }
        catch(Exception $e){
            throw $e;
        }
    }

    /**
     * Executes secure query protecting from all different kinds of hacks
     * @param string $sql query string
     * @param array $params Key-value pairs to be linked to query
     * @return array response
     */
    public function executeQuery($sql="", $params=array()){
        if(empty($sql) || !$this->pdo) return false;
        $prepare = $this->pdo->prepare($sql);
        foreach($params as $key => &$val){
            $val == NULL ? $prepare->bindParam($key, $val, PDO::PARAM_NULL) : $prepare->bindParam($key, $val);
        }
        if(!$prepare->execute())return false;
        return $prepare->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Executes statements that do not require fetching information.
     * This includes SELECT and UPDATE statements
     * @param string $sql
     * @param array $params Key-value pairs to be linked to query
     * @return boolean success
     */
    public function executeNonQuery($sql="", $params=array()){
        if(empty($sql) || !$this->pdo) return false;
        $prepare = $this->pdo->prepare($sql);
        foreach($params as $key => &$val){
            $val == NULL ? $prepare->bindParam($key, $val, PDO::PARAM_NULL) : $prepare->bindParam($key, $val);
        }
        return $prepare->execute() == 0 ? false : true;
    }
    
    /**
     * Begin the process of fetching mysql rows
     * one by one (if RAM is an issue).
     * @param string $class_name Calling class. (used to store which prepared statement goes to which class)
     * @param type $sql SQL Statement
     * @param type $params Parameters to be bound
     * @param string $class_name Calling class. (used to store which prepared statement goes to which class)
     * @return boolean Success
     */
    public function startLinearFetch($class_name, $sql, $params=array()){
        if($this->prepared[$class_name] != NULL)
            $this->endLinearFetch($class_name);
        if(empty($sql) || !$this->pdo)
            return false;
        $this->prepared[$class_name] = $this->pdo->prepare($sql);
        foreach($params as $key => &$val){
            $val == NULL ? $this->prepared[$class_name]->bindParam($key, $val, PDO::PARAM_NULL) : $this->prepared[$class_name]->bindParam($key, $val);
        }
        return $this->prepared[$class_name]->execute() == 0 ? false : true;
    }
    
    /**
     * End the process of making a linear fetch.
     * @param string $class_name Calling class. (used to store which prepared statement goes to which class)
     */
    public function endLinearFetch($class_name){
        unset($this->prepared[$class_name]);
    }
    
    /**
     * Check if this database connector has a linear
     * fetch process started.
     * @param string $class_name Calling class. (used to store which prepared statement goes to which class)
     * @return boolean
     */
    public function getIsLinearFetchStarted($class_name){
        return isset($this->prepared[$class_name]) && $this->prepared[$class_name] != NULL; // making sure that class has a prepared statement.
    }
    
    /**
     * Get the next row in the linear fetch.
     * @param string $class_name Calling class. (used to store which prepared statement goes to which class)
     * @return array row
     */
    public function getNextRow($class_name){
        if($this->prepared[$class_name] == NULL)
            return NULL;
        return $this->prepared[$class_name]->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get the id from the last row inserted
     * @return string
     */
    public function getLastInsertId(){
        return $this->pdo->lastInsertId();
    }

    /**
     * Get errors from query
     * @return array Errors
     */
    public function getLastError(){
        return $this->pdo->errorInfo();
    }
}
// used to avoid max connections being reached
$dbConn = new DatabaseConnector();
?>
