function loadPartial(id, file) {
  fetch(file)
    .then(r => r.text())
    .then(html => {
      document.getElementById(id).innerHTML = html;
    });
}

loadPartial('navigation', 'partials/navigation.html');
loadPartial('header', 'partials/header.html');
loadPartial('theme-customizer', 'partials/theme-customizer.html');

