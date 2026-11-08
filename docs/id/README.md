# Donation Guard (Bahasa Indonesia)

Plugin WordPress untuk mengecek donasi yang masih **pending** ke payment gateway (Midtrans, Xendit, Tripay), lalu memperbaiki statusnya.

## Kenapa perlu?

Notifikasi (webhook) dari gateway kadang gagal masuk. Donatur sudah bayar, tapi di web masih pending. Akibatnya donatur tidak dapat kuitansi, total kampanye salah, dan tim keuangan harus mencocokkan data secara manual.

## Cara pakai singkat

1. Pasang plugin, buka **Tools → Donation Guard → Settings**.
2. Aktifkan gateway dan isi API key (lebih aman lewat `wp-config.php`).
3. Klik **Run dry-run**. Mode ini hanya melaporkan, tidak mengubah apa pun.
4. Kalau laporannya sudah benar, matikan **Dry-run**.

## Aturan keamanan

- Hanya donasi yang **lunas dengan nominal sama** yang ditandai lunas.
- Nominal beda, refund, atau tidak ditemukan: **tidak diubah**, ditandai untuk dicek manusia.
- Semua keputusan tercatat di log dan bisa diunduh sebagai CSV.

Dokumentasi lengkap (bahasa Inggris): [README](../../README.md).
