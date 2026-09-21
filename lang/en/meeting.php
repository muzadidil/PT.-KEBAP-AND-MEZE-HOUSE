<?php

return [

    'nav' => 'Meeting Progress',
    'subtitle' => 'Action items from meetings, their sub-tasks, and how far along they are.',

    'all_projects' => 'All Projects',
    'no_project' => 'No Project',

    'filter' => [
        'all' => 'All Tasks',
        'today' => 'Today',
        'active' => 'Open',
        'completed' => 'Done',
        'overdue' => 'Overdue',
        'archived' => 'Archive',
        'all_categories' => 'All Categories',
        'all_priorities' => 'All Priorities',
    ],

    'sort' => [
        'newest' => 'Newest',
        'oldest' => 'Oldest',
        'priority' => 'Priority',
        'due' => 'Due date',
    ],

    'category' => [
        'kerja' => 'Work',
        'pribadi' => 'Personal',
        'belajar' => 'Learning',
        'bug' => 'Bug',
    ],

    'priority' => [
        'high' => 'High',
        'medium' => 'Medium',
        'low' => 'Low',
    ],

    'deadline' => [
        'overdue' => 'Overdue',
        'soon' => 'Due soon',
    ],

    'stat' => [
        'total' => 'Total Tasks',
        'active' => 'Open',
        'done' => 'Done',
        'progress' => 'Progress',
    ],

    'field' => [
        'task' => 'Add a new task… (press Enter)',
        'subtask' => 'Add a sub-task… (Enter)',
        'text' => 'Task',
        'project' => 'Project',
        'category' => 'Category',
        'priority' => 'Priority',
        'deadline' => 'Due',
        'link' => 'Link',
        'project_name' => 'Project name… (Enter)',
        'search' => 'Search tasks…',
    ],

    'action' => [
        'add' => 'Add',
        'add_project' => 'Add project',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'archive' => 'Archive',
        'restore' => 'Restore',
        'subtasks' => 'Sub-tasks',
        'add_subtask' => 'Sub-task',
        'import' => 'Import',
        'pdf' => 'PDF',
        'copy_text' => 'Text',
        'copy_wa' => 'WA',
        'send_wa' => 'Send WA',
        'copied' => 'Copied!',
        'rename' => 'Rename',
    ],

    'confirm' => [
        'delete_task' => 'Delete this task and its sub-tasks?',
        'delete_project' => 'Delete this project and all its tasks?',
    ],

    'import' => [
        'title' => 'Import tasks from text',
        'hint' => 'Paste a list of tasks — one task per line.',
        'placeholder' => "Buy presentation materials\nReview weekly report\nCall the supplier",
        'done' => ':count tasks imported',
        'empty' => 'Paste the task list first, one task per line.',
    ],

    'share' => [
        'scope' => 'Scope',
        'text_title' => 'Meeting Progress — Task List: :name',
        'wa_title' => 'Meeting Progress Report — :name',
        'overall' => 'Overall progress: :percent%',
        'progress' => 'progress :percent%',
        'active' => 'OPEN',
        'done' => 'DONE',
        'none' => '(none)',
        'empty' => 'No tasks yet.',
        'priority' => 'Priority :priority',
        'deadline' => 'deadline :date',
        'due' => 'due :date',
    ],

    'pdf' => [
        'title' => 'Meeting Progress Report',
        'summary' => 'Summary',
        'total' => 'Total',
        'done' => 'Done',
        'in_progress' => 'In progress',
        'not_started' => 'Not started',
        'pending' => 'Open',
        'status' => 'Status',
        'generated' => 'Generated',
    ],

    'empty' => 'No tasks yet. Add the first action item from the meeting!',
    'empty_archive' => 'The archive is empty. Finished tasks are archived automatically a day later.',
    'archive_hint' => 'Done — archived automatically tomorrow',

];
