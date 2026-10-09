/**
 * modules/salary/admin.js
 *
 * ลงทะเบียน route และ helper ของโมดูลเงินเดือน
 */
EventManager.on('router:initialized', () => {
  // หน้าแรกของระบบเป็นสรุปเงินเดือน แทน Dashboard เดิม
  RouterManager.register('/', {
    template: 'salary/dashboard.html',
    title: '{LNG_Salary}',
    requireAuth: true
  });

  RouterManager.register('/salary', {
    template: 'salary/slips.html',
    title: '{LNG_My Slip}',
    requireAuth: true
  });

  RouterManager.register('/salary-list', {
    template: 'salary/salaries.html',
    title: '{LNG_List of} {LNG_Salary}',
    requireAuth: true
  });

  RouterManager.register('/salary-edit', {
    template: 'salary/record.html',
    title: '{LNG_Salary}',
    menuPath: '/salary-list',
    requireAuth: true
  });

  RouterManager.register('/salary-import', {
    template: 'salary/import.html',
    title: '{LNG_Import Salary Data}',
    requireAuth: true
  });

  RouterManager.register('/salary-importusers', {
    template: 'salary/importusers.html',
    title: '{LNG_Import Employee Data}',
    requireAuth: true
  });

  RouterManager.register('/salary-settings', {
    template: 'salary/settings.html',
    title: '{LNG_Module settings} {LNG_Salary}',
    requireAuth: true
  });
});

/**
 * ฟอร์มเพิ่ม/แก้ไขรายการเงินเดือน
 * คำนวณค่าล่วงเวลา ประกันสังคม ภาษี และเงินสุทธิ ให้เห็นระหว่างกรอกข้อมูล
 * (ค่าที่บันทึกจริงคำนวณซ้ำที่ฝั่งเซิร์ฟเวอร์เสมอ ฟังก์ชันนี้ทำหน้าที่แสดงผลเท่านั้น)
 *
 * - กรอกชั่วโมงล่วงเวลา = ค่าล่วงเวลาคำนวณจากฐานเงินเดือน ช่องจำนวนเงินจึงอ่านอย่างเดียว
 * - ปิดการคำนวณอัตโนมัติ (ตั้งค่าโมดูล) = ผู้ดูแลกรอกประกันสังคมและภาษีเอง
 *
 * เรียกโดย data-on-load ของฟอร์ม
 *
 * @param {HTMLElement} form
 *
 * @return {Function} ฟังก์ชันสำหรับถอด event listener
 */
function salaryRecordForm(form) {
  const inputNames = ['basic_salary', 'allowance', 'overtime_hours', 'overtime', 'bonus', 'deduction', 'social_security', 'tax'];
  const field = name => form.querySelector('[name="' + name + '"]');
  const overtime = field('overtime');
  const socialSecurity = field('social_security');
  const tax = field('tax');
  const netSalary = field('net_salary');
  const totalIncome = form.querySelector('[data-role="total_income"]');
  const inputs = inputNames.map(field).filter(element => element !== null);

  let timer = null;

  const format = value => Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });

  const calculate = async () => {
    const payload = {};
    inputNames.forEach(name => {
      const element = field(name);
      payload[name] = element ? element.value : 0;
    });

    try {
      const response = await window.http.post('api/salary/record/calculate', payload, {
        throwOnError: false
      });
      const data = response && response.data ? response.data : null;
      if (!data) {
        return;
      }

      // ช่อง type=number รับตัวเลขที่มีจุลภาคไม่ได้ จึงไม่ใช้ format()
      if (overtime) {
        overtime.readOnly = Boolean(data.overtime_from_hours);
        if (data.overtime_from_hours) {
          overtime.value = Number(data.overtime || 0).toFixed(2);
        }
      }

      // เปิดการคำนวณอัตโนมัติ = ประกันสังคมและภาษีเป็นผลลัพธ์ ปิด = ช่องให้กรอกเอง
      const autoCalculate = Boolean(data.auto_calculate);
      [socialSecurity, tax].forEach(element => {
        if (element) {
          element.readOnly = autoCalculate;
        }
      });
      if (autoCalculate) {
        if (socialSecurity) {
          socialSecurity.value = format(data.social_security);
        }
        if (tax) {
          tax.value = format(data.tax);
        }
      }

      if (netSalary) {
        netSalary.value = format(data.net_salary);
      }
      if (totalIncome) {
        totalIncome.textContent = format(data.total_income);
      }
    } catch (error) {
      console.error('salaryRecordForm', error);
    }
  };

  const onInput = () => {
    clearTimeout(timer);
    timer = setTimeout(calculate, 400);
  };

  inputs.forEach(element => {
    element.addEventListener('input', onInput);
    element.addEventListener('change', onInput);
  });

  calculate();

  return () => {
    clearTimeout(timer);
    inputs.forEach(element => {
      element.removeEventListener('input', onInput);
      element.removeEventListener('change', onInput);
    });
  };
}

/**
 * ลิงก์ดาวน์โหลด CSV ของหน้ารายการเงินเดือน
 * ระบบเดิมส่งออกตามตัวกรองปีและเดือนที่เลือกอยู่ จึงประกอบ URL ใหม่ตอนคลิก
 * (ตอนโหลดหน้ายังไม่รู้ค่าที่ผู้ใช้จะเลือก)
 */
document.addEventListener('click', event => {
  const link = event.target.closest('#salary_export');
  if (!link) {
    return;
  }

  const params = new URLSearchParams({type: 'csv'});
  const form = document.querySelector('form[data-table-filter="salarySalaries"]');
  if (form) {
    ['year', 'month', 'status', 'search'].forEach(name => {
      const field = form.querySelector('[name="' + name + '"]');
      if (field && field.value !== '' && field.value !== '-1') {
        params.set(name, field.value);
      }
    });
  }

  link.href = 'api/salary/salaries/export?' + params.toString();
});
