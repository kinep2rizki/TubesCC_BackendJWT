# Tugas Besar CC - Auth Service (JWT)

Repositori ini adalah layanan **Auth Service** yang dipisahkan dari aplikasi monolitik PETA menjadi sebuah arsitektur Microservices.
Layanan ini bertanggung jawab khusus untuk **Autentikasi**, **Penerbitan Token (JWT)**, **Manajemen Profil/Role Pengguna**, serta **Pemulihan Akun (Lupa Password & Verifikasi Email)**.

## 🚀 Walkthrough Instalasi & Konfigurasi

1. **Clone & Install Dependencies**
   ```bash
   git clone https://github.com/kinep2rizki/TubesCC_BackendJWT.git
   cd TubesCC_BackendJWT
   composer install
   ```

2. **Setup Environment (.env)**
   - Salin file `.env.example` ke `.env`.
   - Sesuaikan konfigurasi utama berikut:
   ```env
   # Setup Database
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=peta_auth
   DB_USERNAME=postgres
   DB_PASSWORD=password_anda

   # URL Frontend PETA (Penting untuk CORS & Link Email)
   FRONTEND_URL="http://127.0.0.1:8000"

   # Durasi Token JWT (dalam menit, default: 60)
   JWT_TTL=60

   # Setup SMTP Email (Wajib untuk fitur Lupa Password & Verifikasi)
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=465
   MAIL_USERNAME="emailanda@gmail.com"
   MAIL_PASSWORD="password_app_gmail"
   MAIL_ENCRYPTION=ssl
   MAIL_FROM_ADDRESS="no-reply@peta-auth.com"
   MAIL_FROM_NAME="PETA Auth Service"
   ```

   **💡 Cara Mendapatkan Konfigurasi SMTP Gmail (MAIL_PASSWORD):**
   Demi keamanan, Google tidak mengizinkan penggunaan *password* asli Gmail Anda di aplikasi pihak ketiga. Anda harus menggunakan **App Password (Sandi Aplikasi)**:
   1. Login ke akun Gmail/Google Anda di browser.
   2. Buka **Kelola Akun Google Anda** -> Menu **Keamanan** (Security).
   3. Pastikan **Verifikasi 2 Langkah (2-Step Verification)** sudah dalam keadaan **Aktif**.
   4. Ketik "Sandi Aplikasi" atau "App Passwords" di bilah pencarian bagian atas pengaturan Keamanan Google.
   5. Masukkan nama aplikasi (misal: "PETA Auth Laravel"), lalu klik **Buat** (Create).
   6. Google akan memberikan **16 karakter password acak** (berlatar kuning).
   7. Salin 16 karakter tersebut tanpa spasi, dan masukkan sebagai nilai `MAIL_PASSWORD` di file `.env`.
   8. Isi `MAIL_USERNAME` dengan alamat Gmail asli Anda.

3. **Generate Keys & Rahasia JWT**
   ```bash
   php artisan key:generate
   php artisan jwt:secret
   ```

4. **Jalankan Migrasi & Seeder**
   Pastikan Anda sudah membuat database `peta_auth` di PostgreSQL, lalu jalankan:
   ```bash
   php artisan migrate:fresh --seed
   ```
   *(Catatan: Seeder akan membuat Role default 'Super Admin' dan 'User')*.

5. **Akses Storage (Avatar)**
   Agar foto profil/avatar yang diunggah dapat diakses oleh publik (Frontend), jalankan:
   ```bash
   php artisan storage:link
   ```

6. **Jalankan Layanan (Port 8001)**
   Karena layanan utama berjalan di Port 8000, jalankan Auth Service di Port 8001:
   ```bash
   php artisan serve --port=8001
   ```

---

## 🔗 Endpoint API Tersedia

Pastikan Header `Accept: application/json` diset untuk setiap request.

### 1. Autentikasi Publik (Tidak butuh Token)
- `POST /api/auth/register` : Mendaftar user baru. (Body: `name`, `email`, `password`, `password_confirmation`).
- `POST /api/auth/login` : Login untuk mendapatkan token JWT. (Body: `email`, `password`).
- `POST /api/auth/password/email` : Mengirim email link Lupa Password. (Body: `email`).
- `POST /api/auth/password/reset` : Mereset password dengan token dari email. (Body: `email`, `token`, `password`, `password_confirmation`).

### 2. Autentikasi Privat (Butuh `Authorization: Bearer <token_jwt>`)
- `GET /api/auth/me` : Mendapatkan profil pengguna (Introspeksi Token).
- `POST /api/auth/refresh` : Memperbarui JWT yang sudah hampir kadaluarsa (TTL).
- `POST /api/auth/logout` : Keluar dan membatalkan/mengahancurkan token.
- `POST /api/auth/email/resend` : Mengirim ulang email verifikasi.
- `POST /api/auth/profile` : Update profil nama dan upload foto profil. (Gunakan *form-data*: `name`, file `avatar`).
- `PUT /api/auth/profile/password` : Update password saat user login. (Body: `current_password`, `new_password`, `new_password_confirmation`).

### 3. Komunikasi Antar Microservice (Internal)
- `POST /api/auth/users/batch` : Endpoint khusus untuk Project Service mengambil data lengkap *users* berdasarkan kumpulan ID (Data Stitching). (Body: `ids: [1,2,3]`).

---

## ⚠️ Solusi Masalah Cross-Service (Microservices JOIN)

Karena tabel `users` tidak lagi berada di *Project Service*, **Project Service** tidak bisa lagi menggunakan *Eloquent Relationship* biasa.

### Panduan Implementasi Komunikasi Antar-Layanan
Untuk menampilkan nama User di UI, kita harus menggabungkan data via HTTP Request.

#### Opsi 1: Penggabungan Data di Tingkat Frontend (Direkomendasikan)
1. Frontend memanggil `http://localhost:8000/api/communities/1/members`. Responnya hanya daftar ID: `[{id: 1, user_id: 5, role: 'Admin'}]`
2. Frontend memanggil `http://localhost:8001/api/auth/users/batch` dengan Body `{"ids": [5]}`. Respon dari Auth Service berisi detail nama/avatar.
3. Frontend menggabungkan data lalu menampilkannya.

#### Opsi 2: Sinkronisasi via Backend (Guzzle HTTP di Project Service)
```php
use Illuminate\Support\Facades\Http;

public function getMembers($community_id) {
    $members = CommunityMember::where('community_id', $community_id)->get();
    $userIds = $members->pluck('user_id')->toArray();
    
    // Minta data user ke Auth Service (Port 8001)
    $response = Http::get('http://127.0.0.1:8001/api/auth/users/batch', ['ids' => $userIds]);
    $usersData = collect($response->json());
    
    // Gabungkan data
    $members->transform(function ($member) use ($usersData) {
        $member->user_detail = $usersData->firstWhere('id', $member->user_id);
        return $member;
    });

    return response()->json($members);
}
```

> [!WARNING]
> Sangat penting bagi Frontend untuk selalu mengirimkan *Token JWT* sebagai **Bearer Token** pada Header Authorization di **setiap request** baik ke Auth Service maupun Project Service.

---

## 🎨 Penyesuaian Desain Email (Custom PETA Theme)
Tema default email Laravel telah dimodifikasi secara lokal untuk mengikuti estetika *web app* PETA (Menggunakan *Dark Background* `#131315`, warna *Primary Button* `#adc6ff`, dan tulisan "PETA"). Tema CSS dapat diedit di `resources/views/vendor/mail/html/themes/default.css`.
