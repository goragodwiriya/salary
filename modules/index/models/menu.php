<?php
/**
 * @filesource modules/index/models/menu.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Menu;

use Gcms\Login;
use Kotchasan\Language;

/**
 * รายการเมนู
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model
{
    /**
     * รายการเมนู
     *
     * @param array $login
     *
     * @return array
     */
    public static function getMenus($login)
    {
        $notDemoMode = Login::notDemoMode($login);
        // แอดมิน
        $isAdmin = $notDemoMode && Login::isAdmin();
        // สามารถตั้งค่าได้
        $can_config = Login::checkPermission($login, 'can_config');
        // เมนูตั้งค่า
        $settings = [];
        if ($can_config) {
            // สามารถตั้งค่าระบบได้
            $settings['system'] = [
                'text' => '{LNG_Site settings}',
                'url' => 'index.php?module=system'
            ];
            $settings['theme'] = [
                'text' => '{LNG_Website template}',
                'url' => 'index.php?module=theme'
            ];
            $settings['loginpage'] = [
                'text' => '{LNG_Login page}',
                'url' => 'index.php?module=loginpage'
            ];
        }
        if ($isAdmin) {
            $settings['mailserver'] = [
                'text' => '{LNG_Email settings}',
                'url' => 'index.php?module=mailserver'
            ];
            $settings['linesettings'] = [
                'text' => '{LNG_LINE settings}',
                'url' => 'index.php?module=linesettings'
            ];
            $settings['telegramsettings'] = [
                'text' => '{LNG_Telegram settings}',
                'url' => 'index.php?module=telegramsettings'
            ];
            $settings['apis'] = [
                'text' => 'API',
                'url' => 'index.php?module=apis'
            ];
            $settings['sms'] = [
                'text' => '{LNG_SMS Settings}',
                'url' => 'index.php?module=sms'
            ];
            $settings['modules'] = [
                'text' => '{LNG_Module}',
                'url' => 'index.php?module=modules'
            ];
        }
        if ($can_config) {
            $settings['language'] = [
                'text' => '{LNG_Language}',
                'url' => 'index.php?module=language'
            ];
            foreach (Language::get('CATEGORIES', []) as $k => $label) {
                $settings[$k] = [
                    'text' => $label,
                    'url' => 'index.php?module=categories&amp;type='.$k
                ];
            }
        }
        if ($isAdmin) {
            foreach (Language::get('PAGES', []) as $src => $label) {
                $settings['write'.$src] = [
                    'text' => $label,
                    'url' => 'index.php?module=write&amp;src='.$src,
                    'target' => '_self'
                ];
            }
            $settings['consentsettings'] = [
                'text' => '{LNG_Cookie Policy}',
                'url' => 'index.php?module=consentsettings'
            ];
        }
        if ($notDemoMode && Login::checkPermission($login, 'can_view_usage_history')) {
            $settings['usage'] = [
                'text' => '{LNG_Usage history}',
                'url' => 'index.php?module=usage'
            ];
        }
        if ($login) {
            return [
                'home' => [
                    'text' => '{LNG_Home}',
                    'url' => 'index.php?module=home'
                ],
                'member' => [
                    'text' => '{LNG_Users}',
                    'submenus' => [
                        [
                            'text' => '{LNG_Member list}',
                            'url' => 'index.php?module=member'
                        ],
                        [
                            'text' => '{LNG_Permission}',
                            'url' => 'index.php?module=permission'
                        ],
                        [
                            'text' => '{LNG_Member status}',
                            'url' => 'index.php?module=memberstatus'
                        ]
                    ]
                ],
                'report' => [
                    'text' => '{LNG_Report}',
                    'url' => 'index.php?module=report',
                    'submenus' => []
                ],
                'settings' => [
                    'text' => '{LNG_Settings}',
                    'url' => 'index.php?module=settings',
                    'submenus' => $settings
                ]
            ];
        }
        // ไม่ได้ login
        return [
            'home' => [
                'text' => '{LNG_Home}',
                'url' => 'index.php?module=home'
            ]
        ];
    }
}
