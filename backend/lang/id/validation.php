<?php

// Only the messages users can trigger from the UI; anything else falls back to English.
return [
    'custom' => [
        'description' => [
            'required' => 'Uraian wajib diisi.',
            'min' => 'Uraian minimal :min karakter, jelaskan langkah yang diambil.',
            'max' => 'Uraian maksimal :max karakter.',
        ],
        'target_date' => [
            'required' => 'Tanggal target wajib diisi.',
            'after_or_equal' => 'Tanggal target tidak boleh sebelum hari ini.',
            'before' => 'Tanggal target maksimal satu tahun dari sekarang.',
            'date_format' => 'Format tanggal target tidak valid.',
        ],
        'files' => [
            'required' => 'Lampirkan minimal satu berkas bukti.',
            'max' => 'Maksimal :max berkas.',
        ],
        'files.*' => [
            'max' => 'Ukuran berkas maksimal 5 MB.',
            'mimetypes' => 'Berkas harus berupa PDF, PNG, atau JPG.',
        ],
        'decision' => ['required' => 'Pilih keputusan.'],
        'note' => [
            'required_if' => 'Catatan wajib diisi saat mengembalikan pengajuan.',
            'max' => 'Catatan maksimal :max karakter.',
        ],
    ],
];
