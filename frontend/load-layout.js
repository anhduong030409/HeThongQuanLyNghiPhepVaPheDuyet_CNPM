function loadPartial(id, file) {
    fetch(file)
        .then(r => r.text())
        .then(html => {
            document.getElementById(id).innerHTML = html;
        });
}

function loadPartial(id, file) {
    return fetch(file)
        .then(r => r.text())
        .then(html => {
            document.getElementById(id).innerHTML = html;

            if (id === 'header') {
                const user = JSON.parse(localStorage.getItem('user') || '{}');
                const nameEl = document.getElementById('headerName');
                const emailEl = document.getElementById('headerEmail');

                const roleMap = {
                    admin: { text: 'Admin', css: 'bg-soft-danger text-danger' },
                    director: { text: 'Director', css: 'bg-soft-primary text-primary' },
                    hr: { text: 'HR', css: 'bg-soft-info text-info' },
                    manager: { text: 'Manager', css: 'bg-soft-warning text-warning' },
                    employee: { text: 'Nhân viên', css: 'bg-soft-success text-success' }
                };
                const role = roleMap[user.role] ?? { text: user.role, css: 'bg-soft-secondary text-secondary' };

                if (nameEl && user.full_name) {
                    nameEl.innerHTML = `${user.full_name} <span class="badge ${role.css} ms-1">${role.text}</span>`;
                }
                if (emailEl && user.email) {
                    emailEl.innerText = user.email;
                }
                
                // Tải danh sách thông báo
                loadNotifications();
            }

            // Thêm vào đây luôn, sau khi nav được chèn vào DOM
            if (id === 'navigation') {
                applyRoleBasedNav();
            }
        });
}

function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = 'index.html';
}

function applyRoleBasedNav() {
    const userStr = localStorage.getItem('user');
    if (!userStr) return;

    const user = JSON.parse(userStr);
    const userRole = user.role;

    const navItems = document.querySelectorAll('.nxl-item[data-roles]');
    navItems.forEach(item => {
        const allowedRoles = item.getAttribute('data-roles').split(',');
        item.style.display = allowedRoles.includes(userRole) ? '' : 'none';
    });

    hideEmptyCaptions();
}

function hideEmptyCaptions() {
    const captions = document.querySelectorAll('.nxl-caption');
    captions.forEach(caption => {
        let nextEl = caption.nextElementSibling;
        let hasVisible = false;
        while (nextEl && !nextEl.classList.contains('nxl-caption')) {
            if (nextEl.style.display !== 'none') {
                hasVisible = true;
                break;
            }
            nextEl = nextEl.nextElementSibling;
        }
        caption.style.display = hasVisible ? '' : 'none';
    });
}

loadPartial('navigation', 'partials/navigation.html');
loadPartial('header', 'partials/header.html');
loadPartial('footer', 'partials/footer.html');

// ====== GIAO TIẾP VỚI API NOTIFICATIONS ======
async function loadNotifications() {
    try {
        const res = await callAPI('notifications/list.php');
        if (res && res.status === 'success') {
            const badge = document.querySelector('.nxl-h-badge');
            const menu = document.querySelector('.nxl-notifications-menu');
            if (!badge || !menu) return;

            // Cập nhật số đếm Chưa đọc
            if (res.unread > 0) {
                badge.innerText = res.unread;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }

            // Xóa HTML cứng cũ, tạo Khung đầu và List thân tin nhắn
            const headHtml = `
                <div class="d-flex justify-content-between align-items-center notifications-head">
                    <h6 class="fw-bold text-dark mb-0">Thông báo từ hệ thống</h6>
                    <a href="javascript:void(0);" onclick="markAsRead('all')" class="fs-11 text-success text-end ms-auto">
                        <i class="feather-check"></i>
                        <span>Đánh dấu đã đọc tất cả</span>
                    </a>
                </div>
            `;
            let itemsHtml = '';

            if (res.data.length === 0) {
                itemsHtml = '<div class="text-center p-3 text-muted">Chưa có thông báo nào</div>';
            } else {
                res.data.forEach(n => {
                    // Custom Icon theo Type (nếu database set type theo action)
                    let icon = 'feather-info text-primary';
                    if (n.type === 'approved') icon = 'feather-check-circle text-success';
                    if (n.type === 'rejected') icon = 'feather-x-circle text-danger';
                    
                    const bgClass = n.is_read == 0 ? 'bg-light border-start border-3 border-primary' : '';
                    const timeStr = new Date(n.created_at).toLocaleString('vi-VN', {hour: '2-digit', minute:'2-digit', day:'2-digit', month:'2-digit', year:'numeric'});

                    itemsHtml += `
                        <div class="notifications-item py-2 px-3 mb-1 ${bgClass}">
                            <div class="notifications-desc w-100">
                                <a href="javascript:void(0);" onclick="markAsRead(${n.id})" class="font-body text-truncate-2-line">
                                    <span class="fw-semibold text-dark"><i class="${icon} me-1"></i>Hệ thống tự động</span> 
                                    <br/>
                                    <span style="font-size: 13px !important; color: #495057 !important; display: block; margin-top: 4px;">${n.message}</span>
                                </a>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <div class="notifications-date text-muted fs-11">${timeStr}</div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }

            menu.innerHTML = headHtml + itemsHtml + `
                <div class="text-center notifications-footer">
                    <a href="javascript:void(0);" class="fs-13 fw-semibold text-dark">Lịch sử thông báo</a>
                </div>
            `;
        }
    } catch (e) {
        console.error("Lỗi khi tải thông báo", e);
    }
}

async function markAsRead(id) {
    const res = await callAPI('notifications/read.php', 'POST', { id: id });
    if (res && res.status === 'success') {
        loadNotifications(); // Reload lại menu thông báo sau khi click xem
    }
}
