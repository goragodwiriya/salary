function printSalarySlip() {
  window.print();
};

function calculateSalary() {
  const formData = new FormData();

  const basicSalary = document.getElementById('salary_basic_salary');
  const allowance = document.getElementById('salary_allowance');
  const overtime = document.getElementById('salary_overtime');
  const bonus = document.getElementById('salary_bonus');
  const deduction = document.getElementById('salary_deduction');

  if (basicSalary) formData.append('basic_salary', basicSalary.value);
  if (allowance) formData.append('allowance', allowance.value);
  if (overtime) formData.append('overtime', overtime.value);
  if (bonus) formData.append('bonus', bonus.value);
  if (deduction) formData.append('deduction', deduction.value);

  const tokenElement = document.getElementsByName('token')[0];
  if (tokenElement) {
    formData.append('token', tokenElement.value);
  }

  const xhr = new XMLHttpRequest();
  xhr.open('POST', 'index.php/salary/model/calculator/ajax', true);
  xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4 && xhr.status === 200) {
      try {
        const result = JSON.parse(xhr.responseText);
        if (result.success) {
          const socialSecurityField = document.getElementById('salary_social_security');
          const taxField = document.getElementById('salary_tax');
          const netSalaryField = document.getElementById('salary_net_salary');

          if (socialSecurityField) {
            socialSecurityField.value = toCurrency(result.social_security);
          }
          if (taxField) {
            taxField.value = toCurrency(result.income_tax);
          }
          if (netSalaryField) {
            netSalaryField.value = toCurrency(result.net_salary);
          }
        } else if (result.message) {
          console.error('Calculation error:', result.message);
        }
      } catch (e) {
        console.error('Error parsing response:', e);
      }
    }
  };

  xhr.send(formData);
}

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = function() {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function initSalaryWrite() {
  const salarySelects = ['salary_member_id', 'salary_year', 'salary_month'];
  const salaryInputs = ['salary_basic_salary', 'salary_allowance', 'salary_overtime', 'salary_bonus', 'salary_deduction'];

  const debouncedCalculate = debounce(function() {
    calculateSalary();
  }, 500);

  salarySelects.forEach(function(inputId) {
    const element = document.getElementById(inputId);
    if (element) {
      element.addEventListener('change', function() {
        debouncedCalculate();
      });
    }
  });

  salaryInputs.forEach(function(inputId) {
    const element = document.getElementById(inputId);
    if (element) {
      element.addEventListener('input', function() {
        debouncedCalculate();
      });
    }
  });

  if (document.getElementById('salary_basic_salary')) {
    calculateSalary();
  }
}

function initSalaryHomeTrends() {
  if (document.getElementById('departmentTrendChart')) {
    new GGraphs('departmentTrendChart', {
      curveType: 'curve',
      table: 'departmentTrendTable',
      fillArea: true
    });
  }
  if (document.getElementById('memberSalaryTrendChart')) {
    new GGraphs('memberSalaryTrendChart', {
      curveType: 'curve',
      table: 'memberSalaryTrendTable',
      fillArea: true
    });
  }
  if (document.getElementById('departmentSalaryPieChart')) {
    new GGraphs('departmentSalaryPieChart', {
      type: 'pie',
      table: 'departmentSalaryPieTable'
    });
  }

  if (document.getElementById('departmentEmployeePieChart')) {
    new GGraphs('departmentEmployeePieChart', {
      type: 'pie',
      table: 'departmentEmployeePieTable'
    });
  }

  if (document.getElementById('departmentEmployeeTrendChart')) {
    new GGraphs('departmentEmployeeTrendChart', {
      type: 'line',
      table: 'departmentEmployeeTrendTable',
      fillArea: true
    });
  }

  if (document.getElementById('salaryDistributionChart')) {
    new GGraphs('salaryDistributionChart', {
      type: 'donut',
      table: 'salaryDistributionTable',
      donutThickness: 40
    });
  }
}