
// Toggle: Set to true for server, false for local
const IS_SERVER = false; // Sửa ở đây

const API_URL = IS_SERVER 
    ? "https://duralux.is-great.org/backend/api" // Server URL
    : "http://localhost:82/HeThongQuanLyNghiPhepVaPheDuyet_CNPM/backend/api"; // Local URL

// ===== TOKEN =====
function saveToken(token) { localStorage.setItem("token", token); }
function getToken() { return localStorage.getItem("token"); }
function removeToken() { localStorage.removeItem("token"); }

// ===== USER =====
function saveUser(user) { localStorage.setItem("user", JSON.stringify(user)); }
function getUser() { return JSON.parse(localStorage.getItem("user")); }
function removeUser() { localStorage.removeItem("user"); }

// ===== AUTH =====
function checkLogin() {
    const token = getToken();
    if (!token) {
        window.location.href = "auth-login.html";
        return null;
    }
    return getUser();
}

function checkRole(allowedRoles) {
    const user = checkLogin();
    if (!user || !allowedRoles.includes(user.role)) {
        window.location.href = "dashboard.html";
        return null;
    }
    return user;
}

// ==== LOGOUT ben file load-layout.js ======

// ===== CALL API =====
async function callAPI(endpoint, method = "GET", body = null) {
    const options = {
        method: method,
        headers: {
            "Content-Type": "application/json",
            "Authorization": "Bearer " + (getToken() || "")
        }
    };
    if (body) options.body = JSON.stringify(body);
    try {
        const res = await fetch(`${API_URL}/${endpoint}`, options);
        const data = await res.json();

        // SỬA Ở ĐÂY: Nếu trả về 401 NHƯNG KHÔNG PHẢI là API login/change-password thì mới đá văng (logout)
        if (res.status === 401 && !endpoint.includes("login.php") && !endpoint.includes("change-password.php")) {
            // Kiểm tra xem hàm logout có tồn tại không để tránh lỗi văng catch
            if (typeof logout === "function") {
                logout();
            } else {
                window.location.href = "auth-login.html";
            }
            return null;
        }

        // Nếu là API login bị 401, nó vẫn sẽ đi tiếp xuống đây và trả về data báo sai pass
        return data;
    } catch (error) {
        console.error("Lỗi tại callAPI:", error); // In ra console để sau này dễ debug
        return { status: "error", message: "Lỗi kết nối server" };
    }
}


// ===== CALL API VỚI FILE =====
async function callAPIFile(endpoint, formData) {
    try {
        const res = await fetch(`${API_URL}/${endpoint}`, {
            method: "POST",
            headers: { "Authorization": "Bearer " + (getToken() || "") },
            // KHÔNG set Content-Type — browser tự set multipart/form-data
            body: formData
        });
        const data = await res.json();
        if (res.status === 401) { logout(); return null; }
        return data;
    } catch (error) {
        return { status: "error", message: "Loi ket noi server" };
    }
}

// ===== LEAVE =====
async function getLeaveBalance(user_id) {
    return await callAPI(`leave/balance.php?user_id=${user_id}`);
}

async function getLeaveTypes() {
    return await callAPI("leave/type.php");
}

// ===== NOTIFICATIONS =====
async function loadNotifications() {
    const user = getUser();
    if (!user) return;

    const res = await callAPI(`notifications.php?user_id=${user.id}`);
    if (!res || res.status !== "success") return;

    const badge = document.getElementById("notif-badge");
    if (badge) {
        badge.innerText = res.unread_count;
        badge.style.display = res.unread_count > 0 ? "inline" : "none";
    }

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

async function markAsRead(id, user_id) {
    await callAPI(`notifications.php?user_id=${user_id}`, "POST", { id });
    loadNotifications();
}

async function markAllAsRead(user_id) {
    await callAPI(`notifications.php?user_id=${user_id}`, "POST", {});
    loadNotifications();
}

// ===== UTILS =====
function formatDate(dateStr) {
    if (!dateStr) return '--';
    try {
        const parts = dateStr.split(' ')[0].split('-');
        if (parts.length !== 3) return dateStr;
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    } catch (e) {
        return dateStr;
    }
}