<?php
/**
 * @filesource Gcms/Router.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Gcms;

/**
 * Router Class สำหรับ GCMS
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Router extends \Kotchasan\Router
{
    /**
     * กฏของ Router สำหรับการแยกหน้าเว็บไซต์
     *
     * @var array
     */
    protected $rules = [
        // api.php/<modules>/<method>/<action>
        '/api\.php\/([a-z0-9]+)\/([a-z]+)\/([a-z]+)/i' => ['module', 'method', 'action']
    ];
}
