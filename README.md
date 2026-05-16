# 📝 Note Manager

Ứng dụng quản lý ghi chú với khả năng **cộng tác real-time**, **PWA offline**, và **thông báo email tự động**.

## 🛠 Yêu cầu hệ thống

| Phần mềm | Phiên bản     | Ghi chú                                    |
| --------- | ------------- | ------------------------------------------- |
| PHP       | >= 8.2        | Bật extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` |
| Composer  | >= 2.x        | [getcomposer.org](https://getcomposer.org)  |
| Node.js   | >= 18.x       | [nodejs.org](https://nodejs.org)            |
| MySQL     | >= 8.0        | Hoặc MariaDB >= 10.6                       |
| XAMPP     | (khuyên dùng) | Đã tích hợp PHP + MySQL + Apache           |

---

## 🚀 Hướng dẫn cài đặt

### 1. Clone dự án

```bash
git clone https://github.com/qtan1401/FinaltermProject.git
cd FinaltermProject
```

### 2. Di chuyển vào thư mục backend

```bash
cd backend
```

### 3. Cài đặt PHP dependencies

```bash
composer install
```

### 4. Cài đặt Node.js dependencies

```bash
npm install
```

### 5. Tạo file cấu hình môi trường

```bash
cp .env.example .env
```

> **Trên Windows (CMD):**
> ```cmd
> copy .env.example .env
> ```

### 6. Cấu hình file `.env`

Mở file `.env` và chỉnh sửa các thông số sau:

#### Database
```env
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=note_manager_db
DB_USERNAME=root
DB_PASSWORD=
```

#### Email (tuỳ chọn — dùng cho thông báo & khôi phục mật khẩu)
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your_email@gmail.com
MAIL_PASSWORD=your_app_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="your_email@gmail.com"
MAIL_FROM_NAME="NoteApp"
```

> 💡 Với Gmail, bạn cần tạo **App Password** tại [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords).


### 7. Generate App Key, tạo Database & chạy Migration

> ⚠️ Lệnh `migrate` chỉ tạo **bảng** — bạn cần tạo **database** trước (qua phpMyAdmin hoặc MySQL CLI):
> ```sql
> CREATE DATABASE note_manager_db;
> ```

```bash
php artisan key:generate
php artisan migrate
php artisan storage:link
```

---

## ▶️ Chạy dự án

Bạn cần mở **3 terminal** riêng biệt (tất cả đều ở thư mục `backend`):

### Terminal 1 — Laravel Server

```bash
php artisan serve
```

> Mặc định chạy tại: `http://localhost:8000`

### Terminal 2 — Queue Worker (xử lý email & thông báo)

```bash
php artisan queue:work
```

### Terminal 3 — WebSocket Server (cộng tác real-time)

```bash
npm run ws
```

> WebSocket mặc định chạy tại: `ws://localhost:6001`


## 🌐 Truy cập ứng dụng

- **Ứng dụng chính:** [http://localhost:8000](http://localhost:8000)

---

## 📁 Cấu trúc dự án

```
note-manage/
└── backend/                  # Toàn bộ source code
    ├── app/                  # Logic ứng dụng (Models, Controllers, Mail...)
    ├── config/               # File cấu hình Laravel
    ├── database/migrations/  # Database migrations
    ├── public/               # Entry point & Frontend (HTML, CSS, JS)
    │   ├── frontend/         # Giao diện người dùng
    │   ├── sw.js             # Service Worker (PWA)
    │   └── manifest.json     # PWA Manifest
    ├── resources/            # Views & Assets (Blade, CSS, JS)
    ├── routes/               # Định nghĩa Routes (API & Web)
    ├── storage/              # File uploads & logs
    ├── websocket-server.js   # WebSocket server cho real-time
    ├── .env.example          # Mẫu file cấu hình
    ├── composer.json         # PHP dependencies
    └── package.json          # Node.js dependencies
```

---

## ✨ Tính năng chính

- 🔐 **Xác thực:** Đăng ký, đăng nhập, đăng xuất, khôi phục mật khẩu
- 📝 **Quản lý ghi chú:** Tạo, sửa, xoá, ghim, tìm kiếm
- 🏷 **Nhãn (Labels):** Phân loại ghi chú theo nhãn
- 🔒 **Khoá ghi chú:** Bảo vệ ghi chú bằng mật khẩu
- 🖼 **Hình ảnh:** Đính kèm nhiều hình ảnh vào ghi chú
- 👥 **Chia sẻ & Cộng tác:** Chia sẻ ghi chú, chỉnh sửa real-time qua WebSocket
- 📧 **Thông báo Email:** Gửi email tự động qua Laravel Queue
- 📱 **PWA:** Hỗ trợ offline

---

## ❓ Xử lý lỗi thường gặp

| Lỗi | Giải pháp |
| --- | --------- |
| `SQLSTATE[HY000] [1049] Unknown database` | Tạo database `note_manager_db` trước khi chạy migrate |
| `No application encryption key` | Chạy `php artisan key:generate` |
| `Permission denied — storage/` | Chạy `chmod -R 775 storage bootstrap/cache` (Linux/Mac) |
| WebSocket không kết nối | Kiểm tra Terminal 3 có đang chạy `npm run ws` |
| Email không gửi được | Kiểm tra cấu hình MAIL trong `.env` và Queue worker |
