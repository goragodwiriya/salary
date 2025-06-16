<?php
/**
 * @filesource Kotchasan/Database/Exception.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 * @author Goragod Wiriya <admin@goragod.com>
 * @package Kotchasan
 */

namespace Kotchasan\Database;

/**
 * Database Exception class
 *
 * Exception class for handling database errors.
 *
 * @see https://www.kotchasan.com/
 */
class Exception extends \Exception
{
    /**
     * SQL query that caused the exception
     *
     * @var string|null
     */
    protected $sql;

    /**
     * Query parameters that caused the exception
     *
     * @var array
     */
    protected $parameters;

    /**
     * Database type where the exception occurred
     *
     * @var string|null
     */
    protected $databaseType;

    /**
     * Constructor
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     * @param string|null $sql SQL query that caused the exception
     * @param array $parameters Query parameters
     * @param string|null $databaseType Database type
     */
    public function __construct(
        $message = "",
        $code = 0,
        \Throwable $previous = null,
        $sql = null,
        array $parameters = [],
        $databaseType = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->sql = $sql;
        $this->parameters = $parameters;
        $this->databaseType = $databaseType;
    }

    /**
     * Get the SQL query that caused the exception
     *
     * @return string|null
     */
    public function getSql()
    {
        return $this->sql;
    }

    /**
     * Get the query parameters that caused the exception
     *
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Get the database type where the exception occurred
     *
     * @return string|null
     */
    public function getDatabaseType()
    {
        return $this->databaseType;
    }

    /**
     * Get detailed error information
     *
     * @return array
     */
    public function getErrorDetails()
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'sql' => $this->sql,
            'parameters' => $this->parameters,
            'database_type' => $this->databaseType,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString()
        ];
    }

    /**
     * Convert exception to string with additional database information
     *
     * @return string
     */
    public function __toString()
    {
        $result = parent::__toString();

        if ($this->sql) {
            $result .= "\nSQL: ".$this->sql;
        }

        if (!empty($this->parameters)) {
            $result .= "\nParameters: ".json_encode($this->parameters);
        }

        if ($this->databaseType) {
            $result .= "\nDatabase Type: ".$this->databaseType;
        }

        return $result;
    }

    /**
     * Create a connection exception
     *
     * @param string $message Exception message
     * @param string|null $databaseType Database type
     * @param \Throwable|null $previous Previous exception
     * @return static
     */
    public static function connectionError($message, $databaseType = null, \Throwable $previous = null)
    {
        return new static("Database connection error: ".$message, 1001, $previous, null, [], $databaseType);
    }

    /**
     * Create a query execution exception
     *
     * @param string $message Exception message
     * @param string $sql SQL query
     * @param array $parameters Query parameters
     * @param string|null $databaseType Database type
     * @param \Throwable|null $previous Previous exception
     * @return static
     */
    public static function queryError($message, $sql, array $parameters = [], $databaseType = null, \Throwable $previous = null)
    {
        return new static("Query execution error: ".$message, 1002, $previous, $sql, $parameters, $databaseType);
    }

    /**
     * Create a transaction exception
     *
     * @param string $message Exception message
     * @param string|null $databaseType Database type
     * @param \Throwable|null $previous Previous exception
     * @return static
     */
    public static function transactionError($message, $databaseType = null, \Throwable $previous = null)
    {
        return new static("Transaction error: ".$message, 1003, $previous, null, [], $databaseType);
    }

    /**
     * Create a configuration exception
     *
     * @param string $message Exception message
     * @param \Throwable|null $previous Previous exception
     * @return static
     */
    public static function configurationError($message, \Throwable $previous = null)
    {
        return new static("Database configuration error: ".$message, 1004, $previous);
    }
}
