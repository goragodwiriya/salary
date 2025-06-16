<?php
/**
 * @filesource Kotchasan/Database/PdoPostgresqlDriver.php
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
 * PostgreSQL database driver
 *
 * @see https://www.kotchasan.com/
 */
class PdoPostgresqlDriver extends Driver
{
    /**
     * Variable to store the last query statement
     *
     * @var \PDOStatement|null
     */
    protected $last_stmt = null;

    /**
     * Default schema
     * @var string
     */
    protected $default_schema = 'public';

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

        $dsn = 'pgsql:host='.$this->settings->hostname;
        if (!empty($this->settings->port)) {
            $dsn .= ';port='.$this->settings->port;
        }
        $dsn .= ';dbname='.$this->settings->dbname;

        $this->options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        try {
            $this->connection = new PDO($dsn, $this->settings->username, $this->settings->password, $this->options);

            // Set character set if provided
            if (!empty($this->settings->char_set)) {
                $this->connection->exec('SET NAMES \''.$this->settings->char_set.'\'');
            }

            // Set schema search path
            $schema = empty($this->settings->schema) ? $this->default_schema : $this->settings->schema;
            if ($schema) {
                $this->connection->exec('SET search_path TO '.$this->quoteIdentifier($schema));
                $this->default_schema = $schema;
            }

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
        $sql = "SELECT 1 FROM pg_database WHERE datname = ?";
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
     * @param int          $limit      Optional. The maximum number of records to delete (not used in PostgreSQL)
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
        $sql = 'TRUNCATE TABLE '.$this->quoteTableName($table_name).' RESTART IDENTITY';
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
        list($schema, $table) = $this->parseTableName($table_name);
        $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$schema, $table, $column_name]);
            $this->log('Query', $this->interpolateQuery($sql, [$schema, $table, $column_name]));
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, [$schema, $table, $column_name]));
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
                    'type' => isset($meta['native_type']) ? strtolower($meta['native_type']) : 'unknown',
                    'len' => isset($meta['len']) ? $meta['len'] : (isset($meta['precision']) ? $meta['precision'] : null),
                    'not_null' => !empty($meta['flags']) && in_array('not_null', $meta['flags']),
                    'primary_key' => !empty($meta['flags']) && in_array('primary_key', $meta['flags']),
                    'unique' => !empty($meta['flags']) && in_array('unique_key', $meta['flags']),
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
        list($schema, $table) = $this->parseTableName($table_name);
        $sql = "SELECT 1 FROM pg_indexes WHERE schemaname = ? AND tablename = ? AND indexname = ?";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$schema, $table, $index]);
            $this->log('Query', $this->interpolateQuery($sql, [$schema, $table, $index]));
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, [$schema, $table, $index]));
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
        list($schema, $table) = $this->parseTableName($table_name);
        $sql = "SELECT data_type FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$schema, $table, $column]);
            $this->log('Query', $this->interpolateQuery($sql, [$schema, $table, $column]));
            $result = $stmt->fetchColumn();
            return $result === false ? false : (string) $result;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, [$schema, $table, $column]));
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
        $primary_key_column = 'id';
        $params = [];
        $sql = $this->makeInsert($table_name, $save, $params, $primary_key_column);
        try {
            $this->last_stmt = $this->connection->prepare($sql);
            $this->last_stmt->execute($params);
            $this->log('Insert', $this->interpolateQuery($sql, $params));
            $this->incrementQueryCount();

            $result = $this->last_stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result[$primary_key_column])) {
                return (int) $result[$primary_key_column];
            } elseif ($this->last_stmt->rowCount() > 0) {
                return 0;
            }
            return false;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Insert Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, $params));
            return false;
        }
    }

    /**
     * Insert data into a table or update if it already exists (ON CONFLICT DO UPDATE).
     *
     * @param string $table_name Table name
     * @param array $save Data to save
     * @return int|false Returns last insert ID for insert/update, 0 if no ID, false on error
     */
    public function insertOrUpdate($table_name, $save)
    {
        if (empty($save)) {
            return false;
        }

        $primary_key_column = 'id'; // Default primary key
        $conflict_target = $this->quoteIdentifier($primary_key_column);
        $returning_pk = $primary_key_column;

        $fields = [];
        $placeholders = [];
        $update_set_parts = [];
        $params = [];

        foreach ($save as $key => $value) {
            $quoted_key = $this->quoteIdentifier($key);
            $fields[] = $quoted_key;
            $placeholder = ':'.$key;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;

            // Exclude primary key from update set
            if ($key !== $primary_key_column) {
                $update_set_parts[] = $quoted_key.' = EXCLUDED.'.$quoted_key;
            }
        }

        $sql = 'INSERT INTO '.$this->quoteTableName($table_name).' ('.implode(', ', $fields).') ';
        $sql .= 'VALUES ('.implode(', ', $placeholders).') ';
        $sql .= 'ON CONFLICT ('.$conflict_target.') ';

        if (!empty($update_set_parts)) {
            $sql .= 'DO UPDATE SET '.implode(', ', $update_set_parts).' ';
        } else {
            $sql .= 'DO NOTHING ';
        }
        $sql .= 'RETURNING '.$this->quoteIdentifier($returning_pk);

        try {
            $this->last_stmt = $this->connection->prepare($sql);
            $this->last_stmt->execute($params);
            $this->log('Upsert', $this->interpolateQuery($sql, $params));
            $this->incrementQueryCount();

            $result = $this->last_stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result[$returning_pk])) {
                return (int) $result[$returning_pk];
            } elseif ($this->last_stmt->rowCount() > 0) {
                return 0;
            }
            return false;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Upsert Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, $params));
            return false;
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
            $query .= 'SELECT '.$sqls['select'];
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
        if (isset($sqls['limit'])) {
            $query .= ' LIMIT '.(int) $sqls['limit'];
        }
        if (isset($sqls['start']) && (int) $sqls['start'] > 0) {
            $query .= ' OFFSET '.(int) $sqls['start'];
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
     * @return bool Returns true (operation not directly applicable to PostgreSQL).
     */
    public function optimizeTable($table_name)
    {
        $this->log('Table Operation', "Optimize table (VACUUM) is often automated for PostgreSQL. Table: ".$this->quoteTableName($table_name));
        return true;
    }
    /**
     * Returns a random value for use in SQL queries.
     *
     * @return string The SQL command to generate a random value.
     */
    public function random()
    {
        return 'RANDOM()';
    }

    /**
     * Quote a table name for use in a query.
     *
     * @param string $table_name The table name.
     * @return string The quoted table name.
     */
    public function quoteTableName($table_name)
    {
        // Remove backticks if QueryBuilder used them
        $table_name = str_replace('`', '', $table_name);

        if (strpos($table_name, '"') !== false) {
            return $table_name;
        }
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
     * @return bool Returns true (operation not directly applicable to PostgreSQL).
     */
    public function repairTable($table_name)
    {
        $this->log('Table Operation', "Repair table (REINDEX) is a manual operation for PostgreSQL. Table: ".$this->quoteTableName($table_name));
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
            $sql .= ' LIMIT '.(int) $limit;
        }

        return $this->doCustomQuery($sql, $params);
    }

    /**
     * Selects a database.
     *
     * @param string $database The name of the database.
     *
     * @return bool Returns true if current database matches requested, false otherwise.
     */
    public function selectDB($database)
    {
        if (isset($this->settings->dbname) && $this->settings->dbname == $database) {
            $this->log('Database', 'Database '.$this->quoteIdentifier($database).' is already selected.');
            return true;
        }
        $this->log('Database Error', 'Cannot switch database post-connection in PostgreSQL using this method.');
        return false;
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
        list($schema, $table) = $this->parseTableName($table_name);
        $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?";
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([$schema, $table]);
            $this->log('Query', $this->interpolateQuery($sql, [$schema, $table]));
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            $this->setError($e->getMessage());
            $this->log('Query Error', 'Error: '.$e->getMessage().' SQL: '.$this->interpolateQuery($sql, [$schema, $table]));
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
     * @param string|null $returning_column Column name to return (e.g., primary key)
     * @return string SQL INSERT statement
     */
    protected function makeInsert($table_name, $save, &$params, $returning_column = null)
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
        $sql = 'INSERT INTO '.$this->quoteTableName($table_name).' ('.implode(', ', $fields).') VALUES ('.implode(', ', $values).')';
        if ($returning_column) {
            $sql .= ' RETURNING '.$this->quoteIdentifier($returning_column);
        }
        return $sql;
    }

    /**
     * Quote a SQL expression or field name.
     *
     * @param string $expression
     * @param bool $isSelectFields True if used in SELECT clause
     * @return string
     */
    protected function quoteSqlExpression($expression, $isSelectFields = false)
    {
        if ($isSelectFields && $expression === '*') {
            return '*';
        }
        if (strpos($expression, '(') !== false && strpos($expression, ')') !== false) {
            return $expression;
        }
        if (preg_match('/\s|[=<>!+\-\*\/%]/', $expression)) {
            return $expression;
        }
        return $this->quoteIdentifier($expression);
    }

    /**
     * Parse a table name to extract schema and table.
     *
     * @param string $table_name
     * @return array [schema, table]
     */
    private function parseTableName($table_name)
    {
        if (strpos($table_name, '.') !== false) {
            list($schema, $table) = explode('.', str_replace('"', '', $table_name), 2);
            return [$schema, $table];
        }
        return [$this->default_schema, str_replace('"', '', $table_name)];
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
                $value = $value ? 'TRUE' : 'FALSE';
            }

            $interpolated = str_replace($placeholder, $value, $interpolated);
        }

        return $interpolated;
    }
}
