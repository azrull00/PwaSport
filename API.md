# SportApp API Documentation

## 🚀 Gambaran Umum
SportApp API menyediakan endpoint untuk aplikasi olahraga dengan sistem role-based untuk **Player** dan **Host**. API menggunakan Laravel Sanctum untuk autentikasi dan mendukung operasi CRUD lengkap untuk event, community, venue, dan matchmaking.

---

## 🔐 Autentikasi

### Base URL
```
http://localhost/api
```

### Headers untuk Request Terautentikasi
```json
{
    "Authorization": "Bearer {token}",
    "Content-Type": "application/json",
    "Accept": "application/json"
}
```

---

## 📋 Endpoint Berdasarkan Role User

### 🔓 **Public Endpoints (Tanpa Autentikasi)**

| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `POST` | `/auth/register` | Daftar akun baru |
| `POST` | `/auth/login` | Login user |
| `GET` | `/sports` | List semua olahraga |
| `GET` | `/users/{user}/profile-picture` | Akses foto profil |
| `GET` | `/communities/{community}/icon` | Akses icon komunitas |
| `GET` | `/events/{event}/thumbnail` | Akses thumbnail event |

---

## 🏃‍♂️ **PLAYER ENDPOINTS**

### **📱 Auth & Profil**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `POST` | `/auth/logout` | Logout |
| `GET` | `/auth/profile` | Data profil sendiri |
| `PUT` | `/auth/profile` | Update profil sendiri |

### **👥 User Management**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/users` | Cari player lain (filter: sport, city, skill) |
| `GET` | `/users/{user}` | Detail profil player |
| `GET` | `/users/{user}/sport-ratings` | Rating olahraga player |
| `GET` | `/users/{user}/match-history` | Riwayat pertandingan |
| `POST` | `/users/{user}/block` | Block player |
| `DELETE` | `/users/{user}/unblock` | Unblock player |
| `GET` | `/users/blocked` | List player yang diblock |

### **📸 Profile Management**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/users/my-qr-code` | QR code untuk check-in |
| `POST` | `/users/regenerate-qr` | Generate ulang QR code |
| `POST` | `/users/upload-profile-picture` | Upload foto profil |
| `DELETE` | `/users/delete-profile-picture` | Hapus foto profil |

### **🏆 Events (Player Perspective)**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/events` | Cari event (filter: sport, skill, location, date) |
| `GET` | `/events/{event}` | Detail event |
| `POST` | `/events/{event}/join` | Daftar ke event |
| `DELETE` | `/events/{event}/leave` | Keluar dari event |
| `GET` | `/events/{event}/participants` | List peserta |

### **👥 Communities (Player)**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/communities` | Cari komunitas |
| `GET` | `/communities/{community}` | Detail komunitas |
| `POST` | `/communities/{community}/rate` | Beri rating komunitas |
| `GET` | `/communities/{community}/ratings` | Lihat rating komunitas |
| `GET` | `/communities/{community}/events` | Event dalam komunitas |

### **🎯 Matchmaking & Matches**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/matchmaking/{eventId}` | Lihat status matchmaking (jika peserta) |
| `GET` | `/matches` | Riwayat pertandingan |
| `GET` | `/matches/stats/{user}` | Statistik pertandingan |
| `GET` | `/matches/{match}` | Detail pertandingan |

### **⭐ Rating & Review**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/ratings` | Rating yang diberikan |
| `POST` | `/ratings` | Beri rating player |
| `GET` | `/ratings/stats/{user}` | Statistik rating |
| `POST` | `/ratings/{rating}/report` | Laporkan rating palsu |

### **💳 Credit Score**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/credit-score` | Riwayat credit score |
| `GET` | `/credit-score/summary` | Summary credit score |
| `GET` | `/credit-score/restrictions` | Pembatasan akun |

### **📍 Location Services**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/location/preferred-areas` | Area favorit |
| `POST` | `/location/preferred-areas` | Tambah area favorit |
| `PUT` | `/location/preferred-areas/{area}` | Edit area favorit |
| `DELETE` | `/location/preferred-areas/{area}` | Hapus area favorit |
| `POST` | `/location/search/events` | Cari event berdasarkan lokasi |
| `POST` | `/location/search/communities` | Cari komunitas berdasarkan lokasi |
| `POST` | `/location/calculate-distance` | Hitung jarak |
| `GET` | `/location/preferred-areas/events` | Event di area favorit |

### **🏟️ Venues (Player View)**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/venues` | Cari venue (filter: city, sport, available) |
| `GET` | `/venues/{id}` | Detail venue |
| `POST` | `/venues/{id}/check-availability` | Cek ketersediaan |
| `GET` | `/venues/{id}/schedule` | Jadwal venue |

### **🚨 Reports**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `POST` | `/reports` | Laporkan user/event |
| `GET` | `/reports/my-reports` | Laporan yang dibuat |
| `GET` | `/reports/against-me` | Laporan terhadap saya |
| `GET` | `/reports/stats` | Statistik laporan |

### **🔔 Notifications**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/notifications` | List notifikasi |
| `GET` | `/notifications/stats` | Statistik notifikasi |
| `POST` | `/notifications/{notification}/read` | Tandai sudah dibaca |
| `POST` | `/notifications/mark-all-read` | Tandai semua dibaca |
| `GET` | `/notifications/preferences` | Preferensi notifikasi |
| `PUT` | `/notifications/preferences` | Update preferensi |

---

## 🎯 **HOST ENDPOINTS**

### **🏆 Event Management (Host)**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `POST` | `/events` | Buat event baru |
| `PUT` | `/events/{event}` | Edit event |
| `DELETE` | `/events/{event}` | Hapus event |
| `PUT` | `/events/{event}/participants/{participant}/confirm` | Konfirmasi peserta |
| `PUT` | `/events/{event}/participants/{participant}/reject` | Tolak peserta |
| `POST` | `/events/{event}/check-in/{participant}` | Check-in manual |
| `POST` | `/events/{event}/check-in-qr` | Check-in via QR |
| `POST` | `/events/{event}/bulk-check-in` | Check-in massal |
| `GET` | `/events/{event}/check-in-stats` | Statistik check-in |
| `POST` | `/events/{event}/upload-thumbnail` | Upload thumbnail |
| `DELETE` | `/events/{event}/delete-thumbnail` | Hapus thumbnail |

### **🎲 Matchmaking (Host)**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/matchmaking/{eventId}` | Status matchmaking |
| `POST` | `/matchmaking/{eventId}/generate` | Generate matchmaking |
| `POST` | `/matchmaking/{eventId}/save` | Simpan hasil matchmaking |

### **👥 Community Management (Host)**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `POST` | `/communities` | Buat komunitas |
| `PUT` | `/communities/{community}` | Edit komunitas |
| `DELETE` | `/communities/{community}` | Hapus komunitas |
| `POST` | `/communities/{community}/upload-icon` | Upload icon |
| `DELETE` | `/communities/{community}/delete-icon` | Hapus icon |

### **🏟️ Venue Management (Host)**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `POST` | `/venues` | Tambah venue |
| `PUT` | `/venues/{id}` | Edit venue |
| `DELETE` | `/venues/{id}` | Hapus venue |

### **📊 Match History Management (Host)**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `POST` | `/matches` | Record hasil pertandingan |
| `PUT` | `/matches/{match}` | Edit hasil |
| `DELETE` | `/matches/{match}` | Hapus record |

### **💳 Credit Score Management (Host)**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `POST` | `/credit-score/cancel-event` | Proses penalti cancel |
| `POST` | `/credit-score/no-show` | Proses penalti tidak hadir |
| `POST` | `/credit-score/completion-bonus` | Bonus completion |

---

## 👑 **ADMIN ENDPOINTS**

### **📊 Dashboard & Analytics**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/admin/dashboard` | Dashboard admin |
| `GET` | `/admin/analytics` | Analytics sistem |

### **👥 User Management**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/admin/users` | Kelola semua user |
| `GET` | `/admin/users/{userId}` | Detail user |
| `POST` | `/admin/users/{userId}/toggle-status` | Aktif/nonaktifkan user |
| `POST` | `/admin/users/{userId}/adjust-credit` | Sesuaikan credit score |

### **🚨 Report Management**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/admin/reports` | Kelola semua laporan |
| `POST` | `/admin/reports/{reportId}/assign` | Assign laporan |
| `POST` | `/admin/reports/{reportId}/resolve` | Resolve laporan |

### **📈 Platform Analytics**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/admin/matches/history` | Riwayat semua pertandingan |
| `GET` | `/admin/activities` | Log aktivitas admin |

---

## 💬 **REAL-TIME FEATURES**

### **Chat & Broadcasting**
| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `POST` | `/realtime/chat/send` | Kirim pesan chat |
| `POST` | `/realtime/event/broadcast` | Broadcast update event |
| `POST` | `/realtime/notification/send` | Kirim notifikasi real-time |
| `POST` | `/realtime/auth` | Autentikasi real-time |
| `GET` | `/realtime/online-users` | User yang online |

---

## 🏅 **SPORTS ENDPOINTS**

| Method | Endpoint | Fungsi |
|--------|----------|---------|
| `GET` | `/sports/{sport}` | Detail olahraga |
| `GET` | `/sports/{sport}/events` | Event per olahraga |
| `GET` | `/sports/{sport}/communities` | Komunitas per olahraga |

---

## 📝 **Format Response**

### Success Response
```json
{
    "status": "success",
    "message": "Operasi berhasil!",
    "data": {
        // response data
    }
}
```

### Error Response
```json
{
    "status": "error",
    "message": "Pesan error",
    "errors": {
        "field": ["Error detail"]
    }
}
```

### Pagination Response
```json
{
    "status": "success",
    "data": {
        "items": [...],
        "pagination": {
            "current_page": 1,
            "last_page": 5,
            "per_page": 15,
            "total": 75
        }
    }
}
```

---

## 🔍 **Filter Parameters**

### Event Filters
- `sport_id` - Filter berdasarkan olahraga
- `event_type` - mabar, friendly_match, tournament
- `skill_level` - pemula, menengah, mahir, ahli, profesional
- `city` - Filter berdasarkan kota
- `date_from` & `date_to` - Range tanggal
- `premium_only` - Hanya event premium
- `available_only` - Hanya event yang masih tersedia

### User Filters
- `sport_id` - User dengan rating di olahraga tertentu
- `city` - User di kota tertentu
- `skill_level` - Level skill tertentu

### Community Filters
- `sport_id` - Komunitas olahraga tertentu
- `community_type` - public, private, invitation_only
- `skill_level` - Focus level skill
- `city` - Lokasi komunitas

---

## 🛡️ **Permissions & Restrictions**

### Player Permissions
- ✅ Join/leave events
- ✅ Rate communities & players
- ✅ Search users, events, communities
- ✅ Manage own profile
- ✅ View matchmaking status (jika peserta event)
- ❌ Create events
- ❌ Manage venues
- ❌ Generate/save matchmaking

### Host Permissions
- ✅ All Player permissions
- ✅ Create/manage events
- ✅ Create/manage communities
- ✅ Create/manage venues
- ✅ Generate matchmaking
- ✅ Check-in participants
- ✅ Record match results

### Admin Permissions
- ✅ All Host permissions
- ✅ User management
- ✅ Report management
- ✅ Platform analytics
- ✅ Credit score adjustments

---

## 📊 **Rate Limiting**

- **General API**: 60 requests/minute
- **Authentication**: 5 requests/minute
- **File Upload**: 10 requests/minute
- **Real-time**: 100 requests/minute

---

## 🔄 **Status Codes**

- `200` - Success
- `201` - Created
- `422` - Validation Error
- `403` - Forbidden
- `404` - Not Found
- `500` - Server Error

---

## 📱 **File Upload**

### Supported Formats
- **Profile Pictures**: JPG, PNG (max 2MB)
- **Event Thumbnails**: JPG, PNG (max 5MB)
- **Community Icons**: JPG, PNG (max 1MB)

### Upload Response
```json
{
    "status": "success",
    "message": "File berhasil diupload!",
    "data": {
        "file_url": "http://localhost/storage/uploads/..."
    }
}
```

---

## 🎲 **FLOW MATCHMAKING SYSTEM**

### **1. 🏃‍♂️ Player Flow:**
```
1. Join Event → Waiting for confirmation
2. Host confirms → Status: "confirmed" 
3. Event starts → View matchmaking results
4. See assigned matches & court numbers
```

### **2. 🎯 Host Flow:**
```
1. Create Event
2. Confirm participants
3. Generate matchmaking → Algorithm pairs players by skill
4. Review & adjust matches
5. Save matchmaking → Start matches
6. Monitor progress & record results
```

### **3. 📊 Matchmaking Algorithm:**
- **Singles**: Pairs players with similar skill ratings
- **Doubles**: Forms balanced teams 
- **Skill Tolerance**: Configurable MMR difference (default: 200)
- **Court Assignment**: Automatic court numbering
- **Quality Score**: Based on skill & experience balance

### **4. 🔍 Permissions:**
- **View Matchmaking**: Host + Participants + Admin
- **Generate Matchmaking**: Host + Admin only
- **Save Matchmaking**: Host + Admin only
- **Modify Results**: Host + Admin only

### **5. 📱 Real-time Updates:**
- Participants get notified when matchmaking is complete
- Live updates on match progress
- Automatic MMR updates after matches