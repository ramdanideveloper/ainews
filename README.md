# AI News Assistant

Plugin WordPress dengan Smart News Form untuk mendeteksi jenis berita, memandu wartawan menghimpun fakta melalui form adaptif, membuat draft, memeriksa 5W+1H, menyiapkan SEO dan caption sosial, lalu menyimpan hasil untuk approval manusia.

## Instalasi

1. Salin folder ini sebagai `wp-content/plugins/ai-news-assistant`.
2. Aktifkan **AI News Assistant** dari menu Plugins WordPress.
3. Buka **AI News Assistant → Settings**.
4. Isi Backend URL pada **Account & Backend**. Guest dapat mencoba 10 kali; register mendapat welcome credit Rp5.000 dan Connected Site token dibuat otomatis.

Persyaratan: WordPress modern, PHP 7.4 atau lebih baru.

## Cara mencoba

1. Buka **AI News Assistant → Newsroom**.
2. Isi judul sementara lalu klik **Deteksi Jenis Berita**.
3. Tinjau hasil klasifikasi atau ganti jenis berita secara manual.
4. Isi form fakta adaptif dan gunakan **Cek Data Kurang**.
5. Pilih gaya dan panjang, lalu klik **Generate Draft**.
6. Tinjau naskah, checklist, SEO, dan caption.
7. Klik **Save as Draft**. Plugin hanya membuat `draft` atau `pending`, tidak pernah publish otomatis.
8. Buka tautan **Edit Post** dan periksa meta box **AI News Assistant Review**.

## Data yang disimpan

Pengaturan disimpan pada option `aina_settings`. Hasil editorial disimpan dalam post meta `_aina_review_status`, `_aina_fact_checklist`, `_aina_seo`, `_aina_social_captions`, dan `_aina_generation_notes`.

Plugin tidak menyimpan API key Gemini/OpenAI. Semua request dikirim ke backend SaaS menggunakan guest install ID atau Connected Site token; provider key hanya disimpan terenkripsi pada backend.

## Integrasi Rank Math SEO

Jika Rank Math aktif, buka **AI News Assistant → Settings** dan aktifkan **Sync SEO to Rank Math**. Saat draft baru disimpan, SEO title, meta description, dan focus keyword disalin ke metadata Rank Math. Metadata Rank Math yang sudah terisi tidak ditimpa kecuali opsi overwrite diaktifkan. Integrasi tidak memproses posting lama.

## Backend SaaS

Backend Laravel berada di folder `backend/` dan harus di-deploy terpisah pada subdomain API dengan document root ke `backend/public`. Jalankan migration/seeder sesuai `backend/README.md`, tambahkan provider Gemini/OpenAI di Filament, lalu isi URL tersebut pada **AI News Assistant → Account & Backend**. Plugin versi 2 tidak lagi menyimpan API key provider.
