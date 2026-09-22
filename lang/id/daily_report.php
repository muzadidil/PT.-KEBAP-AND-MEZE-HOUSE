<?php

return [

    'nav' => 'Daily Report',
    'subtitle' => 'Catatan harian per bagian, dengan sub-catatan bertingkat, untuk dibagikan ke WhatsApp.',

    'field' => [
        'note_placeholder' => 'Tambah bagian baru… (Enter)',
        'sub_placeholder' => 'Tambah sub-catatan… (Enter)',
        'nominal' => 'Nominal',
    ],

    'action' => [
        'add' => 'Tambah',
        'add_sub' => 'Sub-catatan',
        'save' => 'Simpan',
        'cancel' => 'Batal',
        'edit' => 'Ubah',
        'delete' => 'Hapus',
        'fill_template' => 'Isi bagian standar',
        'copy_wa' => 'Salin WA',
        'send_wa' => 'Kirim WA',
        'copied' => 'Tersalin!',
        'today' => 'Hari ini',
        'prev_day' => 'Hari sebelumnya',
        'next_day' => 'Hari berikutnya',
    ],

    'confirm' => [
        'delete' => 'Hapus catatan ini beserta sub-catatannya?',
    ],

    // Nama bagian ikut istilah aslinya di contoh laporan, sengaja tidak
    // diterjemahkan — sudah jadi istilah baku tim sehari-hari.
    'template' => [
        'sales' => 'Sales',
        'sales.total_sales' => 'Total Sales',
        'sales.total_guest' => 'Total Guest',
        'operation' => 'Operation',
        'operation.overall' => 'Overall',
        'operation.main_issue' => 'Main Issue',
        'staff' => 'Staff',
        'staff.attendance' => 'Attendance',
        'staff.staff_issue' => 'Staff Issue',
        'reviews' => 'Google Reviews',
        'reviews.rating' => 'Rating',
        'reviews.total_reviews' => 'Total Reviews',
        'reviews.new_reviews' => 'New Reviews',
        'reviews.replied' => 'Replied',
        'reviews.negative_reviews' => 'Negative Reviews',
        'reviews.follow_up' => 'Follow-up',
        'task' => 'Task / Work Update',
        'notes' => 'Important Notes',
        'plan' => 'Plan / Follow-up',
    ],

    'share' => [
        'title' => 'Daily Report – :name',
        'date_label' => 'Date',
    ],

    'empty_day' => 'Belum ada catatan untuk tanggal ini.',

];
