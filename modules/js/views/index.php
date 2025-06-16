<?php
/**
 * @filesource modules/js/views/index.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Js\Index;

use Kotchasan\Language;

/**
 * Generate JS file
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Kotchasan\KBase
{
    /**
     * สร้างไฟล์ js
     */
    public function index()
    {
        // default js
        $js = [];
        $js[] = 'var WEB_URL="'.WEB_URL.'";';
        $js[] = file_get_contents(ROOT_PATH.'js/gajax.js');
        $js[] = file_get_contents(ROOT_PATH.'js/autocomplete.js');
        $js[] = file_get_contents(ROOT_PATH.'js/clock.js');
        $js[] = file_get_contents(ROOT_PATH.'js/datalist.js');
        $js[] = file_get_contents(ROOT_PATH.'js/ddmenu.js');
        $js[] = file_get_contents(ROOT_PATH.'js/ddpanel.js');
        $js[] = file_get_contents(ROOT_PATH.'js/dragdrop.js');
        $js[] = file_get_contents(ROOT_PATH.'js/editinplace.js');
        $js[] = file_get_contents(ROOT_PATH.'js/facebook.js');
        $js[] = file_get_contents(ROOT_PATH.'js/google.js');
        $js[] = file_get_contents(ROOT_PATH.'js/ggraphs.js');
        $js[] = file_get_contents(ROOT_PATH.'js/loader.js');
        $js[] = file_get_contents(ROOT_PATH.'js/multiselect.js');
        $js[] = file_get_contents(ROOT_PATH.'js/table.js');
        $js[] = file_get_contents(ROOT_PATH.'js/common.js');
        $js[] = file_get_contents(ROOT_PATH.'js/pdpa.js');
        // โหลดโมดูลที่ติดตั้งแล้ว
        $modules = \Gcms\Modules::create();
        // ไดเร็คทอรี่โมดูล
        $dir = $modules->getDir();
        // js ของโมดูล
        foreach ($modules->get() as $module) {
            if (is_file($dir.$module.'/script.js')) {
                $js[] = file_get_contents($dir.$module.'/script.js');
            }
        }
        $lng = Language::name();
        $data_folder = Language::languageFolder();
        if (is_file($data_folder.$lng.'.js')) {
            $js[] = file_get_contents($data_folder.$lng.'.js');
        }
        $languages = Language::getItems([
            'MONTH_SHORT',
            'MONTH_LONG',
            'DATE_LONG',
            'DATE_SHORT',
            'YEAR_OFFSET'
        ]);
        $js[] = 'Date.monthNames = ["'.implode('", "', $languages['MONTH_SHORT']).'"];';
        $js[] = 'Date.longMonthNames = ["'.implode('", "', $languages['MONTH_LONG']).'"];';
        $js[] = 'Date.longDayNames = ["'.implode('", "', $languages['DATE_LONG']).'"];';
        $js[] = 'Date.dayNames = ["'.implode('", "', $languages['DATE_SHORT']).'"];';
        $js[] = 'Date.yearOffset = '.(int) $languages['YEAR_OFFSET'].';';
        if (!empty(self::$cfg->facebook_appId)) {
            $js[] = 'initFacebook("'.self::$cfg->facebook_appId.'", "'.$lng.'");';
        }
        if (!empty(self::$cfg->google_client_id)) {
            $js[] = 'initGooleSignin("'.self::$cfg->google_client_id.'");';
        }
        // cookies consent
        if (!empty(self::$cfg->cookie_policy)) {
            $js[] = 'new PDPA();';
        }
        // compress javascript
        $compressedJs = self::compress($js);

        // Generate ETag
        $etag = md5($compressedJs);

        // Response
        $response = new \Kotchasan\Http\Response();
        $headers = [
            'Content-type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=604800',
            'Expires' => gmdate('D, d M Y H:i:s', time() + 604800).' GMT',
            'Pragma' => 'cache',
            'Vary' => 'Accept-Encoding',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'ETag' => '"'.$etag.'"'
        ];

        // Check if-none-match header
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === '"'.$etag.'"') {
            $response->withHeaders($headers)
                ->withStatus(304)
                ->send();
            exit;
        }

        // Check if-modified-since header
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $if_modified_since = strtotime(preg_replace('/;.*$/', '', $_SERVER['HTTP_IF_MODIFIED_SINCE']));
            if ($if_modified_since >= time() - 604800) {
                $response->withHeaders($headers)
                    ->withStatus(304)
                    ->send();
                exit;
            }
        }

        $headers['Last-Modified'] = gmdate('D, d M Y H:i:s').' GMT';
        $response->withHeaders($headers)
            ->withContent($compressedJs)
            ->send();
    }

    /**
     * @param array $contents
     */
    public static function compress($contents)
    {
        $patt = ['#/\*(?:[^*]*(?:\*(?!/))*)*\*/#u', '#[\r\t]#', '#\n//.*\n#', '#;//.*\n#', '#[\n]#', '#[\s]{2,}#', '/[\s]{0,}([\+\-\*\|\&<>=;!])[\s]{0,}/'];
        $replace = ['', '', '', ";\n", '', ' ', '\\1'];
        return preg_replace($patt, $replace, implode("\n", $contents));
    }
}
