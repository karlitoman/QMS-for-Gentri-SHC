// Centered login success dialog
function showLoginDialog({ title = 'Successfully Logged In', subtitle = 'Welcome', redirect = 'admin_index.html', delay = 500 } = {}) {
  const overlay = document.createElement('div');
  overlay.className = 'login-overlay';
  overlay.innerHTML = `
    <div class="login-card">
      <div class="login-icon">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="12" cy="12" r="10" stroke="#34a853" stroke-width="2" fill="none" opacity="0.15"/>
          <path d="M7 12l3 3 7-7" stroke="#34a853" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h2>${title}</h2>
      <p>${subtitle}</p>
    </div>`;
  document.body.appendChild(overlay);
  requestAnimationFrame(() => overlay.classList.add('show'));
  setTimeout(() => {
    window.location.href = redirect;
  }, delay);
}

// Intercept login form submit on each login page
(function setupLogin(){
  const form = document.querySelector('.login-form');
  if (!form) return;
  const current = (window.location.pathname.split('/').pop() || '').toLowerCase();
  if (current === 'login.php') return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const role = current === 'staff_login.html' ? 'staff' : (current === 'doctor_login.html' ? 'doctor' : 'admin');
    const username = document.getElementById('username')?.value || '';
    const password = document.getElementById('password')?.value || '';

    try {
      const fd = new FormData();
      fd.append('username', username);
      fd.append('password', password);
      fd.append('role', role);
      const res = await fetch('backend/login.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data && data.ok) {
        try {
          const display = data.display_name || username || '';
          localStorage.setItem('currentUserName', display);
          localStorage.setItem('currentUserEmail', username);
          localStorage.setItem('currentUserRole', role);
        } catch (_) {}
        showLoginDialog({ title: 'Successfully Logged In', subtitle: 'Welcome', redirect: data.redirect, delay: 500 });
      } else {
        alert(data && data.error ? data.error : 'Login failed');
      }
    } catch (err) {
      alert('Unable to contact server. Please try again.');
    }
  });
})();