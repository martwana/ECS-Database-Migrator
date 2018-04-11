<?php

require(dirname(__FILE__) . '/../vendor/autoload.php');

date_default_timezone_set('UTC');

const NUM_ARGUMENTS_REQUIRED = 5; // This is the required number of arguments for the script to execute

$argumentNames = [ // The list of arguments, in the order that they should be passed into the script.
    "migrationsDirectory",
    "databaseUsername",
    "databaseHost",
    "databaseName",
    "databasePassword"
];

$arguments = [];

try {   

    // Validate argumments

    if ($_SERVER['argc'] < (NUM_ARGUMENTS_REQUIRED + 1)) { // + 1 used because the script name is always the first argument
        throw new \InvalidArgumentException("Wrong number of arguments passed", 1);
    }

    foreach ($argumentNames as $position => $argumentName) {
        $position++; // remember the first argument is the script name, so adjust the key by 1
        $arguments[$argumentName] = $_SERVER['argv'][$position];
    }

    // Setup database connection

    $dsn = "mysql:host=" . $arguments["databaseHost"] . ";" . "dbname=" . $arguments["databaseName"] . ";";
    $db = new \PDO($dsn, $arguments["databaseUsername"], $arguments["databasePassword"]);

    // Get a list of the sql files

    $migrationsDirectory = rtrim($arguments['migrationsDirectory'], '/') . '/';
    $migrationsDirectory = $migrationsDirectory . "*.sql"; // we're only interested in .sql files in the migrationsDirectory
    $files = glob($migrationsDirectory); // get a list of the filenames
   
    $migrator = new \ECS\DatabaseMigrator\Migrator($db, $files);

    $returnCode = $migrator->migrate();

    if ($returnCode) {
        exit(0);
    }

    echo "There was an unknown error migrating the database" . PHP_EOL;
    exit(1);

} catch (\Exception $e) {

	echo $e->getMessage() . PHP_EOL;
    exit($e->getCode());
}