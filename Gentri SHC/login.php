<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Trias Super Health Center Login</title>
    <link rel="stylesheet" href="assets/css/login_style.css">
</head>
<body class="login">
    <header>
        <div class="header-content">
            <div class="logo-placeholder left-logo"></div>
            <h1>GENERAL TRIAS SUPER HEALTH CENTER</h1>
            <p>Arnaldo Hwy, General Trias, Cavite</p>
        </div>
    </header>

    <main>
        <div class="login-container">
            <div class="logo-placeholder center-logo"></div>

            <form action="auth_login.php" method="POST" class="login-form">
                <label for="username">Email</label>
                <input type="text" name="username" placeholder="Email" required>

                <label for="password">Password</label>
                <input type="password" name="password" placeholder="Password" required>
                <input type="hidden" name="captcha" id="captchaHidden" />

                <button type="submit" class="login-button">LOGIN</button>
            </form>
        </div>
    </main>

    <footer>
        <div class="footer-content">
            <div class="footer-logo-placeholder"></div>
            <p>Let's join forces! For a more progressive General Trias.</p>
        </div>
    </footer>
<script>
(function(){
  var params = new URLSearchParams(window.location.search);
  var err = params.get('error');
  if (err) {
    var el = document.createElement('div');
    el.className = 'toast toast-error';
    el.textContent = err;
    el.style.position = 'fixed';
    el.style.left = '50%';
    el.style.transform = 'translateX(-50%)';
    el.style.bottom = '20px';
    el.style.background = '#dc2626';
    el.style.color = '#fff';
    el.style.padding = '10px 14px';
    el.style.borderRadius = '6px';
    el.style.boxShadow = '0 4px 10px rgba(0,0,0,0.2)';
    el.style.opacity = '0';
    el.style.transition = 'opacity 200ms ease';
    document.body.appendChild(el);
    requestAnimationFrame(function(){ el.style.opacity = '1'; });
    setTimeout(function(){ el.style.opacity = '0'; setTimeout(function(){ el.remove(); }, 200); }, 2400);
  }
  var form = document.querySelector('.login-form');
  if (!form) return;
  var deviceKey = 'captcha_device_v1';
  function isVerified(){ try { return localStorage.getItem(deviceKey) === '1'; } catch(_) { return false; } }
  function showCaptcha(onSuccess){
    var overlay = document.createElement('div');
    overlay.className = 'captcha-overlay';
    overlay.innerHTML = '<div class="captcha-card"><h2 style="margin:0 0 8px;font-size:18px;">Verify you are human</h2><p style="margin:0 0 12px;color:#4b5563;">Please solve the code below. This will be required only once on this device.</p><canvas id="captchaCanvas" width="360" height="120" aria-label="CAPTCHA"></canvas><div class="captcha-input-row" style="display:flex;gap:8px;margin-top:12px;"><input type="text" id="captchaInput" placeholder="Enter code" autocomplete="off" style="flex:1;padding:10px;border:1px solid #d1d5db;border-radius:6px;" /><button id="captchaRefresh" class="captcha-refresh" type="button" style="padding:10px 12px;border:1px solid #d1d5db;background:#f3f4f6;border-radius:6px;cursor:pointer;">⟳</button></div><div class="captcha-actions" style="text-align:right;margin-top:12px;"><button id="captchaVerify" class="captcha-verify" type="button" style="padding:10px 14px;background:#2563eb;color:#fff;border:none;border-radius:6px;cursor:pointer;">Verify & Continue</button></div></div>';
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(0,0,0,0.35)';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.zIndex = '9999';
    var card = overlay.querySelector('.captcha-card');
    card.style.width = '420px';
    card.style.maxWidth = '90%';
    card.style.background = '#fff';
    card.style.borderRadius = '10px';
    card.style.padding = '16px';
    card.style.boxShadow = '0 8px 24px rgba(0,0,0,0.2)';
    document.body.appendChild(overlay);
    var canvas = overlay.querySelector('#captchaCanvas');
    var ctx = canvas.getContext('2d');
    var inputEl = overlay.querySelector('#captchaInput');
    var refreshBtn = overlay.querySelector('#captchaRefresh');
    var verifyBtn = overlay.querySelector('#captchaVerify');
    var code = '';
    function draw(text){
      ctx.clearRect(0,0,360,120);
      var grd = ctx.createLinearGradient(0,0,360,0); grd.addColorStop(0,'#eef2ff'); grd.addColorStop(1,'#f7f9ff');
      ctx.fillStyle = grd; ctx.fillRect(0,0,360,120);
      for(var i=0;i<40;i++){ ctx.fillStyle = 'rgba(17,24,39,'+(Math.random()*0.15)+')'; ctx.beginPath(); ctx.arc(Math.random()*360, Math.random()*120, Math.random()*2+0.5, 0, Math.PI*2); ctx.fill(); }
      for(var i=0;i<6;i++){ ctx.strokeStyle = 'rgba(79,70,229,'+(0.25+Math.random()*0.4)+')'; ctx.lineWidth = 1 + Math.random()*1.5; ctx.beginPath(); ctx.moveTo(Math.random()*360, Math.random()*120); ctx.bezierCurveTo(Math.random()*360, Math.random()*120, Math.random()*360, Math.random()*120, Math.random()*360, Math.random()*120); ctx.stroke(); }
      var x=20; for(var k=0;k<text.length;k++){ var ch=text[k]; var angle=(Math.random()*0.7-0.35); var y=60+Math.sin((x+k*10)/30)*8+(Math.random()*6-3); ctx.save(); ctx.translate(x,y); ctx.rotate(angle); ctx.font=(42+Math.floor(Math.random()*8))+'px Arial Black'; ctx.fillStyle=['#1e40af','#0ea5e9','#16a34a','#dc2626'][k%4]; ctx.shadowColor='rgba(0,0,0,0.25)'; ctx.shadowBlur=6; ctx.shadowOffsetY=2; ctx.fillText(ch,0,0); ctx.restore(); x+=50+Math.random()*8; }
      for(var j=0;j<80;j++){ ctx.fillStyle = 'rgba(31,41,55,'+(Math.random()*0.15)+')'; ctx.fillRect(Math.random()*360, Math.random()*120, 1, 1); }
    }
    function renew(){ fetch('backend/captcha.php?renew=1', {cache:'no-store'}).then(function(r){ return r.json(); }).then(function(j){ code = (j.code||'').toUpperCase(); draw(code); }).catch(function(){ code=''; draw('ERROR'); }); }
    refreshBtn.addEventListener('click', function(e){ e.preventDefault(); renew(); inputEl.value=''; inputEl.focus(); });
    verifyBtn.addEventListener('click', function(e){ e.preventDefault(); var v = (inputEl.value||'').trim().toUpperCase(); if (v===code && v) { try { localStorage.setItem(deviceKey,'1'); } catch(_) {} var hidden = document.getElementById('captchaHidden'); if (hidden) hidden.value = v; overlay.remove(); if (typeof onSuccess==='function') onSuccess(); } else { inputEl.style.borderColor = '#dc2626'; inputEl.focus(); } });
    renew(); inputEl && inputEl.focus();
  }
  form.addEventListener('submit', function(e){ if (isVerified()) { return; } e.preventDefault(); showCaptcha(function(){ form.submit(); }); });
})();
</script>
</body>
</html>