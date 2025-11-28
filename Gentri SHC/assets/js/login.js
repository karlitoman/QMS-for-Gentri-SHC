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
function showToast(message, type = 'error', duration = 2000) {
  const el = document.createElement('div');
  el.className = 'toast' + (type === 'error' ? ' toast-error' : '');
  el.textContent = message;
  document.body.appendChild(el);
  requestAnimationFrame(() => el.classList.add('show'));
  setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 200); }, duration);
}

(function setupLogin(){
  const form = document.querySelector('.login-form');
  if (!form) return;
  const current = (window.location.pathname.split('/').pop() || '').toLowerCase();
  if (current === 'login.php') return;
  const emailInput = document.getElementById('username');
  const passInput = document.getElementById('password');
  [emailInput, passInput].forEach(i => i && i.addEventListener('input', () => i.classList.remove('input-error')));

  const deviceKey = 'captcha_device_v1';
  const isCaptchaVerified = () => localStorage.getItem(deviceKey) === '1';
  const forceCaptcha = new URLSearchParams(location.search).get('force_captcha') === '1';
  let lastCaptcha = '';
  function generateCode(len = 6) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let s = '';
    for (let i = 0; i < len; i++) s += chars[Math.floor(Math.random() * chars.length)];
    return s;
  }
  function showCaptcha(onSuccess){
    const overlay = document.createElement('div');
    overlay.className = 'captcha-overlay';
    overlay.innerHTML = `
      <div class="captcha-card">
        <h2>Verify you are human</h2>
        <p>Please solve the code below. This will be required only once on this device.</p>
        <canvas id="captchaCanvas" width="360" height="120" aria-label="CAPTCHA"></canvas>
        <div class="captcha-input-row">
          <input type="text" id="captchaInput" placeholder="Enter code" autocomplete="off" />
          <button id="captchaRefresh" class="captcha-refresh" type="button" aria-label="Refresh">⟳</button>
        </div>
        <div class="captcha-actions">
          <button id="captchaVerify" class="captcha-verify" type="button">Verify & Continue</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);
    const canvas = overlay.querySelector('#captchaCanvas');
    const ctx = canvas.getContext('2d');
    const inputEl = overlay.querySelector('#captchaInput');
    const refreshBtn = overlay.querySelector('#captchaRefresh');
    const verifyBtn = overlay.querySelector('#captchaVerify');
    let code = '';
    function draw(text){
      ctx.clearRect(0,0,canvas.width,canvas.height);
      const grd = ctx.createLinearGradient(0,0,360,0); grd.addColorStop(0,'#eef2ff'); grd.addColorStop(1,'#f7f9ff');
      ctx.fillStyle = grd; ctx.fillRect(0,0,360,120);
      for(let i=0;i<40;i++){ ctx.fillStyle = `rgba(17,24,39,${Math.random()*0.15})`; ctx.beginPath(); ctx.arc(Math.random()*360, Math.random()*120, Math.random()*2+0.5, 0, Math.PI*2); ctx.fill(); }
      for(let i=0;i<6;i++){ ctx.strokeStyle = `rgba(79,70,229,${0.25+Math.random()*0.4})`; ctx.lineWidth = 1 + Math.random()*1.5; ctx.beginPath(); ctx.moveTo(Math.random()*360, Math.random()*120); ctx.bezierCurveTo(Math.random()*360, Math.random()*120, Math.random()*360, Math.random()*120, Math.random()*360, Math.random()*120); ctx.stroke(); }
      const chars = text.split('');
      let x = 20;
      chars.forEach((ch, idx)=>{
        const angle = (Math.random()*0.7 - 0.35);
        const y = 60 + Math.sin((x+idx*10)/30)*8 + (Math.random()*6-3);
        ctx.save();
        ctx.translate(x,y);
        ctx.rotate(angle);
        ctx.font = `${42 + Math.floor(Math.random()*8)}px Arial Black`;
        ctx.fillStyle = ['#1e40af','#0ea5e9','#16a34a','#dc2626'][idx%4];
        ctx.shadowColor = 'rgba(0,0,0,0.25)'; ctx.shadowBlur = 6; ctx.shadowOffsetY = 2;
        ctx.fillText(ch,0,0);
        ctx.restore();
        x += 50 + Math.random()*8;
      });
      for(let i=0;i<80;i++){ ctx.fillStyle = `rgba(31,41,55,${Math.random()*0.15})`; ctx.fillRect(Math.random()*360, Math.random()*120, 1, 1); }
    }
    async function renew(){ try{ const r=await fetch('backend/captcha.php?renew=1'); const j=await r.json(); code = (j.code||'').toUpperCase(); draw(code); }catch(_){ code=''; draw('ERROR'); } }
    refreshBtn.addEventListener('click', (e)=>{ e.preventDefault(); renew(); inputEl.value=''; inputEl.focus(); });
    verifyBtn.addEventListener('click', (e)=>{
      e.preventDefault();
      const v = (inputEl.value || '').trim().toUpperCase();
      if (v === code && v) {
        lastCaptcha = v;
        try { localStorage.setItem(deviceKey,'1'); } catch(_) {}
        showToast('Verified', 'success', 1200);
        overlay.remove();
        if (typeof onSuccess === 'function') onSuccess();
      } else {
        showToast('Incorrect code', 'error', 2000);
        inputEl.focus();
      }
    });
    renew();
    inputEl && inputEl.focus();
  }

  async function doLogin(){
    const role = current === 'staff_login.html' ? 'staff' : (current === 'doctor_login.html' ? 'doctor' : 'admin');
    const username = (emailInput?.value || '').trim();
    const password = (passInput?.value || '').trim();
    if (!username) { if (emailInput) { emailInput.classList.add('input-error'); emailInput.focus(); } showToast('Email is required', 'error', 2000); return; }
    if (!password) { if (passInput) { passInput.classList.add('input-error'); passInput.focus(); } showToast('Password is required', 'error', 2000); return; }
    try {
      const fd = new FormData();
      fd.append('username', username);
      fd.append('password', password);
      fd.append('role', role);
      if (lastCaptcha) fd.append('captcha', lastCaptcha);
      const res = await fetch('backend/login.php', { method: 'POST', body: fd });
      const data = await res.json().catch(() => null);
      if (data && data.ok) {
        try {
          const display = data.display_name || username || '';
          localStorage.setItem('currentUserName', display);
          localStorage.setItem('currentUserEmail', username);
          localStorage.setItem('currentUserRole', role);
        } catch (_) {}
        showLoginDialog({ title: 'Successfully Logged In', subtitle: 'Welcome', redirect: data.redirect, delay: 500 });
      } else {
        showToast((data && data.error) ? data.error : 'Login failed', 'error', 2500);
      }
    } catch (_) {
      showToast('Unable to contact server. Please try again.', 'error', 2500);
    }
  }

  const verifyBtn = document.getElementById('verifyHumanBtn');
  if (verifyBtn) {
    verifyBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (isCaptchaVerified()) { showToast('Device already verified', 'success', 1200); return; }
      showCaptcha(() => showToast('Device verified. You can login without CAPTCHA next time.', 'success', 2000));
    });
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (forceCaptcha || !isCaptchaVerified()) { showCaptcha(() => doLogin()); return; }
    doLogin();
  });
})();