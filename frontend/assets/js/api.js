const API_URL = "http://localhost:82/HeThongQuanLyNghiPhepVaPheDuyet_CNPM/backend/api";

async function callAPI(endpoint, method = "GET", body = null) {
    const options = {
        method: method,
        headers: { "Content-Type": "application/json" }
    };
    if (body) options.body = JSON.stringify(body);
    try {
        const res  = await fetch(`${API_URL}/${endpoint}`, options);
        const data = await res.json();
        return data;
    } catch (error) {
        return { status: "error", message: "Loi ket noi server" };
    }
}

// ===== USER =====
function saveUser(user)   { localStorage.setItem("user", JSON.stringify(user)); }
function getUser()        { return JSON.parse(localStorage.getItem("user")); }
function removeUser()     { localStorage.removeItem("user"); }

function checkLogin() {
    const user = getUser();
    if (!user) {
        window.location.href = "/HETHONGQUANLYNGHIPHEPV/frontend/Duralux-admin-1.0.0/auth-login.html";
        return null;
    }
    return user;
}

// Kiem tra quyen truy cap
function checkRole(allowedRoles) {
    const user = checkLogin();
    if (!allowedRoles.includes(user.role)) {
        window.location.href = "dashboard.html";
        return null;
    }
    return user;
}

// ===== LEAVE BALANCE =====
async function getLeaveBalance(user_id) {
    return await callAPI(`leave/balance.php?user_id=${user_id}`);
}

// ===== LEAVE TYPES =====
async function getLeaveTypes() {
    return await callAPI("leave/type.php");
}

// ===== NOTIFICATIONS =====
async function getNotifications(user_id) {
    return await callAPI(`notifications.php?user_id=${user_id}`);
}

async function markAsRead(id, user_id) {
    return await callAPI(`notifications.php?user_id=${user_id}`, "POST", { id });
}

async function markAllAsRead(user_id) {
    return await callAPI(`notifications.php?user_id=${user_id}`, "POST", {});
}

// ===== LOAD THONG BAO TREN HEADER =====
async function loadNotifications() {
    const user = getUser();
    if (!user) return;

    const res = await getNotifications(user.id);
    if (res.status !== "success") return;

    // Hien so thong bao chua doc
    const badge = document.getElementById("notif-badge");
    if (badge) {
        badge.innerText  = res.unread_count;
        badge.style.display = res.unread_count > 0 ? "inline" : "none";
    }

    // Hien danh sach thong bao
    const list = document.getElementById("notif-list");
    if (list) {
        list.innerHTML = "";
        res.data.forEach(n => {
            list.innerHTML += `
                <a href="#" class="dropdown-item ${n.is_read ? '' : 'fw-bold'}"
                   onclick="markAsRead(${n.id}, ${user.id})">
                    <small class="text-muted">${n.created_at}</small>
                    <p class="mb-0">${n.message}</p>
                </a>
            `;
        });
    }
}
```

---

## Tổng hợp toàn bộ API
```
// backend/api/
// ├── auth/
// │   ├── login.php          → POST - dang nhap
// │   └── logout.php         → POST - dang xuat
// ├── leave/
// │   ├── request.php        → GET/POST - don nghi phep
// │   ├── approve.php        → POST - duyet don
// │   ├── balance.php        → GET - so ngay con lai
// │   └── type.php           → GET - loai nghi phep
// └── notifications.php      → GET/POST - thong bao