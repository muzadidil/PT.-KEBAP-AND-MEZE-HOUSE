<?php

return [

    'nav' => 'Cash Left in Till',
    'saved' => 'Saved',
    'formula' => 'Cash left = opening balance + cash sales − cash purchases. Read from Daily Income and Cash Purchases, the same as the Monthly Ledger. Petty cash, supplier transfers and payroll are not counted.',
    'before_opening' => 'Before the opening date, not counted',

    'card' => [
        'balance' => 'Cash left in till',
        'as_of' => 'As of :date',
        'start' => 'Balance at start of range',
        'opening' => 'Opening balance :amount on :date',
        'no_opening' => 'No opening date set: everything recorded is counted, from 0.',
    ],

    'col' => [
        'opening' => 'Opening',
        'cash_in' => 'Cash sales',
        'cash_out' => 'Cash purchases',
        'balance' => 'Cash left',
    ],

    'opening' => [
        'action' => 'Opening balance',
        'heading' => 'Opening cash balance',
        'description' => 'The cash already in the till on the start date. Counting begins on that date.',
        'amount' => 'Opening balance',
        'date' => 'Start date',
        'date_hint' => 'Leave empty to count every recorded day from 0.',
    ],

];
