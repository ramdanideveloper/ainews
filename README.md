# AI News Assistant

Plugin WordPress dengan Smart News Form untuk mendeteksi jenis berita, memandu wartawan menghimpun fakta melalui form adaptif, membuat draft, memeriksa 5W+1H, menyiapkan SEO dan caption sosial, lalu menyimpan hasil untuk approval manusia.

## Instalasi

1. Salin folder ini sebagai `wp-content/plugins/ai-news-assistant`.
2. Aktifkan **AI News Assistant** dari menu Plugins WordPress.
3. Buka **AI News Assistant → Settings**.
4. Opsional: isi API key, endpoint chat-completions yang OpenAI-compatible, dan model. Tanpa API key, plugin otomatis memakai Demo Mode.

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

API key hanya digunakan server-side melalui `wp_remote_post`; key tidak dilokalkan ke JavaScript dan field settings tidak menampilkan kembali nilainya.
