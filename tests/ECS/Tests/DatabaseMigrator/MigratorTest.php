<?php

namespace ECS\Tests\DatabaseMigrator;

use ECS\DatabaseMigrator\Migrator;

use org\bovigo\vfs\vfsStream; // to mock the filesystem calls
use org\bovigo\vfs\vfsStreamDirectory;

use PHPUnit\Framework\TestCase;

class MigratorTest extends TestCase
{   

    private $db;

    private $versionTable = "versionTable";
    private $versionTableColumn = "version";

    private $directory;

    public function setUp()
    {
        $this->db = $this->prophesize(\PDO::class);
        $this->statement = $this->prophesize(\PDOStatement::class);
    }

    public function testDatabaseVersionIsReturned()
    {   

        $this->statement->execute()->willReturn();
        $this->statement->fetch()->willReturn([
            "version" => 1
        ]);

        $this->statement->closeCursor()->willReturn();

        $query = "SELECT `{$this->versionTableColumn}` FROM `{$this->versionTable}` ORDER BY `{$this->versionTableColumn}` DESC LIMIT 1";

        $this->db->query($query)->willReturn($this->statement->reveal());

        $migrator = new Migrator($this->db->reveal(), null); // file list isnt required for this test
        
        $this->assertEquals($migrator->getDatabaseVersion(), 1);

    }

    public function testDatabaseVersionIsReturnedEvenIfTheDatabaseHasNoRecord()
    {   

        $this->statement->execute()->willReturn();
        $this->statement->fetch()->willReturn(null);

        $this->statement->closeCursor()->willReturn();

        $query = "SELECT `{$this->versionTableColumn}` FROM `{$this->versionTable}` ORDER BY `{$this->versionTableColumn}` DESC LIMIT 1";

        $this->db->query($query)->willReturn($this->statement->reveal());

        $migrator = new Migrator($this->db->reveal(), null); // file list isnt required for this test
        
        $this->assertEquals($migrator->getDatabaseVersion(), 0);

    }

    public function testExceptionIsThrownOnFailureToRunQuery()
    {   

        $this->statement->execute()->willReturn(false);

        $query = "SELECT `{$this->versionTableColumn}` FROM `{$this->versionTable}` ORDER BY `{$this->versionTableColumn}` DESC LIMIT 1";

        $this->db->query($query)->willReturn($this->statement->reveal());

        $migrator = new Migrator($this->db->reveal(), null); // file list isnt required for this test

        $this->expectException(\Exception::class);

        $migrator->getDatabaseVersion();

    }

    public function testMigrationsWithAVersionHigherThanZeroAreReturned()
    {

        $fileNames = [
            "001.migration.sql",
            "002.migration.sql",
            "003.migration.sql"
        ];

        $expectedMigrations = [
            1 => "001.migration.sql",
            2 => "002.migration.sql",
            3 => "003.migration.sql"
        ];

        $migrator = new Migrator($this->db->reveal(), $fileNames);

        $validMigrations = $migrator->getMigrationsForVersion(0);

        $this->assertEquals($validMigrations, $expectedMigrations);

    }

    public function testMigrationsWithAVersionHigherThanOneAreReturned()
    {
        $fileNames = [
            "001.migration.sql",
            "002.migration.sql",
            "003.migration.sql"
        ];

        $expectedMigrations = [
            2 => "002.migration.sql",
            3 => "003.migration.sql"
        ];

        $migrator = new Migrator($this->db->reveal(), $fileNames);

        $validMigrations = $migrator->getMigrationsForVersion(1);

        $this->assertEquals($validMigrations, $expectedMigrations);

    }

    public function testExceptionIsThrownIfVersionNumberIsNotFoundInFilename()
    {
        $fileNames = [
            "one.migration.sql",
            "002.migration.sql",
            "003.migration.sql"
        ];

        $migrator = new Migrator($this->db->reveal(), $fileNames);
        
        $this->expectException(\Exception::class);

        $migrator->getMigrationsForVersion(1);

    }

    public function testExceptionIsThrownIfVThereIsDuplicateVersionNumbers()
    {
        $fileNames = [
            "1.migration.sql",
            "01.migration.sql",
            "003.migration.sql"
        ];

        $migrator = new Migrator($this->db->reveal(), $fileNames);
        
        $this->expectException(\Exception::class);

        $migrator->getMigrationsForVersion(0);
    }

    public function testCanPullTheNumbersFromTheFileName()
    {

        $filename = './migrations/001.createTable.sql';

        $migrator = new Migrator($this->db->reveal(), null);

        $versionNumber = $migrator->getVersionNumberFromFileName($filename);

        $this->assertEquals($versionNumber, 1);

    }

    public function testCanPullTheNumbersFromTheFileNameWithoutDots()
    {

        $filename = './migrations/002createTable.sql';

        $migrator = new Migrator($this->db->reveal(), null);

        $versionNumber = $migrator->getVersionNumberFromFileName($filename);

        $this->assertEquals($versionNumber, 2);

    }

    public function testCanPullTheNumbersFromTheFileNameWithoutLeadingZeros()
    {

        $filename = './migrations/3createTable.sql';

        $migrator = new Migrator($this->db->reveal(), null);

        $versionNumber = $migrator->getVersionNumberFromFileName($filename);

        $this->assertEquals($versionNumber, 3);

    }

    public function testTheNumbersCanBeSplitApartAndItReturnsTheConcatenatedNumbers()
    {

        $filename = './migrations/1.createTable2.3.sql';

        $migrator = new Migrator($this->db->reveal(), null);

        $versionNumber = $migrator->getVersionNumberFromFileName($filename);

        $this->assertEquals($versionNumber, 123);

    }

    public function testReturnsFalseIfItCantFindAnumberInTheFilename()
    {

        $filename = './migrations/four.createTable.sql';

        $migrator = new Migrator($this->db->reveal(), null);

        $versionNumber = $migrator->getVersionNumberFromFileName($filename);

        $this->assertFalse($versionNumber);

    }

    public function testMigrationsCanBeExecuted()
    {
        $files = [
            "001.migration.sql" => "SELECT * FROM versionTable;"
        ];
        
        $this->directory = vfsStream::setup('/', 0777, $files);

        $filenames = [
            $this->directory->url('/') . "001.migration.sql"
        ];

        $this->statement->execute()->willReturn(true);
        $this->statement->closeCursor()->willReturn();

        $this->db->query("SELECT * FROM versionTable;")->willReturn($this->statement->reveal());

        $migrator = new Migrator($this->db->reveal(), null);

        $result = $migrator->executeMigrations($filenames);

        $this->assertTrue($result);

    }

    public function testThrowsExceptionIfTheQueryCannotBePrepared()
    {
        $files = [
            "001.migration.sql" => "SELECT * FROM versionTable;"
        ];
        
        $this->directory = vfsStream::setup('/', 0777, $files);

        $filenames = [
            $this->directory->url('/') . "001.migration.sql"
        ];

        $this->db->query("SELECT * FROM versionTable;")->willReturn(false);

        $migrator = new Migrator($this->db->reveal(), null);
        
        $this->expectException(\Exception::class);

        $migrator->executeMigrations($filenames);

    }

    public function testThrowsExceptionIfTheQueryCannotBeExecuted()
    {
        $files = [
            "001.migration.sql" => "SELECT * FROM versionTable;"
        ];
        
        $this->directory = vfsStream::setup('/', 0777, $files);

        $filenames = [
            $this->directory->url('/') . "001.migration.sql"
        ];

        $this->statement->execute()->willReturn(false);
        $this->statement->closeCursor()->willReturn();

        $this->db->query("SELECT * FROM versionTable;")->willReturn($this->statement->reveal());
        $this->db->errorInfo()->willReturn('Error Message');

        $migrator = new Migrator($this->db->reveal(), null);

        $this->expectException(\Exception::class);

        $migrator->executeMigrations($filenames);

    }

    public function testUpdateQueryExecutes()
    {       
        $currentDatabaseVersion = 1;
        $newMigrationVersion = 5;

        $query = "UPDATE {$this->versionTable} SET {$this->versionTableColumn} = {$newMigrationVersion} WHERE {$this->versionTableColumn} = {$currentDatabaseVersion}";

        $this->statement->execute()->willReturn(true);

        $this->db->query($query)->willReturn($this->statement->reveal());

        $migrator = new Migrator($this->db->reveal(), null);

        $result = $migrator->updateDatabaseVersion($newMigrationVersion, $currentDatabaseVersion);

        $this->assertTrue($result);

    }

    public function testUpdateQueryThrowsExceptionIfStatementCantBePrepared()
    {       
        $currentDatabaseVersion = 1;
        $newMigrationVersion = 5;

        $query = "UPDATE {$this->versionTable} SET {$this->versionTableColumn} = {$newMigrationVersion} WHERE {$this->versionTableColumn} = {$currentDatabaseVersion}";

        $this->db->query($query)->willReturn(false);

        $migrator = new Migrator($this->db->reveal(), null);

        $this->expectException(\Exception::class);

        $migrator->updateDatabaseVersion($newMigrationVersion, $currentDatabaseVersion);

    }

    public function testUpdateQueryThrowsExceptionIfStatementCantBeExecuted()
    {       
        $currentDatabaseVersion = 1;
        $newMigrationVersion = 5;

        $query = "UPDATE {$this->versionTable} SET {$this->versionTableColumn} = {$newMigrationVersion} WHERE {$this->versionTableColumn} = {$currentDatabaseVersion}";

        $this->statement->execute()->willReturn(false);
        $this->db->query($query)->willReturn($this->statement->reveal());
        
        $migrator = new Migrator($this->db->reveal(), null);

        $this->expectException(\Exception::class);

        $migrator->updateDatabaseVersion($newMigrationVersion, $currentDatabaseVersion);

    }

}