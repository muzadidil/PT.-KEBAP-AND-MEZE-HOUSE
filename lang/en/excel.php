<?php

return [

    'yes' => 'Yes',
    'no' => 'No',
    'month' => 'Month',
    'guide_tab' => 'Guide',
    'lists_tab' => 'Lists',

    'action' => [
        'template' => 'Download template',
        'import' => 'Import Excel',
        'download' => 'Download',
    ],

    'template' => [
        'heading' => ':title template',
        'month_hint' => 'Dates in the template are limited to this month, so a wrong month or year shows up right away.',
    ],

    'import' => [
        'heading' => 'Import :title from Excel',
        'description' => 'Use a file from the Download template button. If one row is wrong, nothing is saved and the errors are listed row by row.',
        'file' => 'Excel file',
    ],

    'guide' => [
        'title' => 'Import template — :title',
        'subtitle' => 'Fill in the ":sheet" sheet, then upload it with the Import Excel button on the :title page.',
        'rules' => 'How to fill it in',
        'examples' => 'Examples (examples only, do not copy them into the data sheet)',
    ],

    'rule' => [
        'header' => 'Keep the heading row as it is. The column order may change, and extra columns are ignored.',
        'required' => 'Columns marked * are required. Each column shows a hint when its cell is selected.',
        'date' => 'Dates are day-first: 5/8/2026 means 5 August.',
        'money' => 'Numbers are plain: 35000, not Rp 35.000 or 35k.',
        'list' => 'Columns with an arrow are picked from a list.',
        'update' => 'Rows whose :keys already exist in the app are updated, not added. An emptied cell does not erase the old value.',
        'dedupe' => 'Rows exactly the same as one already recorded are skipped, so importing the same file twice is safe.',
        'all_or_nothing' => 'If one row is wrong, the whole file is refused and the errors are listed row by row. Fix them, then upload again.',
        'rows' => 'Input rules are prepared for :rows rows.',
    ],

    'prompt' => [
        'required' => 'Required.',
        'date' => 'A date, day first: 5/8/2026.',
        'money' => 'Digits only, without Rp or dots: 35000.',
        'number' => 'A whole number.',
        'open_list' => 'Pick from the list. New entries are allowed.',
        'closed_list' => 'Pick from the list.',
        'creates' => 'Pick from the list. New names are created on import.',
        'text' => 'Up to :max characters.',
        'free' => 'May be left empty.',
    ],

    'error' => [
        'required' => 'is required',
        'too_long' => 'is too long, at most :max characters',
        'number' => 'is not a number (":value")',
        'between' => 'must be between :min and :max',
        'min' => 'must not be less than :min',
        'date' => 'is not a date (":value"), write it day first: 5/8/2026',
        'date_rule' => 'Enter a date, day first: 5/8/2026.',
        'boolean' => 'enter Yes or No, not ":value"',
        'choice' => '":value" is not in the list',
        'open_list' => 'Not in the list yet. Use this entry anyway?',
        'closed_list' => 'Pick one of the listed values.',
        'no_header' => 'The heading row was not found. Columns looked for: :columns. Use a file from the Download template button.',
        'row' => 'Row :row · :column: :message',
        'duplicate' => 'Row :row: same as row :first. Remove one of them.',
        'save' => 'Row :row could not be saved: :message',
        'failed' => 'Import failed: :message',
    ],

    'pos' => [
        'action' => 'Import POS sales',
        'heading' => 'Import sales from the POS report',
        'description' => 'Use the "Report Item Details" file (CSV or Excel). Net Sales is added up per date and payment method (Cash, BNI, GrabFood, GoFood, GoPay). Dates that already have a row are skipped, never overwritten. If anything is wrong, nothing is saved.',
        'file' => 'CSV or Excel file',
        'unreadable' => 'The file could not be read. Use the CSV or Excel file exported from the POS.',
        'missing_column' => 'Column ":column" is missing. Use the "Report Item Details" file as exported.',
        'bad_date' => 'Row :row: date ":value" is not valid. Use day-month-year, e.g. 30-09-2026.',
        'bad_amount' => 'Row :row: Net Sales ":value" is not a number.',
        'unknown_method' => 'Payment method ":method" (rows :rows) is not recognised. Known: :valid. Rename it in the file, or ask for it to be added.',
        'skipped_note' => 'Skipped, already filled: :dates',
    ],

    'telegram' => [
        'action' => 'Pull from Telegram',
        'heading' => 'Pull daily sales from Telegram',
        'description' => 'Paste the daily sales messages, or upload the Telegram Desktop "Export chat history" file (JSON). One message = one day, starting with "Penjualan <date>". Dates that already have a row are skipped, never overwritten. If anything is wrong, nothing is saved.',
        'text' => 'Paste messages',
        'file' => 'Or upload the Telegram export file',
        'file_help' => 'result.json from Telegram Desktop → ⋮ → Export chat history → JSON format.',
        'empty' => 'Paste messages or upload a file first.',
        'unreadable' => 'The Telegram JSON file could not be read.',
        'nothing_found' => 'No sales messages found. A message must start with "Penjualan 26 Sep 2026", then one line per channel, e.g. "Cash: 1.545.390".',
        'unknown_label' => ':date: name ":label" is not recognised. Known: :valid.',
        'bad_amount' => ':date: amount ":value" on line :label looks wrong. Write it like 1.545.390.',
        'no_amounts' => ':date: no amounts at all. Write one line per channel, e.g. "Cash: 1.545.390".',
        'total_mismatch' => ':date: Total :total does not match the channel sum :sum. Check the numbers.',
        'repeated_note' => 'Sent more than once, the last message is used: :dates',
    ],

    'count' => [
        'created' => ':count new',
        'updated' => ':count updated',
        'skipped' => ':count already there',
        'imported' => ':count row imported|:count rows imported',
        'replaced' => ':count replaced',
        'manual' => ':count skipped (already typed in by hand)',
    ],

    'result' => [
        'done' => 'Import finished',
        'unchanged' => 'Nothing changed',
        'empty' => 'The file has no data rows.',
        'failed' => 'Import cancelled: :count error|Import cancelled: :count errors',
        'more' => '…and :count more errors.',
        'nothing_saved' => 'Nothing was saved. Fix the file, then upload again.',
        'range' => 'Dates read: :range',
    ],

];
