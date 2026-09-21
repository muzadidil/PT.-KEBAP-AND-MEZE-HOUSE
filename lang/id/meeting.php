<?php

return [

    'nav' => 'Progres Rapat',
    'subtitle' => 'Tugas hasil rapat, sub-tugasnya, dan seberapa jauh sudah dikerjakan.',

    'all_projects' => 'Semua Proyek',
    'no_project' => 'Tanpa Proyek',

    'filter' => [
        'all' => 'Semua Tugas',
        'today' => 'Hari Ini',
        'active' => 'Belum Selesai',
        'completed' => 'Selesai',
        'overdue' => 'Terlambat',
        'archived' => 'Arsip',
        'all_categories' => 'Semua Kategori',
        'all_priorities' => 'Semua Prioritas',
    ],

    'sort' => [
        'newest' => 'Terbaru',
        'oldest' => 'Terlama',
        'priority' => 'Prioritas',
        'due' => 'Tenggat Waktu',
    ],

    'category' => [
        'kerja' => 'Kerja',
        'pribadi' => 'Pribadi',
        'belajar' => 'Belajar',
        'bug' => 'Bug',
    ],

    'priority' => [
        'high' => 'Tinggi',
        'medium' => 'Sedang',
        'low' => 'Rendah',
    ],

    'deadline' => [
        'overdue' => 'Terlambat',
        'soon' => 'Mendekati',
    ],

    'stat' => [
        'total' => 'Total Tugas',
        'active' => 'Belum Selesai',
        'done' => 'Selesai',
        'progress' => 'Progress',
    ],

    'field' => [
        'task' => 'Tambah tugas baru… (tekan Enter)',
        'subtask' => 'Tambah sub-tugas… (Enter)',
        'text' => 'Tugas',
        'project' => 'Proyek',
        'category' => 'Kategori',
        'priority' => 'Prioritas',
        'deadline' => 'Tenggat',
        'link' => 'Link',
        'project_name' => 'Nama proyek… (Enter)',
        'search' => 'Cari tugas…',
    ],

    'action' => [
        'add' => 'Tambah',
        'add_project' => 'Tambah proyek',
        'save' => 'Simpan',
        'cancel' => 'Batal',
        'edit' => 'Ubah',
        'delete' => 'Hapus',
        'archive' => 'Arsipkan',
        'restore' => 'Kembalikan',
        'subtasks' => 'Sub-tugas',
        'add_subtask' => 'Sub-tugas',
        'import' => 'Impor',
        'pdf' => 'PDF',
        'copy_text' => 'Teks',
        'copy_wa' => 'WA',
        'send_wa' => 'Kirim WA',
        'copied' => 'Tersalin!',
        'rename' => 'Ganti nama',
    ],

    'confirm' => [
        'delete_task' => 'Hapus tugas ini beserta sub-tugasnya?',
        'delete_project' => 'Hapus proyek ini beserta seluruh tugasnya?',
    ],

    'import' => [
        'title' => 'Impor tugas dari teks',
        'hint' => 'Tempel daftar tugas — satu tugas per baris.',
        'placeholder' => "Beli bahan presentasi\nReview laporan mingguan\nTelepon pemasok",
        'done' => ':count tugas diimpor',
        'empty' => 'Tempel dulu daftar tugasnya, satu tugas per baris.',
    ],

    'share' => [
        'scope' => 'Cakupan',
        'text_title' => 'Progres Rapat — Daftar Tugas: :name',
        'wa_title' => 'Laporan Progres Rapat — :name',
        'overall' => 'Progres keseluruhan: :percent%',
        'progress' => 'progres :percent%',
        'active' => 'BELUM SELESAI',
        'done' => 'SELESAI',
        'none' => '(tidak ada)',
        'empty' => 'Belum ada tugas.',
        'priority' => 'Prioritas :priority',
        'deadline' => 'deadline :date',
        'due' => 'tenggat :date',
    ],

    'pdf' => [
        'title' => 'Laporan Progres Rapat',
        'summary' => 'Ringkasan',
        'total' => 'Total',
        'done' => 'Selesai',
        'in_progress' => 'Proses',
        'not_started' => 'Belum Mulai',
        'pending' => 'Belum',
        'status' => 'Status',
        'generated' => 'Dibuat',
    ],

    'empty' => 'Belum ada tugas. Tambahkan tugas pertama hasil rapat!',
    'empty_archive' => 'Arsip masih kosong. Tugas yang selesai diarsipkan otomatis sehari kemudian.',
    'archive_hint' => 'Selesai — diarsipkan otomatis besok',

];
