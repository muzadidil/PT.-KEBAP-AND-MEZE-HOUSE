<?php

return [

    'nav' => [
        'group' => 'Payroll',
        'employees' => 'Employees',
        'components' => 'Pay Components',
        'payslips' => 'Payslips',
    ],

    'field' => [
        'name' => 'Full name',
        'nik' => 'Employee ID',
        'position' => 'Position',
        'section' => 'Section',
        'basic_salary' => 'Basic salary',
        'type' => 'Type',
        'component' => 'Item name',
        'default_amount' => 'Default amount',
        'fixed' => 'Fixed amount',
        'employee' => 'Employee',
        'period' => 'Period',
        'issued_on' => 'Payslip date',
        'number' => 'Payslip no.',
        'earnings' => 'Earnings',
        'deductions' => 'Deductions',
        'label' => 'Description',
        'amount' => 'Amount',
        'total_earnings' => 'Total earnings',
        'total_deductions' => 'Total deductions',
        'net_pay' => 'Net pay',
    ],

    'type' => [
        'earning' => 'Earning',
        'deduction' => 'Deduction',
    ],

    'help' => [
        'nik' => 'Optional.',
        'active' => 'Employees who leave are deactivated, not deleted, so their past payslips stay intact.',
        'default_amount' => 'Leave empty if it varies. Filled in automatically when this item is picked on a payslip.',
        'fixed' => 'The amount is locked to the default and cannot be changed on a payslip.',
        'lines' => 'Pick from the Pay Components list, or type your own description.',
        'snapshot' => 'Name, ID, and position are copied onto the payslip when it is saved. Changing the employee later does not change this payslip.',
    ],

    'action' => [
        'add_earning' => 'Add allowance',
        'add_deduction' => 'Add deduction',
        'pdf' => 'Download PDF',
        'letterhead' => 'Letterhead & signatory',
    ],

    'letterhead' => [
        'company' => 'Company name',
        'address' => 'Address',
        'city' => 'City (for the signature)',
        'signer_name' => 'Signatory name',
        'signer_title' => 'Signatory title',
        'logo' => 'The logo is taken from Master Data → Appearance.',
        'saved' => 'Payslip letterhead saved',
    ],

    'preview' => 'Payslip preview',
    'duplicate' => 'This employee already has a payslip for that month (:number). Edit that one instead of creating a new one.',

    // Payslip body. Always printed in Indonesian regardless of the app
    // language; these are the English equivalents.
    'slip' => [
        'company' => 'COMPANY NAME',
        'title' => 'EMPLOYEE PAYSLIP',
        'period' => 'Period: :period',
        'number' => 'No: :number',
        'name' => 'Name',
        'nik' => 'ID',
        'position' => 'Position',
        'earnings' => 'EARNINGS',
        'deductions' => 'DEDUCTIONS',
        'basic_salary' => 'Basic Salary',
        'total_earnings' => 'Total Earnings',
        'total_deductions' => 'Total Deductions',
        'net_pay' => 'NET PAY (Take Home Pay)',
        'spelled' => 'In words: :words',
        'recipient' => 'Recipient,',
        'signer' => 'Management',
        'blank' => '(............................)',
    ],

];
