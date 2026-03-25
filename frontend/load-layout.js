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

