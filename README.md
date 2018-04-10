# ECS Database Migrator

A PHP powered database migrator that can be automatically triggered.


The database migrator depends on a table within your database named `versionTable` with a single row, with a single column named `version`. The migrator will execute migrations from the given directory with a version number higher than the current saved version number. When migrating is completed, the saved version number is updated.


To prevent schema damage or data loss, the database migrator will run all of your migrations inside a single MySQL transaction and only commits the changes if ALL migrations are executed. 


In the event of ANY error with ANY migration statement, all previously run migrations will rollback.


## Installation
Clone this repository to your local environment:
`git clone git@github.com:martwana/ECS-Database-Migrator.git`

## Install dependencies
```sh
$ bin/composer.phar install
```
Note: There isnt actually any dependencies for this tool, but you do need to generate the autoload files.

## Running the migrator
The migrator requires **5** arguments to be passed to it. 

```sh
$ php bin/index.php [MIGRATIONS_DIR] [DATABASE_USERNAME] [DATABASE_HOST] [DATABASE_NAME] [DATABASE_PASSWORD]
```

##### Arguments, explained

`MIGRATIONS_DIR` - Full path to the directory containing the migration SQL files.

`DATABASE_USERNAME` - The username to use when authentication with the database

`DATABASE_HOST` - The hostname of the MySQL instance to apply the migrations to

`DATABASE_NAME` - The name of the database on the MySQL instance

`DATABASE_PASSWORD` - The password used to authenticate with the database

The order of the arguments can be customised at any point by modifying the `ARGUMENT_NAMES` array in `src/ECS/DatabaseMigrator/Migrator.php`
