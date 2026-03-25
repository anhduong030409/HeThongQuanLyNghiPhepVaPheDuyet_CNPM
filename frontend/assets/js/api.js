const API_URL = "http://localhost:82/HeThongQuanLyNghiPhepVaPheDuyet_CNPM/backend/api";

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
        if (res.status === 401) { logout(); return null; }
        return data;
    } catch (error) {
        return { status: "error", message: "Loi ket noi server" };
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