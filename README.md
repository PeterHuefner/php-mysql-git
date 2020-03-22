# php-mysql-git
Stores SQL structure in PHP arrays that can be added to git, allowing you to configure the MySQL DB according to the stored structure.

## How it works
1. Your current MySQL/mariaDB Database (Tables, Columns and Data you choose) is stored in arrays in PHP-Files in a directory.
2. These files can be put under version control and checked out on another machine.
3. On that other machine, the running database is compared with the PHP-Files (with the stored Tables, Columns and your chosen Data of step 1.). The comparison results in SQL-Statements that change the database equal to the stored structure.

Unlike migrations php-mysql-git is doing a real comparison of database and stored structure and generates the SQL-Statements exclusive.

## Installation

via composer
    
    composer require peterhufner/php-mysql-git
    
or clone this repository.

 ## Usage
 
 if you haven't installed via composer you can require an extra autoloader through use it via ```require_once 'src/PhpMySqlGit/autoload.php';```.
 
 ### Basic Example
 ```php
 require_once 'PATH/TO/COMPOSER/vendor/autoload.php';

$phpMySqlGit = new PhpMySqlGit([
    'connectionString' => 'mysql:host=DATABASE-HOST;port=DATABASE-PORT;dbname=DATABASE-NAME',
	'username'         => 'DATABASE-USERNAME',
	'password'         => 'USER-PASSWORD',
]);

$structureDirectory = 'PATH/TO/DIRECTORY/WHERE/STRUCTURE/SHOULD/STORED';

// save the structure of the current database to a directory
$phpMySqlGit->saveStructure($structureDirectory);

// save the data of some tables
$phpMySqlGit->saveData($structureDirectory, [
	'TABLE1',
	'ANOTHER_TABLE'
]);

// ouput all statements that are necessary to change the database according to stored structure
echo(implode("\n\n", $phpMySqlGit->configureDatabase($structureDirectory)));

// ouput the insert statements to the stored data
echo(implode("\n\n", $phpMySqlGit->configureData($structureDirectory)));

```
 
In the examples the SQL-Statements are always outputted and never executed directly.
Although you could pass statements directly to an PDO-Instance, you should'nt do that.
It is always better to review statements before execution and you should'nt give your webserver-user the rights to change the database structure.
 
 ### Example with some options
 ```php
require_once 'PATH/TO/COMPOSER/vendor/autoload.php';

$phpMySqlGit = new PhpMySqlGit([
    // specify the database name in the connection string
    //'connectionString' => 'mysql:host=DATABASE-HOST;port=DATABASE-PORT;dbname=DATABASE-NAME',
    // specify the database name later
	'connectionString' => 'mysql:host=DATABASE-HOST;port=DATABASE-PORT;',
	'username'         => 'DATABASE-USERNAME',
	'password'         => 'USER-PASSWORD',
]);

// if you haven't specified the database name in the connection string, then do it here
$phpMySqlGit->setDbname("DATABASE-NAME");

$structureDirectory = 'PATH/TO/DIRECTORY/WHERE/STRUCTURE/SHOULD/STORED';

// if you want to ensure that a charset and collation is used globally ignoring the local used
// these defaults are also availyble for engine and row format
$phpMySqlGit->setOverwriteCharset(true);
// utf8mb4 is the default, so there is no need to specify it again - just here to demonstrate
$phpMySqlGit->setCharset('utf8mb4');
$phpMySqlGit->setCollation('utf8mb4_unicode_ci');

// when using with data you can disable foreign key checks, but be careful it can damage the database when data is not consistent
$phpMySqlGit->setForeignKeyChecksForData(false);

// and now the real stuff
// save the structure of the current database to a directory
$phpMySqlGit->saveStructure($structureDirectory);

// save the data of some tables
// if you call saveData only with the path, the data of all tables is saved
// if you specify some tables, the data is stored (and later inserted) in order of the appearance in the array
// with the correct order of data, you can insert data with foreign key checks enabled
$phpMySqlGit->saveData($structureDirectory, [
	'TABLE1',
	'ANOTHER_TABLE'
]);

// ouput all statements that are necessary to change the database according to stored structure
echo(implode("\n\n", $phpMySqlGit->configureDatabase($structureDirectory)));

// ouput the insert statements to the stored data
echo(implode("\n\n", $phpMySqlGit->configureData($structureDirectory)));

```