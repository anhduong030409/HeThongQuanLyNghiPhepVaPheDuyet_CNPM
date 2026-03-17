function loadPartial(id, file) {
  fetch(file)
    .then(r => r.text())
    .then(html => {
      document.getElementById(id).innerHTML = html;
    });
}

function loadPartial(id, file) {
    fetch(file)
        .then(r => r.text())
        .then(html => {
            document.getElementById(id).innerHTML = html;

            if (id === 'header') {
                const user    = JSON.parse(localStorage.getItem('user') || '{}');
                const nameEl  = document.getElementById('headerName');
                const emailEl = document.getElementById('headerEmail');

                const roleMap = {
                    admin:    { text: 'Admin',     css: 'bg-soft-danger text-danger' },
                    hr:       { text: 'HR',        css: 'bg-soft-info text-info' },
                    manager:  { text: 'Manager',   css: 'bg-soft-warning text-warning' },
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
        });
}

function logout() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = 'index.html';
}

loadPartial('navigation', 'partials/navigation.html');
loadPartial('header', 'partials/header.html');
loadPartial('theme-customizer', 'partials/theme-customizer.html');

