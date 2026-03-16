<?php
// Copy file nay thanh database.php va sua lai thong tin
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'YOUR_PASSWORD');  // ← sua lai
define('DB_NAME', 'quan_ly_nghi_phep');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die(json_encode(["status" => "error", "message" => mysqli_connect_error()]));
}
mysqli_set_charset($conn, "utf8");
?>
```

---

## Cấu trúc cuối cùng
```
HETHONGQUANLYNGHIPHEPV/
├── frontend/
│   └── Duralux-admin-1.0.0/
├── backend/
│   ├── config/
│   │   ├── database.example.php  ← push lên git
│   │   ├── database.php          ← KHÔNG push (gitignore)
│   │   └── cors.php
│   ├── api/
│   │   ├── auth/
│   │   │   ├── login.php
│   │   │   └── logout.php
│   │   └── leave/
│   │       ├── request.php
│   │       └── approve.php
│   └── database/
│       └── schema.sql
├── .gitignore
└── README.md