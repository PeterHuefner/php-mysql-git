# php-mysql-git
Stores SQL structure in PHP arrays that can be added to git, allowing you to configure the MySQL DB according to the stored structure.

# searching for beta testers

php-mysql-git is currently in beta status.
If you want to support beta testing install and use the tool in a dev environement and report any bugs with the issue tracker.
While in beta you have to install the tool via

    composer require peterhufner/php-mysql-git "<1.0-beta"

## How it works
1. Your current MySQL/mariaDB Database (Tables, Columns and Data you choose) is stored in arrays in PHP-Files in a directory.
2. These files can be put under version control and checked out on another machine.
3. On that other machine, the running database is compared with the PHP-Files (with the stored Tables, Columns and your chosen Data of step 1.). The comparison results in SQL-Statements that change the database equal to the stored structure.

Unlike migrations php-mysql-git is doing a real comparison of database and stored structure and generates the SQL-Statements exclusive.

## Installation

via composer **(but currently not possible due to beta phase)**
    
    composer require peterhufner/php-mysql-git
    
or clone this repository.

 ## Usage
 
 If you haven't installed via composer you can require an extra autoloader for that purposes through ```require_once 'src/PhpMySqlGit/autoload.php';```.
 
 ### Basic Example
 ```php
 require_once 'PATH/TO/COMPOSER/vendor/autoload.php';

$phpMySqlGit = new PhpMySqlGit\PhpMySqlGit([
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
Although you could pass statements directly to an PDO-Instance, you should not do that.
It is always better to review statements before execution and you should not give your webserver-user the rights to change the database structure.
 
 ### Example with options
 
 In this example are nearly all possible options shown.
 
 ```php
require_once 'PATH/TO/COMPOSER/vendor/autoload.php';

$phpMySqlGit = new PhpMySqlGit\PhpMySqlGit([
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
$phpMySqlGit->setOverwriteCharset(true);
// utf8mb4 is the default, so there is no need to specify it again - just here to demonstrate
$phpMySqlGit->setCharset('utf8mb4');
$phpMySqlGit->setCollation('utf8mb4_unicode_ci');

// these defaults are also available for engine and row format
$phpMySqlGit->setRowFormat('DYNAMIC'); // DYNAMIC is the default
$phpMySqlGit->setEngine('InnoDB');     // InnoDB is the default
$phpMySqlGit->setOverwriteRowFormat(true); //false is default
$phpMySqlGit->setOverwriteEngine(true); // false is default

// you also can omit charset, engine and row format - so ignore the checking completly
$phpMySqlGit->setIgnoreCharset(true);
$phpMySqlGit->setIgnoreEngine(true);
$phpMySqlGit->setIgnoreRowFormat(true);

// when generating statements to change database, foreign keys are dropped before and created afterwards, to ensure the databse structure can be changed.
// defaults to false
// you should disable it only when you have a reason (for example a bug in php-mysql-git)
$phpMySqlGit->setSkipForeignKeyChecks(true or false);

// when using with data you can disable foreign key checks, but be careful it can damage the database when data is not consistent
// this leads to the generation of the statement: SET FOREIGN_KEY_CHECKS = 0; so this is done in the database server
$phpMySqlGit->setForeignKeyChecksForData(false);

// mod to create files with
$phpMySqlGit->setFileMod('0664'); //default

// mod to create directories with
$phpMySqlGit->setDirMod('0775'); //default

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

// save data but exclude columns
// if you call saveData with an array as third argument, you can exclude columns
// an element in that array with an integer as key is an exclude for all tables
// an element with tablename as key and value as column or an array of columns exclude only for that table
$phpMySqlGit->saveData(
    $structureDirectory,
    [], // meaning all tables
    [
        'uid',                                        // exclude the column uid for all tables
        'TABLE_NAME' => ['lastUpdate', 'createDate'], // exclude two columns for specific table
        'ANOTHER_TABLE' => 'status',                  // exclude one column for specific table
    ]
);

// ouput all statements that are necessary to change the database according to stored structure
echo(implode("\n\n", $phpMySqlGit->configureDatabase($structureDirectory)));

// ouput the insert statements to the stored data
echo(implode("\n\n", $phpMySqlGit->configureData($structureDirectory)));
```

### Construct with a PDO Instance

Instead of call the PhpMySqlGit-Class with an array, you can pass an instance of PDO-Class directly. 

```php
$phpMySqlGit = new PhpMySqlGit\PhpMySqlGit(new PDO("mysql:host=DATABASE-HOST;port=DATABASE-PORT;", "DATABASE-USER"));
```
 
 With this technique you can specify some extra settings.

### storing multiple databases

Generally you could repeat the process of storing one database multiple times. So storing database 'shop1' to directoy 'shop1' and so on.<br>
With the setter `$phpMySqlGit->setSaveNoDbName(false)` you can store multiple databases in one directory. In which PhpMySqlGit will handle a sub-directory structure for each database.
You have to repeat the save and configuration process for each database, the only advantage is that you do not have to worry about the directory structure.<br>
In this case the database names saved in structure and in database-server must be equal. With `$phpMySqlGit->setDbname("DATABASE-NAME");` you just pick a database from the 'pool' in the directory.

When you want to store multiple databases and database-names vary on the used servers, you have to use a own seperate directory for each database.

### Ensure that your defaults are applied

You can specify defaults for charset, collation, engine and row format with the setter for each of them.
You have to enable the overwriting of these settings with the `setOverwrite`...-Function.
When the default setting and overwriting is enabled, it will be used when saving the structure to a file and when configuring the database from a stored structure.
This means that, with overwritng enabled, the setting stored to file is not used from your database, but from the default setting. And also when configuring the database from a stored structure.

Using the default setting and overwriting you can ensure, that every configuration process will result in a checking and correction of the specified setting.

#### Example
```php
$phpMySqlGit = new PhpMySqlGit\PhpMySqlGit(...);
$phpMySqlGit->setCharset('my_everywhere_wanted_charset');
$phpMySqlGit->setCollation('my_everywhere_wanted_collation');
$phpMySqlGit->setOverwriteCharset(true);

$phpMySqlGit->saveStructure(...); // will store your defined charset and collation, but not that from your used database

$phpMySqlGit->configureDatabase(...); // will check and change database, tables, columns to your defined charset, regardless which charset you have stored in structure
```

Be careful with the defaults, it may change columns and tables you actually dont want to change. 

#### CLI Example
A simple Class "CommandLineInterface" is shipped with the package. The Class collects CLI-Args and passes them to main PhpMySqlGit-Class.
Every CLI-Arg starting with `set` and is a method of PhpMySqlGit is called with the provied value.
In your php-File you can create and prepare a PhpMySqlGit-Instance with some defaults, so you do not have to call every Setter each time.
The CommandLineInterface-Class has special interpretation of the array-keys of the first arg of the PhpMySqlGit-Constructor. So you can pass --connectionString, --username, --password as CLI-Args. 

All you have to do, is to create a php-File which is calling the CommanLineInterface-Class.
For example a cli.php:
```php
<?php

// only allow access through command line
if (php_sapi_name() !== "cli") {
	exit();
}
require_once 'PATH/TO/vendor/autoload.php';

// optionally you can provide a prepared and connected PhpMySqlGit-Instance in the Constructor
$cli = new \PhpMySqlGit\CommandLineInterface();

// optional path to structure, when provided it will overwrite any delcaration in the CLI-Call
$cli->setDataDir('PATH/TO/STRUCTUREDIR');

$cli->execute();
```

Then you can call the cli.php as follows:
```shell script
# data dir defined in cli.php
php cli.php --connectionString="mysql:host=127.0.0.1;port=3306;" --username=demouser --setOverwriteCharset=true --setDbName=sakila --saveStructure

# data dir defined in cli.php, but call extra args on saveData
php cli.php --connectionString="mysql:host=127.0.0.1;port=3306;" --username=demouser --setOverwriteCharset=true --setDbName=sakila --saveStructure --saveData='[null,["film", "film_actor"]]'

# provide path to strcuture dir inline
php cli.php --connectionString="mysql:host=127.0.0.1;port=3306;" --username=demouser --setOverwriteCharset=true --setDbName=sakila --saveStructure --saveData='["/PATH/TO/STRUCTUREDIR",["film", "film_actor"]]'

# use a prepared PhpMySqlGit-Instance and a default strcuture path, what means less args in CLI-Call
php cli.php --setDbName=sakila --saveStructure --saveData='[null,["film", "film_actor"]]'
```

### Configure remote/production server

When updating a production server, you may want to update the database schema before updating the running code. With this process you can minimize downtimes.
To achieve this, you could checkout the new structure on a non-production machine and connect from there via port forwarding to the production database.
Then you can change the production schema without changing the running code. The code can be updated afterwards.

## Compatibility

### Database-Server

php-mysql-git should be compatible with recent MySQL and mariaDB versions. If you encounter a non-compatibility please open an issue.

### PHP

\>= PHP 7.2

### Dependencies

Only basic PHP librarys are needed. PDO must be enabled, which should be standard. 

## Limitations

SQL-Functions, Stored-Procedures, Views, Triggers and Events are not handled at the moment.

Only Tables and Columns are stored and can be configured.