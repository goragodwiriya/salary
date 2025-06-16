<?php
/**
 * @filesource Kotchasan/Database/Db.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 * @author Goragod Wiriya <admin@goragod.com>
 * @package Kotchasan
 */

namespace Kotchasan\Database;

use Kotchasan\Database;

/**
 * Database base class
 *
 * Provides the base functionality for database operations.
 *
 * @see https://www.kotchasan.com/
 */
abstract class Db extends \Kotchasan\KBase
{
    /**
     * Database connection.
     *
     * @var \Kotchasan\Database\Driver
     */
    protected $db;

    /**
     * Class constructor.
     *
     * @param string $conn The connection name. If not specified, no database connection will be made.
     */
    public function __construct($conn)
    {
        $this->db = Database::create($conn);

        // Set database type context for SQL generation
        if ($this->db instanceof PdoMysqlDriver) {
            Sql::setDatabaseType('mysql');
        } elseif ($this->db instanceof PdoMssqlDriver) {
            Sql::setDatabaseType('mssql');
        } elseif ($this->db instanceof PdoPostgresqlDriver) {
            Sql::setDatabaseType('postgresql');
        }
    }

    /**
     * Get the database connection.
     *
     * @return \Kotchasan\Database\Driver The database connection.
     */
    public function db()
    {
        return $this->db;
    }

    /**
     * Begin database transaction
     *
     * @return bool True on success, false on failure
     */
    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit database transaction
     *
     * @return bool True on success, false on failure
     */
    public function commit()
    {
        return $this->db->commit();
    }

    /**
     * Rollback database transaction
     *
     * @return bool True on success, false on failure
     */
    public function rollback()
    {
        return $this->db->rollback();
    }

    /**
     * Check if currently in transaction
     *
     * @return bool True if in transaction, false otherwise
     */
    public function inTransaction()
    {
        return $this->db->inTransaction();
    }

    /**
     * Get the value of a database setting.
     *
     * @param string $key The setting key.
     *
     * @return mixed The value of the setting.
     */
    public function getSetting($key)
    {
        if (isset($this->db->settings->$key)) {
            return $this->db->settings->$key;
        }
        return null;
    }

    /**
     * Get all database settings.
     *
     * @return object The database settings object.
     */
    public function getAllSettings()
    {
        return $this->db->settings;
    }

    /**
     * Get database type
     *
     * @return string Database type (mysql, mssql, postgresql)
     */
    public function getDatabaseType()
    {
        if ($this->db instanceof PdoMysqlDriver) {
            return 'mysql';
        } elseif ($this->db instanceof PdoMssqlDriver) {
            return 'mssql';
        } elseif ($this->db instanceof PdoPostgresqlDriver) {
            return 'postgresql';
        }
        return 'unknown';
    }

    /**
     * Quote an identifier (table name, column name) for PostgreSQL.
     *
     * @param string $name The identifier.
     * @return string The quoted identifier.
     */
    public function quoteIdentifier($name)
    {
        // MySQL uses backticks, PostgreSQL uses double quotes, SQL Server uses square brackets
        switch ($this->getDatabaseType()) {
            case 'mysql':
                return '`'.$name.'`';
            case 'sqlsrv':
            case 'mssql':
                return '['.$name.']';
            case 'pgsql':
            case 'postgresql':
                return '"'.$name.'"';
            default:
                // Default to standard SQL
                return '"'.$name.'"';
        }
    }

    /**
     * Get database error message
     *
     * @return string Error message
     */
    public function getError()
    {
        return $this->db->getError();
    }

    /**
     * Get query count
     *
     * @return int Number of executed queries
     */
    public function getQueryCount()
    {
        return $this->db->queryCount();
    }

    /**
     * Check if database connection is active
     *
     * @return bool True if connected, false otherwise
     */
    public function isConnected()
    {
        return $this->db->connection() !== null;
    }

    /**
     * Close database connection
     */
    public function close()
    {
        $this->db->close();
    }
}
