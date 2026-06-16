# Tugas Besar CC - Auth Service (JWT)

Repositori ini adalah layanan **Auth Service** yang dipisahkan dari aplikasi monolitik PETA menjadi sebuah arsitektur Microservices.
Layanan ini bertanggung jawab khusus untuk **Autentikasi**, **Penerbitan Token (JWT)**, dan **Manajemen Profil/Role Pengguna**.

## 🚀 Walkthrough Instalasi & Konfigurasi

1. **Clone & Install Dependencies**
   ```bash
   git clone https://github.com/kinep2rizki/TubesCC_BackendJWT.git
   cd TubesCC_BackendJWT
   composer install
   ```

2. **Setup Environment**
   - Salin file `.env.example` ke `.env`.
   - Pastikan konfigurasi Database menunjuk ke `peta_auth`.
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=peta_auth
   DB_USERNAME=postgres
   DB_PASSWORD=
   ```

3. **Generate Keys & Rahasia JWT**
   ```bash
   php artisan key:generate
   php artisan jwt:secret
   ```

4. **Jalankan Migrasi**
   Pastikan Anda sudah membuat database `peta_auth` di PostgreSQL, lalu jalankan:
   ```bash
   php artisan migrate
   ```

5. **Jalankan Layanan (Port 8001)**
   Karena layanan utama berjalan di Port 8000, jalankan Auth Service di Port 8001:
   ```bash
   php artisan serve --port=8001
   ```

---

## 🔗 Endpoint API Tersedia

Semua endpoint dilindungi oleh JWT (kecuali Login & Register). Aksesnya harus menyertakan header:
`Authorization: Bearer <token_jwt_anda>`

- `POST /api/auth/register` : Mendaftar user baru. (Beban kerja `name`, `email`, `password`, `password_confirmation`).
- `POST /api/auth/login` : Login untuk mendapatkan token JWT. (Beban kerja `email`, `password`).
- `GET /api/auth/me` : Mendapatkan profil pengguna yang sedang login beserta Role-nya.
- `POST /api/auth/refresh` : Memperbarui JWT yang sudah kadaluarsa.
- `POST /api/auth/logout` : Keluar dan membatalkan token.

---

## ⚠️ Solusi Masalah Cross-Service (Microservices JOIN)

Karena tabel `users` tidak lagi berada di *Project Service*, **Project Service** tidak bisa lagi melakukan pemanggilan seperti ini:
```php
// SALAH (Di Project Service)
$member = CommunityMember::with('user')->get(); 
```

### Panduan Implementasi Komunikasi Antar-Layanan

Untuk menampilkan nama User di UI, kita harus menggabungkan data via HTTP Request (API Gateway / Guzzle). Berikut solusinya:

#### Di Project Service (Yang Punya Komunitas/Event)
Setiap kali menyimpan relasi, pastikan kolom yang disimpan hanya ID angka murninya (`user_id`).

#### Opsi 1: Penggabungan (Stitching) Data di Tingkat Frontend / API Gateway (Direkomendasikan)
1. Frontend memanggil `http://localhost:8002/api/communities/1/members`. Responnya hanya daftar ID: `[{id: 1, user_id: 5, role: 'Admin'}, ...]`
2. Frontend kemudian memanggil `http://localhost:8001/api/auth/users?ids=5,8,12`. Respon dari Auth Service berisi detail nama/email.
3. Frontend menggabungkan data secara asinkron menggunakan Javascript, lalu menampilkannya.

#### Opsi 2: Sinkronisasi via Backend (Guzzle HTTP di Project Service)
Jika Project Service dipaksa untuk mengembalikan data utuh, Controller di Project Service harus mengirim Request ke Auth Service:
```php
use Illuminate\Support\Facades\Http;

public function getMembers($community_id) {
    $members = CommunityMember::where('community_id', $community_id)->get();
    $userIds = $members->pluck('user_id')->toArray();
    
    // Minta data user ke Auth Service (Port 8001)
    $response = Http::withHeaders([
        'Authorization' => request()->header('Authorization') // teruskan token JWT
    ])->get('http://127.0.0.1:8001/api/auth/users/batch', [
        'ids' => $userIds
    ]);
    
    $usersData = collect($response->json());
    
    // Stitch (Gabungkan)
    $members->transform(function ($member) use ($usersData) {
        $member->user_detail = $usersData->firstWhere('id', $member->user_id);
        return $member;
    });

    return response()->json($members);
}
```

> [!WARNING]
> Sangat penting bagi Frontend untuk selalu menyimpan Token JWT (baik di *LocalStorage* atau *Cookie HTTP-Only*) setiap kali menerima _response_ login dari Auth Service, dan menempelkan token tersebut di **setiap request** ke _Project Service_ maupun _Auth Service_.


## 🤖 AI Context: Catatan Penting untuk Pengembangan Selanjutnya

Bagi AI atau developer yang membaca file ini di masa mendatang, berikut adalah konteks penuh terkait pemisahan sistem ini menjadi Microservices:

### 📌 Perubahan yang Telah Terjadi
1. **Pemisahan Repository**: Layanan Autentikasi kini dipisah dari repository utama (Tubes) ke repository ini (TubesCC_BackendJWT).
2. **Database Terpisah**: Repository ini sekarang menggunakan database mandiri bernama peta_auth. Tabel users, personal_access_tokens, dan tabel permission dari spatie/laravel-permission telah dipindahkan ke sini.
3. **Model User yang Bersih**: Model User di repository ini **tidak lagi memiliki relasi** ke entitas proyek seperti Community, Event, Attendance, atau Certificate. Relasi tersebut tetap berada di Project Service (repository utama).
4. **Implementasi JWT**: Autentikasi tidak lagi bergantung pada Session atau Sanctum, namun telah sepenuhnya bermigrasi menggunakan `tymon/jwt-auth`.
5. **API Routes**: Endpoint login, register, logout, refresh, dan me berada di `routes/api.php` dan dilindungi oleh guard `api` dengan driver `jwt`.

### 🚧 Hal-Hal yang Belum Diimplementasikan (TODO)
1. **Modifikasi Project Service (Repository Utama)**:
   - **Menghapus Autentikasi Lokal**: AuthController, Model User, dan migration users di repository utama perlu diubah/dihapus agar tidak bertabrakan.
   - **Validasi Token (Middleware)**: Project Service perlu cara untuk memvalidasi token JWT yang dibawa oleh request dari Frontend. (Perlu membuat Custom Middleware yang memverifikasi JWT _Signature_ atau melakukan introspeksi token ke Auth Service).
2. **Sistem Sinkronisasi/Stitching Data**:
   - Relasi data yang terputus (misalnya: menampilkan nama user pada daftar anggota komunitas) **belum** ditangani di Project Service.
   - Harus diimplementasikan menggunakan pemanggilan HTTP (contoh: Guzzle) ke Auth Service (/api/auth/users/batch) seperti yang dijelaskan pada opsi di atas.
3. **Event Broadcasting (Reverb)**:
   - Jika ada notifikasi realtime (misal: pendaftaran user baru), pastikan konfigurasi Laravel Reverb dipecah atau diatur agar dapat berkomunikasi antar _service_ jika diperlukan.
4. **Manajemen Role Spesifik (Spatie)**:
   - Saat ini Spatie Role/Permission terpasang di Auth Service. Namun otorisasi aksi di Project Service (misal: _Apakah user ini bisa mengedit Event?_) harus diselesaikan, mungkin dengan meneruskan informasi Role dalam Payload JWT (Custom Claims).

