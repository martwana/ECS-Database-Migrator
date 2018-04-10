<?php

namespace ECS\DatabaseMigrator;

use \PDO;

class Migrator {

    private $NUM_ARGUMENTS_REQUIRED = 5; // This is the required number of arguments for the script to execute

    private $ARGUMENT_NAMES = [ // The list of arguments, in the order that they should be passed into the script.
        "migrationsDirectory",
        "databaseUsername",
        "databaseHost",
        "databaseName",
        "databasePassword"
    ];

    private $versionTable = "versionTable"; // The name of the table in the database that holds the current schema version
    private $versionTableColumn = "version"; // The name of the column to query for the version
    private $newMigrationVersion = 0; // This is where we will store the new latest version so we can update the table after we run the migrations

    /**
     * The entrypoint for the migrator. 
     */
    public function init() 
    {
        $this->collectArguments(); // get the arguments

        $this->initDatabaseConnection(); // init the connection to the db

        return $this->migrate(); // run the migration logic
    }


    private function migrate()
    {
        $currentDatabaseVersion = $this->getDatabaseVersion();

        $migrations = $this->getMigrationsForVersion($currentDatabaseVersion);

        if (!$migrations) {
            echo "No matching migrations to run" . PHP_EOL;
            return true;
        }

        // this is an absolute MUST. Dont commit the migrations to the database until we are sure that they can execute correctly.
        // failure to do so may leave the database in a broken state
        $this->db->beginTransaction();

        try {

        $this->executeMigrations($migrations);

        $this->updateDatabaseVersion($this->newMigrationVersion, $currentDatabaseVersion);

        } catch (\Exception $e) {
        echo "Rolling back changes" . PHP_EOL;
        $this->db->rollback(); // Roll back the transaction
        throw $e;
        }

        $this->db->commit();
        echo "Database Migrated" . PHP_EOL;

        return true; // nothing threw an exception before now, we're good to return a true status
    }

    /**
     * Collect all the CLI parameters passed to the script and place them in properties on this object
     */
        private function collectArguments()
        {
            if ($_SERVER['argc'] < ($this->NUM_ARGUMENTS_REQUIRED + 1)) { // + 1 used because the script name is always the first argument
                throw new Exception("Wrong number of arguments passed", 1);
            }

            foreach ($this->ARGUMENT_NAMES as $position => $argumentName) {
                $position++; // remember the first argument is the script name, so adjust the key by 1
                $this->$argumentName = $_SERVER['argv'][$position];
            }
        }

    /**
     * Initilise the database connection from the arguments passed to the script
     */
    private function initDatabaseConnection()
    {
        $dsn = "mysql:host=" . $this->databaseHost . ";" . "dbname=" . $this->databaseName . ";";

        try {
            $this->db = new PDO($dsn, $this->databaseUsername, $this->databasePassword);
        } catch (\Exception $e) {
            throw $e; // later, you would maybe catch specific errors and handle them differently
        }
    }

    /**
     * Check the $versionTable for the current migration version
     */
    private function getDatabaseVersion()
    {
        // build the query to check get the version. order by version descending and limiting for 1 to prevent accidental extra rows from causing any issues
        $query = "SELECT `{$this->versionTableColumn}` FROM `{$this->versionTable}` ORDER BY `{$this->versionTableColumn}` DESC LIMIT 1";

        try {

            $result = $this->db->query($query); 
            $result->execute(); // execute the query

            if (!$result) { // PDO doesnt throw execeptions when queries fail, it only returns false so do a check
                throw new \Exception("Unable to execute version check query", 2);
            }

            $row = $result->fetch(); // get the first row from the query result
            $result->closeCursor(); // required to end the current query

            $version = (int) $row["version"]; // cast to int to make checks easier

            echo "Current Database Version: " . $version . PHP_EOL;
            return $version;

        } catch (\Exception $e) {
            throw $e; // again, should expand this to handle different kind of errors
        }
    }

    /**
     * Get the migrations from the given directory that have a version number greater than the current database version
     */
    private function getMigrationsForVersion($currentDatabaseVersion)
    {
        $migrationsDirectory = rtrim($this->migrationsDirectory, '/') . '/'; // ensure that the directory will always have a trailing slash (by always removing one if its there and always adding one)

        $migrationsDirectory .= "*.sql"; // we're only interested in .sql files in the migrationsDirectory

        $files = glob($migrationsDirectory); // get a list of the filenames

        $validMigrations = []; // to store the migrations we are ok to apply

        foreach ($files as $filename) {

            $migrationVersion = $this->getVersionNumberFromFileName($filename);

            if (!$migrationVersion) { // if theres an issue getting the migration, then stop. Do not miss any migrations when updating the database
                throw new \Exception("Unable to determine version number of migration file {$filename}. Halting!", 3);
            }

            if (array_key_exists($migrationVersion, $validMigrations)) { // if theres more than one file with the same number we cannot understand the order to execute them, so stop
                throw new \Exception("A duplicate migration file version has been found. Halting!", 4); 
            }
     
            if ($migrationVersion > $currentDatabaseVersion) {
                $validMigrations[$migrationVersion] = $filename; // pop this in with the others, keyed by the version number
            }

        }

        // if theres no migrations, we can stop here
        if (empty($validMigrations)) {
            return false;
        }

        ksort($validMigrations); // sort the migrations in numerical order, so that we execute them in the right order

        $this->newMigrationVersion = max(array_keys($validMigrations)); // keep track of the highest version number thats valid so we can write it back to the $versionTable

        return $validMigrations;
    }

    /**
     * Returns the version number taken from the given filename or false if it could not be found
     */
    private function getVersionNumberFromFileName($filename)
    {
        $filename = ltrim($filename, $this->migrationsDirectory); // remove the path to the file for this check - numbers in directories would match the regex below

        $pattern = "/.*?(\d+)/"; // will grab all numbers from the filename

        $hasValidVersionNumber = preg_match($pattern, $filename, $matches);

        if ($hasValidVersionNumber !== 1) { // if the regex failed, then this filename isnt compatible
            return false;
        }

        $versionNumber = (int) $matches[0]; // cast the version to an int to make matching easier

        return $versionNumber;
    }

    private function executeMigrations($migrations) 
    {

        foreach ($migrations as $migrationFilePath) {

            try {

                echo "Executing Migration: " . $migrationFilePath . PHP_EOL;
                $sql = file_get_contents($migrationFilePath); // get the content of the migration file
                $statement = $this->db->query($sql); // run the sql

                if (!$statement) {
                    throw new \Exception("Failed to prepare migration: " . $migrationFilePath . PHP_EOL, 5);
                }

                $result = $statement->execute();
                $statement->closeCursor();

                if (!$result) {
                    print_r($this->db->errorInfo());
                    throw new \Exception("Failed to execute SQL" . $this->db->errorCode(), 6);
                }

                echo "SUCCESS Migration: " . $migrationFilePath . PHP_EOL;

            } catch (\Exception $e) {
                throw $e; // like the rest
            }

        }

        echo "Migration Execution Completed" . PHP_EOL;

    }

    private function updateDatabaseVersion($newMigrationVersion, $currentDatabaseVersion)
    {

        echo "Setting new migration version to: " . $newMigrationVersion . PHP_EOL;

        $updateQuery = "UPDATE {$this->versionTable} SET {$this->versionTableColumn} = {$newMigrationVersion} WHERE {$this->versionTableColumn} = {$currentDatabaseVersion}";

        $updateStatement = $this->db->query($updateQuery);

        if (!$updateStatement) {
            throw new \Exception("Error while creating update statement" . PHP_EOL, 5);
        }

        $result = $updateStatement->execute();

        if (!$result) {
            print_r($this->db->errorInfo());
            throw new \Exception("Failed to execute update statement" . $this->db->errorCode(), 7);
        }

        return result;

    }

}