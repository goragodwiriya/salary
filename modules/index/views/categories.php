<?php
/**
 * @filesource modules/index/views/categories.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Categories;

use Kotchasan\DataTable;
use Kotchasan\Form;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=categories
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * หมวดหมู่แยกตามภาษา
     *
     * @var bool
     */
    private $multiple_language = false;
    /**
     * @var array
     */
    private $installedlanguage;

    /**
     * รายการหมวดหมู่
     *
     * @param Request $request
     * @param array $params
     *
     * @return string
     */
    public function render(Request $request, $params)
    {
        // ภาษาที่ติดตั้ง
        $this->installedlanguage = Language::installedLanguage();
        // form
        $form = Html::create('form', [
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/index/model/categories/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ]);
        $fieldset = $form->add('fieldset', [
            'titleClass' => 'icon-menus',
            'title' => '{LNG_Details of} '.$params['categories'][$params['type']]
        ]);
        // ตารางหมวดหมู่
        $table = new DataTable([
            /* ข้อมูลใส่ลงในตาราง */
            'datas' => \Index\Categories\Model::toDataTable($params['type'], $this->multiple_language),
            /* ฟังก์ชั่นจัดรูปแบบการแสดงผลแถวของตาราง */
            'onRow' => [$this, 'onRow'],
            'border' => true,
            'responsive' => true,
            'pmButton' => true,
            'showCaption' => false,
            'headers' => [
                'category_id' => [
                    'text' => '{LNG_ID}'
                ]
            ]
        ]);
        if (!$this->multiple_language) {
            $table->headers['topic'] = [
                'text' => '{LNG_Detail}'
            ];
        }
        $fieldset->add('div', [
            'class' => 'item',
            'innerHTML' => $table->render()
        ]);
        $fieldset = $form->add('fieldset', [
            'class' => 'submit'
        ]);
        // submit
        $fieldset->add('submit', [
            'class' => 'button save large icon-save',
            'value' => '{LNG_Save}'
        ]);
        // type
        $fieldset->add('hidden', [
            'id' => 'type',
            'value' => $params['type']
        ]);
        // คืนค่า HTML
        return $form->render();
    }

    /**
     * จัดรูปแบบการแสดงผลในแต่ละแถว
     *
     * @param array  $item ข้อมูลแถว
     * @param int    $o    ID ของข้อมูล
     * @param object $prop กำหนด properties ของ TR
     *
     * @return array
     */
    public function onRow($item, $o, $prop)
    {
        $item['category_id'] = Form::text([
            'name' => 'category_id[]',
            'labelClass' => 'g-input',
            'size' => 2,
            'value' => $item['category_id']
        ])->render();
        if (isset($item['topic'])) {
            $item['topic'] = Form::text([
                'name' => 'topic[]',
                'labelClass' => 'g-input',
                'value' => $item['topic']
            ])->render();
        } else {
            foreach ($this->installedlanguage as $lng) {
                $item[$lng] = Form::text([
                    'name' => $lng.'[]',
                    'labelClass' => 'g-input',
                    'value' => isset($item[$lng]) ? $item[$lng] : $item[''],
                    'style' => 'background-image:url(../language/'.$lng.'.gif)'
                ])->render();
            }
        }
        return $item;
    }
}
