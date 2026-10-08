<?php

return [

    'nav' => 'Bank Transactions',

    'col' => [
        'when' => 'Date and time',
        'to' => 'Paid to',
        'amount' => 'Amount',
        'direction' => 'Direction',
        'status' => 'Status',
        'reference' => 'BNI reference',
    ],

    'direction' => [
        'out' => 'Payment out',
        'in' => 'Money in',
    ],

    'status' => [
        'pending' => 'Waiting',
        'recorded' => 'Recorded',
        'ignored' => 'Ignored',
    ],

    'action' => [
        'record' => 'Record as Supplier Transfer',
        'record_heading' => 'Record as Supplier Transfer?',
        'record_description' => ':amount to :to will be added to Supplier Transfers.',
        'recorded' => 'Recorded in Supplier Transfers',
        'ignore' => 'Ignore',
    ],

    'fetch' => [
        'action' => 'Fetch from email',
        'done' => 'Email checked',
        'failed' => 'Could not read the mailbox',
        'summary' => ':checked emails checked: :added new, :duplicate already in the queue',
        'other_format' => ':count transaction emails in another format were not read yet (please tell the developer)',
        'skipped' => ':count other emails from the bank skipped (not transaction notifications)',
        'rejected' => ':count ignored: not from the bank, or failed the sender check',
    ],

    'paste' => [
        'action' => 'Paste BNI email',
        'heading' => 'Paste BNI notification email',
        'description' => 'Paste the whole email text. Several emails at once are fine. Only successful transactions are read, nothing is recorded until you approve it, and the same notification is never added twice.',
        'field' => 'Email text',
        'submit' => 'Read email',
        'done' => 'Email read',
        'added' => ':count added to the queue',
        'duplicate' => ':count already in the queue, skipped',
    ],

];
