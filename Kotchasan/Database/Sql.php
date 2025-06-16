<?php
/**
 * @filesource Kotchasan/Database/Sql.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 * @author Goragod Wiriya <admin@goragod.com>
 * @package Kotchasan
 */

namespace Kotchasan\Database;

/**
 * SQL Function Helper
 *
 * @see https://www.kotchasan.com/
 */
class Sql
{
    /**
     * SQL statement stored here
     *
     * @var string
     */
    protected $sql;

    /**
     * Array to store parameters for binding
     *
     * @var array
     */
    protected $values;

    /**
     * Database type context for SQL generation
     *
     * @var string
     */
    protected static $database_type = 'mysql';

    /**
     * Set database type for context-aware SQL generation
     *
     * @param string $type Database type: mysql, mssql, postgresql
     */
    public static function setDatabaseType($type)
    {
        self::$database_type = strtolower($type);
    }

    /**
     * Get current database type
     *
     * @return string
     */
    public static function getDatabaseType()
    {
        return self::$database_type;
    }

    /**
     * Calculate the average of the selected column
     *
     * @param string      $column_name The name of the column to calculate the average for
     * @param string|null $alias       The alias for the resulting column, optional
     * @param bool        $distinct    If true, calculates the average of distinct values only; default is false
     *
     * @return static
     */
    public static function AVG($column_name, $alias = null, $distinct = false)
    {
        $expression = 'AVG('.($distinct ? 'DISTINCT ' : '').self::fieldName($column_name).')';
        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }
        return self::create($expression);
    }

    /**
     * Generate a SQL BETWEEN ... AND ... clause
     *
     * @param string $column_name The name of the column for the BETWEEN clause
     * @param string $min The minimum value for the range
     * @param string $max The maximum value for the range
     *
     * @return static
     */
    public static function BETWEEN($column_name, $min, $max)
    {
        $expression = self::fieldName($column_name).' BETWEEN '.self::fieldName($min).' AND '.self::fieldName($max);
        return self::create($expression);
    }

    /**
     * Generate a SQL CONCAT or CONCAT_WS clause
     *
     * @param array       $fields    List of fields to concatenate
     * @param string|null $alias     The alias for the resulting concatenation, optional
     * @param string|null $separator Null (default) to use CONCAT, specify a separator to use CONCAT_WS
     *
     * @throws \InvalidArgumentException If $fields is not an array
     *
     * @return static
     */
    public static function CONCAT($fields, $alias = null, $separator = null)
    {
        if (!is_array($fields)) {
            throw new \InvalidArgumentException('$fields must be an array');
        }

        $fs = [];
        foreach ($fields as $item) {
            $fs[] = self::fieldName($item);
        }

        // Handle database-specific CONCAT syntax
        switch (self::$database_type) {
            case 'mssql':
                // SQL Server uses + operator for concatenation
                if ($separator !== null) {
                    // Simulate CONCAT_WS behavior
                    $expression = implode(" + '$separator' + ", $fs);
                } else {
                    $expression = implode(' + ', $fs);
                }
                break;
            case 'postgresql':
                // PostgreSQL has CONCAT function
                $expression = ($separator === null ? 'CONCAT(' : "CONCAT_WS('$separator', ").implode(', ', $fs).')';
                break;
            default: // mysql
                $expression = ($separator === null ? 'CONCAT(' : "CONCAT_WS('$separator', ").implode(', ', $fs).')';
                break;
        }

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Count the number of records for the selected column
     *
     * @param string      $column_name The name of the column to count, defaults to '*'
     * @param string|null $alias       The alias for the resulting count, optional
     * @param bool        $distinct    If true, counts only distinct values; default is false
     *
     * @return static
     */
    public static function COUNT($column_name = '*', $alias = null, $distinct = false)
    {
        $column_name = $column_name == '*' ? '*' : self::fieldName($column_name);
        $expression = 'COUNT('.($distinct ? 'DISTINCT ' : '').$column_name.')';

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Extract date from a DATETIME column
     *
     * @param string      $column_name The name of the DATETIME column
     * @param string|null $alias       The alias for the resulting date, optional
     *
     * @return static
     */
    public static function DATE($column_name, $alias = null)
    {
        switch (self::$database_type) {
            case 'mssql':
                $expression = 'CAST('.self::fieldName($column_name).' AS DATE)';
                break;
            case 'postgresql':
                $expression = self::fieldName($column_name).'::DATE';
                break;
            default: // mysql
                $expression = 'DATE('.self::fieldName($column_name).')';
                break;
        }

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Calculate the difference in days between two dates or between a date and NOW()
     *
     * @param string $column_name1 The first date column or a specific date string
     * @param string $column_name2 The second date column or a specific date string
     * @param string $alias        The alias for the resulting difference, optional
     *
     * @return static
     */
    public static function DATEDIFF($column_name1, $column_name2, $alias = null)
    {
        switch (self::$database_type) {
            case 'mssql':
                $expression = 'DATEDIFF(DAY, '.self::fieldName($column_name2).', '.self::fieldName($column_name1).')';
                break;
            case 'postgresql':
                $expression = '('.self::fieldName($column_name1).' - '.self::fieldName($column_name2).')';
                break;
            default: // mysql
                $expression = 'DATEDIFF('.self::fieldName($column_name1).', '.self::fieldName($column_name2).')';
                break;
        }

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Format a date column for display
     *
     * @param string      $column_name The name of the date column
     * @param string      $format      The format string for date formatting
     * @param string|null $alias       The alias for the resulting formatted date, optional
     *
     * @return static
     */
    public static function DATE_FORMAT($column_name, $format, $alias = null)
    {
        switch (self::$database_type) {
            case 'mssql':
                $expression = 'FORMAT('.self::fieldName($column_name).", '$format')";
                break;
            case 'postgresql':
                $expression = 'TO_CHAR('.self::fieldName($column_name).", '$format')";
                break;
            default: // mysql
                $expression = 'DATE_FORMAT('.self::fieldName($column_name).", '$format')";
                break;
        }

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Extract the day from a DATE or DATETIME column
     *
     * @param string      $column_name The name of the DATE or DATETIME column
     * @param string|null $alias       The alias for the resulting day, optional
     *
     * @return static
     */
    public static function DAY($column_name, $alias = null)
    {
        switch (self::$database_type) {
            case 'mssql':
                $expression = 'DAY('.self::fieldName($column_name).')';
                break;
            case 'postgresql':
                $expression = 'EXTRACT(DAY FROM '.self::fieldName($column_name).')';
                break;
            default: // mysql
                $expression = 'DAY('.self::fieldName($column_name).')';
                break;
        }

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Return distinct values of a column
     *
     * @param string      $column_name The name of the column to retrieve distinct values from
     * @param string|null $alias       The alias for the resulting distinct values, optional
     *
     * @return static
     */
    public static function DISTINCT($column_name, $alias = null)
    {
        $expression = 'DISTINCT '.self::fieldName($column_name);

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Extract sorting information and return as an array.
     *
     * @param array  $columns List of columns that are valid for sorting.
     * @param string $sort    Sorting instructions in the format 'column_name direction'.
     * @param array  $default Default sorting array if no valid sort instructions are provided.
     *
     * @return array|string
     */
    public static function extractSort($columns, $sort, $default = [])
    {
        $all_fields = implode('|', $columns);
        $sorts = explode(',', $sort);
        $result = [];

        foreach ($sorts as $item) {
            if (preg_match('/('.$all_fields.')([\s]+(asc|desc))?$/i', trim($item), $match)) {
                $result[] = $match[0];
            }
        }

        if (empty($result)) {
            return $default;
        } elseif (count($result) === 1) {
            return $result[0];
        } else {
            return $result;
        }
    }

    /**
     * Format a column for display
     *
     * This function generates a SQL FORMAT expression to format a column for display.
     *
     * @param string      $column_name The name of the column to format
     * @param string      $format      The format string for formatting
     * @param string|null $alias       The alias for the resulting formatted column, optional
     *
     * @return static
     */
    public static function FORMAT($column_name, $format, $alias = null)
    {
        // Build the SQL FORMAT expression
        $expression = 'FORMAT('.self::fieldName($column_name).", '$format')";

        // Add an alias if provided
        if ($alias) {
            $expression .= " AS `$alias`";
        }

        // Create and return the SQL expression
        return self::create($expression);
    }

    /**
     * Create a GROUP_CONCAT SQL statement
     *
     * @param string       $column_name The name of the column to concatenate
     * @param string|null  $alias       The alias for the resulting concatenated column, optional
     * @param string       $separator   The separator to use between concatenated values, default is ','
     * @param bool         $distinct    If true, returns only distinct values; default is false
     * @param string|array $order       The order in which concatenated values should appear
     *
     * @return static
     */
    public static function GROUP_CONCAT($column_name, $alias = null, $separator = ',', $distinct = false, $order = null)
    {
        // Handle ordering if specified
        $order_clause = '';
        if (!empty($order)) {
            $orders = [];
            if (is_array($order)) {
                foreach ($order as $item) {
                    $orders[] = self::fieldName($item);
                }
            } else {
                $orders[] = self::fieldName($order);
            }
            $order_clause = empty($orders) ? '' : ' ORDER BY '.implode(',', $orders);
        }

        switch (self::$database_type) {
            case 'mssql':
                // SQL Server uses STRING_AGG (2017+) or XML PATH for older versions
                $expression = 'STRING_AGG('.($distinct ? 'DISTINCT ' : '').self::fieldName($column_name).", '$separator')";
                if ($order_clause) {
                    $expression .= ' WITHIN GROUP ('.$order_clause.')';
                }
                break;
            case 'postgresql':
                $expression = 'STRING_AGG('.($distinct ? 'DISTINCT ' : '').self::fieldName($column_name).", '$separator'".$order_clause.')';
                break;
            default: // mysql
                $expression = 'GROUP_CONCAT('.($distinct ? 'DISTINCT ' : '').self::fieldName($column_name).$order_clause." SEPARATOR '$separator')";
                break;
        }

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Extract the hour from a DATETIME column
     *
     * @param string      $column_name The name of the DATETIME column
     * @param string|null $alias       The alias for the resulting hour, optional
     *
     * @return static
     */
    public static function HOUR($column_name, $alias = null)
    {
        switch (self::$database_type) {
            case 'mssql':
                $expression = 'DATEPART(HOUR, '.self::fieldName($column_name).')';
                break;
            case 'postgresql':
                $expression = 'EXTRACT(HOUR FROM '.self::fieldName($column_name).')';
                break;
            default: // mysql
                $expression = 'HOUR('.self::fieldName($column_name).')';
                break;
        }

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Create an IFNULL SQL statement
     *
     * @param string      $column_name1 The first column name
     * @param string      $column_name2 The second column name
     * @param string|null $alias        The alias for the resulting expression, optional
     *
     * @return static
     */
    public static function IFNULL($column_name1, $column_name2, $alias = null)
    {
        switch (self::$database_type) {
            case 'mssql':
                $expression = 'ISNULL('.self::fieldName($column_name1).', '.self::fieldName($column_name2).')';
                break;
            case 'postgresql':
                $expression = 'COALESCE('.self::fieldName($column_name1).', '.self::fieldName($column_name2).')';
                break;
            default: // mysql
                $expression = 'IFNULL('.self::fieldName($column_name1).', '.self::fieldName($column_name2).')';
                break;
        }

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Create an IS NOT NULL SQL statement
     *
     * @param string $column_name The column name to check for not null
     *
     * @return static
     */
    public static function ISNOTNULL($column_name)
    {
        $expression = self::fieldName($column_name).' IS NOT NULL';
        return self::create($expression);
    }

    /**
     * Create an IS NULL SQL statement
     *
     * @param string $column_name The column name to check for null
     *
     * @return static
     */
    public static function ISNULL($column_name)
    {
        $expression = self::fieldName($column_name).' IS NULL';
        return self::create($expression);
    }

    /**
     * Find the maximum value of a column
     *
     * @param string      $column_name The column name to find the maximum value
     * @param string|null $alias       The alias for the resulting maximum value, optional
     *
     * @return static
     */
    public static function MAX($column_name, $alias = null)
    {
        $expression = 'MAX('.self::fieldName($column_name).')';

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Find the minimum value of a column
     *
     * @param string      $column_name The column name to find the minimum value
     * @param string|null $alias       The alias for the resulting minimum value, optional
     *
     * @return static
     */
    public static function MIN($column_name, $alias = null)
    {
        $expression = 'MIN('.self::fieldName($column_name).')';

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Extract minutes from a DATETIME column
     *
     * This function generates a SQL MINUTE expression to extract minutes from a DATETIME column.
     *
     * @param string      $column_name The column name to extract minutes from
     * @param string|null $alias       The alias for the resulting minutes, optional
     *
     * @return static
     */
    public static function MINUTE($column_name, $alias = null)
    {
        // Build the SQL MINUTE expression
        $expression = 'MINUTE('.self::fieldName($column_name).')';

        // Add an alias if provided
        if ($alias) {
            $expression .= " AS `$alias`";
        }

        // Create and return the SQL expression
        return self::create($expression);
    }

    /**
     * Extract month from a DATE or DATETIME column
     *
     * @param string      $column_name The column name to extract the month from
     * @param string|null $alias       The alias for the resulting month, optional
     *
     * @return static
     */
    public static function MONTH($column_name, $alias = null)
    {
        switch (self::$database_type) {
            case 'mssql':
                $expression = 'MONTH('.self::fieldName($column_name).')';
                break;
            case 'postgresql':
                $expression = 'EXTRACT(MONTH FROM '.self::fieldName($column_name).')';
                break;
            default: // mysql
                $expression = 'MONTH('.self::fieldName($column_name).')';
                break;
        }

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Generate SQL to find the next value in a sequence (MAX + 1)
     *
     * Used to find the next ID in a table.
     *
     * @param string $field      The field name to find the maximum value
     * @param string $table_name The table name
     * @param mixed  $condition  (optional) WHERE condition for the query
     * @param string $alias      (optional) Alias for the resulting field, null means no alias
     * @param string $operator   (optional) Logical operator like AND or OR
     * @param string $id         (optional) Key field name
     *
     * @return static
     */
    public static function NEXT($field, $table_name, $condition = null, $alias = null, $operator = 'AND', $id = 'id')
    {
        $obj = new static();

        // Build the WHERE clause if condition is provided
        if (!empty($condition)) {
            $condition = ' WHERE '.$obj->buildWhere($condition, $obj->values, $operator, $id);
        } else {
            $condition = '';
        }

        // Build the SQL expression to find next ID
        $obj->sql = '(1 + IFNULL((SELECT MAX(`'.$field.'`) FROM '.$table_name.' AS X'.$condition.'), 0))';

        // Add an alias if provided
        if (isset($alias)) {
            $obj->sql .= " AS `$alias`";
        }

        // Return the SQL object
        return $obj;
    }

    /**
     * Returns the current date and time as a SQL function NOW().
     *
     * @param string|null $alias Optional alias for the NOW() function result in SQL.
     *
     * @return static
     */
    public static function NOW($alias = null)
    {
        switch (self::$database_type) {
            case 'mssql':
                $sql = 'GETDATE()';
                break;
            case 'postgresql':
                $sql = 'NOW()';
                break;
            default: // mysql
                $sql = 'NOW()';
                break;
        }

        if ($alias) {
            $sql .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($sql);
    }

    /**
     * Searches for a substring in a string and returns its position. If not found, returns 0; indexing starts from 1.
     *
     * @param string      $substr The substring to search for. If it's a field name, it should be enclosed in ``.
     * @param string      $str    The original string to search within. If it's a field name, it should be enclosed in ``.
     * @param string|null $alias  Optional alias for the result of the LOCATE() function in SQL.
     *                            If provided, formats the SQL as LOCATE(...) AS `$alias`.
     * @param int         $pos    Optional starting position for the search. Defaults to 0 (search from the beginning).
     *
     * @return static
     */
    public static function POSITION($substr, $str, $alias = null, $pos = 0)
    {
        // Adjust substrings if they are not field names to be SQL-compatible
        $substr = strpos($substr, '`') === false ? "'$substr'" : $substr;
        $str = strpos($str, '`') === false ? "'$str'" : $str;

        // Build the SQL expression for LOCATE() with optional alias and position
        $sql = "LOCATE($substr, $str".(empty($pos) ? ')' : ", $pos)").($alias ? " AS `$alias`" : '');

        // Assuming self::create() constructs or modifies a query or model object
        return self::create($sql);
    }

    /**
     * Generates a random number.
     *
     * @param string|null $alias Optional alias for the RAND() function result in SQL.
     *
     * @return static
     */
    public static function RAND($alias = null)
    {
        switch (self::$database_type) {
            case 'mssql':
                $sql = 'NEWID()'; // SQL Server uses NEWID() for random
                break;
            case 'postgresql':
                $sql = 'RANDOM()';
                break;
            default: // mysql
                $sql = 'RAND()';
                break;
        }

        if ($alias) {
            $sql .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($sql);
    }

    /**
     * Extracts the seconds from a DATETIME column.
     *
     * @param string      $column_name The name of the DATETIME column to extract seconds from.
     * @param string|null $alias       Optional alias for the SECOND() function result in SQL.
     *                                 If provided, formats the SQL as SECOND(...) AS `$alias`.
     *
     * @return static
     */
    public static function SECOND($column_name, $alias = null)
    {
        // Build the SQL expression for SECOND() with optional alias
        $sql = 'SECOND('.self::fieldName($column_name).')'.($alias ? " AS `$alias`" : '');

        // Assuming self::create() constructs or modifies a query or model object
        return self::create($sql);
    }

    /**
     * Calculates the sum of values in a selected column.
     *
     * @param string      $column_name The name of the column to sum
     * @param string|null $alias       Optional alias for the SUM() function result in SQL
     * @param bool        $distinct    Optional. If true, sums only distinct values in the column
     *
     * @return static
     */
    public static function SUM($column_name, $alias = null, $distinct = false)
    {
        $sql = 'SUM('.($distinct ? 'DISTINCT ' : '').self::fieldName($column_name).')';

        if ($alias) {
            $sql .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($sql);
    }

    /**
     * Calculates the time difference between two datetime columns or values.
     *
     * @param string $column_name1 The first datetime column or value. If it's a column name, it should be enclosed in ``.
     * @param string $column_name2 The second datetime column or value. If it's a column name, it should be enclosed in ``.
     * @param string $alias        Optional alias for the TIMEDIFF() function result in SQL.
     *                             If provided, formats the SQL as TIMEDIFF(...) AS `$alias`.
     *
     * @return static
     */
    public static function TIMEDIFF($column_name1, $column_name2, $alias = null)
    {
        // Build the SQL expression for TIMEDIFF() with optional alias
        $sql = 'TIMEDIFF('.self::fieldName($column_name1).', '.self::fieldName($column_name2).')'.($alias ? " AS `$alias`" : '');

        // Assuming self::create() constructs or modifies a query or model object
        return self::create($sql);
    }

    /**
     * Calculates the difference between two datetime columns or values in specified units.
     *
     * @param string $unit        The unit of time difference to calculate:
     *                            FRAC_SECOND (microseconds), SECOND, MINUTE, HOUR, DAY, WEEK, MONTH, QUARTER, or YEAR.
     * @param string $column_name1 The first datetime column or value. If it's a column name, it should be enclosed in ``.
     * @param string $column_name2 The second datetime column or value. If it's a column name, it should be enclosed in ``.
     * @param string $alias       Optional alias for the TIMESTAMPDIFF() function result in SQL.
     *                            If provided, formats the SQL as TIMESTAMPDIFF(...) AS `$alias`.
     *
     * @return static
     */
    public static function TIMESTAMPDIFF($unit, $column_name1, $column_name2, $alias = null)
    {
        // Build the SQL expression for TIMESTAMPDIFF() with optional alias
        $sql = 'TIMESTAMPDIFF('.$unit.', '.self::fieldName($column_name1).', '.self::fieldName($column_name2).')'.($alias ? " AS `$alias`" : '');

        // Assuming self::create() constructs or modifies a query or model object
        return self::create($sql);
    }

    /**
     * Extracts the year from a DATE or DATETIME column.
     *
     * @param string      $column_name The name of the DATE or DATETIME column
     * @param string|null $alias       Optional alias for the YEAR() function result in SQL
     *
     * @return static
     */
    public static function YEAR($column_name, $alias = null)
    {
        switch (self::$database_type) {
            case 'mssql':
                $expression = 'YEAR('.self::fieldName($column_name).')';
                break;
            case 'postgresql':
                $expression = 'EXTRACT(YEAR FROM '.self::fieldName($column_name).')';
                break;
            default: // mysql
                $expression = 'YEAR('.self::fieldName($column_name).')';
                break;
        }

        if ($alias) {
            $expression .= ' AS '.self::quoteIdentifier($alias);
        }

        return self::create($expression);
    }

    /**
     * Class constructor
     *
     * @param string $sql
     */
    public function __construct($sql = null)
    {
        $this->sql = $sql;
        $this->values = [];
    }

    /**
     * Create Object Sql
     *
     * @param string $sql
     */
    public static function create($sql)
    {
        return new static($sql);
    }

    /**
     * Wraps a column name with appropriate quotes for SQL identifiers.
     * Handles database-specific quoting.
     *
     * @param string|int $column_name The column name or value to be formatted for SQL.
     *
     * @throws \InvalidArgumentException If the column name format is invalid.
     *
     * @return string|int
     */
    public static function fieldName($column_name)
    {
        if ($column_name instanceof self || $column_name instanceof QueryBuilder) {
            return $column_name->text();
        } elseif (is_string($column_name)) {
            if (preg_match('/^SQL\((.+)\)$/', $column_name, $match)) {
                return $match[1];
            } elseif (preg_match('/^`?([a-z0-9_]{2,})`?(\s(ASC|DESC|asc|desc))?$/', $column_name, $match)) {
                return self::quoteIdentifier($match[1]).(empty($match[3]) ? '' : $match[2]);
            } elseif (preg_match('/^([A-Z][0-9]{0,2}\.)`?([a-zA-Z0-9_]+)`?(\s(ASC|DESC|asc|desc))?$/', $column_name, $match)) {
                return $match[1].self::quoteIdentifier($match[2]).(empty($match[4]) ? '' : $match[3]);
            } elseif (preg_match('/^`?([a-zA-Z0-9_]+)`?\.`?([a-zA-Z0-9_]+)`?(\s(ASC|DESC|asc|desc))?$/', $column_name, $match)) {
                return self::quoteIdentifier($match[1]).'.'.self::quoteIdentifier($match[2]).(empty($match[4]) ? '' : $match[3]);
            } else {
                return "'$column_name'";
            }
        } elseif (is_numeric($column_name)) {
            return $column_name;
        }

        throw new \InvalidArgumentException('Invalid arguments in fieldName');
    }

    /**
     * Quote identifier based on database type
     *
     * @param string $name
     * @return string
     */
    protected static function quoteIdentifier($name)
    {
        switch (self::$database_type) {
            case 'mssql':
                return '['.$name.']';
            case 'postgresql':
                return '"'.$name.'"';
            default: // mysql
                return '`'.$name.'`';
        }
    }

    /**
     * Retrieves or merges bind parameters ($values) used for prepared statements in SQL queries.
     *
     * @param array $values Optional. An array of bind parameters to merge or retrieve.
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
     * Quotes and prepares a value for use in SQL queries, handling various data types and formats.
     *
     * @param string $column_name The column name or identifier to associate with the value.
     * @param mixed  $value       The value to quote and prepare for the query.
     * @param array  $values      Reference to an array to store bind parameters for prepared statements.
     *
     * @throws \InvalidArgumentException If the value format is invalid or not handled.
     *
     * @return string|int
     */
    public static function quoteValue($column_name, $value, &$values)
    {
        if (is_array($value)) {
            $qs = [];
            foreach ($value as $v) {
                $qs[] = self::quoteValue($column_name, $v, $values);
            }
            $sql = '('.implode(', ', $qs).')';
        } elseif ($value === null) {
            $sql = 'NULL';
        } elseif ($value === '') {
            $sql = "''";
        } elseif (is_string($value)) {
            if (preg_match('/^([0-9\s\r\n\t\.\_\-:]+)$/', $value)) {
                $sql = "'$value'";
            } elseif (preg_match('/0x[0-9]+/is', $value)) {
                $sql = ':'.strtolower(preg_replace('/[`\.\s\-_]+/', '', $column_name));
                if (empty($values) || !is_array($values)) {
                    $sql .= 0;
                } else {
                    $sql .= count($values);
                }
                $values[$sql] = $value;
            } else {
                if (preg_match('/^(([A-Z][0-9]{0,2})|`([a-zA-Z0-9_]+)`)\.`?([a-zA-Z0-9_]+)`?$/', $value, $match)) {
                    $sql = $match[3] == '' ? "$match[2].".self::quoteIdentifier($match[4]) : self::quoteIdentifier($match[3]).'.'.self::quoteIdentifier($match[4]);
                } elseif (preg_match('/^([a-zA-Z0-9_]+)\.`([a-zA-Z0-9_]+)`$/', $value, $match)) {
                    $sql = self::quoteIdentifier($match[1]).'.'.self::quoteIdentifier($match[2]);
                } elseif (!preg_match('/[\s\r\n\t`;\(\)\*\=<>\/\'"]+/s', $value) && !preg_match('/(UNION|INSERT|DELETE|TRUNCATE|DROP|0x[0-9]+)/is', $value)) {
                    $sql = "'$value'";
                } else {
                    $sql = ':'.strtolower(preg_replace('/[`\.\s\-_]+/', '', $column_name));
                    if (empty($values) || !is_array($values)) {
                        $sql .= 0;
                    } else {
                        $sql .= count($values);
                    }
                    $values[$sql] = $value;
                }
            }
        } elseif (is_numeric($value)) {
            $sql = $value;
        } elseif ($value instanceof self) {
            $sql = $value->text($column_name);
            $values = $value->getValues($values);
        } elseif ($value instanceof QueryBuilder) {
            $sql = '('.$value->text().')';
            $values = $value->getValues($values);
        } else {
            throw new \InvalidArgumentException('Invalid arguments in quoteValue');
        }

        return $sql;
    }

    /**
     * Creates a SQL string literal by wrapping the given value in single quotes.
     *
     * @param string $value The string value to be wrapped in single quotes.
     *
     * @return static
     */
    public static function strValue($value)
    {
        return self::create("'$value'");
    }

    /**
     * Returns the SQL command as a string.
     * If $sql is null, returns :$key for binding purposes.
     *
     * @param string|null $key The key used for binding (optional).
     *
     * @return string
     *
     * @throws \InvalidArgumentException When $key is provided but empty.
     */
    public function text($key = null)
    {
        if ($this->sql === null) {
            if (is_string($key) && $key != '') {
                return ':'.preg_replace('/[\.`]/', '', strtolower($key));
            } else {
                throw new \InvalidArgumentException('$key must be a non-empty string');
            }
        } else {
            return $this->sql;
        }
    }

    /**
     * Constructs WHERE clause based on given conditions.
     *
     * @param mixed  $condition The condition(s) to build into WHERE clause.
     * @param string $operator  Logical operator (e.g., AND, OR) to combine multiple conditions.
     * @param string $id        Field name used as key in conditions.
     *
     * @return static
     */
    public static function WHERE($condition, $operator = 'AND', $id = 'id')
    {
        $obj = new static();
        $obj->sql = $obj->buildWhere($condition, $obj->values, $operator, $id);
        return $obj;
    }

    /**
     * Constructs SQL WHERE command based on given conditions.
     *
     * @param mixed  $condition The condition(s) to build into WHERE clause.
     * @param array  $values    Array to collect values for parameter binding.
     * @param string $operator  Logical operator (e.g., AND, OR) to combine multiple conditions.
     * @param string $id        Field name used as key in conditions.
     *
     * @return string
     */
    private function buildWhere($condition, &$values, $operator, $id)
    {
        if (is_array($condition)) {
            $qs = [];

            if (is_array($condition[0])) {
                foreach ($condition as $item) {
                    if ($item instanceof QueryBuilder) {
                        $qs[] = '('.$item->text().')';
                        $values = $item->getValues($values);
                    } elseif ($item instanceof self) {
                        $qs[] = $item->text();
                        $values = $item->getValues($values);
                    } else {
                        $qs[] = $this->buildWhere($item, $values, $operator, $id);
                    }
                }
                $sql = count($qs) > 1 ? '('.implode(' '.$operator.' ', $qs).')' : implode(' '.$operator.' ', $qs);
            } else {
                if ($condition[0] instanceof QueryBuilder) {
                    $key = $condition[0]->text();
                    $values = $condition[0]->getValues($values);
                } elseif ($condition[0] instanceof self) {
                    $key = $condition[0]->text();
                    $values = $condition[0]->getValues($values);
                } elseif (preg_match('/^SQL(\(.*\))$/', $condition[0], $match)) {
                    $key = $match[1];
                } else {
                    $key = self::fieldName($condition[0]);
                }

                $c = count($condition);

                if ($c == 2) {
                    if ($condition[1] instanceof QueryBuilder) {
                        $operator = 'IN';
                        $value = '('.$condition[1]->text().')';
                        $values = $condition[1]->getValues($values);
                    } elseif ($condition[1] instanceof self) {
                        $operator = '=';
                        $value = $condition[1]->text();
                        $values = $condition[1]->getValues($values);
                    } elseif ($condition[1] === null) {
                        $operator = 'IS';
                        $value = 'NULL';
                    } else {
                        $operator = '=';
                        if (is_array($condition[1]) && $operator == '=') {
                            $operator = 'IN';
                        }
                        $value = self::quoteValue($key, $condition[1], $values);
                    }
                } elseif ($c == 3) {
                    if ($condition[2] instanceof QueryBuilder) {
                        $operator = trim($condition[1]);
                        $value = '('.$condition[2]->text().')';
                        $values = $condition[2]->getValues($values);
                    } elseif ($condition[2] instanceof self) {
                        $operator = trim($condition[1]);
                        $value = $condition[2]->text();
                        $values = $condition[2]->getValues($values);
                    } elseif ($condition[2] === null) {
                        $operator = trim($condition[1]);
                        if ($operator == '=') {
                            $operator = 'IS';
                        } elseif ($operator == '!=') {
                            $operator = 'IS NOT';
                        }
                        $value = 'NULL';
                    } else {
                        $operator = trim($condition[1]);
                        if (is_array($condition[2]) && $operator == '=') {
                            $operator = 'IN';
                        }
                        $value = self::quoteValue($key, $condition[2], $values);
                    }
                }

                if (isset($value)) {
                    $sql = $key.' '.$operator.' '.$value;
                } else {
                    $sql = $key;
                }
            }
        } elseif ($condition instanceof QueryBuilder) {
            $sql = '('.$condition->text().')';
            $values = $condition->getValues($values);
        } elseif ($condition instanceof self) {
            $sql = $condition->text();
            $values = $condition->getValues($values);
        } elseif (preg_match('/^SQL\((.+)\)$/', $condition, $match)) {
            $sql = $match[1];
        } else {
            $sql = self::fieldName($id).' = '.self::quoteValue($id, $condition, $values);
        }

        return $sql;
    }
}
