<?php
/**
 * @filesource Kotchasan/Database/DbCache.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 * @author Goragod Wiriya <admin@goragod.com>
 * @package Kotchasan
 */

namespace Kotchasan\Database;

use Kotchasan\Cache\CacheItem as Item;
use Kotchasan\Cache\FileCache as Cache;
use Kotchasan\Text;

/**
 * Provides caching functionality for database query results.
 *
 * @see https://www.kotchasan.com/
 */
class DbCache
{
    /**
     * Defines the cache loading behavior.
     *
     * - 0: Disable caching.
     * - 1: Load and automatically save cache.
     * - 2: Load cache but do not automatically save cache.
     *
     * @var int
     */
    private $action = 0;

    /**
     * Cache driver instance.
     *
     * @var Cache
     */
    private $db_cache;

    /**
     * Singleton instance of the class.
     *
     * @var DbCache
     */
    private static $instance = null;

    /**
     * Cache statistics
     *
     * @var array
     */
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'saves' => 0
    ];

    /**
     * Enable caching.
     *
     * Cache will be checked before querying data.
     *
     * @param bool $auto_save (optional) Whether to automatically save cache results. Default is true.
     *
     * @return void
     */
    public function cacheOn($auto_save = true)
    {
        $this->action = $auto_save ? 1 : 2;
    }

    /**
     * Disable caching.
     *
     * @return void
     */
    public function cacheOff()
    {
        $this->action = 0;
    }

    /**
     * Clear the cache.
     *
     * @return bool|array True if the cache is cleared successfully, or an array of failed items.
     */
    public function clear()
    {
        try {
            return $this->db_cache->clear();
        } catch (\Exception $e) {
            error_log('Database cache clear failed: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Create an instance of the class (Singleton).
     *
     * @return DbCache
     */
    public static function create()
    {
        if (null === static::$instance) {
            static::$instance = new static;
        }
        return static::$instance;
    }

    /**
     * Get data from the cache.
     *
     * Returns the cached data or false if the cache is not available.
     *
     * @param Item $item The cache item.
     *
     * @return mixed The cached data or false.
     */
    public function get(Item $item)
    {
        try {
            if ($item->isHit()) {
                $this->stats['hits']++;
                return $item->get();
            } else {
                $this->stats['misses']++;
                return false;
            }
        } catch (\Exception $e) {
            error_log('Database cache get failed: '.$e->getMessage());
            $this->stats['misses']++;
            return false;
        }
    }

    /**
     * Get the current cache action.
     *
     * - 0: Disable caching.
     * - 1: Load and automatically save cache.
     * - 2: Load cache but do not automatically save cache.
     *
     * @return int The cache action.
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Get cache statistics
     *
     * @return array Array containing hits, misses, and saves counts
     */
    public function getStats()
    {
        return $this->stats;
    }

    /**
     * Get cache hit ratio
     *
     * @return float Cache hit ratio between 0 and 1
     */
    public function getHitRatio()
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        return $total > 0 ? $this->stats['hits'] / $total : 0.0;
    }

    /**
     * Initialize a cache item based on the SQL query and its values.
     *
     * @param string $sql The SQL query.
     * @param array $values The query values.
     *
     * @return Item The cache item.
     */
    public function init($sql, $values)
    {
        try {
            $cache_key = $this->generateCacheKey($sql, $values);
            return $this->db_cache->getItem($cache_key);
        } catch (\Exception $e) {
            error_log('Database cache init failed: '.$e->getMessage());
            // Return a dummy cache item that always misses
            return new Item('dummy_key', null, false);
        }
    }

    /**
     * Check if caching is enabled
     *
     * @return bool True if caching is enabled, false otherwise
     */
    public function isEnabled()
    {
        return $this->action > 0;
    }

    /**
     * Reset cache statistics
     *
     * @return void
     */
    public function resetStats()
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'saves' => 0
        ];
    }

    /**
     * Save the cache item with the provided data.
     *
     * Once the cache is saved, the automatic cache action will be disabled.
     * Use this method when calling `cacheOn(false)` to enable caching manually.
     * Subsequent queries that require caching must enable cache before each query.
     *
     * @param Item $item The cache item.
     * @param mixed $data The data to be cached.
     *
     * @return bool True if the cache is saved successfully, false otherwise.
     */
    public function save(Item $item, $data)
    {
        try {
            $this->action = 0;
            $item->set($data);
            $result = $this->db_cache->save($item);

            if ($result) {
                $this->stats['saves']++;
            }

            return $result;
        } catch (\Exception $e) {
            error_log('Database cache save failed: '.$e->getMessage());
            return false;
        }
    }

    /**
     * Set the cache action.
     *
     * - 0: Disable caching.
     * - 1: Load and automatically save cache.
     * - 2: Load cache but do not automatically save cache.
     *
     * @param int $value The cache action value.
     *
     * @return DbCache
     */
    public function setAction($value)
    {
        if (!in_array($value, [0, 1, 2])) {
            throw new \InvalidArgumentException('Cache action must be 0, 1, or 2');
        }

        $this->action = $value;
        return $this;
    }

    /**
     * Check if the data was retrieved from the cache item.
     *
     * @param Item $item The cache item.
     *
     * @return bool True if the cache item was used, false otherwise.
     */
    public function usedCache(Item $item)
    {
        return $item->isHit();
    }

    /**
     * Class constructor.
     */
    private function __construct()
    {
        $this->db_cache = new Cache();
    }

    /**
     * Generate a cache key from SQL and values
     *
     * @param string $sql The SQL query
     * @param array $values The query values
     * @return string The generated cache key
     */
    private function generateCacheKey($sql, $values)
    {
        // Use Text::replace if available, otherwise fallback to basic replacement
        if (class_exists('Kotchasan\Text') && method_exists('Kotchasan\Text', 'replace')) {
            $key = Text::replace($sql, $values);
        } else {
            $key = $sql.serialize($values);
        }

        // Generate a shorter, more cache-friendly key
        return 'db_'.md5($key);
    }
}
