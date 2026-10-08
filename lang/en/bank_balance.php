<?php

return [

    'nav' => 'Bank Balance',
    'saved' => 'Saved',
    'formula' => 'Bank balance = opening balance + non-cash sales (BNI, Grab Food, Go Food, Go Pay) − payments from the account (Supplier Transfers, and Expenses paid by transfer). This is an ESTIMATE, not the bank statement: bank fees, Grab/Go commission and payout delays are not recorded. Compare it with the statement and reset the opening balance when needed. Salaries paid by bank belong in Expenses (not in Payroll), so they are not counted twice.',
    'before_opening' => 'Before the opening date, not counted',

    'card' => [
        'balance' => 'Estimated bank balance',
        'as_of' => 'As of :date',
        'start' => 'Balance at start of range',
        'opening' => 'Opening balance :amount on :date',
        'no_opening' => 'No opening date set: everything recorded is counted, from 0.',
    ],

    'col' => [
        'opening' => 'Opening',
        'money_in' => 'Non-cash sales',
        'money_out' => 'Payments out',
        'balance' => 'Balance',
    ],

    'opening' => [
        'action' => 'Opening balance',
        'heading' => 'Opening bank balance',
        'description' => 'The balance of the company account on the start date, taken from the bank statement. Counting begins on that date.',
        'amount' => 'Opening balance',
        'date' => 'Start date',
        'date_hint' => 'Leave empty to count every recorded day from 0.',
    ],

];
