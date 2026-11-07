<?php
/**
 * @filesource modules/salary/controllers/importusers.php
 *
 * @copyright 2026 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Importusers;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * API นำเข้าข้อมูลพนักงานจากไฟล์ CSV
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET api/salary/importusers/get
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
            if (!\Index\Users\Model::canManage($login)) {
                return $this->errorResponse('Permission required', 403);
            }

            return $this->successResponse([
                'data' => (object) [
                    'encoding' => self::$cfg->salary_csv_language,
                    'sample_url' => WEB_URL.'export.php?module=salary&typ=sample&kind=employee'
                ]
            ], 'Import form loaded');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }

    /**
     * POST api/salary/importusers/save
     * อ่านไฟล์ CSV แล้วบันทึกข้อมูลพนักงาน
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
            if (!\Index\Users\Model::canManage($login)) {
                return $this->errorResponse('Permission required', 403);
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
                    $imported = Model::import($file->getTempFileName());
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
            \Index\Log\Model::add(0, 'salary', 'Import Users', $message, $login->id);

            return $this->redirectResponse('/users', $message);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500, $e);
        }
    }
}
