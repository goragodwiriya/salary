<?php
/**
 * @filesource Kotchasan/Database/Schema.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 * @author Goragod Wiriya <admin@goragod.com>
 * @package Kotchasan
 */

namespace Kotchasan\Database;

/**
 * Database schema class
 *
 * This class is responsible for retrieving and managing database schema information.
 *
 * @see https://www.kotchasan.com/
 */
class Schema
{
    /**
     * Database object
     *
     * @var Driver
     */
    private $db;

    /**
     * List of loaded schemas
     *
     * @var array
     */
    private $tables = [];

    /**
     * Database type
     *
     * @var string
     */
    private $databaseType;

    /**
     * Create Schema Class
     *
     * @param Driver $db The database driver object
     *
     * @return static
     */
    public static function create(Driver $db)
    {
        $obj = new static();
        $obj->db = $db;

        // Determine database type
        if ($db instanceof PdoMysqlDriver) {
            $obj->databaseType = 'mysql';
        } elseif ($db instanceof PdoMssqlDriver) {
            $obj->databaseType = 'mssql';
        } elseif ($db instanceof PdoPostgresqlDriver) {
            $obj->databaseType = 'postgresql';
        } else {
            $obj->databaseType = 'unknown';
        }

        return $obj;
    }

    /**
     * Get the field names of a table
     *
     * Retrieve all field names in the specified table.
     *
     * @param string $table The table name
     *
     * @return array The array of field names
     *
     * @throws \InvalidArgumentException if the table name is empty
     * @throws Exception if there's an error retrieving schema information
     */
    public function fields($table)
    {
        if (empty($table)) {
            throw new \InvalidArgumentException('Table name is empty in fields');
        }

        $this->init($table);
        return array_keys($this->tables[$table]);
    }

    /**
     * Get complete column information for a table
     *
     * @param string $table The table name
     * @return array Array of column information
     *
     * @throws \InvalidArgumentException if the table name is empty
     * @throws Exception if there's an error retrieving schema information
     */
    public function getColumns($table)
    {
        if (empty($table)) {
            throw new \InvalidArgumentException('Table name is empty in getColumns');
        }

        $this->init($table);
        return $this->tables[$table];
    }

    /**
     * Get information about a specific column
     *
     * @param string $table The table name
     * @param string $column The column name
     * @return array|null Column information or null if not found
     *
     * @throws \InvalidArgumentException if the table or column name is empty
     */
    public function getColumn($table, $column)
    {
        if (empty($table)) {
            throw new \InvalidArgumentException('Table name is empty in getColumn');
        }

        if (empty($column)) {
            throw new \InvalidArgumentException('Column name is empty in getColumn');
        }

        $this->init($table);
        return isset($this->tables[$table][$column]) ? $this->tables[$table][$column] : null;
    }

    /**
     * Check if a column exists in a table
     *
     * @param string $table The table name
     * @param string $column The column name
     * @return bool True if column exists, false otherwise
     */
    public function hasColumn($table, $column)
    {
        try {
            return $this->getColumn($table, $column) !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get primary key columns for a table
     *
     * @param string $table The table name
     * @return array Array of primary key column names
     */
    public function getPrimaryKeys($table)
    {
        $this->init($table);
        $primaryKeys = [];

        foreach ($this->tables[$table] as $columnName => $columnInfo) {
            if (isset($columnInfo['Key']) && $columnInfo['Key'] === 'PRI') {
                $primaryKeys[] = $columnName;
            }
        }

        return $primaryKeys;
    }

    /**
     * Get the database type
     *
     * @return string Database type (mysql, mssql, postgresql, unknown)
     */
    public function getDatabaseType()
    {
        return $this->databaseType;
    }

    /**
     * Clear cached schema information for a table
     *
     * @param string|null $table Table name, or null to clear all
     */
    public function clearCache($table = null)
    {
        if ($table === null) {
            $this->tables = [];
        } else {
            unset($this->tables[$table]);
        }
    }

    /**
     * Get list of all cached tables
     *
     * @return array Array of table names that have been cached
     */
    public function getCachedTables()
    {
        return array_keys($this->tables);
    }

    /**
     * Initialize the schema data for a table
     *
     * @param string $table The table name
     * @throws Exception if there's an error retrieving schema information
     */
    private function init($table)
    {
        if (empty($this->tables[$table])) {
            try {
                $columns = $this->getColumnsQuery($table);

                if (empty($columns)) {
                    throw new Exception("Table '$table' not found or has no columns", 0, null, null, [], $this->databaseType);
                }

                $datas = [];
                foreach ($columns as $column) {
                    $fieldName = $this->getFieldName($column);
                    $datas[$fieldName] = $this->normalizeColumnInfo($column);
                }

                $this->tables[$table] = $datas;
            } catch (\Exception $e) {
                if ($e instanceof Exception) {
                    throw $e;
                }
                throw new Exception($this->db->getError() ?: $e->getMessage(), 0, $e, null, [], $this->databaseType);
            }
        }
    }

    /**
     * Get columns query based on database type
     *
     * @param string $table The table name
     * @return array Column information
     */
    private function getColumnsQuery($table)
    {
        switch ($this->databaseType) {
            case 'mysql':
                $sql = "SHOW FULL COLUMNS FROM ".$this->db->quoteTableName($table);
                break;
            case 'mssql':
                $sql = "SELECT
                    COLUMN_NAME as Field,
                    DATA_TYPE as Type,
                    IS_NULLABLE as 'Null',
                    COLUMN_DEFAULT as 'Default',
                    CASE WHEN COLUMNPROPERTY(OBJECT_ID(TABLE_SCHEMA+'.'+TABLE_NAME), COLUMN_NAME, 'IsIdentity') = 1 THEN 'auto_increment' ELSE '' END as Extra,
                    '' as Collation,
                    '' as Privileges,
                    '' as Comment
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION";
                return $this->db->customQuery($sql, true, [$table]);
            case 'postgresql':
                $sql = "SELECT
                    column_name as Field,
                    data_type as Type,
                    is_nullable as \"Null\",
                    column_default as \"Default\",
                    CASE WHEN column_default LIKE 'nextval%' THEN 'auto_increment' ELSE '' END as Extra,
                    '' as Collation,
                    '' as Privileges,
                    '' as Comment
                FROM information_schema.columns
                WHERE table_name = ?
                ORDER BY ordinal_position";
                return $this->db->customQuery($sql, true, [$table]);
            default:
                $sql = "SHOW FULL COLUMNS FROM ".$this->db->quoteTableName($table);
                break;
        }

        return $this->db->cacheOn()->customQuery($sql, true);
    }

    /**
     * Get field name from column information
     *
     * @param array $column Column information
     * @return string Field name
     */
    private function getFieldName($column)
    {
        return $column['Field'] ?? $column['field'] ?? $column['FIELD'] ?? '';
    }

    /**
     * Normalize column information across different database types
     *
     * @param array $column Raw column information
     * @return array Normalized column information
     */
    private function normalizeColumnInfo($column)
    {
        // Convert all keys to consistent format
        $normalized = [];
        foreach ($column as $key => $value) {
            $normalizedKey = ucfirst(strtolower($key));
            $normalized[$normalizedKey] = $value;
        }

        // Ensure standard keys exist
        $standardKeys = ['Field', 'Type', 'Null', 'Key', 'Default', 'Extra', 'Collation', 'Privileges', 'Comment'];
        foreach ($standardKeys as $key) {
            if (!isset($normalized[$key])) {
                $normalized[$key] = '';
            }
        }

        // Normalize boolean values
        if (isset($normalized['Null'])) {
            $normalized['Null'] = strtolower($normalized['Null']) === 'yes' || strtolower($normalized['Null']) === 'true' ? 'YES' : 'NO';
        }

        return $normalized;
    }
}
