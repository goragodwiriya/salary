<?php
/**
 * @filesource modules/salary/views/import.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Import;

use Kotchasan\Html;
use Kotchasan\Http\UploadedFile;
use Kotchasan\Language;

/**
 * module=salary-import
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * ฟอร์มนำเข้าข้อมูล เงินเดือน
     *
     * @return string
     */
    public function render()
    {
        $form = Html::create('form', [
            'id' => 'setup_frm',
            'class' => 'setup_frm',
            'autocomplete' => 'off',
            'action' => 'index.php/salary/model/import/submit',
            'onsubmit' => 'doFormSubmit',
            'ajax' => true,
            'token' => true
        ]);
        $fieldset = $form->add('fieldset', [
            'titleClass' => 'icon-import',
            'title' => '{LNG_Import} {LNG_Salary}'
        ]);
        // import
        $fieldset->add('file', [
            'id' => 'import',
            'labelClass' => 'g-input icon-excel',
            'itemClass' => 'item',
            'label' => '{LNG_Browse file}',
            'placeholder' => 'salary.csv {ENCODE}',
            'comment' => Language::replace('File size is less than :size', [':size' => UploadedFile::getUploadSize()]),
            'accept' => ['csv']
        ]);
        // เดือน
        $fieldset->add('select', [
            'id' => 'month',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'item',
            'label' => '{LNG_Month}',
            'options' => Language::get('MONTH_LONG'),
            'value' => date('n')
        ]);
        // ปี
        $fieldset->add('number', [
            'id' => 'year',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'item',
            'label' => '{LNG_Year}',
            'value' => date('Y'),
            'maxlength' => 4
        ]);
        $file = 'modules/salary/views/import_'.Language::name().'.html';
        if (!is_file(ROOT_PATH.$file)) {
            $file = 'modules/salary/views/import_th.html';
        }
        if (is_file(ROOT_PATH.$file)) {
            $fieldset->add('div', [
                'class' => 'message',
                'innerHTML' => file_get_contents(ROOT_PATH.$file)
            ]);
        } else {
            $fieldset->add('div', [
                'class' => 'message',
                'innerHTML' => '<p>{LNG_The file must be in CSV format, encoding UTF-8 or TIS-620 only.}</p>'
            ]);
        }
        $fieldset = $form->add('fieldset', [
            'class' => 'submit'
        ]);
        // submit
        $fieldset->add('submit', [
            'class' => 'button save large icon-save',
            'value' => '{LNG_Import}'
        ]);
        \Gcms\Controller::$view->setContentsAfter([
            '/{ENCODE}/' => Language::get('CSV_ENCODING', '', self::$cfg->csv_language)
        ]);
        // คืนค่า HTML
        return $form->render();
    }
}
