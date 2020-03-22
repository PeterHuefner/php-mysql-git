# php-mysql-git
Stores SQL structure in PHP arrays that can be added to git, allowing you to configure the MySQL DB according to the stored structure.

## How it works
1. Your current MySQL/mariaDB Database (Tables, Columns and Data you choose) is stored in arrays in PHP-Files in a directory.
2. These files can be put under version control and checked out on another machine.
3. On that other machine, the running database is compared with the PHP-Files (with the stored Tables, Columns and your chosen Data of step 1.). The comparison results in SQL-Statements that change the database equal to the stored structure.

Unlike migrations php-mysql-git is doing a real comparison of database and stored structure and generates the SQL-Statements exclusive. 