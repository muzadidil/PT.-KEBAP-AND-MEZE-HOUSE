<?php

/*
| Pembukuan bulanan gaya berkas Excel klien.
|
| Berkas sendiri, tidak menumpang lang/en/field.php dan kawan-kawan, supaya
| istilah khas berkas Excel ("Supplier cash", "Outstanding INV") tidak
| tercampur dengan istilah kasir yang sudah ada di sana.
*/

return [

    'nav' => [
        'group' => 'Monthly Bookkeeping',
        'daily_income' => 'Daily Income',
        'purchases' => 'Cash Purchases',
        'transfers' => 'Supplier Transfers',
        'payroll' => 'Payroll',
        'outstanding' => 'Outstanding Invoices',
        'purchase_items' => 'Purchased Goods',
        'payment_methods' => 'Payment Methods',
        'import' => 'Import Excel',
        'ledger' => 'Monthly Ledger',
    ],

    'card' => [
        'sales' => 'Total sales',
        'cashless' => 'Cashless income',
        'cash_expense' => 'Cash purchases',
        'transfers' => 'Supplier transfers',
        'payroll' => 'Payroll',
        'total_expenses' => 'Total expenses',
        'outstanding' => 'Unpaid invoices',
        'supplier_cash' => 'Supplier cash',
        'remaining_supplier_cash' => 'Remaining supplier cash',
        'profit' => 'Net profit',
        'global_balance' => 'Global balance',
    ],

    'hint' => [
        'petty_cash' => 'Excluded from total sales, exactly as in the original spreadsheet.',
        'supplier_cash' => 'Opening float :opening plus the day’s cash.',
        'remaining_supplier_cash' => 'Last recorded day, not a sum across days.',
        'outstanding' => 'All unpaid invoices, not only those in this period.',
        'global_balance' => 'Sales − expenses − unpaid invoices.',
        'recorded_days' => ':recorded of :days days recorded',
    ],

    'col' => [
        'total_sales' => 'Total sales',
        'cashless' => 'Cashless',
        'expense' => 'Purchases',
        'supplier_cash' => 'Supplier cash',
        'remaining_supplier_cash' => 'Remaining',
    ],

    'field' => [
        'vendor' => 'Vendor',
        'item' => 'Item',
        'qty' => 'Qty',
        'unit' => 'Unit',
        'price' => 'Price',
        'disc' => 'Discount',
        'tax' => 'Tax',
        'total' => 'Total',
        'status' => 'Status',
        'method' => 'Payment method',
        'basic' => 'Basic salary',
        'bpjs' => 'BPJS deduction',
        'grand_total' => 'Grand total',
        'section' => 'Section',
        'month' => 'Month',
        'due_date' => 'Due date',
        'bank' => 'Bank',
        'bank_account' => 'Account number',
        'account_name' => 'Account name',
        'payment_method' => 'Payment method',
        'last_price' => 'Last price',
        'source' => 'Entered by',
        'note' => 'Note',
        'date' => 'Date',
        'name' => 'Name',
    ],

    'source' => [
        'manual' => 'Typed',
        'import' => 'Excel',
    ],

    'section' => [
        'front' => 'Front Staff',
        'kitchen' => 'Kitchen Staff',
        'owner' => 'Owner',
    ],

    'status' => [
        'need' => 'Need the payment',
        'waiting' => 'Waiting the payment',
        'paid' => 'PAID',
        'kebap_paid' => 'PT KEBAP PAID',
        'aslan_paid' => 'ASLAN PAID',
        'none' => '—',
    ],

    'import' => [
        'title' => 'Import the monthly Excel file',
        'intro' => 'The importer looks for each sheet by name and each column by its heading. A sheet whose shape it does not recognise is reported, never guessed at.',
        'safe' => 'Importing the same file twice is safe: each row gets a number derived from its contents, so the second import overwrites the same rows instead of adding new ones. Rows you typed by hand are never touched.',
        'pick' => 'Excel file',
        'payroll_month' => 'Month for the Payroll sheet',
        'payroll_month_hint' => 'The Payroll sheet does not state its own month — its title is a free-form sentence. It is asked for rather than guessed.',
        'run' => 'Import',
        'reading' => 'Reading…',
        'done' => 'Import finished',
        'nothing' => 'Nothing was imported.',
        'rows' => 'Rows',
        'range' => 'Dates read',
        'replaced' => 'Replaced',
        'sheet' => 'Sheet',
        'ok' => 'OK',
        'error' => [
            'sheet_missing' => 'Sheet not found in this file.',
            'header_missing' => 'No heading row with the expected column titles.',
            'month_missing' => 'Pick the month for the Payroll sheet first.',
            'unreadable' => 'The sheet could not be read.',
            'empty' => 'No data rows below the heading.',
            'failed' => 'Import failed: :message',
        ],
    ],

    'template' => [
        'download' => 'Download template',
        'hint' => 'The template is built from the same column titles the importer looks for, so a file filled in from it cannot be rejected for its shape.',
        'tab' => 'Guide',
        'title' => 'Zeytin — import template',
        'sheet' => 'Keep the sheet names exactly as they are. Sheets with other names are ignored.',
        'header' => 'Keep the heading row. Extra columns are fine — they are ignored, not refused.',
        'date' => 'Dates may be written day-first (31/08/2026) or as real Excel dates. In the purchase sheets, only the first row of each day needs a date.',
        'money' => 'Amounts as plain numbers, without "Rp" and without decimals.',
        'month' => 'The Payroll sheet has no month column — the month is asked for when the file is uploaded.',
        'extra' => 'Do not leave example rows in the file: anything below the heading row is imported as real data.',
        'sheet_list' => 'Sheets and their column titles',
    ],

    'export' => [
        'download' => 'Download Excel',
        'summary' => 'Summary',
        'daily' => 'Daily',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'period' => 'Period',
        'recorded_days' => 'Days recorded',
    ],

    'pdf' => [
        'view' => 'View PDF',
        'summary' => 'Summary',
        'breakdown' => 'Breakdown',
        'generated' => 'Generated',
        'page' => 'Page',
        'title' => [
            'daily' => 'Daily Report',
            'monthly' => 'Monthly Report',
            'yearly' => 'Yearly Report',
        ],
    ],

    'ledger' => [
        'daily' => 'Daily',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
        'channels' => 'Balance by channel',
        'cash' => 'Cash',
        'noncash' => 'Non-cash',
        'excluded' => 'excl.',
    ],

    'help' => [
        'price' => 'Only a starting suggestion — type over it whenever the supplier’s price has changed.',
        'total' => 'Calculated as (qty × price) + tax − discount, the formula from the original spreadsheet.',
        'source' => 'Rows brought in by an Excel file are marked so a later import may replace them. A row you edit here becomes yours, and no import will overwrite it.',
        'month' => 'Stored as the first day of the month.',
    ],

];
