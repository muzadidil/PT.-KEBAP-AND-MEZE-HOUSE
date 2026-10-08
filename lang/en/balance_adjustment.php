<?php

return [

    'nav' => 'Balance Corrections',
    'col' => 'Correction',

    'account' => [
        'cash' => 'Cash in till',
        'bank' => 'Bank account',
    ],

    'direction' => [
        'minus' => 'Decrease the balance',
        'plus' => 'Increase the balance',
    ],

    'field' => [
        'date' => 'Date',
        'account' => 'Which balance',
        'direction' => 'Type',
        'amount' => 'Amount',
        'reason' => 'Reason',
    ],

    'help' => [
        'date' => 'The correction counts from this date on. It never changes older records.',
        'reason' => 'e.g. BI-FAST transfer fees, or till count difference',
    ],

];
