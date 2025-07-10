<?php
/**
 * @filesource modules/hr/controllers/Recruitment.php
 *
 * @copyright 2024 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Hr\Recruitment;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Controller for managing recruitment tracking.
 *
 * @author Jules <jules@example.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * Display recruitment list or add/edit form.
     *
     * @param Request $request
     * @return string
     */
    public function render(Request $request)
    {
        $login = Login::isMember();
        if (!$login) {
            return \Index\Error\Controller::execute($this, $request->getUri());
        }

        if (!Login::checkPermission($login, 'hr_management')) {
            return \Index\Error\Controller::execute($this, $request->getUri());
        }

        $action = $request->request('action')->toString();
        $id = $request->request('id')->toInt();

        if ($action === 'edit' && $id > 0) {
            return $this->showForm($request, $id, $login);
        } elseif ($action === 'add') {
            return $this->showForm($request, 0, $login);
        } else {
            return $this->showList($request, $login);
        }
    }

    /**
     * Display the list of recruitment records.
     *
     * @param Request $request
     * @param array $login Login information.
     * @return string HTML content for the list.
     */
    private function showList(Request $request, $login)
    {
        $this->title = Language::get('Recruitment Tracker');
        $this->menu = 'hr';

        $section = Html::create('section');
        $breadcrumbs = $section->add('nav', ['class' => 'breadcrumbs']);
        $ul = $breadcrumbs->add('ul');
        $ul->appendChild('<li><span class="icon-home">{LNG_Home}</span></li>');
        $ul->appendChild('<li><span>{LNG_HR Management}</span></li>');
        $ul->appendChild('<li><span>'.$this->title.'</span></li>');

        $header = $section->add('header', [
            'innerHTML' => '<h2 class="icon-project">'.$this->title.'</h2>' // Choose appropriate icon
        ]);

        $header->add('a', [
            'class' => 'button green icon-add float_right',
            'href' => $request->getUri()->withParams(['module' => 'hr-recruitment', 'action' => 'add'])->toString(),
            'innerHTML' => '{LNG_Add New Recruitment}'
        ]);

        $div = $section->add('div', ['class' => 'content_bg']);
        $div->appendChild(\Hr\Recruitment\View::create()->render($request, $login));

        return $section->render();
    }

    /**
     * Display the form for adding or editing a recruitment record.
     *
     * @param Request $request
     * @param int $id Recruitment ID for editing, 0 for adding.
     * @param array $login Login information.
     * @return string HTML content for the form.
     */
    private function showForm(Request $request, $id, $login)
    {
        $recruitment = ($id > 0) ? \Hr\Model\Recruitment::get($id) : null;

        if ($id > 0 && !$recruitment) {
            return \Index\Error\Controller::execute($this, $request->getUri());
        }

        $this->title = Language::get($id > 0 ? 'Edit Recruitment Record' : 'Add New Recruitment Record');
        $this->menu = 'hr';

        $section = Html::create('section');
        $breadcrumbs = $section->add('nav', ['class' => 'breadcrumbs']);
        $ul = $breadcrumbs->add('ul');
        $ul->appendChild('<li><span class="icon-home">{LNG_Home}</span></li>');
        $ul->appendChild('<li><span>{LNG_HR Management}</span></li>');
        $ul->appendChild('<li><a href="'.$request->getUri()->withParams(['module' => 'hr-recruitment'])->toString().'">{LNG_Recruitment Tracker}</a></li>');
        $ul->appendChild('<li><span>'.($id > 0 ? '{LNG_Edit}' : '{LNG_Add}').'</span></li>');

        $section->add('header', [
            'innerHTML' => '<h2 class="icon-edit">'.$this->title.'</h2>'
        ]);

        $div = $section->add('div', ['class' => 'content_bg']);
        $div->appendChild(\Hr\Recruitment\View::create()->renderForm($request, $recruitment, $login));

        return $section->render();
    }
}
?>
