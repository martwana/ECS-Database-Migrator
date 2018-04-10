<?php

namespace ECS\Tests\DatabaseMigrator;

use ECS\DatabaseMigrator\Migrator;

use PHPUnit\Framework\TestCase;

class MigratorTest extends TestCase
{

    public function testScriptWillThrowExceptionIfWrongAmountOfArgumentsArePassed()
    {   

        $_SERVER['argc'] = 1;

        $this->expectException(\InvalidArgumentException::class);

        $migrator = new Migrator();

        $migrator->collectArguments();

    }



}