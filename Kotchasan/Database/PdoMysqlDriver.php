<?php
/**
 * @filesource Kotchasan/Database/PdoMysqlDriver.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 * @author Goragod Wiriya <admin@goragod.com>
 * @package Kotchasan
 */

namespace Kotchasan\Database;

/**
 * PDO MySQL Database Adapter Class.
 *
 * This class provides a PDO-based database adapter for MySQL.
 *
 * @see https://www.kotchasan.com/
 */
class PdoMysqlDriver extends Driver
{
    /**
     * Connection options
     *
     * @var array
     */
    protected $options = [];

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
     * Connect to the database.
     *
     * @param mixed $params Connection parameters.
     *
     * @return static
     * @throws \InvalidArgumentException if the database configuration is invalid.
     * @throws \Exception if there's an error connecting to the database.
     */
    public function connect($params)
    {
        $this->options = [
            \PDO::ATTR_STRINGIFY_FETCHES => 0,
            \PDO::ATTR_EMULATE_PREPARES => 0,
            \PDO::ATTR_PERSISTENT => 1,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ];

        foreach ($params as $key => $value) {
            $this->{$key} = $value;
        }

        if ($this->settings->dbdriver == 'mysql') {
            $this->options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES '.$this->settings->char_set;
            $this->options[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = 1;
        }

        $dsn = $this->settings->dbdriver.':host='.$this->settings->hostname;
        $dsn .= empty($this->settings->port) ? '' : ';port='.$this->settings->port;
        $dsn .= empty($this->settings->dbname) ? '' : ';dbname='.$this->settings->dbname;

        if (isset($this->settings->username) && isset($this->settings->password)) {
            try {
                $this->connection = new \PDO($dsn, $this->settings->username, $this->settings->password, $this->options);

                if (defined('SQL_MODE')) {
                    $this->connection->query("SET SESSION sql_mode='".constant('SQL_MODE')."'");
                }
            } catch (\PDOException $e) {
                $this->setError($e->getMessage());
                throw new \Exception($e->getMessage(), 500, $e);
            }
        } else {
            throw new \InvalidArgumentException('Database configuration is invalid');
        }

        return $this;
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
        $search = $this->doCustomQuery("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$database]);
        return $search && count($search) == 1;
    }

    /**
     * Deletes records from a table based on the given condition.
     *
     * @param string       $table_name The name of the table to delete records from
     * @param array|string $condition  The condition for deleting records (can be an array or a string)
     * @param int          $limit      Optional. The maximum number of records to delete (default: 1)
     * @param string       $operator   Optional. The operator used to combine multiple conditions (default: 'AND')
     *
     * @return bool Returns true if the delete operation is successful, false otherwise
     */
    public function delete($table_name, $condition, $limit = 1, $operator = 'AND')
    {
        $condition = $this->buildWhere($condition, $operator);
        if (is_array($condition)) {
            $values = $condition[1];
            $condition = $condition[0];
        } else {
            $values = [];
        }
        $sql = 'DELETE FROM '.$this->quoteTableName($table_name).' WHERE '.$condition;
        if (is_int($limit) && $limit > 0) {
            $sql .= ' LIMIT '.$limit;
        }
        return $this->doQuery($sql, $values);
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
        return $this->query("TRUNCATE TABLE ".$this->quoteTableName($table_name)) !== false;
    }

    /**
     * Get the number of fields in the query result.
     *
     * @return int
     */
    public function fieldCount()
    {
        if (isset($this->result_id)) {
            return $this->result_id->columnCount();
        } else {
            return 0;
        }
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
        $result = $this->customQuery("SHOW COLUMNS FROM $table_name LIKE '$column_name'");
        return !empty($result);
    }

    /**
     * Get the list of fields from the query result.
     *
     * @return array
     */
    public function getFields()
    {
        $fieldList = [];

        for ($i = 0, $c = $this->fieldCount(); $i < $c; ++$i) {
            $result = @$this->result_id->getColumnMeta($i);
            if ($result) {
                $fieldList[$result['name']] = $result;
            }
        }

        return $fieldList;
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
        $sql = "SELECT MAX(".$this->quoteIdentifier($primary_key).") AS `Auto_increment` FROM ".$this->quoteTableName($table_name);
        $values = [];
        if (!empty($condition)) {
            $condition = $this->buildWhere($condition, $operator);
            if (is_array($condition)) {
                $values = $condition[1];
                $condition = $condition[0];
            }
            $sql .= ' WHERE '.$condition;
        }
        $result = $this->doCustomQuery($sql, $values);
        return (int) $result[0]['Auto_increment'] + 1;
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
        $sql = "SELECT * FROM information_schema.statistics WHERE table_schema=? AND table_name = ? AND column_name = ?";
        $result = $this->customQuery($sql, true, [$database_name, $table_name, $index]);
        return !empty($result);
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
        // SQL query to fetch the data type of the specified column
        $sql = "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        $result = $this->customQuery($sql, false, [$database_name, $table_name, $column]);
        // Return the data type if found, otherwise return false
        return empty($result) ? false : $result[0]->DATA_TYPE;
    }

    /**
     * Insert a new row into a table.
     *
     * @param string $table_name The name of the table.
     * @param array $save The data to be inserted. Format array('key1'=>'value1', 'key2'=>'value2', ...)
     *
     * @return int|bool The ID of the inserted row or false on failure.
     */
    public function insert($table_name, $save)
    {
        $params = [];
        $sql = $this->makeInsert($table_name, $save, $params);
        try {
            $query = $this->connection->prepare($sql);
            $query->execute($params);
            $this->log('insert', $sql, $params);
            $this->incrementQueryCount();
            return (int) $this->connection->lastInsertId();
        } catch (\PDOException $e) {
            $this->setError($e->getMessage());
            throw new \Exception($e->getMessage(), 500, $e);
        }
    }

    /**
     * Insert a new row into a table or update an existing row if a unique key constraint is violated.
     *
     * @param string $table_name The name of the table.
     * @param array|object $save The data to be inserted or updated.
     *
     * @return int The ID of the inserted row.
     * @throws \Exception if there's an error executing the query.
     */
    public function insertOrUpdate($table_name, $save)
    {
        $updates = [];
        $params = [];

        foreach ($save as $key => $value) {
            $updates[] = $this->quoteIdentifier($key).'=VALUES('.$this->quoteIdentifier($key).')';
        }

        $sql = $this->makeInsert($table_name, $save, $params);
        $sql .= ' ON DUPLICATE KEY UPDATE '.implode(', ', $updates);

        try {
            $query = $this->connection->prepare($sql);
            $query->execute($params);
            $this->log(__FUNCTION__, $sql, $params);
            $this->incrementQueryCount();
            return (int) $this->connection->lastInsertId();
        } catch (\PDOException $e) {
            $this->setError($e->getMessage());
            throw new \Exception($e->getMessage(), 500, $e);
        }
    }

    /**
     * Generate an SQL query command based on the given query builder parameters.
     *
     * @param array $sqls The SQL commands from the query builder.
     *
     * @return string The generated SQL command.
     */
    public function makeQuery($sqls)
    {
        if (!empty($sqls['tmptable'])) {
            $sql = 'CREATE TEMPORARY TABLE '.$sqls['tmptable'].' ';
        } elseif (!empty($sqls['view'])) {
            $sql = 'CREATE OR REPLACE VIEW '.$sqls['view'].' AS ';
        } elseif (!empty($sqls['explain'])) {
            $sql = 'EXPLAIN ';
        } else {
            $sql = '';
        }

        if (isset($sqls['insert'])) {
            if (isset($sqls['select'])) {
                $sql .= 'INSERT INTO '.$sqls['insert'];
                if (!empty($sqls['keys'])) {
                    $sql .= ' ('.$this->quoteIdentifier(implode('`, `', $sqls['keys'])).')';
                }
                $sql .= ' '.$sqls['select'];
            } else {
                $keys = array_keys($sqls['keys']);
                $sql .= 'INSERT INTO '.$sqls['insert'].' ('.$this->quoteIdentifier(implode('`, `', $keys));
                $sql .= ') VALUES ('.implode(', ', $sqls['keys']).')';
            }

            if (isset($sqls['orupdate'])) {
                $sql .= ' ON DUPLICATE KEY UPDATE '.implode(', ', $sqls['orupdate']);
            }
        } else {
            if (isset($sqls['union'])) {
                if (isset($sqls['select'])) {
                    $sql .= 'SELECT '.$sqls['select'].' FROM (('.implode(') UNION (', $sqls['union']).')) AS U9';
                } else {
                    $sql .= '('.implode(') UNION (', $sqls['union']).')';
                }
            } elseif (isset($sqls['unionAll'])) {
                if (isset($sqls['select'])) {
                    $sql .= 'SELECT '.$sqls['select'].' FROM (('.implode(') UNION ALL (', $sqls['unionAll']).')) AS U9';
                } else {
                    $sql .= '('.implode(') UNION ALL (', $sqls['unionAll']).')';
                }
            } else {
                if (isset($sqls['select'])) {
                    $sql .= 'SELECT '.$sqls['select'];
                    if (isset($sqls['from'])) {
                        $sql .= ' FROM '.$sqls['from'];
                    }
                } elseif (isset($sqls['update'])) {
                    $sql .= 'UPDATE '.$sqls['update'];
                } elseif (isset($sqls['delete'])) {
                    $sql .= 'DELETE FROM '.$sqls['delete'];
                }
            }

            if (isset($sqls['join'])) {
                foreach ($sqls['join'] as $join) {
                    $sql .= $join;
                }
            }

            if (isset($sqls['set'])) {
                $sql .= ' SET '.implode(', ', $sqls['set']);
            }

            if (isset($sqls['where'])) {
                $sql .= ' WHERE '.$sqls['where'];

                if (isset($sqls['exists'])) {
                    $sql .= ' AND '.implode(' AND ', $sqls['exists']);
                }
            } elseif (isset($sqls['exists'])) {
                $sql .= ' WHERE '.implode(' AND ', $sqls['exists']);
            }

            if (isset($sqls['group'])) {
                $sql .= ' GROUP BY '.$sqls['group'];
            }

            if (isset($sqls['having'])) {
                $sql .= ' HAVING '.$sqls['having'];
            }

            if (isset($sqls['order'])) {
                $sql .= ' ORDER BY '.$sqls['order'];
            }

            if (isset($sqls['limit'])) {
                $sql .= ' LIMIT '.(empty($sqls['start']) ? '' : $sqls['start'].',').$sqls['limit'];
            }
        }

        return $sql;
    }

    /**
     * Optimize a table.
     *
     * @param string $table_name The table name.
     *
     * @return bool Returns true if successful.
     */
    public function optimizeTable($table_name)
    {
        return $this->query("OPTIMIZE TABLE ".$this->quoteTableName($table_name)) !== false;
    }

    /**
     * Returns a random value for use in SQL queries.
     *
     * @return string The SQL command to generate a random value.
     */
    public function random()
    {
        return 'RAND()';
    }

    /**
     * Quote a table name for use in a query for MySQL.
     *
     * @param string $name The table name.
     * @return string The quoted table name.
     */
    public function quoteTableName($name)
    {
        if (strpos($name, '`') !== false && strpos($name, '.') === false) {
            // Already quoted and not schema.table
            return $name;
        }
        if (strpos($name, '.') !== false) {
            list($database, $table) = explode('.', $name, 2);
            return $this->quoteIdentifier(trim($database)).'.'.$this->quoteIdentifier(trim($table));
        }
        return $this->quoteIdentifier(trim($name));
    }

    /**
     * Repair a table.
     *
     * @param string $table_name The table name.
     *
     * @return bool Returns true if successful.
     */
    public function repairTable($table_name)
    {
        return $this->query("REPAIR TABLE ".$this->quoteTableName($table_name)) !== false;
    }

    /**
     * Retrieve data from the specified table.
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
        $values = [];
        $sql = 'SELECT * FROM '.$this->quoteTableName($table_name);

        if (!empty($condition)) {
            $condition = $this->buildWhere($condition);

            if (is_array($condition)) {
                $values = $condition[1];
                $condition = $condition[0];
            }

            $sql .= ' WHERE '.$condition;
        }

        if (!empty($sort)) {
            if (is_string($sort) && preg_match('/^([a-z0-9_]+)(\s(asc|desc))?$/i', trim($sort), $match)) {
                $sql .= ' ORDER BY '.$this->quoteIdentifier($match[1]).(empty($match[3]) ? ' ASC' : ' '.$match[3]);
            } elseif (is_array($sort)) {
                $qs = [];

                foreach ($sort as $item) {
                    if (preg_match('/^([a-z0-9_]+)(\s(asc|desc))?$/i', trim($item), $match)) {
                        $qs[] = $this->quoteIdentifier($match[1]).(empty($match[3]) ? ' ASC' : ' '.$match[3]);
                    }
                }

                if (count($qs) > 0) {
                    $sql .= ' ORDER BY '.implode(', ', $qs);
                }
            }
        }

        if (is_int($limit) && $limit > 0) {
            $sql .= ' LIMIT '.$limit;
        }

        return $this->doCustomQuery($sql, $values);
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
        $this->settings->dbname = $database;
        $result = $this->connection->query("USE ".$this->quoteIdentifier($database));
        $this->incrementQueryCount();
        return $result !== false;
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
        $result = $this->doCustomQuery("SHOW TABLES LIKE ?", [str_replace($this->quoteIdentifier(''), '', $table_name)]);
        return !empty($result);
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
        $sets = [];
        $values = [];

        foreach ($save as $key => $value) {
            if ($value instanceof QueryBuilder) {
                $sets[] = $this->quoteIdentifier($key).' = ('.$value->text().')';
            } elseif ($value instanceof Sql) {
                $sets[] = $this->quoteIdentifier($key).' = '.$value->text();
                $values = $value->getValues($values);
            } else {
                $k = ':'.$key.count($values);
                $sets[] = $this->quoteIdentifier($key).' = '.$k;
                $values[$k] = $value;
            }
        }

        $q = Sql::WHERE($condition);
        $sql = 'UPDATE '.$this->quoteTableName($table_name).' SET '.implode(', ', $sets).' WHERE '.$q->text();
        $values = $q->getValues($values);

        try {
            $query = $this->connection->prepare($sql);
            $query->execute($values);
            $this->log(__FUNCTION__, $sql, $values);
            $this->incrementQueryCount();
            return $query->rowCount() > 0;
        } catch (\PDOException $e) {
            $this->setError($e->getMessage());
            throw new \Exception($e->getMessage(), 500, $e);
        }
    }

    /**
     * Executes an SQL query to retrieve data and returns the result as an array of matching records.
     *
     * @param string $sql    The SQL query string.
     * @param array  $values If specified, it will use prepared statements instead of direct query execution.
     *
     * @return array|bool Returns an array of records that match the condition on success, or false on failure.
     */
    protected function doCustomQuery($sql, $values = [])
    {
        $action = $this->cache->getAction();
        if ($action) {
            $cache = $this->cache->init($sql, $values);
            $result = $this->cache->get($cache);
        } else {
            $result = false;
        }
        if (!$result) {
            try {
                if (empty($values)) {
                    $this->result_id = $this->connection->query($sql);
                } else {
                    $this->result_id = $this->connection->prepare($sql);
                    $this->result_id->execute($values);
                }
                $this->incrementQueryCount();
                $result = $this->result_id->fetchAll(\PDO::FETCH_ASSOC);
                if ($action == 1) {
                    $this->cache->save($cache, $result);
                } elseif ($action == 2) {
                    $this->cache_item = $cache;
                }
            } catch (\PDOException $e) {
                $this->setError($e->getMessage());
                throw new \Exception($e->getMessage(), 500, $e);
            }
            $this->log('Database', $sql, $values);
        } else {
            $this->cache->setAction(0);
            $this->cache_item = null;
            $this->log('Cached', $sql, $values);
        }
        return $result;
    }

    /**
     * Executes an SQL query that does not require a result set, such as CREATE, INSERT, or UPDATE statements.
     *
     * @param string $sql    The SQL query string.
     * @param array  $values If specified, it will use prepared statements instead of direct query execution.
     *
     * @return int|bool Returns the number of affected rows on success, or false on failure.
     */
    protected function doQuery($sql, $values = [])
    {
        try {
            if (empty($values)) {
                $query = $this->connection->query($sql);
            } else {
                $query = $this->connection->prepare($sql);
                $query->execute($values);
            }
            $this->incrementQueryCount();
            $this->log(__FUNCTION__, $sql, $values);
            return $query->rowCount();
        } catch (\PDOException $e) {
            $this->setError($e->getMessage());
            throw new \Exception($e->getMessage(), 500, $e);
        }
    }

    /**
     * Generates an SQL INSERT statement for saving data.
     *
     * @param string       $table_name The table name.
     * @param array|object $save       The data to be saved in the format array('key1'=>'value1', 'key2'=>'value2', ...).
     * @param array        $params     An array variable to receive parameter values for execution.
     *
     * @return string Returns the generated INSERT statement.
     */
    private function makeInsert($table_name, $save, &$params)
    {
        $keys = [];
        $values = [];
        foreach ($save as $key => $value) {
            if ($value instanceof QueryBuilder) {
                $keys[] = $key;
                $values[] = '('.$value->text().')';
            } elseif ($value instanceof Sql) {
                $keys[] = $key;
                $values[] = $value->text();
                $params = $value->getValues($params);
            } else {
                $keys[] = $key;
                $values[] = ':'.$key;
                $params[':'.$key] = $value;
            }
        }
        return 'INSERT INTO '.$this->quoteTableName($table_name).' ('.$this->quoteIdentifier(implode('`,`', $keys)).') VALUES ('.implode(',', $values).')';
    }
}
