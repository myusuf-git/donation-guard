# Donation Guard (Bahasa Indonesia)

Plugin WordPress untuk mengecek donasi yang masih **pending** ke payment gateway (Midtrans, Xendit, Tripay), lalu memperbaiki statusnya. Mendukung **WooCommerce, GiveWP, dan DonasiAja**.

## Kenapa perlu?

Donasi pending menumpuk, apalagi saat kampanye ramai. Sebagian besar sebenarnya **kedaluwarsa** (tidak jadi bayar), sebagian **kode bayarnya gagal dibuat** karena gangguan gateway, dan sesekali ada yang **sudah lunas tapi callback-nya tidak masuk**. Tanpa dicek, angka kampanye, laporan, dan data konversi iklan jadi tebakan.

Donation Guard mengecek semuanya tiap jam, dan juga memeriksa kesehatan URL callback.

**DonasiAja:** donasi lunas dikonfirmasi lewat fungsi DonasiAja sendiri, jadi donatur tetap dapat kuitansi WA/email. Donasi kedaluwarsa hanya dilaporkan, tidak diubah (DonasiAja tidak punya status gagal).

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
