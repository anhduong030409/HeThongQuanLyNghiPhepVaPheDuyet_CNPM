// URL gốc của API - cả nhóm chỉ sửa 1 chỗ này
const API_URL = "http://localhost/HETHONGQUANLYNGHIPHEPV/backend/api";

// Hàm gọi API dùng chung
async function callAPI(endpoint, method = "GET", body = null) {
    const options = {
        method: method,
        headers: { "Content-Type": "application/json" }
    };

    if (body) options.body = JSON.stringify(body);

    try {
        const res = await fetch(`${API_URL}/${endpoint}`, options);
        const data = await res.json();
        return data;
    } catch (error) {
        console.error("Loi API:", error);
        return { status: "error", message: "Loi ket noi server" };
    }
}

// Luu user vao localStorage
function saveUser(user) {
    localStorage.setItem("user", JSON.stringify(user));
}

// Lay user tu localStorage
function getUser() {
    return JSON.parse(localStorage.getItem("user"));
}

// Xoa user khoi localStorage
function removeUser() {
    localStorage.removeItem("user");
}

// Kiem tra da dang nhap chua
function checkLogin() {
    const user = getUser();
    if (!user) {
        window.location.href = "/HETHONGQUANLYNGHIPHEPV/frontend/Duralux-admin-1.0.0/auth-login.html";
        return null;
    }
    return user;
}