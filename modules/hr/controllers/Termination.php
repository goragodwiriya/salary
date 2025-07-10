<?php
/**
 * @filesource modules/hr/controllers/Termination.php
 *
 * @copyright 2024 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Hr\Termination;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * Controller for managing employee terminations.
 *
 * @author Jules <jules@example.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Controller
{
    /**
     * Display termination list or add/edit form.
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
     * Display the list of terminations.
     *
     * @param Request $request
     * @param array $login Login information.
     * @return string HTML content for the termination list.
     */
    private function showList(Request $request, $login)
    {
        $this->title = Language::get('Termination Records');
        $this->menu = 'hr'; // Assuming it falls under the main HR menu

        $section = Html::create('section');
        $breadcrumbs = $section->add('nav', ['class' => 'breadcrumbs']);
        $ul = $breadcrumbs->add('ul');
        $ul->appendChild('<li><span class="icon-home">{LNG_Home}</span></li>');
        $ul->appendChild('<li><span>{LNG_HR Management}</span></li>');
        $ul->appendChild('<li><span>'.$this->title.'</span></li>');

        $header = $section->add('header', [
            'innerHTML' => '<h2 class="icon-exit">'.$this->title.'</h2>' // icon-exit or similar
        ]);

        $header->add('a', [
            'class' => 'button green icon-add float_right',
            'href' => $request->getUri()->withParams(['module' => 'hr-termination', 'action' => 'add'])->toString(),
            'innerHTML' => '{LNG_Add New Termination Record}'
        ]);

        $div = $section->add('div', ['class' => 'content_bg']);
        $div->appendChild(\Hr\Termination\View::create()->render($request, $login));

        return $section->render();
    }

    /**
     * Display the form for adding or editing a termination record.
     *
     * @param Request $request
     * @param int $id Termination ID for editing, 0 for adding.
     * @param array $login Login information.
     * @return string HTML content for the form.
     */
    private function showForm(Request $request, $id, $login)
    {
        $termination = ($id > 0) ? \Hr\Model\Termination::get($id) : null;

        if ($id > 0 && !$termination) {
            return \Index\Error\Controller::execute($this, $request->getUri());
        }

        $this->title = Language::get($id > 0 ? 'Edit Termination Record' : 'Add New Termination Record');
        $this->menu = 'hr';

        $section = Html::create('section');
        $breadcrumbs = $section->add('nav', ['class' => 'breadcrumbs']);
        $ul = $breadcrumbs->add('ul');
        $ul->appendChild('<li><span class="icon-home">{LNG_Home}</span></li>');
        $ul->appendChild('<li><span>{LNG_HR Management}</span></li>');
        $ul->appendChild('<li><a href="'.$request->getUri()->withParams(['module' => 'hr-termination'])->toString().'">{LNG_Termination Records}</a></li>');
        $ul->appendChild('<li><span>'.($id > 0 ? '{LNG_Edit}' : '{LNG_Add}').'</span></li>');

        $section->add('header', [
            'innerHTML' => '<h2 class="icon-edit">'.$this->title.'</h2>'
        ]);

        $div = $section->add('div', ['class' => 'content_bg']);
        $div->appendChild(\Hr\Termination\View::create()->renderForm($request, $termination, $login));

        return $section->render();
    }
}
?>
