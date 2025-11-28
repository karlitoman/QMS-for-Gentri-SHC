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
  if (!err) return;
  var el = document.createElement('div');
  el.className = 'toast toast-error';
  el.textContent = err;
  document.body.appendChild(el);
  requestAnimationFrame(function(){ el.classList.add('show'); });
  setTimeout(function(){ el.classList.remove('show'); setTimeout(function(){ el.remove(); }, 200); }, 2400);
})();
</script>
</body>
</html>
