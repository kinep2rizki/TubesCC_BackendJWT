# Tugas Besar CC - Auth Service (JWT)

Repositori ini adalah layanan **Auth Service** yang dipisahkan dari aplikasi monolitik PETA menjadi sebuah arsitektur Microservices.
Layanan ini bertanggung jawab khusus untuk **Autentikasi**, **Penerbitan Token (JWT)**, **Manajemen Profil/Role Pengguna**, serta **Pemulihan Akun (Lupa Password & Verifikasi Email)**.

## 🏗️ Penjelasan Arsitektur Microservices

Pada arsitektur PETA saat ini, sistem dibagi menjadi beberapa layanan mandiri (*Microservices*) untuk meningkatkan skalabilitas dan pemisahan fokus:
1. **Frontend**: Antarmuka pengguna (UI/UX) yang diakses oleh klien.
2. **Auth Service (Backend JWT - Port 8001)**: Menangani semua logika yang berhubungan dengan *User*. Layanan ini menjadi gerbang utama (*Identity Provider*) yang mengelola *Login*, *Register*, penerbitan *Token JWT*, serta profil *User*.
3. **Project Service (Port 8002)**: Menangani fungsionalitas inti aplikasi, seperti Manajemen Proyek, Komunitas, dan Tugas. *Project Service* tidak menyimpan password atau email user, namun akan memvalidasi *Token JWT* dan berkomunikasi dengan *Auth Service* (melalui HTTP) untuk mendapatkan data profil user jika diperlukan (misalnya menampilkan nama anggota di sebuah komunitas).

## 🚀 Walkthrough Instalasi & Konfigurasi

1. **Clone & Install Dependencies**
   ```bash
   git clone https://github.com/kinep2rizki/TubesCC_BackendJWT.git
   cd TubesCC_BackendJWT
   composer install
   ```

2. **Setup Environment (.env)**
   - Salin file `.env.example` ke `.env`.
   - File `.env.example` sudah disediakan dengan struktur *environment* yang dibutuhkan, tanpa *credential* asli.
   - Berikut adalah penjelasan untuk variabel utama yang harus Anda isi:

   ```env
   # Setup Database PostgreSQL
   # Pastikan Anda telah membuat database bernama "peta_auth" di lokal.
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=peta_auth
   DB_USERNAME=your_db_username      # Isi dengan username postgres Anda (misal: postgres)
   DB_PASSWORD=your_db_password      # Isi dengan password postgres Anda

   # URL Frontend PETA (Penting untuk CORS & Link Verifikasi Email)
   FRONTEND_URL="http://127.0.0.1:8000" # Sesuaikan jika Frontend jalan di port lain

   # Setup SMTP Email (Wajib untuk fitur Lupa Password & Verifikasi)
   # Gunakan Gmail Anda dan buat App Password (bukan password login asli)
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=465
   MAIL_USERNAME="your_email@gmail.com" # Email Gmail asli Anda
   MAIL_PASSWORD="your_app_password"    # 16 karakter App Password dari akun Google
   MAIL_ENCRYPTION=ssl
   MAIL_FROM_ADDRESS="no-reply@peta-auth.com"
   MAIL_FROM_NAME="PETA Auth Service"
   
   # JWT Secret
   # Dapatkan dengan menjalankan php artisan jwt:secret setelah setup DB
   JWT_SECRET=your_jwt_secret_here
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
   Karena layanan utama (Frontend/Project) mungkin berjalan di Port 8000/8002, jalankan Auth Service di Port 8001:
   ```bash
   php artisan serve --port=8001
   ```

---

## 🔗 Daftar Endpoint API (Method & Response)

Semua *request* harus menyertakan Header:
`Accept: application/json`

### 1. Autentikasi Publik (Tidak butuh Token)

- **`POST /api/auth/register`**
  - **Deskripsi:** Mendaftar user baru.
  - **Body:** `name`, `email`, `password`, `password_confirmation`.
  - **Response Success (200):** Token JWT dan Data User.
  - **Response Error (400):** Validasi Error.

- **`POST /api/auth/login`**
  - **Deskripsi:** Login untuk mendapatkan token JWT.
  - **Body:** `email`, `password`.
  - **Response Success (200):**
    ```json
    {
      "access_token": "eyJ0eX...",
      "token_type": "bearer",
      "expires_in": 3600,
      "user": {
        "id": 1,
        "name": "Budi",
        "email": "budi@example.com"
      }
    }
    ```
  - **Response Error (401):** `{"error": "Unauthorized"}`

- **`POST /api/auth/password/email`**
  - **Deskripsi:** Mengirim email link Lupa Password.
  - **Body:** `email`.
  - **Response Success (200):** `{"message": "Passwort reset link sent!"}`

- **`POST /api/auth/password/reset`**
  - **Deskripsi:** Mereset password dengan token dari email.
  - **Body:** `email`, `token`, `password`, `password_confirmation`.
  - **Response Success (200):** `{"message": "Password has been reset!"}`

### 2. Autentikasi Privat (Butuh `Authorization: Bearer <token_jwt>`)

- **`GET /api/auth/me`**
  - **Deskripsi:** Mendapatkan profil pengguna yang sedang login.
  - **Response Success (200):** Data User.

- **`POST /api/auth/refresh`**
  - **Deskripsi:** Memperbarui JWT yang sudah hampir kadaluarsa (TTL).
  - **Response Success (200):** Token JWT Baru.

- **`POST /api/auth/logout`**
  - **Deskripsi:** Keluar dan membatalkan/mengahancurkan token.
  - **Response Success (200):** `{"message": "Successfully logged out"}`

- **`POST /api/auth/email/resend`**
  - **Deskripsi:** Mengirim ulang email verifikasi.
  - **Response Success (200):** `{"message": "Verification link sent"}`

- **`POST /api/auth/profile`**
  - **Deskripsi:** Update profil nama dan upload foto profil.
  - **Body (form-data):** `name`, `avatar` (file gambar).
  - **Response Success (200):** `{"message": "Profile updated successfully", "user": {...}}`

- **`PUT /api/auth/profile/password`**
  - **Deskripsi:** Update password saat user sedang login.
  - **Body:** `current_password`, `new_password`, `new_password_confirmation`.
  - **Response Success (200):** `{"message": "Password updated successfully"}`

### 3. Komunikasi Antar Microservice (Internal)

- **`POST /api/auth/users/batch`**
  - **Deskripsi:** Endpoint khusus untuk *Project Service* mengambil data lengkap *users* berdasarkan kumpulan ID (Data Stitching).
  - **Body:** `ids: [1,2,3]`
  - **Response Success (200):**
    ```json
    [
      {"id": 1, "name": "Budi", "avatar": "avatar.jpg"},
      {"id": 2, "name": "Andi", "avatar": null}
    ]
    ```

- **`POST /api/auth/users/find-or-create`** / **`POST /api/auth/users/search`**
  - **Deskripsi:** Memungkinkan service lain mencari user atau mendaftarkan user *placeholder* jika diperlukan dalam proses bisnis spesifik.

---

## 🗂️ Postman Collection / API Docs

Untuk mempermudah pengujian seluruh Endpoint API Auth Service secara instan, Anda bisa mengunduh **Postman Collection**.

- **Download File Collection JSON:** Anda dapat menyimpan dan mengekspor request dari Postman ke sebuah file `.json` lalu menyimpannya di repositori ini (misal di direktori `docs/` atau di root repositori).
- **Cara Import ke Postman:**
  1. Buka aplikasi Postman.
  2. Klik tombol **"Import"** di pojok kiri atas.
  3. Pilih file JSON Postman Collection (contoh: `PETA_Auth_Service.postman_collection.json`).
  4. Atur Environment Variable di Postman:
     - `base_url`: `http://127.0.0.1:8001/api`
     - `token`: (Isi dengan token dari *Response Login* agar *Endpoint* privat bisa diakses).

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
