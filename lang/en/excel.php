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

    'count' => [
        'created' => ':count new',
        'updated' => ':count updated',
        'skipped' => ':count already there',
        'imported' => ':count row imported|:count rows imported',
        'replaced' => ':count replaced',
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
