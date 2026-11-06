<?php

return [
    'col' => 'Paid',
    'unpaid' => 'Not recorded',
    'submit' => 'Record payment',
    'nothing' => 'No payroll recorded',
    'nothing_body' => 'Every row here already has a paid date, or the month is empty.',
    'marked' => 'Salary payment recorded',
    'marked_body' => ':count people · :total · :date · via :via. The amount now reduces the balance.',
    'unmarked' => ':count rows set back to unpaid',

    'action' => 'Record salary payment',
    'heading' => 'Record one month\'s salary payment',
    'description' => 'Pick the salary month, the date the money left, and how. Every row of that month without a paid date is recorded, and the amount reduces Bank Balance (bank) or Cash Balance (cash) on the paid date. Do not record the same salary again in Expenses, or it is deducted twice.',

    'field' => [
        'month' => 'Salary month',
        'paid_on' => 'Paid on',
        'paid_on_help' => 'The date the money left (see the bank statement), not the salary month.',
        'paid_via' => 'Paid via',
    ],

    'via' => [
        'bank' => 'Bank (transfer)',
        'cash' => 'Cash',
    ],

    'bulk' => [
        'paid' => 'Record as paid',
        'paid_heading' => 'Record payment for the selected rows',
        'paid_description' => 'Only rows without a paid date are recorded. Use this when not everyone is paid on the same date or the same way.',
        'unpaid' => 'Undo paid record',
        'unpaid_heading' => 'Undo the payment record?',
        'unpaid_description' => 'The selected salaries go back to unpaid and no longer reduce any balance.',
    ],

    'report' => [
        'col_paid' => 'Paid on',
        'btn_record' => 'Record paid date',
        'btn_change' => 'Change',
        'btn_undo' => 'Undo',
        'heading' => 'Salary payment date :month',
        'description' => 'Enter the date the salary money left (see the bank statement) and how. From that date the month\'s total salary reduces Bank Balance (bank) or Cash Balance (cash). Do not record the same salary again in Expenses.',
        'saved_body' => 'Salary :month · :count people · paid :date · via :via. The balance now drops on that date.',
        'partial' => 'only :paid of :people people',
        'undo_heading' => 'Undo the paid record for :month salary?',
        'undone' => 'Paid record undone',
        'note' => 'Enter the payment date for each month so the salary reduces Bank Balance. Without a date, salary reduces no balance.',
    ],
];
