<?php
/**
 * @filesource Kotchasan/Database/QueryBuilder.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 * @author Goragod Wiriya <admin@goragod.com>
 * @package Kotchasan
 */

namespace Kotchasan\Database;

use Kotchasan\ArrayTool;

/**
 * SQL Query builder
 *
 * @setup $driver = new PdoMysqlDriver;
 * @setup $this = $driver->createQuery();
 *
 * @see https://www.kotchasan.com/
 */
class QueryBuilder extends \Kotchasan\Database\Query
{
    /**
     * Return results as Array
     *
     * @var bool
     */
    protected $toArray = false;

    /**
     * Array to store parameters for binding
     *
     * @var array
     */
    protected $values;

    /**
     * Class constructor
     *
     * @param Driver $db database driver
     */
    public function __construct(Driver $db)
    {
        $this->db = $db;
        $this->values = [];
    }

    /**
     * Create WHERE condition with AND operator if previous WHERE exists
     *
     * @param mixed  $condition query string or array
     * @param string $operator  default AND
     * @param string $id        Primary Key like id (default)
     *
     * @return static
     */
    public function andWhere($condition, $operator = 'AND', $id = 'id')
    {
        if (!empty($condition)) {
            $ret = $this->buildWhere($condition, $operator, $id);
            if (is_array($ret)) {
                $this->sqls['where'] = empty($this->sqls['where']) ? $ret[0] : '('.$this->sqls['where'].') AND ('.$ret[0].')';
                $this->values = ArrayTool::replace($this->values, $ret[1]);
            } else {
                $this->sqls['where'] = empty($this->sqls['where']) ? $ret : '('.$this->sqls['where'].') AND ('.$ret.')';
            }
        }
        return $this;
    }

    /**
     * Import properties from another class
     *
     * @param \Kotchasan\Orm\Recordset $src
     *
     * @return static
     */
    public function assignment($src)
    {
        $this->sqls = [
            'function' => 'customQuery',
            'select' => '*'
        ];
        if ($src instanceof \Kotchasan\Orm\Recordset) {
            $this->sqls['from'] = $src->getField()->getTableWithAlias();
        }
        foreach ($src->sqls as $k => $v) {
            $this->sqls[$k] = $v;
        }
        $this->values = $src->getValues();
        return $this;
    }

    /**
     * Enable caching
     * Will check cache before querying data
     *
     * @param bool $auto_save (optional) true (default) automatically save results, false must save cache manually
     *
     * @return static
     */
    public function cacheOn($auto_save = true)
    {
        $this->db()->cacheOn($auto_save);
        return $this;
    }

    /**
     * Copy this class as new instance
     *
     * @return static
     */
    public function copy()
    {
        return clone $this;
    }

    /**
     * Execute SQL command and return number of result rows
     * Returns number of rows
     *
     * @return int
     */
    public function count()
    {
        if (!isset($this->sqls['select'])) {
            $this->selectCount('* count');
        }
        $result = $this->toArray()->execute();
        return count($result) == 1 ? (int) $result[0]['count'] : 0;
    }

    /**
     * Create DELETE command
     *
     * @param string $table
     * @param mixed  $condition query string or array
     *
     * @return static
     */
    public function delete($table, $condition = [])
    {
        $this->sqls['function'] = 'query';
        $this->sqls['delete'] = $this->quoteTableName($table);
        $this->where($condition);
        return $this;
    }

    /**
     * Execute SQL command
     * Returns array of results on success
     *
     * @return mixed
     */
    public function execute()
    {
        $result = $this->db->execQuery($this->sqls, $this->values, $this->debugger);
        if ($this->toArray) {
            $this->toArray = false;
        } elseif (is_array($result)) {
            foreach ($result as $i => $items) {
                $result[$i] = (object) $items;
            }
        }
        return $result;
    }

    /**
     * Create SQL EXISTS
     *
     * @param string $table     table name
     * @param mixed  $condition query WHERE
     *
     * @return static
     */
    public function exists($table, $condition)
    {
        $ret = $this->buildWhere($condition);
        if (is_array($ret)) {
            $this->values = ArrayTool::replace($this->values, $ret[1]);
            $ret = $ret[0];
        }
        if (!isset($this->sqls['exists'])) {
            $this->sqls['exists'] = [];
        }
        $this->sqls['exists'][] = 'EXISTS (SELECT 1 FROM '.$this->quoteTableName($table).' WHERE '.$ret.')';
        return $this;
    }

    /**
     * Command for viewing Query details
     *
     * @return static
     */
    public function explain()
    {
        $this->sqls['explain'] = true;
        return $this;
    }

    /**
     * Create View command
     *
     * @param string $table     table name
     *
     * @return static
     */
    public function createView($table)
    {
        $this->sqls['view'] = $this->quoteTableName($table);
        return $this;
    }

    /**
     * Create temporary table command
     *
     * @param string $table     table name
     *
     * @return static
     */
    public function createTmpTable($table)
    {
        $this->sqls['tmptable'] = $this->quoteTableName($table);
        return $this;
    }

    /**
     * Execute SQL command that requires only one result
     * Returns the single result found, or false if not found
     *
     * @param string $fields (option) field names field1, field2, field3, ...
     *
     * @return mixed
     */
    public function first($fields = '*')
    {
        if (func_num_args() > 1) {
            $fields = func_get_args();
        }
        if (!empty($fields)) {
            // If fields are specified
            call_user_func([$this, 'select'], $fields);
        }
        if (empty($this->sqls['select'])) {
            // Select all fields if no field is selected
            call_user_func([$this, 'select'], '*');
        }
        $this->sqls['limit'] = 1;
        $result = $this->execute();
        return empty($result) ? false : $result[0];
    }

    /**
     * Create FROM command
     *
     * @param string $tables table names table1, table2, table3, ...
     *
     * @return static
     */
    public function from($tables)
    {
        $qs = [];
        foreach (func_get_args() as $table) {
            $qs[] = $this->quoteTableName($table);
        }
        if (count($qs) > 0) {
            $this->sqls['from'] = implode(', ', $qs);
        }
        return $this;
    }

    /**
     * Get array storing parameters for binding combined with $values
     *
     * @param array $values
     *
     * @return array
     */
    public function getValues($values = [])
    {
        if (empty($values)) {
            return $this->values;
        }
        foreach ($this->values as $key => $value) {
            $values[$key] = $value;
        }
        return $values;
    }

    /**
     * GROUP BY
     *
     * @param string $fields field names like field1, field2, ..
     *
     * @return static
     */
    public function groupBy($fields)
    {
        $args = is_array($fields) ? $fields : func_get_args();
        $sqls = [];
        foreach ($args as $item) {
            if ($item instanceof Sql) {
                $sqls[] = $item->text();
            } elseif (preg_match('/^SQL\((.+)\)$/', $item, $match)) {
                // SQL()
                $sqls[] = $match[1];
            } elseif (preg_match('/^(([a-z0-9]+)\.)?([a-z0-9_]+)?$/i', $item, $match)) {
                // column.alias
                $sqls[] = "$match[1]".$this->db->quoteIdentifier($match[3]);
            }
        }
        if (count($sqls) > 0) {
            $this->sqls['group'] = implode(', ', $sqls);
        }
        return $this;
    }

    /**
     * HAVING
     *
     * @param mixed  $condition query string or array
     * @param string $operator   default AND
     *
     * @return static
     */
    public function having($condition, $operator = 'AND')
    {
        if (!empty($condition)) {
            $ret = $this->buildWhere($condition, $operator);
            if (is_array($ret)) {
                $this->sqls['having'] = $ret[0];
                $this->values = ArrayTool::replace($this->values, $ret[1]);
            } else {
                $this->sqls['having'] = $ret;
            }
        }
        return $this;
    }

    /**
     * Create INSERT INTO command
     * Can set value as query string
     *
     * @param string $table table name
     * @param mixed  $datas format array(key1=>value1, key2=>value2)
     * @param array  $fields specify columns if $datas is QueryBuilder
     *
     * @return static
     */
    public function insert($table, $datas, $fields = [])
    {
        $this->sqls['function'] = 'query';
        $this->sqls['insert'] = $this->getFullTableName($table);
        if ($datas instanceof QueryBuilder || $datas instanceof Sql) {
            $this->sqls['keys'] = $fields;
            $this->sqls['select'] = $datas->text();
        } elseif (is_array($datas)) {
            foreach ($datas as $key => $value) {
                if (preg_match('/^SQL\((.+)\)$/', $value, $match)) {
                    // SQL()
                    $this->sqls['keys'][$key] = '('.$match[1].')';
                } else {
                    $this->sqls['keys'][$key] = ':'.$key;
                    $this->values[':'.$key] = $value;
                }
            }
        }
        return $this;
    }

    /**
     * Create INSERT INTO command
     * Check KEY if exists it will UPDATE data
     *
     * @param string $table table name
     * @param array  $datas format array(key1=>value1, key2=>value2)
     *
     * @return static
     */
    public function insertOrUpdate($table, $datas)
    {
        $this->insert($table, $datas);
        $this->sqls['orupdate'] = [];
        foreach ($datas as $key => $value) {
            $this->sqls['orupdate'][] = "`$key`=VALUES(`$key`)";
        }
        return $this;
    }

    /**
     * Create JOIN command
     *
     * @param string|array $table table name must have alias or (QueryBuilder, alias)
     * @param string       $type  like INNER OUTER LEFT RIGHT
     * @param mixed        $on    query string or array
     *
     * @return static
     */
    public function join($table, $type, $on)
    {
        $ret = $this->buildJoin($table, $type, $on);
        if (is_array($ret)) {
            $this->sqls['join'][] = $ret[0];
            $this->values = ArrayTool::replace($this->values, $ret[1]);
        } else {
            $this->sqls['join'][] = $ret;
        }
        return $this;
    }

    /**
     * Limit results and set starting record
     *
     * @param int $count number of results needed
     * @param int $start starting record
     *
     * @return static
     */
    public function limit($count, $start = 0)
    {
        if (!empty($start)) {
            $this->sqls['start'] = (int) $start;
        }
        if (!empty($count)) {
            $this->sqls['limit'] = (int) $count;
        }
        return $this;
    }

    /**
     * Create SQL NOT EXISTS
     *
     * @param string $table     table name
     * @param mixed  $condition query WHERE
     * @param string $operator  (optional) like AND or OR
     *
     * @return static
     */
    public function notExists($table, $condition, $operator = 'AND')
    {
        $ret = $this->buildWhere($condition, $operator);
        if (is_array($ret)) {
            $this->values = ArrayTool::replace($this->values, $ret[1]);
            $ret = $ret[0];
        }
        if (!isset($this->sqls['exists'])) {
            $this->sqls['exists'] = [];
        }
        $this->sqls['exists'][] = 'NOT EXISTS (SELECT 1 FROM '.$this->quoteTableName($table).' WHERE '.$ret.')';
        return $this;
    }

    /**
     * Create WHERE condition with OR operator if previous WHERE exists
     *
     * @param mixed  $condition query string or array
     * @param string $operator   default AND
     * @param string $id        Primary Key like id (default)
     *
     * @return static
     */
    public function orWhere($condition, $operator = 'AND', $id = 'id')
    {
        if (!empty($condition)) {
            $ret = $this->buildWhere($condition, $operator, $id);
            if (is_array($ret)) {
                $this->sqls['where'] = empty($this->sqls['where']) ? $ret[0] : '('.$this->sqls['where'].') OR ('.$ret[0].')';
                $this->values = ArrayTool::replace($this->values, $ret[1]);
            } else {
                $this->sqls['where'] = empty($this->sqls['where']) ? $ret : '('.$this->sqls['where'].') OR ('.$ret.')';
            }
        }
        return $this;
    }

    /**
     * Create ORDER BY query
     *
     * @param mixed $columns array('field ASC','field DESC') or 'field ASC', 'field DESC', ...
     *
     * @return static
     */
    public function order($columns)
    {
        $columns = is_array($columns) ? $columns : func_get_args();
        $ret = $this->buildOrder($columns);
        if (!empty($ret)) {
            $this->sqls['order'] = $ret;
        }
        return $this;
    }

    /**
     * SELECT `field1`, `field2`, `field3`, ...
     *
     * @param string $fields (option) field names field1, field2, field3, ...
     *
     * @return static
     */
    public function select($fields = '*')
    {
        $qs = [];
        if ($fields == '*') {
            $qs[] = '*';
        } else {
            foreach (func_get_args() as $item) {
                if (!empty($item)) {
                    $qs[] = $this->buildSelect($item);
                }
            }
        }
        if (count($qs) > 0) {
            $this->sqls['function'] = 'customQuery';
            $this->sqls['select'] = implode(',', $qs);
        }
        return $this;
    }

    /**
     * Create query for counting records
     *
     * @param mixed $fields (option) 'field alias'
     *
     * @return static
     */
    public function selectCount($fields = '* count')
    {
        $args = func_num_args() == 0 ? [$fields] : func_get_args();
        $sqls = [];
        foreach ($args as $item) {
            if (preg_match('/^([a-z0-9_\*]+)([\s]+([a-z0-9_]+))?$/', trim($item), $match)) {
                $sqls[] = 'COUNT('.($match[1] == '*' ? '*' : $this->db->quoteIdentifier($match[1])).')'.(isset($match[3]) ? ' AS '.$this->db->quoteIdentifier($match[3]) : '');
            }
        }
        if (count($sqls) > 0) {
            $this->sqls['function'] = 'customQuery';
            $this->sqls['select'] = implode(', ', $sqls);
        }
        return $this;
    }

    /**
     * SELECT DISTINCT `field1`, `field2`, `field3`, ...
     *
     * @param string $fields (option) field names field1, field2, field3, ...
     *
     * @return static
     */
    public function selectDistinct($fields = '*')
    {
        call_user_func([$this, 'select'], func_get_args());
        $this->sqls['select'] = 'DISTINCT '.$this->sqls['select'];
        return $this;
    }

    /**
     * UPDATE ..... SET
     *
     * @param array|string $datas format array(key1 => value1, query_string) or query_string
     *
     * @return static
     */
    public function set($datas)
    {
        if (is_array($datas) || is_object($datas)) {
            foreach ($datas as $key => $value) {
                if (is_int($key)) {
                    $this->sqls['set'][$value] = $value;
                } else {
                    $field = $this->fieldName($key);
                    $key = $this->aliasName($key, 'S');
                    if ($value instanceof QueryBuilder) {
                        $this->sqls['set'][$key] = $field.'=('.$value->text().')';
                    } elseif ($value instanceof Sql) {
                        $this->sqls['set'][$key] = $field.'='.$value->text();
                    } elseif (is_string($value)) {
                        if (preg_match('/^([A-Z][0-9]{0,2})\.`?([A-Za-z0-9_]+)`?$/', $value, $match)) {
                            $this->sqls['set'][$key] = $field.'='.$match[1].'.'.$this->db->quoteIdentifier($match[2]);
                        } elseif (mb_strlen($value) > 2 && $value[0] === '(' && $value[mb_strlen($value) - 1] === ')') {
                            $this->sqls['set'][$key] = $field.'='.$value;
                        } else {
                            $this->sqls['set'][$key] = $field.'='.$key;
                            $this->sqls['values'][$key] = $value;
                        }
                    } else {
                        $this->sqls['set'][$key] = $field.'='.$key;
                        $this->sqls['values'][$key] = $value;
                    }
                }
            }
        } else {
            $this->sqls['set'][$datas] = $datas;
        }
        return $this;
    }

    /**
     * Return data as Array
     * This function is used before querying data
     *
     * @return static
     */
    public function toArray()
    {
        $this->toArray = true;
        return $this;
    }

    /**
     * UNION
     *
     * @param array $querys array of QueryBuilder or Query String for UNION
     *
     * @return static
     */
    public function union($querys)
    {
        $this->sqls['union'] = [];
        $querys = is_array($querys) ? $querys : func_get_args();
        foreach ($querys as $item) {
            if ($item instanceof QueryBuilder || $item instanceof Sql) {
                $this->sqls['union'][] = $item->text();
            } elseif (is_string($item)) {
                $this->sqls['union'][] = $item;
            } else {
                throw new \InvalidArgumentException('Invalid arguments in union');
            }
        }
        $this->sqls['function'] = 'customQuery';
        return $this;
    }

    /**
     * UNION ALL
     *
     * @param array $querys array of QueryBuilder or Query String for UNION ALL
     *
     * @return static
     */
    public function unionAll($querys)
    {
        $this->sqls['unionAll'] = [];
        $querys = is_array($querys) ? $querys : func_get_args();
        foreach ($querys as $item) {
            if ($item instanceof QueryBuilder || $item instanceof Sql) {
                $this->sqls['unionAll'][] = $item->text();
            } elseif (is_string($item)) {
                $this->sqls['unionAll'][] = $item;
            } else {
                throw new \InvalidArgumentException('Invalid arguments in unionAll');
            }
        }
        $this->sqls['function'] = 'customQuery';
        return $this;
    }

    /**
     * UPDATE
     *
     * @param string $table table name
     *
     * @return static
     */
    public function update($table)
    {
        $this->sqls['function'] = 'query';
        $updates = [];
        foreach (func_get_args() as $tbl) {
            $updates[] = $this->quoteTableName($tbl);
        }
        $this->sqls['update'] = implode(',', $updates);
        return $this;
    }

    /**
     * Empty table
     *
     * @param string $table table name
     *
     * @return static
     */
    public function emptyTable($table)
    {
        $this->db->emptyTable($this->quoteTableName($table));
        return $this;
    }

    /**
     * Create WHERE command
     *
     * @param mixed  $condition query string or array
     * @param string $operator   default AND
     * @param string $id        Primary Key like id (default)
     *
     * @return static
     */
    public function where($condition, $operator = 'AND', $id = 'id')
    {
        if (!empty($condition)) {
            $sql = Sql::WHERE($condition, $operator, $id);
            $this->sqls['where'] = $sql->text();
            $this->values = $sql->getValues($this->values);
        }
        return $this;
    }

    /**
     * Execute SQL command with special handling for insertOrUpdate
     *
     * @return mixed
     */
    protected function executeSpecial()
    {
        if (isset($this->sqls['function']) && $this->sqls['function'] === 'insertOrUpdate') {
            // Call driver's insertOrUpdate method directly
            return $this->db->insertOrUpdate($this->sqls['table'], $this->sqls['datas']);
        }
        return $this->execute();
    }

    /**
     * Enhanced execute method that handles special cases
     *
     * @return mixed
     */
    public function save()
    {
        if (isset($this->sqls['function']) && $this->sqls['function'] === 'insertOrUpdate') {
            return $this->executeSpecial();
        }
        return $this->execute();
    }
}
