<?php

return [

    'from' => 'From',
    'to' => 'To',
    'month' => 'Month',
    'year' => 'Year',
    'apply' => 'Apply',
    'period' => 'Period',
    'date' => 'Date',
    'week' => 'Week',
    'days' => 'Days',
    'transactions' => 'Transactions',
    'total' => 'Total',
    'average_per_recorded_day' => 'Average per recorded day',
    'average_hint' => 'Over the :days days that have sales recorded.',
    'no_data' => 'Nothing recorded in this period.',
    'print' => 'Print',
    'source_bookkeeping' => 'Source: Daily Income in Monthly Bookkeeping — the same as the Monthly Ledger.',
    'open_ledger' => 'Open the ledger for this range',
    'people' => 'People',

    // Where each report's figures come from, shown above its table.
    'source' => [
        'cash_expenses' => 'Source: Cash Purchases in Monthly Bookkeeping — the total matches Cash purchases in the Monthly Ledger for the same range.',
        'online_transfers' => 'Source: Supplier Transfers in Monthly Bookkeeping — the total matches Supplier transfers in the Monthly Ledger for the same range.',
        'salary' => 'Source: Payroll — the same as Payroll in the Monthly Ledger. Monthly totals only; per-person figures are for the Super Admin.',
        'tax' => 'Source: the Expenses menu, Tax category. Not yet part of the Monthly Ledger.',
    ],
    'export_csv' => 'Download CSV',
    'export_excel' => 'Download Excel',
    'pdf' => 'PDF',
    'all_dates' => 'All dates',
    'yes' => 'Yes',
    'no' => 'Not yet',
    'grand_total' => 'Grand total',
    'as_of' => 'As of',

    'preset' => [
        'today' => 'Today',
        'last_year' => 'Last year',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_year' => 'This year',
        'last_7' => 'Last 7 days',
        'last_30' => 'Last 30 days',
    ],

    'summary' => [
        'sales' => 'Sales',
        'expenses' => 'Expenses',
        'profit' => 'Profit',
    ],

    'balance' => [
        'assets' => 'Assets',
        'cash_on_hand' => 'Cash on hand',
        'bank' => 'Bank & card settlements',
        'total_assets' => 'Total assets',

        'liabilities' => 'Liabilities',
        'supplier_payable' => 'Unpaid supplier bills',
        'tax_payable' => 'Tax payable',
        'other_payable' => 'Other unpaid bills',
        'total_liabilities' => 'Total liabilities',

        'equity' => 'Equity',
        'owner_capital' => 'Owner capital',
        'retained_earnings' => 'Accumulated profit',
        'total_equity' => 'Total equity',

        'liabilities_and_equity' => 'Liabilities + equity',
        'balanced' => 'Balanced.',
        'not_balanced' => 'Out of balance by :amount. This should never happen — please report it.',

        'invested' => 'Deposited',
        'withdrawn' => 'Withdrawn',
        'advanced' => 'Paid from own pocket',
        'capital' => 'Capital',

        'note' => 'Every figure here is recomputed from the sales, expenses and capital entries each time this page is opened. Nothing is stored as a running balance.',
    ],

    'owner_split' => [
        'title' => 'Owner Expenses',
        'intro' => 'Expenses the owners paid out of their own pocket during the period. Each owner bears their agreed share; whoever paid more than their share is owed the difference.',
        'total_advanced' => 'Total advanced',
        'paid' => 'Actually paid',
        'share' => 'Their share',
        'balance' => 'Balance',
        'receives' => 'to receive',
        'owes' => 'to pay in',
        'settled' => 'settled',
        'share_warning' => 'Owner shares add up to :total%, not 100%. Fix this under Master Data → Owners before relying on the split.',
        'no_owners' => 'No active owners yet. Add them under Master Data → Owners.',
    ],

];
