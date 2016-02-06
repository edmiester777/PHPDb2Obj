# PHPDb2Obj
===========
PHP library that helps with secure MySQL database connections and queries by encapsulating a table into an object.

###### Examples
Say you have a database with the name "test_table", and it has the following columns

Column Name | Type | Properties
----------: | ---- | ----------
id | BIGINT(11) | UNSIGNED PRIMARY KEY AUTO_INCREMENT
username | VARCHAR(35) |
email | VARCHAR(50) |
age | TINYINT(1) | UNSIGNED
description | TEXT |
relation_id | BIGINT(11) | UNSIGNED

**Setup**
We have to create a class to encapsulate this table. We can import
DatabaseTable.class.php to start the setup process.

```php
require_once dirname(__FILE__).'/DatabaseTable.class.php';
class TestTable extends DatabaseTable{
    public function __construct(){
        // we must first call the parent constructor to setup data.
        parent::__construct();

        // now we must set the name of the table in the database.
        $this->setTableName("test_table");

        // here is where we begin our setup of our columns.
        // addColumnElement function can be called in 4 different ways,
        //    1) addColumnElement($column_name);
        //       adds the column with default (0) properties. it will be treated
        //       with no special systems.
        //       $column_name (string) - name of column in database.
        //    2) addColumnElement($column_name, $properties);
        //       $column_name does the same from function 1.
        //       $properties is a byte value which can be set up with registered
        //       bits turned on in DatabaseColumnElement class. 0 if none.
        //       Example:
        //       DatabaseColumnElement::UNIQUE_ID | DatabaseColumnElement::EXCLUDE_UPDATE
        //       These properties register the column as the unique id of the table,
        //       and make sure it cannot be updated.
        //       A list of these properties can be found in DatabaseColumnElement.class.php
        //    3) addColumnElement($column_name, $properties, $relation);
        //       $column_name does the same as in 1 and 2
        //       $properties does the same as in 2
        //       $relation (string) is the class name of another child of
        //       DatabaseTable that is related to this value.
        //       Note: This relation will load from the unique id of the relational
        //             class. Use method 4 if you do not wish to load using the
        //             unique id.
        //    4) addColumnElement($column_name, $properties, $relation, $relation_column);
        //       $relation class does not have a unique id column that matches
        //       the current column name.
        //       $column_name - same as in 1, 2, and 3
        //       $properties - same as in 2 and 3
        //       $relation - same as in 3
        //       $relation_column (string) - name of the column that we will load
        //       the row of the relational class.

        // adding unique id
        $this->addColumnElement(
          "id",
          DatabaseColumnElement::UNIQUE_ID      |
          DatabaseColumnElement::EXCLUDE_UPDATE |
          DatabaseColumnElement::EXCLUDE_SET
        );

        // other columns
        $this->addColumnElement("username");
        $this->addColumnElement("email");
        $this->addColumnElement("age");
        $this->addColumnElement("relation_id", 0, "TestTable", "id"); // adding a relation to another row in database
    }
}
```
============================
**Loading Columns:**
The first example is loading with a unique id.
```php
$test = TestTable::FromUniqueId(4);
```
Next we will load from a custom column
```php
$test = TestTable::FromColumn("username", "edmiester777");
```
Now we can simply load all rows into a TestTable array.
```php
$tests = TestTable::LoadAllRows();
```
Or we can load our database row by row in order to conserve resources.
```php
TestTable::StartLoadAllRowsLinear();
while(($test = TestTable::GetNextRow()) != NULL){
    work...
}
TestTable::EndLinearQuery();
```
Custom queries can be performed with the Where function
```php
$test = TestTable::Where(
    "username = :username AND email = :email",
    array(
        ":username" => "edmiester777",
        ":email"    => "example@email.com"
    )
);
// returns an array of TestTables where username = "edmiester777" and email = "example@email.com"
```

You can also access the count of rows very simply.
```php
$count = TestTable::Count();
```
==============================
**Accessing Data**
Getting data from a loaded row can be done in several ways

```php
$test = TestTable::FromUniqueId(4);
// ways to get data
$test->getColumnValue("username");
$test->getUsername(); // method is parsed with regular expressions and returns the matching column to method name, therefore you can use case insensitive methods.
$test->username;
```
**Mutating Data** Can be done in several ways.
```php
$test = new TestTable();
$test->setColumnValue("username", "edmiester777);
$test->setUsername("edmiester777");
$test->username = "edmiester777";
```

**Database Functions**
```php
// delete row from database
$test->delete();
// insert row to database (unique id will update after insert)
$test->insert();
// update all qualified columns
$test->update();
// update single column
$test->updateSingleColumn("username");
```

**Relations**
Relations occur when the value of the column represent the value of a different row in the database. This row could be in the same table, or another one. We can access these variables using a bit of magic.
```php
$test = TestTable::FromUniqueId(4);
$relation = $test->getColumnRelation("relation_id");
// $relation = TestTable if row exists, NULL if not.
echo "Relational username = {$relation->username}";
```

Now you're off, go and test out PHPDb2Obj.
