<?php
/**
 * @filesource modules/salary/views/home.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Salary\Home;

use Gcms\Login;
use Kotchasan\Html;
use Kotchasan\Http\Request;

/**
 * View สำหรับแสดงกราฟแนวโน้มเงินเดือนในหน้า Home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class View extends \Gcms\View
{
    /**
     * สร้างบล็อกกราฟแนวโน้มเงินเดือน
     *
     * @param Request $request
     * @param array   $login
     *
     * @return string
     */
    public static function render(Request $request, $login)
    {
        if (!$login) {
            return '';
        }

        // สร้าง container หลัก
        $container = Html::create('div', [
            'class' => 'dashboard_item clear'
        ]);

        // ตรวจสอบสิทธิ์และแสดงกราฟตามประเภทผู้ใช้
        if (Login::checkPermission($login, 'can_manage_salary')) {
            // ผู้ดูแล
            self::renderAdminTrend($container);
        } else {
            // สมาชิกทั่วไป
            self::renderMemberTrend($container, $login['id']);
        }

        // เพิ่ม JavaScript
        $container->script('initSalaryHomeTrends();');

        return $container->render();
    }

    /**
     * แสดงกราฟแนวโน้มสำหรับสมาชิกทั่วไป
     *
     * @param Html $container
     * @param int  $memberId
     */
    private static function renderMemberTrend($container, $memberId)
    {
        // อ่านข้อมูลแนวโน้มเงินเดือนของสมาชิก
        $trendData = \Salary\Home\Model::getMemberSalaryTrend($memberId);

        if (empty($trendData)) {
            $container->add('div', [
                'class' => 'message',
                'innerHTML' => '{LNG_No data available}'
            ]);
            return;
        }

        // สร้างบล็อกกราฟ
        $block = $container->add('div', [
            'class' => 'ggraphs'
        ]);

        $block->add('h4', [
            'innerHTML' => '<span class="icon-stats">{LNG_My Salary Trend}</span>'
        ]);

        // สร้าง table สำหรับข้อมูล (GGraphs format)
        $table = $container->add('table', [
            'id' => 'memberSalaryTrendTable',
            'class' => 'hidden'
        ]);

        // สร้าง thead
        $thead = $table->add('thead');
        $tr = $thead->add('tr');
        $tr->add('th', ['innerHTML' => 'Series']);
        foreach ($trendData as $item) {
            $tr->add('th', ['innerHTML' => $item['period']]);
        }

        $tbody = $table->add('tbody');

        // แถวสำหรับ Net Salary
        $tr = $tbody->add('tr');
        $tr->add('th', ['innerHTML' => '{LNG_Net Salary}']);
        foreach ($trendData as $item) {
            $tr->add('td', ['innerHTML' => $item['net_salary']]);
        }

        // แถวสำหรับ Basic Salary
        $tr = $tbody->add('tr');
        $tr->add('th', ['innerHTML' => '{LNG_Basic Salary}']);
        foreach ($trendData as $item) {
            $tr->add('td', ['innerHTML' => $item['basic_salary']]);
        }

        // พื้นที่สำหรับแสดงกราฟ
        $block->add('div', [
            'id' => 'memberSalaryTrendChart',
            'style' => 'width: 100%; height: 300px; margin: 15px 0;'
        ]);
    }

    /**
     * แสดงกราฟแนวโน้มสำหรับผู้ดูแล
     *
     * @param Html $container
     */
    private static function renderAdminTrend($container)
    {
        $category = \Index\Category\Model::init();

        // อ่านข้อมูลแนวโน้มแยกตามแผนก
        $departmentTrend = \Salary\Home\Model::getDepartmentSalaryTrend();

        // อ่านข้อมูลสำหรับกราฟวงกลม
        $departmentSalaryPie = \Salary\Home\Model::getDepartmentSalaryPie();
        $departmentEmployeePie = \Salary\Home\Model::getDepartmentEmployeeCount();
        $departmentEmployeeTrend = \Salary\Home\Model::getDepartmentEmployeeTrend();
        $salaryDistribution = \Salary\Home\Model::getSalaryDistribution();

        if (!empty($departmentTrend)) {
            // กราฟแนวโน้มเงินเดือนตามแผนก
            self::renderDepartmentTrendChart($category, $container, $departmentTrend);
        }

        if (!empty($departmentEmployeeTrend)) {
            // กราฟแนวโน้มจำนวนพนักงานตามแผนก
            self::renderDepartmentEmployeeTrendChart($category, $container, $departmentEmployeeTrend);
        }

        if (!empty($departmentSalaryPie)) {
            // กราฟวงกลมเงินเดือนตามแผนก
            self::renderDepartmentSalaryPieChart($category, $container, $departmentSalaryPie);
        }

        if (!empty($departmentEmployeePie)) {
            // กราฟวงกลมจำนวนพนักงานตามแผนก
            self::renderDepartmentEmployeePieChart($category, $container, $departmentEmployeePie);
        }

        if (!empty($salaryDistribution)) {
            // กราฟการกระจายเงินเดือน
            self::renderSalaryDistributionChart($container, $salaryDistribution);
        }

        if (empty($departmentTrend) && empty($departmentSalaryPie) && empty($departmentEmployeePie)) {
            $container->add('div', [
                'class' => 'message',
                'innerHTML' => '{LNG_No data available}'
            ]);
        }
    }

    /**
     * สร้างกราฟแนวโน้มตามแผนก
     *
     * @param Html  $container
     * @param array $data
     */
    private static function renderDepartmentTrendChart($category, $container, $data)
    {
        // สร้างบล็อกกราฟ
        $block = $container->add('div', [
            'class' => 'ggraphs'
        ]);

        $block->add('h4', [
            'innerHTML' => '<span class="icon-stats">{LNG_Salary Trend}/{LNG_Department}</span>'
        ]);

        // สร้าง table สำหรับข้อมูลแผนก
        $table = $block->add('table', [
            'id' => 'departmentTrendTable',
            'class' => 'hidden'
        ]);

        $rows = [];

        // สร้าง thead
        $thead = $table->add('thead');
        $tr = $thead->add('tr');
        $tr->add('th', ['innerHTML' => 'Department']);
        foreach ($data as $period => $deptData) {
            $tr->add('th', ['innerHTML' => $period]);
            foreach ($deptData as $dept => $avg_salary) {
                $rows[$dept][$period] = $avg_salary;
            }
        }

        $tbody = $table->add('tbody');

        // สร้างแถวสำหรับแต่ละแผนก
        foreach ($rows as $department => $deptData) {
            $tr = $tbody->add('tr');
            $tr->add('th', ['innerHTML' => $category->get('department', $department)]);
            foreach ($deptData as $period => $value) {
                $tr->add('td', ['innerHTML' => $value]);
            }
        }

        // พื้นที่สำหรับแสดงกราฟ
        $block->add('div', [
            'id' => 'departmentTrendChart',
            'style' => 'width: 100%; height: 300px; margin: 15px 0;'
        ]);
    }

    /**
     * สร้างกราฟวงกลมเงินเดือนตามแผนก
     *
     * @param Html  $container
     * @param array $data
     */
    private static function renderDepartmentSalaryPieChart($category, $container, $data)
    {
        // สร้างบล็อกกราฟ
        $block = $container->add('div', [
            'class' => 'ggraphs'
        ]);

        $block->add('h4', [
            'innerHTML' => '<span class="icon-pie">{LNG_Average Salary}/{LNG_Department}</span>'
        ]);

        // สร้าง table สำหรับข้อมูลเงินเดือนตามแผนก
        $table = $block->add('table', [
            'id' => 'departmentSalaryPieTable',
            'class' => 'hidden'
        ]);

        // สร้าง thead
        $thead = $table->add('thead');
        $tr = $thead->add('tr');
        $tr->add('th', ['innerHTML' => 'Category']);
        foreach ($data as $item) {
            $tr->add('th', ['innerHTML' => $category->get('department', $item['department'])]);
        }

        $tbody = $table->add('tbody');

        // แถวสำหรับเงินเดือนเฉลี่ย
        $tr = $tbody->add('tr');
        $tr->add('th', ['innerHTML' => '{LNG_Average Salary}']);
        foreach ($data as $item) {
            $tr->add('td', ['innerHTML' => floor($item['avg_salary'])]);
        }

        // พื้นที่สำหรับแสดงกราฟ
        $block->add('div', [
            'id' => 'departmentSalaryPieChart',
            'style' => 'width: 100%; height: 300px; margin: 15px 0;'
        ]);
    }

    /**
     * สร้างกราฟวงกลมจำนวนพนักงานตามแผนก
     *
     * @param Html  $container
     * @param array $data
     */
    private static function renderDepartmentEmployeePieChart($category, $container, $data)
    {
        // สร้างบล็อกกราฟ
        $block = $container->add('div', [
            'class' => 'ggraphs'
        ]);

        $block->add('h4', [
            'innerHTML' => '<span class="icon-group">{LNG_Employee Count}/{LNG_Department}</span>'
        ]);

        // สร้าง table สำหรับข้อมูลจำนวนพนักงานตามแผนก
        $table = $block->add('table', [
            'id' => 'departmentEmployeePieTable',
            'class' => 'hidden'
        ]);

        // สร้าง thead
        $thead = $table->add('thead');
        $tr = $thead->add('tr');
        $tr->add('th', ['innerHTML' => 'Category']);
        foreach ($data as $item) {
            $tr->add('th', ['innerHTML' => $category->get('department', $item['department'])]);
        }

        $tbody = $table->add('tbody');

        // แถวสำหรับจำนวนพนักงาน
        $tr = $tbody->add('tr');
        $tr->add('th', ['innerHTML' => '{LNG_Employee Count}']);
        foreach ($data as $item) {
            $tr->add('td', ['innerHTML' => $item['employee_count']]);
        }

        // พื้นที่สำหรับแสดงกราฟ
        $block->add('div', [
            'id' => 'departmentEmployeePieChart',
            'style' => 'width: 100%; height: 300px; margin: 15px 0;'
        ]);
    }

    /**
     * สร้างกราฟแนวโน้มจำนวนพนักงานตามแผนก
     *
     * @param Html  $container
     * @param array $data
     */
    private static function renderDepartmentEmployeeTrendChart($category, $container, $data)
    {
        // สร้างบล็อกกราฟ
        $block = $container->add('div', [
            'class' => 'ggraphs'
        ]);

        $block->add('h4', [
            'innerHTML' => '<span class="icon-stats">{LNG_Employee Count Trend}/{LNG_Department}</span>'
        ]);

        // สร้าง table สำหรับข้อมูลแผนก
        $table = $block->add('table', [
            'id' => 'departmentEmployeeTrendTable',
            'class' => 'hidden'
        ]);

        $rows = [];

        // สร้าง thead
        $thead = $table->add('thead');
        $tr = $thead->add('tr');
        $tr->add('th', ['innerHTML' => 'Department']);
        foreach ($data as $period => $deptData) {
            $tr->add('th', ['innerHTML' => $period]);
            foreach ($deptData as $dept => $count) {
                $rows[$dept][$period] = $count;
            }
        }

        $tbody = $table->add('tbody');

        // สร้างแถวสำหรับแต่ละแผนก
        foreach ($rows as $department => $deptData) {
            $tr = $tbody->add('tr');
            $tr->add('th', ['innerHTML' => $category->get('department', $department)]);
            foreach ($deptData as $period => $value) {
                $tr->add('td', ['innerHTML' => $value]);
            }
        }

        // พื้นที่สำหรับแสดงกราฟ
        $block->add('div', [
            'id' => 'departmentEmployeeTrendChart',
            'style' => 'width: 100%; height: 300px; margin: 15px 0;'
        ]);
    }

    /**
     * สร้างกราฟการกระจายเงินเดือน
     *
     * @param Html  $container
     * @param array $data
     */
    private static function renderSalaryDistributionChart($container, $data)
    {
        // สร้างบล็อกกราฟ
        $block = $container->add('div', [
            'class' => 'ggraphs'
        ]);

        $block->add('h4', [
            'innerHTML' => '<span class="icon-pie">{LNG_Salary Distribution}</span>'
        ]);

        // สร้าง table สำหรับข้อมูลการกระจายเงินเดือน
        $table = $block->add('table', [
            'id' => 'salaryDistributionTable',
            'class' => 'hidden'
        ]);

        // สร้าง thead
        $thead = $table->add('thead');
        $tr = $thead->add('tr');
        $tr->add('th', ['innerHTML' => 'Category']);
        foreach ($data as $item) {
            $tr->add('th', ['innerHTML' => $item['range']]);
        }

        $tbody = $table->add('tbody');

        // แถวสำหรับจำนวนพนักงาน
        $tr = $tbody->add('tr');
        $tr->add('th', ['innerHTML' => '{LNG_Employee Count}']);
        foreach ($data as $item) {
            $tr->add('td', ['innerHTML' => $item['count']]);
        }

        // พื้นที่สำหรับแสดงกราฟ
        $block->add('div', [
            'id' => 'salaryDistributionChart',
            'style' => 'width: 100%; height: 300px; margin: 15px 0;'
        ]);
    }
}
