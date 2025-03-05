# api.rynest.aasi

**api.rynest.aasi** adalah sebuah aplikasi Web API yang dibangun menggunakan **Webman/Workerman** (framework PHP) dan menggunakan **MySQL** sebagai database. Aplikasi ini di-containerisasi menggunakan **Docker** untuk memudahkan deployment dan pengembangan.

---

## Fitur Utama

- **Fitur 1**: Dibangun menggunakan teknologi PHP terbaru yang setara dengan GoLang.
- **Fitur 2**: Penyimpanan asset dan media sudah menggunakan AWS S3.
- **Fitur 3**: Optimalisasi coding di semua bagian terutama pada proses ujian sertifikasi.
- Dan yang lainnya...

---

## Persyaratan Sistem

Sebelum menjalankan aplikasi ini, pastikan sistem Anda memenuhi persyaratan berikut:

- **Docker**: Versi 20.x atau lebih baru.
- **Docker Compose**: Versi 1.29.x atau lebih baru.
- **RAM**: Minimal 2GB.
- **Ruang Penyimpanan**: Minimal 1GB.

---

## Instalasi dengan Docker

Berikut adalah langkah-langkah untuk menjalankan aplikasi ini menggunakan Docker:

1. **Clone Repository**:

   ```
   git clone https://github.com/antho-firuze/api.rynest.aasi.git
   cd api.rynest.aasi
   ```

2. **Setup Environment**:

- Buat file `.env` di folder `./webman`
- Rename file `.env.example` manjadi `.env`. Dan isi dengan konfigurasi yang sesuai.
- (optional) hanya untuk keperluan development. Jika ingin meng-copy vendor yang ada pada container ke local storage, jalankan perintah ini.
  `# docker cp api-aasi:/webman/vendor ./webman`

3. **Jalankan Docker Compose**:

   ```
   # docker-compose up -d --build

   atau

   # docker compose up -d --build
   ```

4. **Akses Aplikasi**:

- Web API akan berjalan di `http://localhost:8787`
- Gunakan Postman atau sejenisnya untuk mengakses endpoint API.

#

## Kontak

Jika Anda memiliki pertanyaan atau masukan, silakan hubungi:

- **Nama**: Ahmad Hertanto
- **Email**: [<antho.firuze@gmail.com>]
- **Website**: [<https://www.aasi.or.id/>]
