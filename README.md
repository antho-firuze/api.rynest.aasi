# api.rynest.aasi

[![PHP Version](https://img.shields.io/badge/php-8.2-777bb4.svg)](https://www.php.net/)
[![Framework](https://img.shields.io/badge/framework-Webman%20%2F%20Workerman-00b2b2.svg)](https://www.workerman.net/webman)
[![Docker](https://img.shields.io/badge/docker-supported-2496ed.svg)](https://www.docker.com/)

`api.rynest.aasi` adalah aplikasi Web API performa tinggi yang dibangun menggunakan **Webman / Workerman** (PHP Framework berbasis *event-driven* dan *non-blocking* menggunakan komponen Swoole/Workerman) dengan **MySQL** sebagai penyimpanan data utama. Aplikasi ini dirancang tangguh untuk menangani lalu lintas tinggi dan telah dikontainerisasi penuh menggunakan **Docker** untuk kemudahan pengembangan serta penerapan (*deployment*).

---

## 🚀 Fitur Utama

* **Performa Tinggi:** Menggunakan arsitektur *memory-resident* dari Webman, memberikan kecepatan eksekusi yang setara dengan GoLang/Node.js.
* **Penyimpanan Cloud Terintegrasi:** Manajemen aset, media, dan dokumen ujian sepenuhnya menggunakan **AWS S3 Storage**.
* **Proses Sertifikasi Optimal:** Optimalisasi kode khusus pada alur backend untuk menangani komputasi intensif dan konkurensi tinggi saat proses ujian sertifikasi berlangsung.
* **Arsitektur API Modern:** Struktur kode bersih, terpisah, dan siap diintegrasikan dengan aplikasi klien (Mobile/Web).

---

## 📋 Persyaratan Sistem

Sebelum menjalankan aplikasi, pastikan mesin Anda sudah terpasang:
* **Docker:** Versi `20.x` atau yang terbaru.
* **Docker Compose:** Versi `1.29.x` atau yang terbaru.
* **Spesifikasi Minimum:** RAM 2GB & Ruang Penyimpanan Kosong 1GB.

---

## 🛠️ Panduan Instalasi & Menjalankan Aplikasi

Ikuti langkah-langkah berikut untuk menjalankan lingkungan pengembangan (*development environment*) menggunakan Docker:

### 1. Klon Repositori
```bash
git clone [https://github.com/antho-firuze/api.rynest.aasi.git](https://github.com/antho-firuze/api.rynest.aasi.git)
cd api.rynest.aasi

```

### 2. Konfigurasi Environment (`.env`)

Masuk ke direktori `webman`, salin berkas contoh konfigurasi, lalu sesuaikan nilai di dalamnya (seperti kredensial database dan AWS S3).

```bash
cd webman
cp .env.example .env
# Buka dan edit file .env sesuai kebutuhan Anda
cd ..

```

> 💡 **Tips Development:** Jika Anda membutuhkan folder `vendor` dari dalam kontainer Docker agar terbaca di text editor lokal (untuk *autocompletion* / *intellisense*), jalankan perintah ini setelah kontainer aktif:
> ```bash
> docker cp api-aasi:/webman/vendor ./webman
> 
> 
> ```
> 
> 

```

### 3. Jalankan Docker Compose
Kembali ke akar direktori proyek, lalu bangun dan jalankan kontainer di latar belakang (*detached mode*):
```bash
docker compose up -d --build

```

*(atau gunakan `docker-compose up -d --build` jika masih menggunakan Docker Compose versi lama)*

### 4. Akses Aplikasi

* **Base URL Web API:** `http://localhost:8787`
* Anda bisa menggunakan **Postman**, **Insomnia**, atau perkakas pengujian API lainnya untuk mulai menembak endpoint yang tersedia.

---

## 📬 Kontak & Informasi Lebih Lanjut

* **Maintainer:** [Ahmad Hertanto](mailto:antho.firuze@gmail.com)
* **Organisasi:** [Asosiasi Asuransi Syariah Indonesia (AASI)](https://www.aasi.or.id/)
