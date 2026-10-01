<?php

return [

    'source' => 'Source: revenue from Daily Income in Monthly Bookkeeping. Edits here apply to this report only — the original data, the Monthly Ledger, and the Balance Sheet are never changed.',
    'pdf_share' => 'PDF deduction (%)',
    'saved' => 'Saved',
    'adjusted' => 'Edited',
    'adjusted_hint' => 'Rates are configurable. Restaurants may owe regional restaurant tax (PBJT) instead of VAT — check with your tax consultant. "Edited" marks a month whose revenue was overridden for reporting.',
    'reported' => 'Reported',
    'not_reported' => 'Not yet reported',

    'month' => [
        'year_view' => 'Whole year',
        'daily_title' => 'Daily sales',
        'inclusive' => 'prices include tax',
        'exclusive' => 'tax added on top',
    ],

    'card' => [
        'system' => 'System figure: :amount',
        'paid' => 'Paid: :amount',
        'final' => 'Final income tax (PPh Final)',
        'ppn' => 'VAT / restaurant tax due',
    ],

    'col' => [
        'system_revenue' => 'System revenue',
        'investor_share' => 'Local investor share',
        'tax_base' => 'Tax base',
        'revenue' => 'Revenue reported',
        'final_due' => 'PPh Final due',
        'paid_final' => 'PPh Final paid',
        'ppn_output' => 'VAT output',
        'ppn_input' => 'VAT input',
        'ppn_due' => 'VAT due',
        'paid_ppn' => 'VAT paid',
        'outstanding' => 'Outstanding',
        'status' => 'Status',
    ],

    'field' => [
        'investor_share' => 'Local investor share (%)',
        'revenue_override' => 'Revenue for reporting',
        'final_rate' => 'PPh Final rate',
        'ppn_rate' => 'VAT / restaurant tax rate',
        'ppn_inclusive' => 'Prices already include tax',
        'ppn_input' => 'VAT input (creditable)',
        'paid_final' => 'PPh Final paid',
        'paid_ppn' => 'VAT paid',
        'is_reported' => 'Already reported to the tax office',
        'note' => 'Note',
    ],

    'rates' => [
        'action' => 'Default rates',
        'heading' => 'Default tax rates',
        'description' => 'Used for every month unless a month sets its own rate.',
        'investor_hint' => 'Share of revenue for the local investor who owns the location. Shown as its own line; tax is calculated on the rest. Leave 0 if none.',
        'ppn_hint' => 'VAT is 11%. Restaurant tax (PBJT) is usually 10% — set what applies to you.',
        'inclusive_hint' => 'On: tax is separated out of the sales price. Off: tax is added on top.',
    ],

    'edit' => [
        'button' => 'Edit',
        'heading' => 'Edit :month',
        'description' => 'For reporting only. Empty fields use the system figure or the default rate. The original data is not changed.',
        'revenue_hint' => 'Leave empty to use the system revenue.',
        'rate_hint' => 'Leave empty to use the default rate.',
        'save' => 'Save',
        'use_default' => 'Use default',
        'inclusive_yes' => 'Yes, already included',
        'inclusive_no' => 'No, added on top',
    ],

    'history' => [
        'title' => 'Change history',
        'empty' => 'No edits yet for this year.',
        'when' => 'When',
        'who' => 'Who',
        'field' => 'Field',
        'from' => 'From',
        'to' => 'To',
    ],

];
