<?php

return [
    'nav' => 'Telegram Queue',
    'date_taken' => 'This date already has data',

    'col' => [
        'date' => 'Date',
        'total' => 'Total sales',
        'status' => 'Status',
        'sender' => 'Sender',
    ],

    'status' => [
        'pending' => 'Pending',
        'recorded' => 'Recorded',
        'ignored' => 'Rejected',
    ],

    'filter' => [
        'period' => 'Report date',
        'from' => 'From date',
        'to' => 'To date',
    ],

    'action' => [
        'record' => 'Confirm',
        'record_heading' => 'Confirm daily sales',
        'record_description' => 'Check the date and amounts. You may change them before recording. Once confirmed, the data goes into Daily Income.',
        'record_submit' => 'Record to Daily Income',
        'recorded' => 'Recorded in Daily Income',
        'taken' => 'That date already has data',
        'taken_body' => 'Daily Income is never overwritten. Change the date to the right day, or reject this report.',
        'ignore' => 'Reject',
        'ignore_heading' => 'Reject this report?',
        'ignore_description' => 'The report is not recorded. The sender is told in the group so they can send it again.',
    ],

    'reply' => [
        'recorded' => 'Recorded: :date · Total :total.',
        'ignored' => 'Report :date was rejected by the admin. Please send the correct one again.',
    ],

    'pull' => [
        'action' => 'Pull from Telegram bot',
        'heading' => 'Pull reports from the Telegram bot',
        'description' => 'Reads new messages in the Telegram group right now. Valid reports land in this queue for you to confirm. Nothing is recorded in Daily Income yet.',
        'submit' => 'Pull now',
        'no_token' => 'TELEGRAM_BOT_TOKEN is not set in the hosting .env.',
        'no_chat_ids' => 'Group id not set yet',
        'chat_ids_found' => 'Put one of these ids in TELEGRAM_CHAT_IDS in .env, then pull again:',
        'no_messages' => 'No messages seen yet. Make the bot a group admin, send a new message to the group, then pull again.',
        'done' => 'Telegram read',
        'summary' => ':checked messages checked: :queued queued, :failed wrong format, :ignored ignored (not a report).',
        'failed' => 'Could not read Telegram',
        'busy' => 'Another process is reading right now. Try again shortly.',
    ],
];
