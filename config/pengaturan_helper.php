<?php
// ============================================================================
// config/pengaturan_helper.php
// Helper untuk mengelola konfigurasi aturan sistem perpustakaan (kuota pinjam,
// lama pinjam, denda keterlambatan, dan identitas perpustakaan).
// Tersimpan dinamis di tabel database `pengaturan`.
// ============================================================================

/**
 * Mengambil seluruh pengaturan perpustakaan dari database.
 * Jika tabel belum ada atau data kosong, otomatis mengembalikan nilai default yang aman.
 * 
 * @param PDO $koneksi Objek koneksi database PDO
 * @return array Associative array kunci => nilai
 */
function ambilPengaturan(PDO $koneksi): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    // Nilai standar bawaan sistem jika belum dikonfigurasi di database
    $default = [
        'nama_aplikasi'     => 'Pinjam.In',
        'slogan_aplikasi'   => 'Perpustakaan Digital',
        'nama_sekolah'      => 'SMKN 1 Panyingkiran',
        'nama_perpustakaan' => 'Perpustakaan Digital SMKN 1 Panyingkiran',
        'maks_pinjam_buku'  => 3,     // Siswa maksimal boleh meminjam 3 buku bersamaan
        'lama_pinjam_hari'  => 7,     // Batas waktu peminjaman standar 7 hari
        'denda_per_hari'    => 1000,  // Denda keterlambatan Rp 1.000 / hari / buku
    ];

    try {
        $stmt = $koneksi->query("SELECT kunci, nilai FROM pengaturan");
        $data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        if (!empty($data)) {
            // Gabungkan default dengan data dari database (konversi angka ke integer)
            $cache = [
                'nama_aplikasi'     => (string) ($data['nama_aplikasi'] ?? $default['nama_aplikasi']),
                'slogan_aplikasi'   => (string) ($data['slogan_aplikasi'] ?? $default['slogan_aplikasi']),
                'nama_sekolah'      => (string) ($data['nama_sekolah'] ?? $default['nama_sekolah']),
                'nama_perpustakaan' => (string) ($data['nama_perpustakaan'] ?? $default['nama_perpustakaan']),
                'maks_pinjam_buku'  => isset($data['maks_pinjam_buku']) ? (int) $data['maks_pinjam_buku'] : $default['maks_pinjam_buku'],
                'lama_pinjam_hari'  => isset($data['lama_pinjam_hari']) ? (int) $data['lama_pinjam_hari'] : $default['lama_pinjam_hari'],
                'denda_per_hari'    => isset($data['denda_per_hari']) ? (int) $data['denda_per_hari'] : $default['denda_per_hari'],
            ];
        } else {
            $cache = $default;
        }
    } catch (Throwable $e) {
        // Jika ada masalah (misal tabel belum dibuat), pakai nilai default
        $cache = $default;
    }

    return $cache;
}

/**
 * Menyimpan pembaruan pengaturan ke tabel `pengaturan`.
 * 
 * @param PDO   $koneksi Objek koneksi database
 * @param array $data    Array asosiatif kunci => nilai baru
 * @return bool True jika berhasil
 */
function simpanPengaturan(PDO $koneksi, array $data): bool
{
    $stmt = $koneksi->prepare("
        INSERT INTO pengaturan (kunci, nilai) 
        VALUES (:kunci, :nilai)
        ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)
    ");

    foreach ($data as $kunci => $nilai) {
        $stmt->execute([
            ':kunci' => (string) $kunci,
            ':nilai' => (string) $nilai,
        ]);
    }

    return true;
}

/**
 * Format angka ke mata uang Rupiah (contoh: 1000 -> Rp 1.000).
 */
function formatRupiah(int $angka): string
{
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
