<?php

return [

    'nav' => 'Daily Report',
    'subtitle' => 'Daily notes by section, with nested sub-notes, ready to share on WhatsApp.',

    'field' => [
        'note_placeholder' => 'Add a new section… (Enter)',
        'sub_placeholder' => 'Add a sub-note… (Enter)',
        'nominal' => 'Amount',
        'value' => 'Number',
        'status' => 'Status',
        'choose' => '— Choose —',
        'icon' => 'Icon',
        'kind' => 'Input type',
    ],

    'kind' => [
        'text' => 'Note',
        'choice' => 'Choice',
        'number' => 'Number',
        'rating' => 'Rating',
        'status' => 'Status',
    ],

    'kind_hint' => [
        'text' => 'Free notes; the Rupiah amount on the right is optional.',
        'choice' => 'Holds one option from the Condition master, e.g. Good / Need Attention / Problem.',
        'number' => 'Its sub-notes take plain numbers, not Rupiah — e.g. review counts.',
        'status' => 'Each sub-note gets a status from the master, e.g. Pending / Process / Finish.',
    ],

    'action' => [
        'add' => 'Add',
        'add_sub' => 'Sub-note',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'fill_template' => 'Fill standard sections',
        'master' => 'Option master',
        'copy_wa' => 'Copy WA',
        'send_wa' => 'Send WA',
        'copied' => 'Copied!',
        'today' => 'Today',
        'prev_day' => 'Previous day',
        'next_day' => 'Next day',
    ],

    'master' => [
        'title' => 'Option master',
        'hint' => 'The options shown in the dropdowns. Icons can be emoji and are sent to WhatsApp too.',
        'condition' => 'Condition (Choice sections)',
        'status' => 'Status (Status sections)',
        'new_condition' => 'New condition…',
        'new_status' => 'New status…',
    ],

    'confirm' => [
        'delete' => 'Delete this note along with its sub-notes?',
        'delete_option' => 'Delete this option? Notes using it become unselected.',
    ],

    'template' => [
        'operation' => 'Operation',
        'staff_issue' => 'Staff Issue',
        'reviews' => 'Google Reviews',
        'reviews_rating' => 'Rating',
        'reviews_total_reviews' => 'Total Reviews',
        'reviews_new_reviews' => 'New Reviews',
        'reviews_replied' => 'Replied',
        'reviews_negative_reviews' => 'Negative Reviews',
        'reviews_follow_up' => 'Follow Up',
        'task' => 'Task/Work Update',
        'notes' => 'Important Notes',
        'plan' => 'Plan/Follow Up',
    ],

    'share' => [
        'title' => 'Daily Report – :name',
        'date_label' => 'Date',
    ],

    'empty_day' => 'No notes for this date yet.',

];
