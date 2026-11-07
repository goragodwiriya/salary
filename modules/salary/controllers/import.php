<?php
/**
 * @filesource modules/salary/controllers/import.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Import;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Salary\Base\Model as Base;

/**
 * API นำเข้าข้อมูลเงินเดือนจากไฟล์ CSV
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/salary/import/get
     * ค่าเริ่มต้นของฟอร์มนำเข้า
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, 'can_manage_salary')) {
                return $this->errorResponse('Permission required', 403);
            }

            // รายชื่อคอลัมน์มาจากตัวนำเข้าโดยตรง หน้าเว็บจึงบอกตรงกับที่ตัวนำเข้าต้องการเสมอ
            $headers = Model::headers();
            $optional = array_intersect_key($headers, array_flip(Model::optionalColumns()));

            return $this->successResponse([
                'data' => (object) [
                    'year' => date('Y'),
                    'month' => Base::monthValue(date('n')),
                    'encoding' => self::$cfg->salary_csv_language,
                    'sample_url' => WEB_URL.'export.php?module=salary&typ=sample&kind=salary',
                    'columns' => implode(', ', array_diff_key($headers, $optional)),
                    'optional_columns' => implode(', ', $optional),
                    'auto_calculate' => \Salary\Calculator\Model::autoCalculate() ? 1 : 0
                ],
                'options' => [
                    'year' => Base::yearOptions(),
                    'month' => Base::monthOptions()
                ]
            ], 'Import form loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/salary/import/save
     * อ่านไฟล์ CSV แล้วบันทึกข้อมูลเงินเดือนของเดือนที่เลือก
     *
     * @param Request $request
     *
     * @return \Kotchasan\Http\Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);
            $this->initLanguage($request);

            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }
            if (!ApiController::canModify($login, ['can_manage_salary'])) {
                return $this->errorResponse('Permission required', 403);
            }

            $year = $request->post('year')->toInt();
            $month = $request->post('month')->toInt();

            $errors = [];
            if ($month < 1 || $month > 12) {
                $errors['month'] = Language::get('Please select');
            }
            if ($year < 2000 || $year > 2100) {
                $errors['year'] = Language::get('Please select');
            }
            if (!empty($errors)) {
                return $this->formErrorResponse($errors);
            }

            $imported = null;
            foreach ($request->getUploadedFiles() as $name => $file) {
                /* @var $file \Kotchasan\Http\UploadedFile */
                if ($file->hasUploadFile()) {
                    if (!$file->validFileExt(['csv'])) {
                        return $this->formErrorResponse([
                            $name => Language::get('The type of file is invalid')
                        ]);
                    }
                    $imported = Model::import($file->getTempFileName(), $year, $month);
                } elseif ($err = $file->getErrorMessage()) {
                    return $this->formErrorResponse([$name => $err]);
                }
            }

            if ($imported === null) {
                return $this->formErrorResponse([
                    'import' => Language::get('Please select a file')
                ]);
            }

            $message = Language::replace('Successfully imported :count items', [':count' => $imported]);
            \Index\Log\Model::add(0, 'salary', 'Import', $message, $login->id);

            return $this->redirectResponse('/salary-list?year='.$year.'&month='.Base::monthValue($month), $message);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
