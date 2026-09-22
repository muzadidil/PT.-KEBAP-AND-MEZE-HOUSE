<?php

return [

    'nav' => 'Daily Report',
    'subtitle' => 'Catatan harian per bagian, dengan sub-catatan bertingkat, untuk dibagikan ke WhatsApp.',

    'field' => [
        'note_placeholder' => 'Tambah bagian baru… (Enter)',
        'sub_placeholder' => 'Tambah sub-catatan… (Enter)',
        'nominal' => 'Nominal',
        'value' => 'Angka',
        'status' => 'Status',
        'choose' => '— Pilih —',
        'icon' => 'Ikon',
        'kind' => 'Jenis isian',
    ],

    // Jenis isian catatan; lihat App\Models\DailyNote.
    'kind' => [
        'text' => 'Catatan',
        'choice' => 'Pilihan',
        'number' => 'Angka',
        'rating' => 'Rating',
        'status' => 'Status',
    ],

    'kind_hint' => [
        'text' => 'Catatan bebas; nominal Rupiah di kanan boleh dikosongkan.',
        'choice' => 'Isinya satu pilihan dari master Kondisi, mis. Good / Need Attention / Problem.',
        'number' => 'Sub-catatannya diisi angka saja, bukan Rupiah — mis. jumlah review.',
        'status' => 'Tiap sub-catatannya punya status dari master, mis. Pending / Process / Finish.',
    ],

    'action' => [
        'add' => 'Tambah',
        'add_sub' => 'Sub-catatan',
        'save' => 'Simpan',
        'cancel' => 'Batal',
        'edit' => 'Ubah',
        'delete' => 'Hapus',
        'fill_template' => 'Isi bagian standar',
        'master' => 'Master pilihan',
        'copy_wa' => 'Salin WA',
        'send_wa' => 'Kirim WA',
        'copied' => 'Tersalin!',
        'today' => 'Hari ini',
        'prev_day' => 'Hari sebelumnya',
        'next_day' => 'Hari berikutnya',
    ],

    'master' => [
        'title' => 'Master pilihan',
        'hint' => 'Pilihan yang muncul di dropdown. Ikon boleh emoji, dan ikut terkirim ke WhatsApp.',
        'condition' => 'Kondisi (bagian jenis Pilihan)',
        'status' => 'Status (bagian jenis Status)',
        'new_condition' => 'Kondisi baru…',
        'new_status' => 'Status baru…',
    ],

    'confirm' => [
        'delete' => 'Hapus catatan ini beserta sub-catatannya?',
        'delete_option' => 'Hapus pilihan ini? Catatan yang memakainya jadi belum dipilih.',
    ],

    // Nama bagian ikut istilah aslinya di contoh laporan, sengaja tidak
    // diterjemahkan — sudah jadi istilah baku tim sehari-hari.
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

    'empty_day' => 'Belum ada catatan untuk tanggal ini.',

];
