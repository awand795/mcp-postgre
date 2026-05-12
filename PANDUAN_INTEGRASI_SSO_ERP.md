# PANDUAN INTEGRASI SSO (SINGLE SIGN-ON) - HANDSHAKE METHOD
**Sistem: Chatbot MCP (Agentic System)**

Panduan ini ditujukan bagi tim backend ERP untuk mengimplementasikan login otomatis ke aplikasi Chatbot di dalam Iframe.

---

## 1. Konsep Utama
Integrasi ini menggunakan metode **Handshake** (Server-ke-Server) untuk menjamin keamanan. Data user dikirim di belakang layar, dan browser hanya menerima token sekali pakai yang berlaku singkat.

1. **Backend ERP** memanggil API Chatbot untuk registrasi/identifikasi user.
2. **Chatbot** membalas dengan **One-Time-Token (OTT)**.
3. **Frontend ERP** memuat Iframe Chatbot dengan URL yang menyertakan token tersebut.

---

## 2. Kredensial API
Silakan minta kredensial berikut kepada Administrator Chatbot:
- **SSO API Key**: `[MASUKKAN_KUNCI_RAHASIA_DI_SINI]` (Simpan di `.env` ERP Anda)
- **Chatbot Base URL**: `https://chatbot-anda.com`

---

## 3. Langkah Implementasi (Backend ERP)

### Endpoint Chatbot
`POST https://chatbot-anda.com/api/sso/generate-token`

### Header Request
| Key | Value | Keterangan |
|:--- |:--- |:--- |
| `X-SSO-KEY` | `[SSO_API_KEY]` | Kunci rahasia untuk otentikasi server |
| `Accept` | `application/json` | |

### Body Request (JSON)
| Field | Type | Required | Keterangan |
|:--- |:--- |:--- |:--- |
| `email` | `string` | **Ya** | Email unik user (sebagai ID utama) |
| `name` | `string` | **Ya** | Nama lengkap user (untuk tampilan) |
| `erp_user_id`| `string` | Tidak | ID internal user di sistem ERP |

### Contoh Request (PHP/Laravel)
```php
$response = Http::withHeaders([
    'X-SSO-KEY' => env('CHATBOT_SSO_KEY'),
    'Accept' => 'application/json',
])->post('https://chatbot-anda.com/api/sso/generate-token', [
    'email' => $user->email,
    'name' => $user->name,
]);

if ($response->successful()) {
    $ottToken = $response->json()['token'];
}
```

---

## 4. Langkah Implementasi (Frontend ERP)

Gunakan token yang didapat dari API untuk memuat Iframe. Token ini hanya berlaku **60 detik** dan akan hangus setelah satu kali pakai.

### Struktur URL Iframe
`https://chatbot-anda.com/auth/sso?token=[OTT_TOKEN]`

### Contoh Kode HTML
```html
<iframe 
    src="https://chatbot-anda.com/auth/sso?token=ott_8291ndk82129..." 
    style="width: 100%; height: 700px; border: none;"
    allow="clipboard-read; clipboard-write"
></iframe>
```

---

## 5. Fitur Registrasi Otomatis
Tim ERP tidak perlu khawatir jika user belum terdaftar di Chatbot. Saat API `generate-token` dipanggil:
- Jika email **belum ada**: Chatbot akan otomatis membuat akun baru dengan role default.
- Jika email **sudah ada**: Chatbot akan memperbarui nama user (jika ada perubahan) dan memberikan akses masuk.
- Admin Chatbot tetap memegang kendali penuh melalui **Panel Admin** untuk mengatur hak akses database atau limit token bagi user tersebut setelah terdaftar.

---

## 6. Catatan Penting
- **X-Frame-Options**: Aplikasi Chatbot telah dikonfigurasi untuk mengizinkan tampilan di dalam Iframe.
- **Cookies**: Gunakan browser modern. Chatbot menggunakan setting `SameSite=None` untuk menjamin sesi login tetap aktif di dalam Iframe.
- **Security**: Jangan pernah membocorkan `SSO API Key` ke sisi Frontend (Javascript client-side). Request token harus selalu dilakukan dari server ERP.
