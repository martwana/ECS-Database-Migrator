<?php

require(dirname(__FILE__) . '/../vendor/autoload.php');

date_default_timezone_set('UTC');

$migrator = new \ECS\DatabaseMigrator\Migrator();

try {
    
    $returnCode = $migrator->init();

    if ($returnCode) {
        exit(0);
    }

    echo "There was an unknown error migrating the database" . PHP_EOL;
    exit(1);

} catch (\Exception $e) {

	echo $e->getMessage() . PHP_EOL;
    exit($e->getCode());
}