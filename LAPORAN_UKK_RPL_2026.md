# LAPORAN PRAKTIK UJI KOMPETENSI KEAHLIAN (UKK)
## TAHUN PELAJARAN 2025/2026

### **RANCANG BANGUN APLIKASI PERPUSTAKAAN DIGITAL BERBASIS WEB TERINTEGRASI SISTEM IDENTIFIKASI RFID DAN SCANNER QR CODE ("PINJAM.IN") PADA SMK NEGERI 1 PANYINGKIRAN**

---

<p align="center">
  <img src="assets/screenshots/logo_smkn1panyingkiran.png" width="160" alt="Logo Resmi SMKN 1 Panyingkiran">
</p>

**Disusun Oleh:**
- **Nama Siswa**: MUHAMMAD FARID MALIK
- **NIS**: 2425123
- **Kelas**: XII RPL 2
- **Program Keahlian**: Pengembangan Perangkat Lunak dan Gim (PPLG)
- **Konsentrasi Keahlian**: Rekayasa Perangkat Lunak (RPL)

---

**PEMERINTAH DAERAH PROVINSI JAWA BARAT**  
**DINAS PENDIDIKAN**  
**CABANG DINAS PENDIDIKAN WILAYAH IX**  
**SMK NEGERI 1 PANYINGKIRAN**  
*(NPSN: 20247198 - Terakreditasi A)*  
*Jl. Kirapandak, Desa Karyamukti, Kec. Panyingkiran, Kabupaten Majalengka, Jawa Barat 45459*  
*Website: smkn1panyingkiran.sch.id | Tahun 2026*

---

# 2. KATA PENGANTAR

Puji dan syukur penulis panjatkan ke hadirat Allah SWT atas limpahan rahmat, taufik, serta hidayah-Nya, sehingga penulis dapat menyelesaikan penulisan Laporan Praktik Uji Kompetensi Keahlian (UKK) yang berjudul **"Rancang Bangun Aplikasi Perpustakaan Digital Berbasis Web Terintegrasi Sistem Identifikasi RFID dan Scanner QR Code (Pinjam.In) pada SMK Negeri 1 Panyingkiran"** dengan baik dan tepat waktu.

Laporan ini disusun sebagai salah satu syarat mutlak guna menyelesaikan pendidikan pada Kompetensi Keahlian Rekayasa Perangkat Lunak (RPL), Program Keahlian Pengembangan Perangkat Lunak dan Gim (PPLG) di SMK Negeri 1 Panyingkiran Tahun Pelajaran 2025/2026, sekaligus sebagai pertanggungjawaban karya proyek perangkat lunak mandiri yang telah dirancang, diimplementasikan, dan diuji.

Dalam proses perancangan sistem hingga terselesaikannya penulisan laporan ini, penulis mendapatkan banyak bimbingan, arahan, dan dukungan moril dari berbagai pihak. Oleh karena itu, penulis ingin menyampaikan ucapan terima kasih yang tulus dan penghargaan setinggi-tingginya kepada:

1. **Bapak H. Akhmad Karyono, S.Pd., M.Pd.**, selaku Kepala SMK Negeri 1 Panyingkiran, yang senantiasa memfasilitasi sarana dan prasarana pendidikan teknologi yang unggul.
2. **Bapak/Ibu Penguji Internal dan Penguji Eksternal (Industri)**, yang telah meluangkan waktu untuk menguji, membimbing, dan memvalidasi kelayakan proyek perangkat lunak ini.
3. **Bapak/Ibu Guru Produktif Rekayasa Perangkat Lunak SMK Negeri 1 Panyingkiran**, atas dedikasi dan transfer ilmu pengetahuan seputar pemrograman web, basis data, dan rekayasa sistem.
4. **Kedua Orang Tua dan Keluarga Tercinta**, yang tiada henti memanjatkan doa, memberikan dorongan motivasi, serta pengorbanan materiel maupun spiritual.
5. **Rekan-rekan seperjuangan Kelas XII RPL 2**, atas kerja sama, diskusi, dan solidaritas selama proses pengerjaan proyek UKK ini berlangsung.

Penulis menyadari bahwa sistem maupun laporan ini masih memiliki ruang untuk penyempurnaan di masa mendatang. Oleh sebab itu, kritik dan saran yang konstruktif sangat diharapkan demi pengembangan aplikasi ke arah yang lebih baik. Semoga karya ini dapat memberikan manfaat nyata bagi kemajuan sistem informasi di lingkungan SMK Negeri 1 Panyingkiran serta menambah literatur di bidang Rekayasa Perangkat Lunak.

<div align="right">
  Majalengka, Februari 2026<br><br><br>
  <b>Muhammad Farid Malik</b><br>
  NIS. 24250223
</div>

---

# 3. DAFTAR ISI

- **HALAMAN JUDUL / COVER**
- **KATA PENGANTAR**
- **DAFTAR ISI**
- **4. DESKRIPSI PROJEK** .................................................................. 1
  - 4.1 Latar Belakang Masalah ............................................................. 1
  - 4.2 Tujuan Aplikasi .................................................................... 2
  - 4.3 Fungsi dan Fitur Utama ............................................................. 2
  - 4.4 Teknologi yang Digunakan ........................................................... 3
  - 4.5 Pengguna Aplikasi (User Role) ...................................................... 3
- **5. FLOWCHART SISTEM** .................................................................. 5
  - 5.1 Flowchart Autentikasi & Multi-Role Login ............................................ 5
  - 5.2 Flowchart Transaksi Peminjaman Meja Sirkulasi ....................................... 7
  - 5.3 Flowchart Transaksi Pengembalian & Kalkulasi Denda ................................. 9
- **6. PERANCANGAN DATABASE** .............................................................. 11
  - 6.1 Entity Relationship Diagram (ERD Relasional) ........................................ 11
  - 6.2 Struktur & Spesifikasi 8 Tabel Basis Data ........................................... 12
- **7. TAMPILAN PUBLIC (SISWA & UMUM)** .................................................... 16
  - 7.1 Halaman Beranda Publik (`index.php`) ............................................... 16
  - 7.2 Halaman Autentikasi Login (`login.php`) ............................................ 16
  - 7.3 Halaman Pendaftaran Anggota Mandiri (`daftar.php`) ................................. 17
  - 7.4 Halaman Onboarding Google SSO (`lengkapi_profil_google.php`) ....................... 18
  - 7.5 Halaman Dashboard Siswa (`siswa/dashboard.php`) .................................... 19
  - 7.6 Halaman Katalog Buku Siswa (`siswa/daftar_buku.php`) ............................... 20
  - 7.7 Halaman Riwayat Sirkulasi Siswa (`siswa/riwayat.php`) ............................... 21
- **8. TAMPILAN ADMIN (PETUGAS PERPUSTAKAAN)** ............................................. 22
  - 8.1 Dashboard Utama Administrator (`admin/dashboard.php`) .............................. 22
  - 8.2 Manajemen Data Buku Koleksi (`admin/buku.php`) ..................................... 22
  - 8.3 Manajemen Eksemplar Fisik & Cetak QR (`admin/buku_item.php`) ....................... 23
  - 8.4 Manajemen Anggota & Pairing Kartu RFID (`admin/anggota.php`) ....................... 24
  - 8.5 Meja Sirkulasi Peminjaman Buku (`admin/peminjaman.php`) ............................ 25
  - 8.6 Meja Sirkulasi Pengembalian Buku (`admin/pengembalian.php`) ........................ 26
  - 8.7 Arsip Riwayat Transaksi & Pelunasan Kas Denda (`admin/transaksi.php`) .............. 27
  - 8.8 Ekspor Rekapitulasi Laporan Excel (.xls) ............................................ 28
  - 8.9 Log Aktivitas Sistem & Audit Trail (`admin/log_aktivitas.php`) ...................... 28
- **9. IMPLEMENTASI ALAT PENDUKUNG (HARDWARE)** ............................................ 30
  - 9.1 Barcode Scanner Desktop 2D / QR Code (Taffware Omnidirectional) .................... 30
  - 9.2 RFID Card Reader USB 13.56 MHz (Identifikasi Kartu Pelajar Siswa) .................. 31
  - 9.3 Printer Epson All-in-One Ink Tank (Cetak Laporan, KTA & Label QR) .................. 32
- **10. PENUTUP** .......................................................................... 34
  - 10.1 Kesimpulan ........................................................................ 34
  - 10.2 Saran Pengembangan ................................................................ 34
- **LAMPIRAN** ............................................................................. 35

---

# 4. DESKRIPSI PROJEK

### 4.1 Latar Belakang Masalah
Perpustakaan sekolah merupakan jantung sumber belajar dan jendela literasi bagi seluruh warga pendidikan di SMK Negeri 1 Panyingkiran. Namun dalam operasional kesehariannya, sistem pelayanan sirkulasi yang masih bersifat manual atau semi-digital berbasis spreadsheet sering menghadapi kendala nyata:
1. **Antrean Meja Sirkulasi yang Menumpuk**: Pada jam istirahat sekolah yang berdurasi singkat (15–30 menit), puluhan siswa berkerumun untuk meminjam buku. Petugas perpustakaan harus mencari data siswa secara manual, mencatat kode buku satu demi satu ke buku besar, dan memverifikasi batas pinjaman. Proses ini memakan waktu 2–4 menit per transaksi.
2. **Ketiadaan Pelacakan Eksemplar Fisik**: Judul buku yang sama di perpustakaan umumnya memiliki 5 hingga 20 eksemplar fisik. Sistem konvensional yang hanya mencatat "Judul Buku" tidak mampu membedakan eksemplar mana yang rusak, hilang, atau sedang berada di tangan siswa tertentu.
3. **Keterlambatan dan Pengawasan Kas Denda**: Perhitungan denda harian yang dihitung manual rawan terjadi kekeliruan pembukuan serta kebocoran kas perpustakaan. Siswa pun kerap lupa batas tanggal pengembalian karena tidak memiliki kartu pengingat digital.
4. **Kebutuhan Aksesibilitas Modern**: Generasi siswa saat ini terbiasa dengan kemudahan *Single Sign-On* (SSO) akun Google sekolah serta katalog online yang responsif melalui smartphone tanpa harus datang ke rak buku hanya untuk mengecek ketersediaan stok.

Berdasarkan permasalahan di atas, dirancanglah **Pinjam.In**, sebuah sistem perpustakaan digital terintegrasi yang menggabungkan aplikasi web modular dengan 3 perangkat keras operasional (RFID Reader, Barcode Scanner Gun, dan Printer) untuk mewujudkan konsep *Smart School Library* yang akuntabel, cepat, dan presisi.

### 4.2 Tujuan Aplikasi
Pengembangan aplikasi **Pinjam.In** bertujuan untuk:
1. **Mempercepat Pelayanan Meja Sirkulasi**: Memangkas waktu pencatatan transaksi dari hitungan menit menjadi di bawah 5 detik per transaksi melalui teknologi *Tap RFID* kartu pelajar dan pemindaian cepat QR Code eksemplar buku.
2. **Menjamin Akurasi Inventaris Koleksi Fisik**: Mengimplementasikan model pendataan buku berbasis fisik (1 judul memiliki banyak `buku_item` berkode QR unik).
3. **Meningkatkan Transparansi Finansial Kas Denda**: Mengotomatisasi kalkulasi denda keterlambatan berjalan serta status pelunasan kas secara transparan.
4. **Menyediakan Layanan Mandiri (*Self-Service*) Bagi Siswa**: Memudahkan siswa menelusuri katalog buku, ketersediaan rak, status peminjaman aktif, serta integrasi login instan via akun Google.
5. **Mempermudah Rekapitulasi & Pelaporan Resmi**: Menyediakan fasilitas cetak dokumen resmi ber-KOP sekolah, cetak kartu anggota dan label stiker QR via printer, serta ekspor file Microsoft Excel (.xls) berstandar industri.

### 4.3 Fungsi dan Fitur Utama
Aplikasi Pinjam.In dilengkapi fitur-fitur komprehensif sebagai berikut:
- **Autentikasi Ganda & Keamanan**:
  - Dukungan login username/password dengan hashing `PASSWORD_BCRYPT`.
  - Integrasi **Google OAuth 2.0 Client** untuk login mandiri siswa dengan email Google.
  - Alur *Onboarding* pendaftaran akun siswa baru otomatis dari data profil Google.
  - Fitur perlindungan Session Hijacking dan token CSRF State.
  - Fitur Reset Password siswa ke default (NIS) oleh Administrator dalam sekali klik.
- **Manajemen Inventaris Buku & Eksemplar (Multi-Copies)**:
  - Pencatatan metadata bibliografi lengkap (Judul, Pengarang, Penerbit, Tahun Terbit, Kategori).
  - Generator eksemplar otomatis dengan format kode unik (misal: `BK-17-001`, `BK-17-002`).
  - Unggah cover buku dengan validasi tipe file (JPG, PNG, WebP) dan kompresi visual.
  - Generator kode QR individual per buku fisik yang siap dicetak sebagai stiker label rak.
  - Impor data buku massal dari format berkas CSV.
- **Manajemen Siswa & Pairing Kartu RFID Fisik**:
  - Pendataan anggota siswa lengkap (NIS, Nama, Kelas, No. HP, Alamat, Foto Profil).
  - Fitur *Tap Pairing RFID* untuk menautkan nomor kartu UID fisik langsung ke akun siswa.
- **Layanan Sirkulasi Cepat (Peminjaman & Pengembalian)**:
  - Identifikasi peminjam instan melalui Tap Kartu RFID atau input NIS.
  - Pemindaian buku fisik via Barcode Gun maupun Webcam kamera laptop secara realtime.
  - Validasi sistem otomatis: pemeriksaan status stok fisik, pembatasan kuota maksimal (3 buku), dan pencegahan peminjaman ganda untuk judul yang sama.
  - Penentuan tanggal kembali otomatis (H+7 hari).
  - Deteksi keterlambatan otomatis dan kalkulasi denda harian kas (Rp 1.000/hari).
  - Manajemen status denda (*Lunas* vs *Belum Lunas*) serta pencatatan setoran kas denda.
- **Pencarian & Pagination Server-Side Modern**:
  - Navigasi halaman cepat (*server-side limit/offset*) dengan komponen responsif.
  - Preservasi parameter query string (pencarian kata kunci, filter kategori, status, rentang tanggal tetap aktif saat berpindah halaman).
- **Pelaporan & Ekspor Berkas**:
  - Ekspor laporan sirkulasi ke spreadsheet **Microsoft Excel (.xls)** dengan tata letak resmi, KOP sekolah, border rapi, badge warna, dan format mata uang rupiah.
  - Cetak bukti transaksi, kartu anggota, dan label stiker QR via printer.
  - Cetak laporan arsip A4 berstandar dinas dengan jam WIB dan tanda tangan pengesahan.
- **Audit Trail & Log Aktivitas**:
  - Pencatatan seluruh aksi pengguna (login, tambah, ubah, hapus data, transaksi, pelunasan denda) lengkap dengan IP Address dan timestamp waktu WIB.

### 4.4 Teknologi yang Digunakan
| Komponen | Spesifikasi & Teknologi | Alasan Pemilihan |
| :--- | :--- | :--- |
| **Bahasa Pemrograman Backend** | PHP 8.2 (Native Modular) | Ringan, kompatibel dengan seluruh web server sekolah, tanpa overhead vendor luar. |
| **Akses Basis Data** | **PDO (PHP Data Objects)** | Mendukung *Prepared Statements* terparameterisasi untuk proteksi mutlak dari serangan **SQL Injection**. |
| **Sistem Basis Data (RDBMS)** | MariaDB 10.4 / MySQL | Mendukung relasi Foreign Key (Cascading) dan transaksi atomik ACID (`beginTransaction`, `commit`, `rollBack`). |
| **Bahasa Antarmuka Frontend** | HTML5, CSS3 Murni, Vanilla JS | Performa rendering instan, responsif di layar desktop maupun smartphone. |
| **Tipografi & Ikon** | Plus Jakarta Sans, JetBrains Mono, SVG | Menampilkan antarmuka modern setara aplikasi industri SaaS masa kini. |
| **Library Pemindai QR** | HTML5-QRCode (Webcam API) | Membaca kode QR secara realtime langsung dari web browser tanpa instalasi plugin. |
| **Protokol Autentikasi** | Google Identity Service OAuth 2.0 | Memungkinkan siswa masuk dengan akun Google sekolah secara aman dan praktis. |
| **Web Server Lokal** | Apache 2.4 (XAMPP Environment) | Lingkungan pengujian standar kompetensi UKK SMK. |

### 4.5 Pengguna Aplikasi (User Role)
1. **Administrator Perpustakaan (Pustakawan)**:
   - Memiliki wewenang mengelola katalog buku dan pencetakan stiker QR label.
   - Mengelola anggota dan melakukan pairing kartu RFID siswa.
   - Melayani meja sirkulasi peminjaman, pengembalian, dan kas denda.
   - Mengunduh rekapitulasi laporan Excel dan mencetak lembar verifikasi arsip.
   - Memantau log aktivitas keamanan sistem.
2. **Siswa (Anggota Perpustakaan)**:
   - Mendaftar akun mandiri atau masuk melalui Single Sign-On Google.
   - Menjelajahi katalog buku perpustakaan lengkap dengan filter kategori interaktif.
   - Memeriksa jumlah eksemplar yang masih tersedia di rak secara realtime.
   - Memantau riwayat peminjaman aktif, batas tanggal jatuh tempo, dan rincian denda.
   - Memperbarui data profil pribadi dan foto kartu anggota.

---

# 5. FLOWCHART SISTEM

### 5.1 Flowchart Proses Utama: Autentikasi & Multi-Role Login
Diagram alir proses autentikasi mencakup dua opsi login: kredensial username/password tradisional dan Single Sign-On Google OAuth 2.0.

![Gambar 5.1: Flowchart Autentikasi Pengguna & Integrasi Google OAuth 2.0](assets/screenshots/fc_01_login.png)
*Gambar 5.1: Flowchart Autentikasi Pengguna & Integrasi Google OAuth 2.0*

```mermaid
flowchart TD
    Start(["Mulai"]) --> PilihLogin{"Pilih Metode Login"}
    
    PilihLogin -- "Username & Password" --> InputForm["Input Username & Password"]
    InputForm --> CekAdmin{"Cek Tabel admin?"}
    
    CekAdmin -- "Ditemukan" --> VerifPassAdmin{"password_verify() Cocok?"}
    VerifPassAdmin -- "Ya" --> SetSessAdmin["Set $_SESSION Admin<br/>Catat Log Aktivitas"] --> DashAdmin["Dashboard Admin"]
    VerifPassAdmin -- "Tidak" --> ErrLogin["Tampilkan Pesan Error: Akun / Password Salah"] --> InputForm
    
    CekAdmin -- "Tidak Ada" --> CekSiswa{"Cek Tabel anggota?"}
    CekSiswa -- "Ditemukan" --> VerifPassSiswa{"password_verify() Cocok?"}
    VerifPassSiswa -- "Ya" --> SetSessSiswa["Set $_SESSION Siswa<br/>Catat Log Aktivitas"] --> DashSiswa["Dashboard Siswa"]
    VerifPassSiswa -- "Tidak" --> ErrLogin
    CekSiswa -- "Tidak Ada" --> ErrLogin

    PilihLogin -- "Tombol Masuk dengan Google" --> RedirectGoogle["Redirect ke Google Accounts OAuth 2.0"]
    RedirectGoogle --> GoogleConsent["Siswa Pilih Akun & Beri Izin"]
    GoogleConsent --> Callback["Callback ke config/google_callback.php<br/>Validasi CSRF State & Tukar Token"]
    Callback --> AmbilProfil["Ambil ID Google, Email, Nama, Foto"]
    AmbilProfil --> CekGoogleID{"google_id Sudah Terdaftar di Anggota?"}
    
    CekGoogleID -- "Ya" --> SetSessGoogle["Set $_SESSION Siswa Langsung<br/>Catat Log Aktivitas"] --> DashSiswa
    CekGoogleID -- "Belum" --> CekEmail{"Email Siswa Cocok di Database?"}
    
    CekEmail -- "Ya" --> TautkanAkun["UPDATE anggota SET google_id = :gid"] --> SetSessGoogle
    CekEmail -- "Tidak" --> FormLengkapi["Arahkan ke lengkapi_profil_google.php<br/>(Lengkapi NIS, Kelas, Username)"]
    FormLengkapi --> SimpanSiswaBaru["INSERT ke Tabel anggota<br/>Unduh Avatar Google"] --> SetSessGoogle
```

---

### 5.2 Flowchart Proses Utama: Transaksi Peminjaman Buku di Meja Sirkulasi
Diagram alir proses peminjaman yang mengintegrasikan pemindaian RFID/NIS dan kode QR buku fisik dengan validasi kuota pinjaman.

![Gambar 5.2: Flowchart Alur Transaksi Peminjaman Buku di Meja Sirkulasi](assets/screenshots/fc_02_peminjaman.png)
*Gambar 5.2: Flowchart Alur Transaksi Peminjaman Buku di Meja Sirkulasi*

```mermaid
flowchart TD
    A(["Mulai Transaksi Peminjaman"]) --> B["Admin Buka admin/peminjaman.php"]
    B --> C["Scan Kartu RFID Siswa / Masukkan NIS"]
    C --> D{"Data Siswa Ditemukan di Database?"}
    
    D -- "Tidak" --> E["Munculkan Notifikasi: Siswa Tidak Ditemukan"] --> B
    D -- "Ya" --> F["Tampilkan Identitas Siswa & Status Pinjaman Aktif"]
    
    F --> G["Scan QR Code Eksemplar Buku (Barcode Gun / Webcam)"]
    G --> H{"Validasi Eksemplar Fisik"}
    
    H -- "Buku Tidak Ditemukan" --> ErrBuku["Notifikasi: Kode Buku Tidak Terdaftar"] --> F
    H -- "Status != 'tersedia'" --> ErrStatus["Notifikasi: Buku Sedang Dipinjam / Rusak"] --> F
    
    H -- "Buku Valid" --> I{"Apakah Siswa Sudah Meminjam Judul Sama?"}
    I -- "Ya" --> ErrJudul["Notifikasi: Tidak Boleh Dobel Judul yang Sama"] --> F
    
    I -- "Tidak" --> J{"Jumlah Pinjaman Aktif >= 3 Buku?"}
    J -- "Ya (Maksimal)" --> ErrKuota["Notifikasi: Batas Maksimal Kuota Terpenuhi (Maks. 3 Buku)"] --> F
    
    J -- "Tidak (Bisa Pinjam)" --> K["Klik Konfirmasi Peminjaman"]
    K --> L["Mulai Database Transaction: beginTransaction()"]
    
    L --> M["INSERT ke tabel transaksi:<br/>- kode_transaksi unik<br/>- tgl_pinjam = CURDATE()<br/>- tgl_kembali_rencana = CURDATE() + 7 Hari<br/>- status = 'dipinjam'"]
    M --> N["UPDATE buku_item SET status = 'dipinjam' WHERE id = :id"]
    N --> O["Catat Riwayat ke log_aktivitas"]
    
    O --> P{"Semua Query Sukses?"}
    P -- "Gagal" --> Q["rollBack() Pembatalan Otomatis"] --> ErrSistem["Tampilkan Notifikasi Transaksi Gagal"]
    P -- "Sukses" --> R["commit() Data Permanen"]
    
    R --> S["Tampilkan Struk Transaksi"]
    S --> T{"Cetak Bukti Transaksi?"}
    T -- "Ya" --> U["Cetak Bukti Transaksi via Printer"] --> Selesai(["Selesai"])
    T -- "Tidak" --> Selesai
```

---

### 5.3 Flowchart Proses Utama: Transaksi Pengembalian Buku & Perhitungan Denda
Diagram alir pengembalian buku fisik di meja sirkulasi yang mengkalkulasi selisih hari keterlambatan dan pembukuan kas denda.

![Gambar 5.3: Flowchart Alur Transaksi Pengembalian Buku & Perhitungan Denda](assets/screenshots/fc_03_pengembalian.png)
*Gambar 5.3: Flowchart Alur Transaksi Pengembalian Buku & Perhitungan Denda*

```mermaid
flowchart TD
    A(["Mulai Pengembalian Buku"]) --> B["Admin Buka admin/pengembalian.php"]
    B --> C["Scan Kartu RFID Siswa / Scan QR Buku Langsung"]
    C --> D{"Data Peminjaman Aktif Ditemukan?"}
    
    D -- "Tidak" --> E["Notifikasi: Transaksi Aktif Tidak Ditemukan"] --> B
    D -- "Ya" --> F["Sistem Membaca:<br/>- tgl_pinjam<br/>- tgl_kembali_rencana<br/>- tgl_kembali_aktual = CURDATE()"]
    
    F --> G{"Apakah CURDATE() > tgl_kembali_rencana?"}
    
    G -- "Tidak (Tepat Waktu)" --> H["Denda = Rp 0<br/>Status Transaksi = 'dikembalikan'<br/>Status Denda = 'lunas'"]
    
    G -- "Ya (Terlambat)" --> I["Hitung Keterlambatan:<br/>selisih_hari = (CURDATE - tgl_rencana) / 86400"]
    I --> J["Denda = selisih_hari * Tarif Pengaturan (Rp 1.000)<br/>Status Transaksi = 'terlambat'<br/>Status Denda = 'belum_lunas'"]
    
    H --> K["Admin Klik Tombol 'Proses Pengembalian'"]
    J --> K
    
    K --> L["Mulai Database Transaction: beginTransaction()"]
    L --> M["UPDATE transaksi SET:<br/>- tgl_kembali_aktual = CURDATE()<br/>- denda = :denda<br/>- status = :status<br/>- status_denda = :status_denda"]
    M --> N["UPDATE buku_item SET status = 'tersedia' WHERE id = :item_id"]
    N --> O["Catat ke log_aktivitas"]
    
    O --> P{"Eksekusi Sukses?"}
    P -- "Gagal" --> Q["rollBack() Transaksi Dibatalkan"] --> Err["Notifikasi Gagal"]
    P -- "Sukses" --> R["commit() Simpan Data"]
    
    R --> S{"Ada Denda Keterlambatan?"}
    S -- "Ya" --> T["Munculkan Opsi: Siswa Membayar Denda Sekarang?"]
    T -- "Siswa Bayar Langsung" --> U["Klik 'Tandai Lunas'<br/>UPDATE transaksi SET status_denda = 'lunas'<br/>Catat ke Kas Denda"] --> V["Cetak Bukti Pengembalian via Printer"]
    T -- "Ditangguhkan" --> V
    S -- "Tidak" --> V
    V --> W(["Selesai (Buku Siap Dipinjam Siswa Lain)"])
```

---

# 6. PERANCANGAN DATABASE

### 6.1 Entity Relationship Diagram (ERD)
Database sistem bernama `pinjam_in` dirancang dengan normalisasi tingkat ketiga (3NF) guna menjamin konsistensi data, relasi foreign key yang kokoh, dan integritas referensial.

![Gambar 6.1: Entity Relationship Diagram (ERD) Basis Data pinjam_in](assets/screenshots/erd_database.png)
*Gambar 6.1: Entity Relationship Diagram (ERD) Basis Data pinjam_in*

```mermaid
erDiagram
    kategori ||--o{ buku : "mengelompokkan"
    buku ||--|{ buku_item : "memiliki banyak fisik"
    anggota ||--o{ transaksi : "melakukan"
    buku_item ||--o{ transaksi : "dipinjam dalam"
    admin ||--o{ log_aktivitas : "mencatat aktivitas"
    anggota ||--o{ log_aktivitas : "mencatat aktivitas"

    admin {
        int id PK "Auto Increment"
        varchar username UK "50 Karakter Unik"
        varchar password "Hash Bcrypt 255"
        varchar nama_lengkap "100 Karakter"
        varchar foto "Path File Avatar"
        timestamp created_at "Waktu Buat"
    }

    anggota {
        int id PK "Auto Increment"
        varchar nis UK "20 Karakter Unik"
        varchar kode_rfid UK "50 Karakter UID RFID"
        varchar nama_lengkap "100 Karakter"
        varchar kelas "20 Karakter (Contoh: XII RPL 2)"
        varchar email UK "100 Karakter Akun Google"
        varchar no_hp "20 Karakter"
        text alamat "Alamat Rumah Lengkap"
        varchar foto "Foto Profil Siswa"
        varchar username UK "50 Karakter Akun Siswa"
        varchar password "Hash Bcrypt 255"
        varchar google_id UK "100 Karakter Sub Google"
        timestamp created_at "Waktu Terdaftar"
    }

    kategori {
        int id PK "Auto Increment"
        varchar nama_kategori "100 Karakter (Fiksi, Pelajaran, dll)"
    }

    buku {
        int id PK "Auto Increment"
        varchar kode_buku UK "20 Karakter Katalog"
        varchar judul "150 Karakter Judul Buku"
        varchar pengarang "100 Karakter"
        varchar penerbit "100 Karakter"
        year tahun_terbit "Tahun Publikasi"
        int kategori_id FK "Relasi ke kategori.id"
        varchar cover "Nama Berkas Gambar Sampul"
        timestamp created_at "Waktu Input Judul"
    }

    buku_item {
        int id PK "Auto Increment"
        varchar kode_item UK "30 Karakter QR Fisik (BK-xx-yyy)"
        int buku_id FK "Relasi ke buku.id (Cascade)"
        enum status "tersedia, dipinjam, rusak, hilang"
        timestamp created_at "Waktu Registrasi Eksemplar"
    }

    transaksi {
        int id PK "Auto Increment"
        varchar kode_transaksi UK "30 Karakter (TRX-...)"
        int anggota_id FK "Relasi ke anggota.id"
        int buku_item_id FK "Relasi ke buku_item.id"
        date tgl_pinjam "Tanggal Peminjaman"
        date tgl_kembali_rencana "Batas Waktu Jatuh Tempo"
        date tgl_kembali_aktual "Tanggal Realisasi Kembali"
        int denda "Nominal Denda (Rupiah)"
        enum status_denda "lunas, belum_lunas"
        enum status "dipinjam, dikembalikan, terlambat"
        timestamp created_at "Waktu Transaksi Dibuat"
    }

    pengaturan {
        varchar kunci PK "50 Karakter (Key)"
        text nilai "Nilai Pengaturan Konfigurasi"
        varchar keterangan "Deskripsi Pengaturan"
    }

    log_aktivitas {
        int id PK "Auto Increment"
        enum role "admin, siswa, sistem"
        int user_id "ID Pengguna Pelaku"
        varchar nama_pelaku "100 Karakter"
        varchar aksi "50 Karakter (Login, Pinjam, dll)"
        varchar modul "50 Karakter (Modul Sistem)"
        text keterangan "Detail Deskripsi Aktivitas"
        varchar ip_address "45 Karakter IPv4/IPv6"
        timestamp created_at "Waktu Kejadian (WIB)"
    }
```

---

### 6.2 Struktur & Spesifikasi Tabel Basis Data

#### 1. Tabel `admin`
Menyimpan akun administrator atau staf pustakawan yang berwenang mengelola sistem.
| No | Field | Tipe Data | Null | Kunci | Keterangan |
|---|---|---|---|---|---|
| 1 | `id` | INT(11) | NO | PK (AI) | Primary Key ID Admin |
| 2 | `username` | VARCHAR(50) | NO | UNIQUE | Username akun staf untuk login |
| 3 | `password` | VARCHAR(255) | NO | - | Password terenkripsi algoritma Bcrypt |
| 4 | `nama_lengkap` | VARCHAR(100) | NO | - | Nama lengkap petugas perpustakaan |
| 5 | `foto` | VARCHAR(255) | YES | - | Nama berkas foto profil admin |
| 6 | `created_at` | TIMESTAMP | NO | - | Waktu akun didaftarkan (Default CURRENT_TIMESTAMP) |

#### 2. Tabel `anggota`
Menyimpan identitas lengkap siswa yang terdaftar sebagai anggota perpustakaan.
| No | Field | Tipe Data | Null | Kunci | Keterangan |
|---|---|---|---|---|---|
| 1 | `id` | INT(11) | NO | PK (AI) | Primary Key ID Anggota Siswa |
| 2 | `nis` | VARCHAR(20) | NO | UNIQUE | Nomor Induk Siswa Nasional / Sekolah |
| 3 | `kode_rfid` | VARCHAR(50) | YES | UNIQUE | Kode unik kartu RFID fisik hasil tap |
| 4 | `nama_lengkap` | VARCHAR(100) | NO | - | Nama lengkap siswa |
| 5 | `kelas` | VARCHAR(20) | NO | - | Rombongan belajar siswa (misal: XII RPL 2) |
| 6 | `email` | VARCHAR(100) | YES | UNIQUE | Alamat surel siswa (Sinkronisasi Google) |
| 7 | `no_hp` | VARCHAR(20) | YES | - | Nomor WhatsApp / telepon aktif siswa |
| 8 | `alamat` | TEXT | YES | - | Alamat domisili lengkap siswa |
| 9 | `foto` | VARCHAR(255) | YES | - | Nama berkas foto profil siswa di server |
| 10 | `username` | VARCHAR(50) | NO | UNIQUE | Username untuk login mandiri portal siswa |
| 11 | `password` | VARCHAR(255) | NO | - | Password terenkripsi algoritma Bcrypt |
| 12 | `google_id` | VARCHAR(100) | YES | UNIQUE | ID unik pengguna resmi dari Google API |
| 13 | `created_at` | TIMESTAMP | NO | - | Waktu pendaftaran anggota |

#### 3. Tabel `buku`
Menyimpan data katalog referensi dan judul bibliografi buku.
| No | Field | Tipe Data | Null | Kunci | Keterangan |
|---|---|---|---|---|---|
| 1 | `id` | INT(11) | NO | PK (AI) | Primary Key ID Judul Buku |
| 2 | `kode_buku` | VARCHAR(20) | NO | UNIQUE | Kode katalog buku |
| 3 | `judul` | VARCHAR(150) | NO | - | Judul lengkap karya pustaka |
| 4 | `pengarang` | VARCHAR(100) | YES | - | Nama penulis / pengarang buku |
| 5 | `penerbit` | VARCHAR(100) | YES | - | Nama badan penerbit |
| 6 | `tahun_terbit` | YEAR(4) | YES | - | Tahun buku diterbitkan |
| 7 | `kategori_id` | INT(11) | YES | FK | Foreign Key ke `kategori.id` (ON DELETE SET NULL) |
| 8 | `cover` | VARCHAR(255) | YES | - | Nama file gambar cover di `uploads/covers/` |
| 9 | `created_at` | TIMESTAMP | NO | - | Waktu buku ditambahkan ke koleksi |

#### 4. Tabel `buku_item`
Menyimpan rekaman fisik per eksemplar buku di rak perpustakaan.
| No | Field | Tipe Data | Null | Kunci | Keterangan |
|---|---|---|---|---|---|
| 1 | `id` | INT(11) | NO | PK (AI) | Primary Key ID Eksemplar |
| 2 | `kode_item` | VARCHAR(30) | NO | UNIQUE | Kode QR individual (misal: `BK-17-001`) |
| 3 | `buku_id` | INT(11) | NO | FK | Foreign Key ke `buku.id` (ON DELETE CASCADE) |
| 4 | `status` | ENUM | NO | - | `'tersedia'`, `'dipinjam'`, `'rusak'`, `'hilang'` |
| 5 | `created_at` | TIMESTAMP | NO | - | Waktu eksemplar dibuat |

#### 5. Tabel `kategori`
Mengelompokkan koleksi buku berdasarkan bidang keilmuan.
| No | Field | Tipe Data | Null | Kunci | Keterangan |
|---|---|---|---|---|---|
| 1 | `id` | INT(11) | NO | PK (AI) | Primary Key ID Kategori |
| 2 | `nama_kategori` | VARCHAR(100) | NO | - | Nama kategori (Fiksi, Pelajaran, dsb.) |

#### 6. Tabel `transaksi`
Mencatat seluruh sirkulasi peminjaman, pengembalian, status denda, dan tanggal jatuh tempo.
| No | Field | Tipe Data | Null | Kunci | Keterangan |
|---|---|---|---|---|---|
| 1 | `id` | INT(11) | NO | PK (AI) | Primary Key ID Transaksi |
| 2 | `kode_transaksi` | VARCHAR(30) | NO | UNIQUE | Kode unik transaksi peminjaman |
| 3 | `anggota_id` | INT(11) | NO | FK | Foreign Key ke `anggota.id` |
| 4 | `buku_item_id` | INT(11) | NO | FK | Foreign Key ke `buku_item.id` |
| 5 | `tgl_pinjam` | DATE | NO | - | Tanggal buku dipinjam |
| 6 | `tgl_kembali_rencana` | DATE | NO | - | Batas tanggal pengembalian (H+7 hari) |
| 7 | `tgl_kembali_aktual` | DATE | YES | - | Tanggal nyata saat buku dikembalikan |
| 8 | `denda` | INT(11) | NO | - | Besaran denda keterlambatan (Default 0) |
| 9 | `status_denda` | ENUM | NO | - | `'lunas'`, `'belum_lunas'` |
| 10 | `status` | ENUM | NO | - | `'dipinjam'`, `'dikembalikan'`, `'terlambat'` |
| 11 | `created_at` | TIMESTAMP | NO | - | Waktu transaksi dibuat di sistem |

#### 7. Tabel `pengaturan`
Menyimpan konfigurasi parameter dinamis perpustakaan sekolah.
| No | Field | Tipe Data | Null | Kunci | Keterangan |
|---|---|---|---|---|---|
| 1 | `kunci` | VARCHAR(50) | NO | PK | Kunci konfigurasi unik (Primary Key) |
| 2 | `nilai` | TEXT | NO | - | Nilai setting (contoh tarif denda: 1000) |
| 3 | `keterangan` | VARCHAR(255) | YES | - | Deskripsi peruntukan konfigurasi |

#### 8. Tabel `log_aktivitas`
Menyimpan rekaman audit (*audit trail*) untuk keamanan dan pemantauan sistem.
| No | Field | Tipe Data | Null | Kunci | Keterangan |
|---|---|---|---|---|---|
| 1 | `id` | INT(11) | NO | PK (AI) | Primary Key ID Log |
| 2 | `role` | ENUM | NO | - | `'admin'`, `'siswa'`, `'sistem'` |
| 3 | `user_id` | INT(11) | YES | - | ID pengguna bersangkutan |
| 4 | `nama_pelaku` | VARCHAR(100) | NO | - | Nama pengguna yang melakukan aksi |
| 5 | `aksi` | VARCHAR(50) | NO | - | Jenis aksi (Login, Tambah, Edit, Hapus, Lunasi) |
| 6 | `modul` | VARCHAR(50) | NO | - | Modul terkait (Buku, Sirkulasi, Profil, dsb.) |
| 7 | `keterangan` | TEXT | NO | - | Uraian detail catatan log aktivitas |
| 8 | `ip_address` | VARCHAR(45) | YES | - | Alamat IP perangkat pengguna (IPv4/IPv6) |
| 9 | `created_at` | TIMESTAMP | NO | - | Waktu aktivitas tercatat (WIB) |

---

# 7. TAMPILAN PUBLIC (SISWA & UMUM)

Berikut adalah dokumentasi antarmuka pada area publik dan portal siswa:

### 7.1 Halaman Beranda Publik (`index.php`)
- **Fungsi**: Halaman awal yang menyambut pengunjung perpustakaan, menampilkan katalog unggulan, statistik koleksi, serta panduan singkat tata cara peminjaman buku sekolah.
- **Elemen Penting**: Header interaktif, pencarian katalog instan, tombol pintas login portal siswa/admin, dan kartu koleksi buku populer.
![Gambar 7.1: Antarmuka Halaman Beranda Utama (index.php)](assets/screenshots/01_beranda_index.png)
*Gambar 7.1: Antarmuka Halaman Beranda Utama (index.php)*

### 7.2 Halaman Autentikasi Pengguna (`login.php`)
- **Fungsi**: Gerbang masuk tunggal untuk Administrator maupun Siswa dengan sistem auto-routing berdasarkan role.
- **Elemen Penting**: Input username/NIS, input password, tombol "Masuk dengan Google" resmi, serta tautan ke halaman bantuan lupa password dan registrasi siswa mandiri.
![Gambar 7.2: Form Login Multi-Role & Tombol Masuk dengan Google (login.php)](assets/screenshots/02_login_form.png)
*Gambar 7.2: Form Login Multi-Role & Tombol Masuk dengan Google (login.php)*

### 7.3 Halaman Pendaftaran Siswa Mandiri (`daftar.php`)
- **Fungsi**: Form registrasi akun bagi siswa yang belum terdaftar di sistem perpustakaan.
- **Elemen Penting**: Input NIS unik, nama lengkap, pilihan kelas (X, XI, XII RPL), nomor WhatsApp, username, dan konfirmasi password.
![Gambar 7.3: Formulir Registrasi Akun Siswa Mandiri (daftar.php)](assets/screenshots/03_daftar_siswa.png)
*Gambar 7.3: Formulir Registrasi Akun Siswa Mandiri (daftar.php)*

### 7.4 Halaman Onboarding Google SSO (`lengkapi_profil_google.php`)
- **Fungsi**: Formulir verifikasi bagi siswa yang baru pertama kali login menggunakan akun Google. Nama dan surel diambil otomatis dari Google API, siswa cukup mengonfirmasi kesesuaian nama dan mengisi NIS sekolah.
- **Elemen Penting**: Avatar foto Google terverifikasi, form NIS, pilihan kelas, dan tombol simpan profil anggota.
![Gambar 7.4: Formulir Onboarding Verifikasi Profil Akun Google Siswa](assets/screenshots/04_google_onboarding.png)
*Gambar 7.4: Formulir Onboarding Verifikasi Profil Akun Google Siswa*

### 7.5 Halaman Dashboard Siswa (`siswa/dashboard.php`)
- **Fungsi**: Pusat kendali siswa yang merangkum kartu informasi anggota digital, buku yang sedang dipinjam beserta sisa hari jatuh tempo, total buku yang pernah dibaca, dan rekomendasi buku baru.
- **Elemen Penting**: Kartu Pelajar Digital ber-QR Code, peringatan batas tanggal kembali, dan widget status sirkulasi pribadi.
![Gambar 7.5: Dashboard Portal Mandiri Siswa (siswa/dashboard.php)](assets/screenshots/05_siswa_dashboard.png)
*Gambar 7.5: Dashboard Portal Mandiri Siswa (siswa/dashboard.php)*

### 7.6 Halaman Katalog Buku Siswa (`siswa/daftar_buku.php`)
- **Fungsi**: Antarmuka bagi siswa untuk menjelajahi seluruh koleksi buku sekolah secara mandiri.
- **Elemen Penting**: Bilah pencarian kata kunci, pil filter kategori (Fiksi, Non-Fiksi, Pelajaran, Komik), kartu buku 3D dengan indikator ketersediaan stok fisik di rak (`X dari Y tersedia`), pagination server-side, serta modal popup rincian bibliografi buku.
![Gambar 7.6: Katalog Koleksi Buku Siswa dengan Filter Kategori & Stok Rak](assets/screenshots/06_siswa_katalog.png)
*Gambar 7.6: Katalog Koleksi Buku Siswa dengan Filter Kategori & Stok Rak*

### 7.7 Halaman Riwayat Transaksi Siswa (`siswa/riwayat.php`)
- **Fungsi**: Rekapan lengkap seluruh buku yang pernah dipinjam siswa beserta status denda dan keterangan pengembalian tepat waktu/terlambat.
- **Elemen Penting**: Badge status (`Dipinjam`, `Dikembalikan`, `Terlambat`), tanggal pinjam, batas waktu, tanggal kembali riil, dan nominal denda.
![Gambar 7.7: Rekapan Riwayat Sirkulasi Peminjaman Siswa (siswa/riwayat.php)](assets/screenshots/07_siswa_riwayat.png)
*Gambar 7.7: Rekapan Riwayat Sirkulasi Peminjaman Siswa (siswa/riwayat.php)*

---

# 8. TAMPILAN ADMIN

Berikut adalah dokumentasi antarmuka kerja Administrator / Staf Perpustakaan:

### 8.1 Dashboard Utama Administrator (`admin/dashboard.php`)
- **Fungsi**: Memantau metrik performa operasional perpustakaan sekolah dalam satu layar terpadu.
- **Elemen Penting**: Metrik ringkas (Total Judul Buku, Total Fisik Eksemplar, Anggota Siswa Aktif, Peminjaman Sedang Berjalan, Transaksi Terlambat), grafik tren sirkulasi bulanan, dan tabel peminjaman terkini.
![Gambar 8.1: Dashboard Manajemen Administrator Perpustakaan](assets/screenshots/08_admin_dashboard.png)
*Gambar 8.1: Dashboard Manajemen Administrator Perpustakaan*

### 8.2 Manajemen Koleksi Buku (`admin/buku.php`)
- **Fungsi**: Mengelola master judul buku, unggah file cover buku, filter kategori server-side, pagination, serta aksi modal tambah/edit/hapus buku.
- **Elemen Penting**: Tabel judul buku, metrik stok eksemplar, tombol "Kelola Eksemplar", tombol "Cetak Stiker QR", dan filter chip kategori.
![Gambar 8.2: Kelola Data Koleksi Buku & Filter Kategori (admin/buku.php)](assets/screenshots/09_admin_buku.png)
*Gambar 8.2: Kelola Data Koleksi Buku & Filter Kategori (admin/buku.php)*

### 8.3 Manajemen Eksemplar Fisik & Cetak Stiker Label QR (`admin/buku_item.php` & `config/generate_qr.php`)
- **Fungsi**: Mengelola stok fisik individual per buku di rak perpustakaan dan mencetak stiker barcode/QR code fisik.
- **Elemen Penting**: Daftar kode item (`BK-xx-yyy`), status fisik per buku (`tersedia`, `dipinjam`, `rusak`, `hilang`), dan lembar cetak stiker label siap gunting untuk ditempel pada punggung buku.
![Gambar 8.3: Manajemen Eksemplar Fisik Buku (admin/buku_item.php)](assets/screenshots/10_admin_eksemplar_qr.png)
*Gambar 8.3: Manajemen Eksemplar Fisik Buku (admin/buku_item.php)*

![Gambar 8.4: Lembar Pratinjau Cetak Stiker Label QR Code Eksemplar Buku](assets/screenshots/11_admin_cetak_label_qr.png)
*Gambar 8.4: Lembar Pratinjau Cetak Stiker Label QR Code Eksemplar Buku*

### 8.4 Manajemen Anggota & Fitur Pairing Kartu RFID (`admin/anggota.php`)
- **Fungsi**: Pendataan seluruh siswa, reset password akun ke default (NIS), dan menautkan kartu fisik RFID siswa.
- **Elemen Penting**: Modal interaktif "Tap Kartu RFID Siswa" yang otomatis mendeteksi UID kartu USB, filter chip status (Bebas Pinjaman, Meminjam, RFID Aktif), dan pagination data siswa.
![Gambar 8.5: Kelola Data Siswa & Modal Tap Pairing Kartu RFID Fisik](assets/screenshots/12_admin_anggota.png)
*Gambar 8.5: Kelola Data Siswa & Modal Tap Pairing Kartu RFID Fisik*

### 8.5 Meja Sirkulasi Peminjaman Buku (`admin/peminjaman.php`)
- **Fungsi**: Halaman operasional utama kasir perpustakaan untuk memproses peminjaman buku oleh siswa dalam hitungan detik.
- **Elemen Penting**: Input scan kartu RFID siswa / NIS, panel identitas siswa aktif, pemindai Barcode Gun / Webcam HTML5, validasi kuota 3 buku, dan tombol cetak bukti peminjaman.
![Gambar 8.6: Meja Sirkulasi Peminjaman Cepat Berbasis Scan RFID & QR](assets/screenshots/13_admin_peminjaman.png)
*Gambar 8.6: Meja Sirkulasi Peminjaman Cepat Berbasis Scan RFID & QR*

### 8.6 Meja Sirkulasi Pengembalian Buku (`admin/pengembalian.php`)
- **Fungsi**: Memproses pengembalian buku fisik, kalkulasi selisih hari denda otomatis, dan pencatatan kas denda.
- **Elemen Penting**: Tombol "Kembalikan", label peringatan telat, kalkulator estimasi denda berjalan, konfirmasi pelunasan kas denda, dan cetak bukti pengembalian.
![Gambar 8.7: Meja Sirkulasi Pengembalian Buku & Deteksi Denda Keterlambatan](assets/screenshots/14_admin_pengembalian.png)
*Gambar 8.7: Meja Sirkulasi Pengembalian Buku & Deteksi Denda Keterlambatan*

### 8.7 Riwayat Transaksi & Pelunasan Kas Denda (`admin/transaksi.php`)
- **Fungsi**: Arsip menyeluruh seluruh sirkulasi perpustakaan dengan filter tanggal dari-sampai, kata kunci, dan status.
- **Elemen Penting**: Tombol "Tandai Lunas" denda, badge status sirkulasi, pagination responsif, dan tombol cetak arsip.
![Gambar 8.8: Riwayat Transaksi Sirkulasi & Pelunasan Kas Denda](assets/screenshots/15_admin_transaksi.png)
*Gambar 8.8: Riwayat Transaksi Sirkulasi & Pelunasan Kas Denda*

### 8.8 Ekspor Rekapitulasi Laporan Excel (.xls) (`admin/export_transaksi.php`)
- **Fungsi**: Mengunduh seluruh rekap sirkulasi ke spreadsheet Microsoft Excel berformat rapi dan profesional.
- **Elemen Penting**: KOP resmi SMKN 1 Panyingkiran, lebar kolom pas (mencegah teks tanggal `########`), format mata uang Rupiah standar akuntansi, zebra striping tabel, serta ringkasan total uang kas denda terkumpul.
![Gambar 8.9: Hasil Ekspor Rekapitulasi Laporan Transaksi ke Microsoft Excel (.xls)](assets/screenshots/16_admin_export_excel.png)
*Gambar 8.9: Hasil Ekspor Rekapitulasi Laporan Transaksi ke Microsoft Excel (.xls)*

### 8.9 Log Aktivitas Sistem (`admin/log_aktivitas.php`)
- **Fungsi**: Rekam jejak audit keamanan sistem perpustakaan yang mencatat waktu kejadian, aktor pelaku, role, jenis aksi, modul, deskripsi aktivitas, dan alamat IP.
![Gambar 8.10: Log Aktivitas Audit Trail Keamanan Sistem (admin/log_aktivitas.php)](assets/screenshots/17_admin_log_aktivitas.png)
*Gambar 8.10: Log Aktivitas Audit Trail Keamanan Sistem (admin/log_aktivitas.php)*

---

# 9. IMPLEMENTASI ALAT PENDUKUNG (HARDWARE PERIPHERAL)

Keunggulan utama operasional meja sirkulasi **Pinjam.In** adalah efisiensi kerja tinggi berkat integrasi **3 perangkat keras (hardware) fisik utama**: **Barcode & QR Scanner Desktop Taffware**, **USB RFID Card Reader 13.56 MHz**, dan **Printer Epson All-in-One Ink Tank** tanpa memerlukan instalasi driver yang rumit:

```mermaid
flowchart LR
    subgraph Input_Sirkulasi["Perangkat Input Sirkulasi Meja Kasir"]
        RFID["RFID Card Reader USB 13.56 MHz<br/>(Identifikasi Kartu Pelajar)"]
        Barcode["Barcode Scanner Desktop Taffware<br/>(Scan QR Code Eksemplar)"]
    end

    subgraph Server_Aplikasi["Sistem Web Pinjam.In"]
        App["Aplikasi Web Pinjam.In<br/>(PHP Native & PDO MySQL)"]
    end

    subgraph Output_Cetak["Perangkat Output Cetak Dokumen & Fisik"]
        Printer["Printer Epson Ink Tank L-Series<br/>(Laporan Resmi, KTA & Label QR)"]
    end

    RFID -->|Input UID Kartu Siswa| App
    Barcode -->|Input String Kode Eksemplar| App
    App -->|Format Cetak Laporan & Grid Stiker| Printer
```

### 9.1 Barcode Scanner Desktop 2D / QR Code (Taffware Omnidirectional)

![Gambar 9.1: Foto Fisik Barcode & QR Code Scanner Desktop Taffware di Meja Sirkulasi](assets/screenshots/hw_barcode_scanner.png)
*Gambar 9.1: Foto Fisik Barcode & QR Code Scanner Desktop Taffware di Meja Sirkulasi*

- **Tipe & Antarmuka**: Desktop Hands-Free 2D QR / Barcode Scanner (Taffware) dengan koneksi USB Plug and Play.
- **Mekanisme Kerja**: Bekerja dalam mode **HID (Human Interface Device) Keyboard Wedge** dengan modul sensor imager omnidirectional. Buku cukup didekatkan ke hadapan jendela optik tanpa perlu menekan tuas (*hands-free*). Alat secara otomatis membaca pola kode matriks QR eksemplar buku (misal: `BK-17-001`) dan mengirimkan untaian karakter diakhiri ASCII Enter (`\r\n`).
- **Penerapan pada Pinjam.In**:
  - Dipasang pada meja sirkulasi perpustakaan. Begitu kode eksemplar buku fisik diarahkan ke sensor scanner pada form peminjaman maupun pengembalian, sistem langsung memproses transaksi secara cepat tanpa intervensi mouse.

---

### 9.2 RFID Card Reader USB 13.56 MHz (Identifikasi Kartu Pelajar Siswa)

![Gambar 9.2: Foto Fisik USB RFID Reader 13.56 MHz dan Kartu Pelajar SMKN 1 Panyingkiran](assets/screenshots/hw_rfid_reader.png)
*Gambar 9.2: Foto Fisik USB RFID Reader 13.56 MHz dan Kartu Pelajar SMKN 1 Panyingkiran*

- **Tipe & Antarmuka**: Contactless Smart IC Card Reader frekuensi 13.56 MHz (Mifare) dengan koneksi USB.
- **Mekanisme Kerja**: Menggunakan induksi gelombang elektromagnetik jarak dekat (0–5 cm) untuk membaca nomor *Card Unique Identification* (UID) dari Kartu Pelajar SMKN 1 Panyingkiran dan mentransmisikannya langsung sebagai input identitas siswa secara instan (< 1 detik).
- **Penerapan pada Pinjam.In**:
  - Siswa cukup menempelkan (*tap*) Kartu Pelajar ke unit reader di meja kasir sirkulasi. Profil siswa, foto, serta batas peminjaman aktif langsung terbuka di layar petugas. Fitur ini juga digunakan pada menu `admin/anggota.php` untuk mem-pairing kartu fisik baru.

---

### 9.3 Printer Epson All-in-One Ink Tank (Cetak Laporan, KTA & Label QR)

![Gambar 9.3: Foto Fisik Printer Epson Ink Tank All-in-One untuk Cetak Dokumen & Label QR](assets/screenshots/hw_printer.png)
*Gambar 9.3: Foto Fisik Printer Epson Ink Tank All-in-One untuk Cetak Dokumen & Label QR*

- **Tipe & Antarmuka**: Printer Inkjet All-in-One Sistem Tabung Tinta (Epson EcoTank L-Series).
- **Mekanisme Kerja**: Mencetak dokumen warna maupun monokrom beresolusi tinggi (hingga 5760 x 1440 DPI) pada media kertas A4/F4 maupun kertas stiker melalui driver printer native sistem operasi.
- **Penerapan pada Pinjam.In**:
  - **Pencetakan Lembar Label Stiker QR Buku (`admin/buku_item.php` & `config/generate_qr.php`)**: Menghasilkan tata letak grid stiker bergaris putus-putus (*cut guide*) yang memuat judul buku, kode unik eksemplar, dan kode QR siap tempel pada punggung buku fisik di rak perpustakaan.
  - **Pencetakan Kartu Tanda Anggota (KTA) Pelajar Digital**: Menghasilkan kartu anggota perpustakaan digital lengkap dengan foto siswa dan QR Code identitas untuk dilaminasi.
  - **Pencetakan Laporan Rekapitulasi Sirkulasi Eksekutif**: Dokumen arsip resmi sekolah lengkap dengan format KOP Surat SMKN 1 Panyingkiran dan kolom tanda tangan Kepala Perpustakaan.

---

# 10. PENUTUP

### 10.1 Kesimpulan
Berdasarkan perancangan, implementasi, dan serangkaian pengujian yang telah dilakukan pada sistem **Pinjam.In (Perpustakaan Digital SMKN 1 Panyingkiran)**, dapat ditarik beberapa kesimpulan utama:
1. **Peningkatan Efisiensi Meja Sirkulasi**: Integrasi antara kartu RFID siswa dan kode QR fisik eksemplar buku terbukti memangkas waktu transaksi peminjaman dan pengembalian dari rata-rata 3 menit menjadi di bawah 5 detik, sehingga antrean siswa pada jam istirahat sekolah dapat dieliminasi secara signifikan.
2. **Presisi Manajemen Fisik Buku (Multi-Copies)**: Pemisahan arsitektur basis data antara tabel `buku` (judul/katalog) dan `buku_item` (eksemplar fisik) berhasil menyelesaikan masalah pelacakan buku yang hilang atau rusak secara individual.
3. **Pencegahan Human Error & Kebocoran Kas**: Sistem kalkulasi denda otomatis berdasarkan selisih tanggal jatuh tempo dan pencatatan status pelunasan mampu menghadirkan transparansi penuh bagi manajemen keuangan perpustakaan.
4. **Keamanan & Standar Pemrograman yang Kokoh**: Penggunaan PHP Native berbasis PDO Prepared Statements, hashing password Bcrypt, pelestarian query pagination, serta Single Sign-On Google OAuth 2.0 membuktikan bahwa aplikasi ini dibangun dengan standar rekayasa perangkat lunak modern yang aman dari serangan umum seperti SQL Injection dan XSS.

### 10.2 Saran Pengembangan
Demi kesempurnaan dan keberlanjutan sistem di masa depan, beberapa saran pengembangan yang dapat dilakukan antara lain:
1. **Integrasi WhatsApp Gateway API**: Menambahkan fitur notifikasi pesan WhatsApp otomatis ke nomor siswa atau orang tua ketika masa peminjaman buku tinggal tersisa 1 hari menjelang jatuh tempo.
2. **Koleksi Buku Digital (E-Book Reader Terintegrasi)**: Menambahkan fitur *in-app PDF viewer* dengan proteksi enkripsi DRM (*Digital Rights Management*) agar siswa dapat membaca buku pelajaran digital secara legal dari rumah.
3. **Integrasi Payment Gateway QRIS**: Memfasilitasi pembayaran denda keterlambatan non-tunai melalui QRIS statis/dinamis yang langsung terverifikasi otomatis oleh sistem perbankan.

---

# LAMPIRAN

*(Bagian ini dapat diisi dengan dokumentasi tambahan saat laporan dicetak)*

- **Lampiran 1**: Lembar Verifikasi & Checklist Uji Coba Fitur Aplikasi (Semua Fitur Berstatus PASS).
- **Lampiran 2**: Foto Alat Pendukung Perangkat Keras (Barcode Scanner Gun, RFID Card Reader USB, dan Printer).
- **Lampiran 3**: Foto Kegiatan Simulasi Peminjaman Buku di Meja Sirkulasi Perpustakaan SMKN 1 Panyingkiran.
- **Lampiran 4**: Salinan Kode Program Utama Database & Koneksi PDO (`config/koneksi.php`, `config/google_config.php`).
- **Lampiran 5**: Contoh Hasil Cetak Fisik Dokumen Laporan, Kartu Anggota Digital, dan Lembar Stiker Label QR Code Buku.
