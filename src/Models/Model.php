<?php

declare(strict_types=1);

namespace App\Models;

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Faker\Factory;
use Faker\Generator;

class Model
{
    // Stores an instance of the PDO class
    protected \PDO $pdo;

    // Stores an instance of the Faker class
    protected Generator $faker;

    /**
     * Returns the name of the table associated to the model
     *
     * @return string
     */
    protected function getTableName(): string
    {
        return '';
    }

    /**
     * Returns the primary key of the table
     *
     * @return string
     */
    protected function getTableKey(): string
    {
        return 'id';
    }

    /**
     * Returns a mock instance of the model
     *
     * @return array
     */
    protected function getFactory(): array
    {
        return [];
    }

    public function __construct()
    {
        $this->faker = Factory::create();
        $dsn = $this->generateDSNString();

        try {
            $this->pdo = new \PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASSWD'], [
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $e) {
            throw new \Exception("Unable to connect to the database:\n" . $e->getMessage());
        } finally {
            if (!$this->pdo) {
                throw new \Exception('Unable to connect to the database');
            }
        }
    }

    /**
     * Generates a fake instance of the model
     *
     * @param bool $write Indicates whether the instance must be stored on the database
     * @param array $attrs A list of attributes to override
     * @return array
     */
    public static function fake(bool $write, $attrs = []): array
    {
        $model = new static();
        $tableName = $model->getTableName();
        $factory = array_merge($model->getFactory(), $attrs);

        // Write the fake record to the database
        if ($write) {
            // Even though it is unnecessary given the nature of the data
            // we properly sanitize the values to enter into the database
            $placeholders = implode(', ', array_fill(0, count($factory), '?'));
            $columns = implode(', ', array_keys($factory));
            $values = array_values($factory);

            $pdo = $model->getPDO();
            $statement = $pdo->prepare("INSERT INTO $tableName ($columns) VALUES($placeholders)");
            $statement->execute($values);

            // NOTE: I know, this is not a good way to fetch the last inserted record
            $statement = $pdo->prepare("SELECT * FROM $tableName ORDER BY created_at DESC LIMIT 1");
            $statement->execute();

            return $statement->fetch();
        }

        return $factory;
    }

    /**
     * Deletes a record based on the value of the primary key
     *
     * @param mixed $value Value of the primary key
     * @return void
     */
    public static function delete(mixed $value): void
    {
        $model = new static();
        $primaryKey = $model->getTableKey();
        $tableName = $model->getTableName();

        $statement = $model->getPDO()->prepare("DELETE FROM $tableName WHERE $primaryKey = ?");
        $statement->execute([$value]);
    }

    /**
     * Returns the active instance of PDO
     *
     * @return \PDO
     */
    public function getPDO(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Generate the Database Source Name string to start connection to the database.
     *
     * @return string
     */
    private function generateDSNString(): string
    {
        return implode(';', [
            $_ENV['DB_DRIVER'] . ':host=' . $_ENV['DB_HOST'],
            'port=' . $_ENV['DB_PORT'],
            'dbname=' . $_ENV['DB_NAME']
        ]);
    }
}
