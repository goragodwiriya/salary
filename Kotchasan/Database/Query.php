<?php
/**
 * @filesource Kotchasan/Database/Query.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 * @author Goragod Wiriya <admin@goragod.com>
 * @package Kotchasan
 */

namespace Kotchasan\Database;

use Kotchasan\ArrayTool;

/**
 * Database Query (base class)
 *
 * @see https://www.kotchasan.com/
 */
abstract class Query extends \Kotchasan\Database\Db
{
    /**
     * Debug mode
     * 1 Show Query text only for console
     * 2 Show Query at bottom of screen
     *
     * @var int
     */
    protected $debugger = 0;

    /**
     * Variable to store SQL commands
     *
     * @var array
     */
    protected $sqls;

    /**
     * Database Driver
     *
     * @var mixed
     */
    protected $db;

    /**
     * Command to display Query on screen
     * Used for debugging Query
     * 1 Show Query text only for console
     * 2 Show Query at bottom of screen
     *
     * @param int $value
     */
    public function debug($value = 1)
    {
        $this->debugger = $value;
        return $this;
    }

    /**
     * Function to read table name from database settings
     * Returns table name with prefix. If no name is defined, returns $table wrapped with table quotes
     *
     * @param string $table table name as defined in settings/database.php
     *
     * @return string
     */
    public function getFullTableName($table)
    {
        $dbname = empty($this->db->settings->dbname) ? '' : $this->quoteIdentifier($this->db->settings->dbname).'.';
        return $dbname.$this->db->quoteTableName($this->getTableName($table));
    }

    /**
     * Function to read table name from database settings
     * Returns table name with prefix. If no name is defined, returns $table
     *
     * @param string $table table name as defined in settings/database.php
     *
     * @return string
     */
    public function getTableName($table)
    {
        $prefix = empty($this->db->settings->prefix) ? '' : $this->db->settings->prefix.'_';
        return $prefix.(isset($this->db->tables->$table) ? $this->db->tables->$table : $table);
    }

    /**
     * Function to create SQL command as text
     *
     * @return string
     */
    public function text()
    {
        $sql = '';
        if (!empty($this->sqls)) {
            $sql = $this->db->makeQuery($this->sqls);
            foreach (array_reverse($this->getValues()) as $key => $value) {
                $sql = str_replace($key, (is_string($value) ? "'$value'" : $value), $sql);
            }
        }
        return $sql;
    }

    /**
     * Function to create key for execution
     *
     * @param string $name   field name
     * @param string $prefix prefix for field name to prevent duplicate variables
     *
     * @return string
     */
    protected function aliasName($name, $prefix = '')
    {
        return ':'.$prefix.trim(preg_replace('/[`\._\-]/', '', $name));
    }

    /**
     * Create query for GROUP BY
     *
     * @param array|string $fields array('U.id', 'U.username') or string U.id
     *
     * @return string
     */
    protected function buildGroup($fields)
    {
        $sqls = [];
        foreach ((array) $fields as $item) {
            $sqls[] = $this->fieldName($item);
        }
        return empty($sqls) ? '' : implode(', ', $sqls);
    }

    /**
     * Create JOIN command
     * Returns empty if no alias
     *
     * @param string|array $table table name must have alias or (QueryBuilder, alias)
     * @param string       $type  like INNER OUTER LEFT RIGHT
     * @param mixed        $on    query string or array
     *
     * @return string
     */
    protected function buildJoin($table, $type, $on)
    {
        $ret = $this->buildWhere($on);
        $sql = is_array($ret) ? $ret[0] : $ret;
        if (is_array($table)) {
            $sql = ' '.$type.' JOIN ('.$table[0]->text().') AS '.$table[1].' ON '.$sql;
        } elseif (preg_match('/^([a-zA-Z0-9_]+)([\s]+(as|AS))?[\s]+([A-Z0-9]{1,3})$/', $table, $match)) {
            $sql = ' '.$type.' JOIN '.$this->getFullTableName($match[1]).' AS '.$match[4].' ON '.$sql;
        } elseif (preg_match('/^([a-z0-9_]+)([\s]+(as|AS))?[\s]+([a-z0-9_]+)$/', $table, $match)) {
            $sql = ' '.$type.' JOIN '.$this->getFullTableName($match[1]).' AS '.$this->quoteIdentifier($match[4]).' ON '.$sql;
        } else {
            $sql = ' '.$type.' JOIN '.$table.' ON '.$sql;
        }
        if (is_array($ret)) {
            return [$sql, $ret[1]];
        } else {
            return $sql;
        }
    }

    /**
     * Create order query
     *
     * @param array|string $fields array('field ASC','field DESC') or 'field ASC', 'field DESC', ...
     *
     * @return string
     */
    protected function buildOrder($fields)
    {
        $sqls = [];
        foreach ((array) $fields as $item) {
            if ($item instanceof QueryBuilder || $item instanceof Sql) {
                // QueryBuilder
                $sqls[] = $item->text();
            } elseif (preg_match('/^SQL\((.+)\)$/', $item, $match)) {
                // SQL command
                $sqls[] = $match[1];
            } elseif (preg_match('/^([A-Z][A-Z0-9]{0,2}\.)([a-zA-Z0-9_]+)([\s]{1,}(ASC|DESC|asc|desc))?$/', $item, $match)) {
                // U.id DESC
                $sqls[] = $match[1].$this->quoteIdentifier($match[2]).(isset($match[4]) ? " $match[4]" : '');
            } elseif (preg_match('/^([a-zA-Z0-9_]+)(\.([a-zA-Z0-9_]+))?(([\s]+)?(ASC|DESC|asc|desc))?$/', $item, $match)) {
                // field.id DESC
                $sqls[] = $this->quoteIdentifier($match[1]).(empty($match[3]) ? '' : '.'.$this->quoteIdentifier($match[3])).(isset($match[6]) ? " $match[6]" : '');
            } elseif (strtoupper($item) === 'RAND()') {
                // RAND() - handle database specific random functions
                $sqls[] = $this->db->random();
            }
        }
        return implode(', ', $sqls);
    }

    /**
     * Function to create query string for SELECT command
     *
     * @param string|array|QueryBuilder $fields
     * @param mixed $alias
     *
     * @return string
     */
    protected function buildSelect($fields, $alias = 0)
    {
        if (is_array($fields)) {
            if (isset($fields[0])) {
                if ($fields[0] instanceof QueryBuilder) {
                    // QueryBuilder
                    $ret = '('.$fields[0]->text().') AS '.$this->quoteIdentifier($fields[1]);
                } elseif (is_string($fields[0]) && preg_match('/^([a-zA-Z0-9\\\]+)::([a-zA-Z0-9]+)$/', $fields[0], $match)) {
                    // Recordset
                    $ret = '\''.addslashes($fields[0]).'\' AS '.$this->quoteIdentifier($fields[1]);
                } else {
                    // multiples
                    $rets = [];
                    foreach ($fields as $k => $item) {
                        $rets[] = $this->buildSelect($item, $k);
                    }
                    $ret = implode(',', $rets);
                }
            } else {
                // array($alias => $column)
                $k = key($fields);
                $ret = $this->buildSelect($fields[$k], $k);
            }
        } elseif ($fields instanceof QueryBuilder || $fields instanceof Sql) {
            // QueryBuilder
            $ret = $fields->text();
        } elseif ($fields == '*') {
            $ret = '*';
        } elseif (preg_match('/^(NULL|[0-9]+)([\s]+as)?[\s]+`?([^`]+)`?$/i', $fields, $match)) {
            // 0 as alias, NULL as alias
            $ret = $match[1].' AS '.$this->quoteIdentifier($match[3]);
        } elseif (preg_match('/^([\'"])(.*)\\1([\s]+as)?[\s]+`?([^`]+)`?$/i', $fields, $match)) {
            // 'string' as alias
            $ret = "'$match[2]' AS ".$this->quoteIdentifier($match[4]);
        } elseif (preg_match('/^([A-Z][A-Z0-9]{0,2})\.`?([\*a-zA-Z0-9_]+)`?(([\s]+(as|AS))?[\s]+`?([^`]+)`?)?$/', $fields, $match)) {
            if (is_string($alias)) {
                // U.id alias U.* AS $alias
                $ret = $match[1].'.'.($match[2] == '*' ? '*' : $this->quoteIdentifier($match[2])).' AS '.$this->quoteIdentifier($alias);
            } else {
                // U.id alias U.* AS alias
                $ret = $match[1].'.'.($match[2] == '*' ? '*' : $this->quoteIdentifier($match[2])).(isset($match[6]) ? ' AS '.$this->quoteIdentifier($match[6]) : '');
            }
        } elseif (preg_match('/^`?([a-z0-9_]+)`?\.`?([\*a-z0-9_]+)`?(([\s]+as)?[\s]+`?([^`]+)`?)?$/i', $fields, $match)) {
            // table.field alias
            $ret = $this->quoteIdentifier($match[1]).'.'.($match[2] == '*' ? '*' : $this->quoteIdentifier($match[2])).(isset($match[5]) ? ' AS '.$this->quoteIdentifier($match[5]) : '');
        } elseif (preg_match('/^`?([a-z0-9_]+)`?([\s]+as)?[\s]+`?([^`]+)`?$/i', $fields, $match)) {
            // field AS alias
            $ret = $this->quoteIdentifier($match[1]).' AS '.$this->quoteIdentifier($match[3]);
        } elseif (preg_match('/^SQL\((.+)\)$/', $fields, $match)) {
            // SQL command
            $ret = $match[1];
        } elseif (preg_match('/([a-z0-9_]+)/i', $fields, $match)) {
            // field name like id
            $ret = $this->quoteIdentifier($fields);
        }
        return isset($ret) ? $ret : '';
    }

    /**
     * Convert data to SQL format
     * Format array('field1', 'condition', 'field2')
     * No condition specified means = or IN
     *
     * @param array $params
     *
     * @return string
     */
    protected function buildValue($params)
    {
        if (is_array($params)) {
            if (count($params) == 2) {
                $params = [$params[0], '=', $params[1]];
            } else {
                $params = [$params[0], trim($params[1]), $params[2]];
            }
            $key = $this->fieldName($params[0]);
            if (is_numeric($params[2]) || is_bool($params[2])) {
                // value is number or boolean
                $value = $params[2];
            } elseif (is_array($params[2])) {
                // value is array
                if ($params[1] == '=') {
                    $params[1] = 'IN';
                }
                $qs = [];
                foreach ($params[2] as $item) {
                    if (is_numeric($item) || is_bool($item)) {
                        $qs[] = $item;
                    } else {
                        $qs[] = "'$item'";
                    }
                }
                $value = '('.implode(', ', $qs).')';
            } elseif (preg_match('/^\((.*)\)([\s]+as)?[\s]+([a-z0-9_]+)$/i', $params[2], $match)) {
                // value is query string
                $value = "($match[1]) AS ".$this->quoteIdentifier($match[3]);
            } elseif (preg_match('/^([A-Z][A-Z0-9]{0,2})\.([a-zA-Z0-9_]+)$/', $params[2], $match)) {
                // U.id
                $value = $match[1].'.'.$this->quoteIdentifier($match[2]);
            } elseif (preg_match('/^([a-z0-9_]+)\.([a-z0-9_]+)$/i', $params[2], $match)) {
                // value is table.field
                $value = $this->quoteIdentifier($match[1]).'.'.$this->quoteIdentifier($match[2]);
            } else {
                // value is string
                $value = "'".$params[2]."'";
            }
            $params = $key.' '.$params[1].' '.$value;
        }
        return $params;
    }

    /**
     * Function to create WHERE command
     * Returns string for WHERE command or returns array(where, values) for use with bind
     *
     * @param mixed  $condition
     * @param string $operator  (optional) like AND or OR
     * @param string $id        (optional) field name that is key
     *
     * @return string|array
     */
    protected function buildWhere($condition, $operator = 'AND', $id = 'id')
    {
        if (is_array($condition)) {
            if (is_array($condition[0])) {
                $qs = [];
                $ps = [];
                foreach ($condition as $i => $item) {
                    $ret = $this->whereValue($item, $i);
                    if (is_array($ret)) {
                        $qs[] = $ret[0];
                        $ps += $ret[1];
                    } else {
                        $qs[] = $ret;
                    }
                }
                $ret = implode(' '.$operator.' ', $qs);
                if (!empty($ps)) {
                    $ret = [$ret, $ps];
                }
            } elseif ($condition[0] instanceof Sql) {
                $qs = [];
                $ps = [];
                foreach ($condition as $i => $item) {
                    $qs[] = $item->text();
                    $ps = array_merge($ps, $item->getValues([]));
                }
                $ret = implode(' '.$operator.' ', $qs);
                if (!empty($ps)) {
                    $ret = [$ret, $ps];
                }
            } else {
                $ret = $this->whereValue($condition);
            }
        } elseif ($condition instanceof Sql) {
            $values = $condition->getValues([]);
            if (empty($values)) {
                $ret = $condition->text();
            } else {
                $ret = [$condition->text(), $values];
            }
        } elseif (preg_match('/^[0-9]+$/', $condition)) {
            // primaryKey
            $ret = $this->fieldName($id).' = '.$condition;
        } else {
            // Invalid parameters
            trigger_error('Invalid arguments in buildWhere('.var_export($condition, true).')', E_USER_ERROR);
        }
        return $ret;
    }

    /**
     * Function to create WHERE command and values without alias for field names
     * Returns ($condition, $values)
     *
     * @param mixed  $condition
     * @param string $operator  (optional) like AND or OR
     * @param string $id        (optional) field name that is key
     *
     * @return array
     */
    protected function buildWhereValues($condition, $operator = 'AND', $id = 'id')
    {
        if (is_array($condition)) {
            $values = [];
            $qs = [];
            if (is_array($condition[0])) {
                foreach ($condition as $item) {
                    $ret = $this->buildWhereValues($item, $operator, $id);
                    $qs[] = $ret[0];
                    $values = ArrayTool::replace($values, $ret[1]);
                }
                $condition = implode(' '.$operator.' ', $qs);
            } elseif (strpos($condition[0], '(') !== false) {
                $condition = $condition[0];
            } else {
                if (count($condition) == 2) {
                    $condition = [$condition[0], '=', $condition[1]];
                } else {
                    $condition[1] = strtoupper(trim($condition[1]));
                }
                if (is_array($condition[2])) {
                    $operator = $condition[1] == '=' ? 'IN' : $condition[1];
                    $qs = [];
                    foreach ($condition[2] as $k => $v) {
                        $qs[] = ":$condition[0]$k";
                        $values[":$condition[0]$k"] = $v;
                    }
                    $condition = $this->fieldName($condition[0]).' '.$operator.' ('.implode(',', $qs).')';
                } else {
                    $values[":$condition[0]"] = $condition[2];
                    $condition = $this->fieldName($condition[0]).' '.$condition[1].' :'.$condition[0];
                }
            }
        } elseif (is_numeric($condition)) {
            // primaryKey
            $values = [":$id" => $condition];
            $condition = $this->quoteIdentifier($id)." = :$id";
        } else {
            $values = [];
        }
        return [$condition, $values];
    }

    /**
     * Convert text for field name or table name
     *
     * @param string $name
     *
     * @return string
     */
    protected function fieldName($name)
    {
        if (is_array($name)) {
            if ($name[0] instanceof QueryBuilder) {
                $ret = '('.$name[0]->text().') AS '.$this->quoteIdentifier($name[1]);
            } else {
                $rets = [];
                foreach ($name as $item) {
                    $rets[] = $this->fieldName($item);
                }
                $ret = implode(', ', $rets);
            }
        } elseif (is_numeric($name)) {
            $ret = $name;
        } elseif (is_string($name)) {
            $name = trim($name);
            if (strpos($name, '(') !== false && preg_match('/^(.*?)(\s{0,}(as)?\s{0,}`?([a-z0-9_]+)`?)?$/i', $name, $match)) {
                // (...) as pos
                $ret = $match[1].(isset($match[4]) ? " AS ".$this->quoteIdentifier($match[4]) : '');
            } elseif (preg_match('/^([A-Z][A-Z0-9]{0,2})\.([\*a-zA-Z0-9_]+)((\s+(as|AS))?\s+([a-zA-Z0-9_]+))?$/', $name, $match)) {
                // U.id as user_id U.*
                $ret = $match[1].'.'.($match[2] == '*' ? '*' : $this->quoteIdentifier($match[2])).(isset($match[6]) ? ' AS '.$this->quoteIdentifier($match[6]) : '');
            } elseif (preg_match('/^`?([a-z0-9_]+)`?\.([\*a-z0-9_]+)(([\s]+as)?[\s]+([a-z0-9_]+))?$/i', $name, $match)) {
                // `user`.id, user.id as user_id
                $ret = $this->quoteIdentifier($match[1]).'.'.($match[2] == '*' ? '*' : $this->quoteIdentifier($match[2])).(isset($match[5]) ? ' AS '.$this->quoteIdentifier($match[5]) : '');
            } elseif (preg_match('/^([a-z0-9_]+)(([\s]+as)?[\s]+([a-z0-9_]+))?$/i', $name, $match)) {
                // user as user_id
                $ret = $this->quoteIdentifier($match[1]).(isset($match[4]) ? ' AS '.$this->quoteIdentifier($match[4]) : '');
            } else {
                $ret = $name == '*' ? '*' : $this->quoteIdentifier($name);
            }
        } elseif ($name instanceof QueryBuilder || $name instanceof Sql) {
            $ret = $name->text();
        } else {
            // Invalid parameters
            trigger_error('Invalid arguments in fieldName('.var_export($name, true).')', E_USER_ERROR);
        }
        return $ret;
    }

    /**
     * Convert text for value
     *
     * @param string $value
     *
     * @return string
     */
    protected function fieldValue($value)
    {
        if (is_array($value)) {
            $rets = [];
            foreach ($value as $item) {
                $rets[] = $this->fieldValue($item);
            }
            $ret = '('.implode(', ', $rets).')';
        } elseif (is_numeric($value)) {
            $ret = $value;
        } elseif (preg_match('/^([a-z0-9_]+)\.([a-z0-9_]+)(([\s]+as)?[\s]+([a-z0-9]+))?$/i', $value, $match)) {
            $ret = $this->quoteIdentifier($match[1]).'.'.$this->quoteIdentifier($match[2]).(isset($match[5]) ? ' AS '.$this->quoteIdentifier($match[5]) : '');
        } else {
            $ret = '\''.$value.'\'';
        }
        return $ret;
    }

    /**
     * Function to group commands and connect each group with AND
     *
     * @param array $params commands format array('field1', 'condition', 'field2')
     *
     * @return \Sql
     */
    protected function groupAnd($params)
    {
        if (func_num_args() > 1) {
            $params = func_get_args();
        }
        $sqls = [];
        foreach ($params as $i => $item) {
            $sqls[] = $this->buildValue($item);
        }
        return Sql::create('('.implode(' AND ', $sqls).')');
    }

    /**
     * Function to group commands and connect each group with OR
     *
     * @param array $params commands format array('field1', 'condition', 'field2')
     *
     * @return \Sql
     */
    protected function groupOr($params)
    {
        if (func_num_args() > 1) {
            $params = func_get_args();
        }
        $sqls = [];
        foreach ($params as $i => $item) {
            $sqls[] = $this->buildValue($item);
        }
        return Sql::create('('.implode(' OR ', $sqls).')');
    }

    /**
     * Function to read table name and alias and wrap table name with quotes
     *
     * @param string $table table name as defined in settings/database.php
     *
     * @return string
     */
    protected function quoteTableName($table)
    {
        if (is_array($table)) {
            if ($table[0] instanceof QueryBuilder) {
                $table = '('.$table[0]->text().') AS '.$table[1];
            } else {
                $table = '('.$table[0].') AS '.$table[1];
            }
        } elseif (preg_match('/^([a-zA-Z0-9_]+)(\s+(as|AS))?[\s]+([A-Z0-9]{1,3})$/', $table, $match)) {
            $table = $this->getFullTableName($match[1]).' AS '.$match[4];
        } elseif (preg_match('/^([a-zA-Z0-9_]+)(\s+(as|AS))?[\s]+([a-zA-Z0-9]+)$/', $table, $match)) {
            $table = $this->getFullTableName($match[1]).' AS '.$this->quoteIdentifier($match[4]);
        } else {
            $table = $this->getFullTableName($table);
        }
        return $table;
    }

    /**
     * Get database type from driver
     *
     * @return string
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
        return 'mysql'; // default
    }

    /**
     * Create WHERE command
     *
     * @param array    $params
     * @param int|null $i
     *
     * @return array|string
     */
    private function whereValue($params, $i = null)
    {
        $result = [];
        if (is_array($params)) {
            if (count($params) == 2) {
                $operator = '=';
                $value = $params[1];
            } else {
                $operator = trim($params[1]);
                $value = $params[2];
            }
            $key = $this->fieldName($params[0]);
            if ($value instanceof QueryBuilder) {
                if ($operator == '=') {
                    $operator = 'IN';
                }
                $values = $value->getValues([]);
                if (empty($values)) {
                    $result = $key.' '.$operator.' ('.$value->text().')';
                } else {
                    $result = [$key.' '.$operator.' ('.$value->text().')', $values];
                }
            } elseif ($value instanceof Sql) {
                if ($operator == '=') {
                    $operator = 'IN';
                }
                $values = $value->getValues([]);
                if (empty($values)) {
                    $result = $key.' '.$operator.' ('.$value->text().')';
                } else {
                    $result = [$key.' '.$operator.' ('.$value->text().')', $values];
                }
            } elseif (is_array($value)) {
                if ($operator == '=') {
                    $operator = 'IN';
                }
                $q = $this->aliasName($key);
                $qs = [];
                $vs = [];
                foreach ($value as $a => $item) {
                    if (empty($item)) {
                        if (is_string($item)) {
                            $qs[] = "'$item'";
                        } elseif (is_numeric($item)) {
                            $qs[] = $item;
                        }
                    } elseif (is_string($item)) {
                        if (preg_match('/^([A-Z][A-Z0-9]{0,2})\.`?([a-zA-Z0-9_\-]+)`?$/', $item, $match)) {
                            $qs[] = "$match[1].".$this->quoteIdentifier($match[2]);
                        } elseif (preg_match('/^`([a-zA-Z0-9_\-]+)`$/', $item, $match)) {
                            $qs[] = $this->quoteIdentifier($match[1]);
                        } else {
                            $k = $q.($i === null ? '' : $i).$a;
                            $qs[] = $k;
                            $vs[$k] = $item;
                        }
                    } else {
                        $k = $q.($i === null ? '' : $i).$a;
                        $qs[] = $k;
                        $vs[$k] = $item;
                    }
                }
                $result = [$key.' '.$operator.' ('.implode(', ', $qs).')', $vs];
            } elseif ($value === null) {
                if ($operator == '=') {
                    $result = $key.' IS NULL';
                } else {
                    $result = $key.' IS NOT NULL';
                }
            } elseif (empty($value)) {
                // value is empty string, 0
                $result = $key.' '.$operator.' '.(is_string($value) ? "'$value'" : $value);
            } elseif (preg_match('/^(\-?[0-9\s\.]+|true|false)$/i', $value)) {
                // value is number, decimal, -, /, , and true, false
                // like number, money, boolean
                $result = "$key $operator ".(is_string($value) ? "'$value'" : $value);
            } elseif (preg_match('/^SQL\((.+)\)$/', $value, $match)) {
                // SQL()
                $result = "$key $operator ($match[1])";
            } elseif (preg_match('/^[0-9\s\-:]+$/', $value)) {
                // date
                $result = "$key $operator '$value'";
            } elseif (preg_match('/^([A-Z][A-Z0-9]{0,2})\.([a-zA-Z0-9_\-]+)$/', $value, $match)) {
                // U.id
                if ($operator == 'IN' || $operator == 'NOT IN') {
                    $result = "$key $operator ($match[1].".$this->quoteIdentifier($match[2]).")";
                } else {
                    $result = "$key $operator $match[1].".$this->quoteIdentifier($match[2]);
                }
            } elseif (preg_match('/^`([a-zA-Z0-9_\-]+)`$/', $value, $match)) {
                // `id`
                if ($operator == 'IN' || $operator == 'NOT IN') {
                    $result = "$key $operator (".$this->quoteIdentifier($match[1]).")";
                } else {
                    $result = "$key $operator ".$this->quoteIdentifier($match[1]);
                }
            } else {
                // value is string
                $q = ':'.preg_replace('/[\.`]/', '', strtolower($key)).($i === null ? '' : $i);
                $result = [$key.' '.$operator.' '.$q, [$q => $value]];
            }
        } elseif ($params instanceof QueryBuilder) {
            $values = $params->getValues([]);
            if (empty($values)) {
                $result = '('.$params->text().')';
            } else {
                $result = ['('.$params->text().')', $values];
            }
        } elseif ($params instanceof Sql) {
            $result = $params->text();
        } elseif (preg_match('/^SQL\((.+)\)$/', $params, $match)) {
            // SQL()
            $result = '('.$match[1].')';
        }
        return $result;
    }
}
