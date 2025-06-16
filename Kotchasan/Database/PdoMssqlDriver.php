<?php
/**
 * @filesource Kotchasan/Database/PdoMssqlDriver.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 * @author Goragod Wiriya <admin@goragod.com>
 * @package Kotchasan
 */

namespace Kotchasan\Database;

use PDO;
use PDOException;

/**
 * Microsoft SQL Server database driver
 *
 * @see https://www.kotchasan.com/
 */
class PdoMssqlDriver extends Driver
{
    /**
     * Variable to store the last query statement
     *
     * @var \PDOStatement|null
     */
    protected $last_stmt = null;

    /**
     * Connection options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Connect to the database
     *
     * @param array $params
     * @return static
     * @throws PDOException if connection fails
     */
    public function connect(array $params)
    {
        $this->settings = (object) $params;
        $dsn = 'sqlsrv:Server='.$this->settings->hostname;
        if (!empty($this->settings->port)) {
            $dsn .= ','.$this->settings->port;
        }
        $dsn .= ';Database='.$this->settings->dbname;

        $this->options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        try {
            $this->connection = new PDO($dsn, $this->settings->username, $this->settings->password, $this->options);
            return $this;
        } catch (PDOException $e) {
            $this->setError('Connection failed: '.$e->getMessage());
            $this->log('Connection Error', 'Connection failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Close the database connection.
     */
    public function close()
    {
        if ($this->in_transaction) {
            $this->rollback();
        }
        $this->connection = null;
    }

    /**
     * Checks if a database exists.
     *
     * @param string $database The name of the database to check
     *
     * @return bool Returns true if the database exists, false otherwise
     */
    public function databaseExists($database)
    {
        $sql = "SELECT 1 FROM sys.databases WHERE name = ?";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$database]);
            $this->log('Query', $this->interpolateQuery($sql, [$database]));
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, [$database]));
            return false;
        }
    }

    /**
     * Deletes records from a table based on the given condition.
     *
     * @param string       $table_name The name of the table to delete records from
     * @param array|string $condition  The condition for deleting records (can be an array or a string)
     * @param int          $limit      Optional. The maximum number of records to delete (ignored in MSSQL)
     * @param string       $operator   Optional. The operator used to combine multiple conditions (default: 'AND')
     *
     * @return int|bool Returns the number of affected rows on success, false on failure
     */
    public function delete($table_name, $condition, $limit = 1, $operator = 'AND')
    {
        $params = [];
        $where_sql = '';

        if (is_array($condition) && !empty($condition)) {
            $wheres = [];
            $i = 0;
            foreach ($condition as $key => $value) {
                $ph = ':w_'.$key.$i;
                $wheres[] = $this->quoteIdentifier($key).' = '.$ph;
                $params[$ph] = $value;
                $i++;
            }
            $where_sql = ' WHERE '.implode(' '.$operator.' ', $wheres);
        } elseif (!empty($condition)) {
            $condition = $this->buildWhere($condition, $operator);
            if (is_array($condition)) {
                $params = $condition[1];
                $condition = $condition[0];
            }
            $where_sql = ' WHERE '.$condition;
        }

        $sql = 'DELETE FROM '.$this->quoteTableName($table_name).$where_sql;
        return $this->doQuery($sql, $params);
    }

    /**
     * Empties a table by deleting all its records.
     *
     * @param string $table_name The name of the table to empty
     *
     * @return bool Returns true if the table is successfully emptied, false otherwise
     */
    public function emptyTable($table_name)
    {
        $sql = 'TRUNCATE TABLE '.$this->quoteTableName($table_name);
        try {
            $this->connection->exec($sql);
            $this->log('Query', $sql);
            return true;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$sql);
            return false;
        }
    }

    /**
     * Returns the number of fields in the result set.
     *
     * @return int
     */
    public function fieldCount()
    {
        if ($this->last_stmt) {
            return $this->last_stmt->columnCount();
        }
        return 0;
    }

    /**
     * Checks if a column exists in the table.
     *
     * @param string $table_name  The table name
     * @param string $column_name The column name
     *
     * @return bool True if the column exists, false otherwise
     */
    public function fieldExists($table_name, $column_name)
    {
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = SCHEMA_NAME() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$table_name, $column_name]);
            $this->log('Query', $this->interpolateQuery($sql, [$table_name, $column_name]));
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, [$table_name, $column_name]));
            return false;
        }
    }

    /**
     * Get information about the fields in the result set.
     *
     * @return array
     */
    public function getFields()
    {
        $fields = [];
        if ($this->last_stmt) {
            $field_count = $this->last_stmt->columnCount();
            for ($i = 0; $i < $field_count; ++$i) {
                $meta = $this->last_stmt->getColumnMeta($i);
                $fields[$meta['name']] = (object) [
                    'name' => $meta['name'],
                    'type' => isset($meta['sqlsrv:decl_type']) ? strtolower($meta['sqlsrv:decl_type']) : (isset($meta['native_type']) ? strtolower($meta['native_type']) : 'unknown'),
                    'len' => isset($meta['len']) ? $meta['len'] : (isset($meta['precision']) ? $meta['precision'] : null),
                    'not_null' => isset($meta['flags']) && is_array($meta['flags']) ? !in_array('nullable', $meta['flags']) : false,
                    'primary_key' => false,
                    'unique' => false,
                    'auto_increment' => false
                ];
            }
        }
        return $fields;
    }

    /**
     * Gets the next ID for the specified table based on the primary key.
     *
     * @param string $table_name The name of the table.
     * @param array $condition An array of conditions for the query (default is empty).
     * @param string $operator The logical operator for combining conditions (default is 'AND').
     * @param string $primary_key The primary key column name (default is 'id').
     *
     * @return int The next ID for the specified table.
     */
    public function getNextId($table_name, $condition = [], $operator = 'AND', $primary_key = 'id')
    {
        $sql = 'SELECT MAX('.$this->quoteIdentifier($primary_key).') + 1 AS next_id FROM '.$this->quoteTableName($table_name);
        $values = [];

        if (!empty($condition)) {
            $condition = $this->buildWhere($condition, $operator);
            if (is_array($condition)) {
                $values = $condition[1];
                $condition = $condition[0];
            }
            $sql .= ' WHERE '.$condition;
        }

        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($values);
            $this->log('Query', $this->interpolateQuery($sql, $values));
            $result = $stmt->fetchColumn();
            return $result === null ? 1 : (int) $result;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, $values));
            return 1;
        }
    }

    /**
     * Check if an index exists in a table.
     *
     * @param string $database_name The database name.
     * @param string $table_name    The table name.
     * @param string $index         The index name.
     *
     * @return bool Returns true if the index exists, false otherwise.
     */
    public function indexExists($database_name, $table_name, $index)
    {
        $tableNameOnly = $table_name;
        if (strpos($table_name, '.') !== false) {
            list(, $tableNameOnly) = explode('.', $table_name, 2);
        }
        $sql = "SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(?) AND name = ?";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$tableNameOnly, $index]);
            $this->log('Query', $this->interpolateQuery($sql, [$tableNameOnly, $index]));
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, [$tableNameOnly, $index]));
            return false;
        }
    }

    /**
     * Retrieves the data type of a specific column in a given table within a database.
     *
     * @param string $database_name The name of the database.
     * @param string $table_name The name of the table.
     * @param string $column The name of the column to check.
     *
     * @return mixed Returns the data type of the column (DATA_TYPE) if found, or false if the column is not found.
     */
    public function columnType($database_name, $table_name, $column)
    {
        $sql = "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = SCHEMA_NAME() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$table_name, $column]);
            $this->log('Query', $this->interpolateQuery($sql, [$table_name, $column]));
            $result = $stmt->fetchColumn();
            return $result === false ? false : (string) $result;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, [$table_name, $column]));
            return false;
        }
    }

    /**
     * Insert data into a table.
     *
     * @param string $table_name Table name
     * @param array $save Data to save
     * @return int|false Returns the last insert ID on success, 0 if no ID, false on failure
     */
    public function insert($table_name, $save)
    {
        $params = [];
        $sql = $this->makeInsert($table_name, $save, $params);
        try {
            $this->last_stmt = $this->connection->prepare($sql);
            $this->last_stmt->execute($params);
            $this->log('Insert', $this->interpolateQuery($sql, $params));
            $this->incrementQueryCount();

            // Try to get the last inserted ID
            $last_id = $this->connection->lastInsertId();
            return $last_id ? (int) $last_id : ($this->last_stmt->rowCount() > 0 ? 0 : false);
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Insert Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, $params));
            return false;
        }
    }

    /**
     * Insert data into a table or update if it already exists.
     * Uses a simplified approach with separate SELECT and INSERT/UPDATE logic.
     *
     * @param string $table_name Table name
     * @param array $save Data to save
     * @return int|false Returns last insert ID for insert, 0 for update, false on error
     */
    public function insertOrUpdate($table_name, $save)
    {
        if (empty($save)) {
            return false;
        }

        // Assume 'id' is the primary key if not specified
        $primary_key = 'id';

        // Check if record exists
        if (isset($save[$primary_key])) {
            $exists_sql = "SELECT 1 FROM ".$this->quoteTableName($table_name)." WHERE ".$this->quoteIdentifier($primary_key)." = ?";
            try {
                $stmt = $this->connection->prepare($exists_sql);
                $stmt->execute([$save[$primary_key]]);
                $exists = $stmt->fetch() !== false;

                if ($exists) {
                    // Update existing record
                    $result = $this->update($table_name, [$primary_key => $save[$primary_key]], $save);
                    return $result ? 0 : false; // 0 indicates update
                } else {
                    // Insert new record
                    return $this->insert($table_name, $save);
                }
            } catch (PDOException $e) {
                $this->setError($e->getMessage());
                return false;
            }
        } else {
            // No primary key specified, just insert
            return $this->insert($table_name, $save);
        }
    }

    /**
     * Build the SQL query string from the provided components.
     *
     * @param array $sqls Array of SQL components (select, from, where, etc.)
     * @return string The constructed SQL query
     */
    public function makeQuery($sqls)
    {
        $query = '';

        // Handle different query types
        if (isset($sqls['union'])) {
            $union_sqls = [];
            foreach ($sqls['union'] as $union_query) {
                $union_sqls[] = '('.(is_array($union_query) ? $this->makeQuery($union_query) : $union_query).')';
            }
            $query .= implode(' UNION ', $union_sqls);
        } elseif (isset($sqls['unionAll'])) {
            $union_sqls = [];
            foreach ($sqls['unionAll'] as $union_query) {
                $union_sqls[] = '('.(is_array($union_query) ? $this->makeQuery($union_query) : $union_query).')';
            }
            $query .= implode(' UNION ALL ', $union_sqls);
        } elseif (isset($sqls['delete'])) {
            $query .= 'DELETE FROM '.$sqls['delete'];
        } elseif (isset($sqls['insert'])) {
            $cols = [];
            $values_clause = [];
            if (isset($sqls['keys']) && is_array($sqls['keys'])) {
                foreach ($sqls['keys'] as $k => $v) {
                    $cols[] = $this->quoteIdentifier($k);
                    if (is_string($v) && $v[0] == '(' && substr($v, -1) == ')') {
                        $values_clause[] = substr($v, 1, -1);
                    } else {
                        $values_clause[] = $v;
                    }
                }
                $query .= 'INSERT INTO '.$sqls['insert'].' ('.implode(', ', $cols).') VALUES ('.implode(', ', $values_clause).')';
            } elseif (isset($sqls['select'])) {
                $query .= 'INSERT INTO '.$sqls['insert'];
                if (!empty($sqls['keys']) && is_array($sqls['keys'])) {
                    $query .= ' ('.implode(', ', array_map([$this, 'quoteIdentifier'], $sqls['keys'])).')';
                }
                $query .= ' '.$this->makeQuery(array_merge($sqls, ['insert' => null]));
            }
        } elseif (isset($sqls['update'])) {
            $query .= 'UPDATE '.$sqls['update'];
        } elseif (isset($sqls['select'])) {
            $select_fields = $sqls['select'];

            // Handle LIMIT without OFFSET using TOP
            if (isset($sqls['limit']) && (!isset($sqls['start']) || (int) $sqls['start'] == 0)) {
                if (stripos($select_fields, 'TOP ') === false) {
                    $is_distinct = stripos($select_fields, 'DISTINCT ') === 0;
                    if ($is_distinct) {
                        $select_fields = 'DISTINCT TOP '.(int) $sqls['limit'].' '.substr($select_fields, 9);
                    } else {
                        $select_fields = 'TOP '.(int) $sqls['limit'].' '.$select_fields;
                    }
                }
            }

            $query .= 'SELECT '.$select_fields;
        }

        // Add other clauses
        if (!empty($sqls['from'])) {
            $query .= ' FROM '.$sqls['from'];
        }
        if (!empty($sqls['join'])) {
            $query .= ' '.implode(' ', $sqls['join']);
        }
        if (!empty($sqls['where'])) {
            $query .= ' WHERE '.$sqls['where'];
        }
        if (!empty($sqls['group'])) {
            $query .= ' GROUP BY '.$sqls['group'];
        }
        if (!empty($sqls['having'])) {
            $query .= ' HAVING '.$sqls['having'];
        }
        if (!empty($sqls['order'])) {
            $query .= ' ORDER BY '.$sqls['order'];
        }

        // Handle OFFSET FETCH for LIMIT with OFFSET (SQL Server 2012+)
        if (isset($sqls['limit']) && isset($sqls['start']) && (int) $sqls['start'] > 0) {
            if (empty($sqls['order'])) {
                $query .= ' ORDER BY (SELECT NULL)';
            }
            $query .= ' OFFSET '.(int) $sqls['start'].' ROWS FETCH NEXT '.(int) $sqls['limit'].' ROWS ONLY';
        }

        // Add SET clause for UPDATE
        if (!empty($sqls['set']) && isset($sqls['update'])) {
            if (is_array($sqls['set'])) {
                $set_clauses = [];
                foreach ($sqls['set'] as $key_val_pair) {
                    $set_clauses[] = $key_val_pair;
                }
                if (!empty($set_clauses)) {
                    $query .= ' SET '.implode(', ', $set_clauses);
                }
            } else {
                $query .= ' SET '.$sqls['set'];
            }
        }

        return $query;
    }

    /**
     * Optimize a table.
     *
     * @param string $table_name The table name.
     *
     * @return bool Returns true (operation not directly applicable to MSSQL).
     */
    public function optimizeTable($table_name)
    {
        $this->log('Table Operation', "Optimize table operation not directly applicable to MSSQL for table: ".$this->quoteTableName($table_name));
        return true;
    }

    /**
     * Returns a random value for use in SQL queries.
     *
     * @return string The SQL command to generate a random value.
     */
    public function random()
    {
        return 'NEWID()';
    }

    /**
     * Quote a table name for use in a query.
     *
     * @param string $table_name The table name.
     * @return string The quoted table name.
     */
    public function quoteTableName($table_name)
    {
        // Remove any existing quotes
        $table_name = str_replace(['[', ']', '`', '"', "'"], '', $table_name);
        if (strpos($table_name, '.') !== false) {
            list($schema, $table) = explode('.', $table_name, 2);
            return $this->quoteIdentifier(trim($schema)).'.'.$this->quoteIdentifier(trim($table));
        }
        return $this->quoteIdentifier(trim($table_name));
    }

    /**
     * Repair a table.
     *
     * @param string $table_name The table name.
     *
     * @return bool Returns true (operation not directly applicable to MSSQL).
     */
    public function repairTable($table_name)
    {
        $this->log('Table Operation', "Repair table operation not directly applicable to MSSQL for table: ".$this->quoteTableName($table_name));
        return true;
    }

    /**
     * Retrieve data from a table.
     *
     * @param string $table_name The table name.
     * @param mixed  $condition  The query WHERE condition.
     * @param array  $sort       The sorting criteria.
     * @param int    $limit      The number of data to retrieve.
     *
     * @return array The resulting data in array format. Returns an empty array if unsuccessful.
     */
    public function select($table_name, $condition = [], $sort = [], $limit = 0)
    {
        $params = [];
        $where_sql = '';

        if (is_array($condition) && !empty($condition)) {
            $wheres = [];
            foreach ($condition as $key => $value) {
                $wheres[] = $this->quoteIdentifier($key).' = :'.$key;
                $params[':'.$key] = $value;
            }
            $where_sql = ' WHERE '.implode(' AND ', $wheres);
        } elseif (!empty($condition)) {
            $condition = $this->buildWhere($condition);
            if (is_array($condition)) {
                $params = $condition[1];
                $condition = $condition[0];
            }
            $where_sql = ' WHERE '.$condition;
        }

        $order_sql = '';
        if (!empty($sort)) {
            $order_sql = ' ORDER BY '.(is_array($sort) ? implode(', ', array_map([$this, 'quoteSqlExpression'], $sort)) : $this->quoteSqlExpression($sort));
        }

        $sql = 'SELECT * FROM '.$this->quoteTableName($table_name).$where_sql.$order_sql;

        if ($limit > 0) {
            $sql = 'SELECT TOP '.(int) $limit.' * FROM '.$this->quoteTableName($table_name).$where_sql.$order_sql;
        }

        return $this->doCustomQuery($sql, $params);
    }

    /**
     * Selects a database.
     *
     * @param string $database The name of the database.
     *
     * @return bool Returns true on success, false on failure.
     */
    public function selectDB($database)
    {
        try {
            $this->connection->exec('USE '.$this->quoteIdentifier($database));
            $this->settings->dbname = $database;
            $this->log('Database', 'USE '.$this->quoteIdentifier($database));
            return true;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Database Error', 'Error: '.$e->getMessage().' SQL: USE '.$this->quoteIdentifier($database));
            return false;
        }
    }

    /**
     * Check if a table exists.
     *
     * @param string $table_name The table name.
     *
     * @return bool Returns true if the table exists, false otherwise.
     */
    public function tableExists($table_name)
    {
        $sql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = SCHEMA_NAME() AND TABLE_NAME = ?";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$table_name]);
            $this->log('Query', $this->interpolateQuery($sql, [$table_name]));
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, [$table_name]));
            return false;
        }
    }

    /**
     * Updates data in the specified table.
     *
     * @param string       $table_name The table name.
     * @param mixed        $condition  The query WHERE condition.
     * @param array|object $save       The data to be saved in the format array('key1'=>'value1', 'key2'=>'value2', ...)
     *
     * @return bool Returns true on success, false on failure.
     */
    public function update($table_name, $condition, $save)
    {
        if (empty($save)) {
            return false;
        }

        $params = [];
        $set_parts = [];
        foreach ($save as $key => $value) {
            $set_parts[] = $this->quoteIdentifier($key).' = :s_'.$key;
            $params[':s_'.$key] = $value;
        }
        $set_sql = implode(', ', $set_parts);

        $where_sql = '';
        if (is_array($condition) && !empty($condition)) {
            $wheres = [];
            $i = 0;
            foreach ($condition as $key => $value) {
                $ph = ':w_'.$key.$i;
                $wheres[] = $this->quoteIdentifier($key).' = '.$ph;
                $params[$ph] = $value;
                $i++;
            }
            $where_sql = ' WHERE '.implode(' AND ', $wheres);
        } elseif (!empty($condition)) {
            $condition = $this->buildWhere($condition);
            if (is_array($condition)) {
                $params = array_merge($params, $condition[1]);
                $condition = $condition[0];
            }
            $where_sql = ' WHERE '.$condition;
        }

        $sql = 'UPDATE '.$this->quoteTableName($table_name).' SET '.$set_sql.$where_sql;
        $result = $this->doQuery($sql, $params);
        return $result !== false && $result > 0;
    }

    /**
     * Executes a custom SQL query that returns results.
     *
     * @param string $sql SQL query
     * @param array  $values Values for prepared statement
     * @return array|false Returns an array of results on success, false on failure
     */
    protected function doCustomQuery($sql, $values = [])
    {
        try {
            $this->last_stmt = $this->connection->prepare($sql);
            $this->last_stmt->execute($values);
            $this->log('Query', $this->interpolateQuery($sql, $values));
            $this->incrementQueryCount();
            return $this->last_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, $values));
            return false;
        }
    }

    /**
     * Executes a SQL query that modifies data.
     *
     * @param string $sql SQL query
     * @param array  $values Values for prepared statement
     * @return int|false Returns the number of affected rows on success, false on failure
     */
    protected function doQuery($sql, $values = [])
    {
        try {
            $this->last_stmt = $this->connection->prepare($sql);
            $this->last_stmt->execute($values);
            $this->log('Query', $this->interpolateQuery($sql, $values));
            $this->incrementQueryCount();
            return $this->last_stmt->rowCount();
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, $values));
            return false;
        }
    }

    /**
     * Helper function to create an INSERT SQL statement.
     *
     * @param string $table_name Table name
     * @param array $save Data to save in key-value format
     * @param array &$params Parameters for query binding
     * @return string SQL INSERT statement
     */
    protected function makeInsert($table_name, $save, &$params)
    {
        $fields = [];
        $values = [];
        $params = [];
        foreach ($save as $key => $value) {
            $fields[] = $this->quoteIdentifier($key);
            $placeholder = ':'.$key;
            $values[] = $placeholder;
            $params[$placeholder] = $value;
        }
        return 'INSERT INTO '.$this->quoteTableName($table_name).' ('.implode(', ', $fields).') VALUES ('.implode(', ', $values).')';
    }

    /**
     * Quote a SQL expression or field name.
     *
     * @param string $expression
     * @param bool $isSelectFields
     * @return string
     */
    protected function quoteSqlExpression($expression, $isSelectFields = false)
    {
        if ($isSelectFields && $expression === '*') {
            return '*';
        }
        if (preg_match('/^[a-zA-Z0-9_.]+$/', $expression)) {
            if (strpos($expression, '.') !== false) {
                list($schema, $field) = explode('.', $expression, 2);
                return $this->quoteIdentifier($schema).'.'.$this->quoteIdentifier($field);
            }
            return $this->quoteIdentifier($expression);
        }
        return $expression;
    }

    /**
     * Interpolate query placeholders with actual values for logging purposes.
     *
     * @param string $sql SQL query with placeholders
     * @param array $values Array of values to substitute
     * @return string Interpolated SQL query
     */
    protected function interpolateQuery($sql, $values = [])
    {
        if (empty($values)) {
            return $sql;
        }

        $interpolated = $sql;
        foreach ($values as $key => $value) {
            $placeholder = is_numeric($key) ? '?' : $key;
            if (strpos($placeholder, ':') !== 0 && !is_numeric($key)) {
                $placeholder = ':'.$placeholder;
            }

            if (is_string($value)) {
                $value = "'".$value."'";
            } elseif (is_null($value)) {
                $value = 'NULL';
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            }

            $interpolated = str_replace($placeholder, $value, $interpolated);
        }

        return $interpolated;
    }
}
