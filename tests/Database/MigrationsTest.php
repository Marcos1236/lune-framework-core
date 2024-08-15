<?php

namespace Lune\Tests\Database;

use Lune\Database\Drivers\DatabaseDriver;
use Lune\Database\Drivers\PdoDriver;
use Lune\Database\Migrations\Migrator;
use PDOException;
use PHPUnit\Framework\TestCase;

class MigrationsTest extends TestCase
{
    use RefreshDatabase {
        setUp as protected dbSetUp;
        tearDown as protected dbTearDown;
    }

    protected ?DatabaseDriver $driver = null;
    protected $templatesDirectory = __DIR__ . "/templates";
    protected $migrationsDirectory = __DIR__ . "/migrations";
    protected static $expectedMigrations = __DIR__ . "/expected";
    protected Migrator $migrator;

    protected function setUp(): void
    {
        if (!file_exists($this->migrationsDirectory)) {
            mkdir($this->migrationsDirectory);
        }

        $this->dbSetUp();

        $this->migrator = new Migrator(
            $this->migrationsDirectory,
            $this->templatesDirectory,
            $this->driver,
            false,
        );
    }

    protected function tearDown(): void
    {
        shell_exec("rm -r $this->migrationsDirectory");
        $this->dbTearDown();
    }

    public static function migrationNames()
    {
        return [
            [
                "create_products_table",
                self::$expectedMigrations . "/create_products_table.php",
            ],
            [
                "add_category_to_products_table",
                self::$expectedMigrations . "/add_category_to_products_table.php",
            ],
            [
                "remove_name_from_products_table",
                self::$expectedMigrations . "/remove_name_from_products_table.php",
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('migrationNames')]
    public function test_creates_migration_files($name, $expectedMigrationFile)
    {
        $expectedName = sprintf("%s_%06d_%s.php", date('Y_m_d'), 0, $name);
        $this->migrator->make($name);

        $file = "$this->migrationsDirectory/$expectedName";

        $this->assertFileExists($file);
        $this->assertFileEquals($expectedMigrationFile, $file);
    }

    #[\PHPUnit\Framework\Attributes\Depends('test_creates_migration_files')]
    public function test_migrate_files()
    {
        $tables = ["users", "products", "sellers"];
        $migrated = [];

        foreach ($tables as $table) {
            $migrated[] = $this->migrator->make("create_{$table}_table");
        }

        $this->migrator->migrate();

        $rows = $this->driver->statement("SELECT * FROM migrations");

        $this->assertEquals(3, count($rows));
        $this->assertEquals($migrated, array_column($rows, "name"));

        foreach ($tables as $table) {
            try {
                $this->driver->statement("SELECT * FROM $table");
            } catch (PDOException $e) {
                $this->fail("Failed accessing migrated table $table: {$e->getMessage()}");
            }
        }
    }

    #[\PHPUnit\Framework\Attributes\Depends('test_creates_migration_files')]
    public function test_rollback_files()
    {
        $tables = ["users", "products", "sellers", "providers", "referals"];
        $migrated = [];
        foreach ($tables as $table) {
            $migrated[] = $this->migrator->make("create_{$table}_table");
        }

        $this->migrator->migrate();

        $this->migrator->rollback(1);
        $rows = $this->driver->statement("SELECT * FROM migrations");
        $this->assertEquals(4, count($rows));
        $this->assertEquals(array_slice($migrated, 0, 4), array_column($rows, "name"));

        try {
            $table = $table[count($tables) - 1];
            $this->driver->statement("SELECT * FROM $table");
            $this->fail("Table $table was not deleted after rolling back");
        } catch (PDOException $e) {
            //
        }

        $this->migrator->rollback(2);
        $rows = $this->driver->statement("SELECT * FROM migrations");
        $this->assertEquals(2, count($rows));
        $this->assertEquals(array_slice($migrated, 0, 2), array_column($rows, "name"));

        foreach (array_slice($tables, 2, 2) as $table) {
            try {
                $this->driver->statement("SELECT * FROM $table");
                $this->fail("Table '$table' was not deleted after rolling back");
            } catch (PDOException $e) {
                //
            }
        }

        $this->migrator->rollback();
        $rows = $this->driver->statement("SELECT * FROM migrations");
        $this->assertEquals(0, count($rows));
    }
}
