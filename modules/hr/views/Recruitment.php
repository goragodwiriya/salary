<?php
/**
 * @filesource modules/hr/views/Recruitment.php
 *
 * @copyright 2024 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Hr\Recruitment;

use Kotchasan\DataTable;
use Kotchasan\Http\Request;
use Kotchasan\Html;
use Kotchasan\Form;
use Kotchasan\Date;
use Kotchasan\Language;

/**
 * View for displaying recruitment tracker list and form.
 *
 * @author Jules <jules@example.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * Render the recruitment tracker list using DataTable.
     *
     * @param Request $request
     * @param array $login Login information.
     * @return string HTML for the DataTable.
     */
    public function render(Request $request, $login)
    {
        $params = $request->getQueryParams();
        $params['module'] = 'hr-recruitment';

        $table = new DataTable([
            'uri' => $request->getUri()->withParams($params)->toString(),
            'model' => \Hr\Model\Recruitment::toDataTable(['status' => $request->request('status', 'open')->toString()]),
            'perPage' => $request->cookie('hrRecruitment_perPage', 30)->toInt(),
            'sort' => $request->cookie('hrRecruitment_sort', 'open_date desc')->toString(),
            'onRow' => [$this, 'onRow'],
            'buttons' => [
                'edit' => [
                    'class' => 'icon-edit button green',
                    'href' => $request->getUri()->withParams(['action' => 'edit', 'id' => ':id'])->toString(),
                    'text' => '{LNG_Edit}'
                ]
            ],
            'headers' => [
                'id' => ['text' => '{LNG_ID}'],
                'position_title' => ['text' => '{LNG_Position Title}', 'sort' => 'position_title'],
                'department' => ['text' => '{LNG_Department}', 'sort' => 'department'],
                'open_date' => ['text' => '{LNG_Open Date}', 'sort' => 'open_date', 'class' => 'center'],
                'close_date' => ['text' => '{LNG_Close Date}', 'sort' => 'close_date', 'class' => 'center'],
                'status' => ['text' => '{LNG_Status}', 'sort' => 'status', 'class' => 'center'],
                'number_of_applicants' => ['text' => '{LNG_Applicants}', 'sort' => 'number_of_applicants', 'class' => 'center'],
                'selected_candidate_name' => ['text' => '{LNG_Hired Candidate}']
            ],
            'cols' => [
                'open_date' => ['class' => 'center'],
                'close_date' => ['class' => 'center'],
                'status' => ['class' => 'center'],
                'number_of_applicants' => ['class' => 'center']
            ]
        ]);

        setcookie('hrRecruitment_perPage', $table->perPage, time() + 2592000, '/', HOST, HTTPS, true);
        setcookie('hrRecruitment_sort', $table->sort, time() + 2592000, '/', HOST, HTTPS, true);

        $filter = $table->addFilter(array(
            'name' => 'status',
            'text' => '{LNG_Status}',
            'options' => array('all' => '{LNG_All items}') + Language::get('RECRUITMENT_STATUS'), // Assumes RECRUITMENT_STATUS is defined
            'value' => $request->request('status', 'open')->text()
        ));

        return $table->render();
    }

    /**
     * Callback function for formatting each row in DataTable.
     */
    public function onRow($item, $o, $prop)
    {
        $item['open_date'] = Date::format($item['open_date'], 'd M Y');
        $item['close_date'] = $item['close_date'] ? Date::format($item['close_date'], 'd M Y') : '';
        $item['status'] = Language::get('RECRUITMENT_STATUS', '', $item['status']);
        return $item;
    }

    /**
     * Render the form for adding or editing a recruitment record.
     */
    public function renderForm(Request $request, $recruitment, $login)
    {
        $form = Html::create('form', [
            'id' => 'recruitment_form',
            'class' => 'setup_frm',
            'method' => 'post',
            'action' => 'index.php/hr/model/recruitment/submit',
            'autocomplete' => 'off',
            'ajax' => true,
            'token' => true
        ]);

        $fieldset = $form->add('fieldset');
        $title = $recruitment ? '{LNG_Edit Recruitment Record}' : '{LNG_Add New Recruitment Record}';
        $fieldset->add('legend', ['innerHTML' => '<span>'.$title.'</span>']);

        if ($recruitment) {
            $fieldset->add('hidden', ['id' => 'id', 'name' => 'id', 'value' => $recruitment->id]);
        }

        $fieldset->add('text', [
            'id' => 'position_title',
            'labelClass' => 'g-input icon-user', // Choose appropriate icon
            'itemClass' => 'item',
            'label' => '{LNG_Position Title}',
            'maxlength' => 255,
            'value' => isset($recruitment->position_title) ? $recruitment->position_title : '',
            'required' => true,
            'autofocus' => true,
        ]);

        $fieldset->add('text', [
            'id' => 'department',
            'labelClass' => 'g-input icon-group',
            'itemClass' => 'item',
            'label' => '{LNG_Department}',
            'maxlength' => 100,
            'value' => isset($recruitment->department) ? $recruitment->department : ''
        ]);

        $fieldset->add('date', [
            'id' => 'open_date',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'item',
            'label' => '{LNG_Open Date}',
            'value' => isset($recruitment->open_date) ? $recruitment->open_date : date('Y-m-d'),
            'required' => true
        ]);

        $fieldset->add('date', [
            'id' => 'close_date',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'item',
            'label' => '{LNG_Close Date}',
            'value' => isset($recruitment->close_date) ? $recruitment->close_date : ''
        ]);

        $fieldset->add('select', [
            'id' => 'status',
            'labelClass' => 'g-input icon-star0',
            'itemClass' => 'item',
            'label' => '{LNG_Status}',
            'options' => Language::get('RECRUITMENT_STATUS'), // e.g. ['open' => 'Open', 'closed' => 'Closed', 'filled' => 'Filled', 'on_hold' => 'On Hold']
            'value' => isset($recruitment->status) ? $recruitment->status : 'open'
        ]);

        $fieldset->add('number', [
            'id' => 'number_of_applicants',
            'labelClass' => 'g-input icon-number',
            'itemClass' => 'item',
            'label' => '{LNG_Number of Applicants}',
            'value' => isset($recruitment->number_of_applicants) ? $recruitment->number_of_applicants : 0
        ]);

        $fieldset->add('text', [
            'id' => 'source_of_applicants',
            'labelClass' => 'g-input icon-world',
            'itemClass' => 'item',
            'label' => '{LNG_Source of Applicants}',
            'maxlength' => 255,
            'value' => isset($recruitment->source_of_applicants) ? $recruitment->source_of_applicants : ''
        ]);

        // Selected Candidate (Dropdown of employees)
        $candidates = \Hr\Model\Recruitment::getPotentialCandidatesForSelect();
        $fieldset->add('select', [
            'id' => 'selected_candidate_id',
            'labelClass' => 'g-input icon-user',
            'itemClass' => 'item',
            'label' => '{LNG_Selected Candidate (if hired)}',
            'options' => [0 => '{LNG_None}'] + $candidates, // 0 or null for none
            'value' => isset($recruitment->selected_candidate_id) ? $recruitment->selected_candidate_id : 0
        ]);

        $fieldset->add('date', [
            'id' => 'candidate_start_date',
            'labelClass' => 'g-input icon-calendar',
            'itemClass' => 'item',
            'label' => '{LNG_Candidate Start Date (if hired)}',
            'value' => isset($recruitment->candidate_start_date) ? $recruitment->candidate_start_date : ''
        ]);

        $fieldset->add('number', [
            'id' => 'cost_per_hire',
            'labelClass' => 'g-input icon-money',
            'itemClass' => 'item',
            'label' => '{LNG_Cost per Hire}',
            'value' => isset($recruitment->cost_per_hire) ? $recruitment->cost_per_hire : ''
        ]);

        // Time to Fill is calculated, so not a form field for direct input

        $fieldset = $form->add('fieldset', ['class' => 'submit']);
        $fieldset->add('submit', [
            'class' => 'button ok large icon-save',
            'value' => '{LNG_Save}'
        ]);
        $fieldset->add('a', [
            'href' => $request->getUri()->withParams(['module' => 'hr-recruitment'])->toString(),
            'class' => 'button cancel large icon-cancel',
            'innerHTML' => '{LNG_Cancel}'
        ]);

        $form->script('initEditInPlace("recruitment_form");');

        return $form->render();
    }
}
?>
