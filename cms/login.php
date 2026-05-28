<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

session_init();

// Already logged in — redirect to appropriate dashboard
if (is_logged_in()) {
    $user = get_cms_user();
    header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif (!$user['is_active']) {
            $error = 'Your account has been deactivated. Please contact an administrator.';
        } else {
            login_user($user);
            header('Location: ' . ($user['role'] === 'admin' ? 'admin.php' : 'dashboard.php'));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — GoodEarth CMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Karla:ital,wght@0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --ge-blue:#0053a4;--ge-gold:#d69100;--ge-maroon:#874545;--ge-olive:#666b4a;
  --ge-ivory:#f5f1eb;--ge-blue-dark:#003d7a;--ge-ivory-deep:#ece8e0;--ge-slate:#3a3a3a;
  --radius-xl:28px;--radius-pill:50px;
}
html,body{height:100%}
body{font-family:'Karla',sans-serif;color:var(--ge-slate);display:flex;min-height:100vh}

/* ── Left panel ── */
.panel-left{
  background:var(--ge-blue);
  width:45%;
  min-height:100vh;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
  padding:3rem 2.5rem;
  position:relative;
  overflow:hidden;
}
.panel-left::before{
  content:'';
  position:absolute;inset:0;
  background:radial-gradient(ellipse at 20% 80%, rgba(214,145,0,.18) 0%, transparent 60%);
  pointer-events:none;
}
.panel-left-inner{position:relative;z-index:1;text-align:center;max-width:340px}
.panel-left .logo{margin-bottom:2.5rem}
.panel-left .logo img{height:52px;filter:brightness(0) invert(1)}
.panel-left h2{
  font-family:'Manrope',sans-serif;font-weight:800;font-size:2rem;
  color:#fff;line-height:1.15;margin-bottom:1rem
}
.panel-left .tagline{color:rgba(255,255,255,.75);font-size:1rem;line-height:1.6}
.panel-left .divider{width:48px;height:3px;background:var(--ge-gold);border-radius:2px;margin:1.75rem auto}
.panel-left .badge{
  display:inline-flex;align-items:center;gap:.5rem;
  background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);
  border-radius:var(--radius-pill);padding:.45rem 1rem;margin-top:1.5rem;
  color:rgba(255,255,255,.9);font-size:.82rem;font-family:'Manrope',sans-serif;font-weight:600;letter-spacing:.04em
}
.panel-left .badge::before{content:'🌿';font-size:.9rem}

/* ── Right panel ── */
.panel-right{
  flex:1;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:3rem 2rem;
  background:var(--ge-ivory);
}
.form-card{width:100%;max-width:420px}
.form-card .welcome{font-family:'Manrope',sans-serif;font-weight:800;font-size:1.75rem;color:var(--ge-blue);margin-bottom:.3rem}
.form-card .sub{color:#777;font-size:.95rem;margin-bottom:2rem}

.form-group{margin-bottom:1.25rem}
.form-group label{display:block;font-size:.88rem;font-weight:600;color:var(--ge-slate);margin-bottom:.4rem}
.form-group input{
  width:100%;padding:.78rem 1.1rem;border:1.5px solid #dde0e8;border-radius:12px;
  font-family:'Karla',sans-serif;font-size:.98rem;color:var(--ge-slate);
  background:#fff;transition:border-color .2s,box-shadow .2s
}
.form-group input:focus{outline:none;border-color:var(--ge-blue);box-shadow:0 0 0 3px rgba(0,83,164,.1)}

.btn-login{
  display:block;width:100%;padding:.85rem;
  background:var(--ge-blue);color:#fff;border:none;border-radius:var(--radius-pill);
  font-family:'Manrope',sans-serif;font-weight:700;font-size:1rem;cursor:pointer;
  transition:background .2s,transform .1s;margin-top:.5rem
}
.btn-login:hover{background:var(--ge-blue-dark)}
.btn-login:active{transform:scale(.98)}

.alert-error{
  background:#fef0f0;border:1px solid #f5c6cb;border-radius:12px;
  padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.9rem;color:#c0392b;
  display:flex;align-items:flex-start;gap:.6rem
}
.alert-error::before{content:'⚠';flex-shrink:0}

.form-footer{margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid #e0ddd7;text-align:center;font-size:.82rem;color:#999}

/* ── Responsive ── */
@media(max-width:768px){
  body{flex-direction:column}
  .panel-left{width:100%;min-height:auto;padding:2.5rem 1.5rem}
  .panel-left h2{font-size:1.5rem}
  .panel-right{padding:2rem 1.25rem}
}
</style>
</head>
<body>

<!-- Left panel -->
<div class="panel-left">
  <div class="panel-left-inner">
    <div class="logo">
      <img src="../assets/goodearth-logo.png" alt="GoodEarth" onerror="this.style.display='none'">
    </div>
    <h2>Project Updates Portal</h2>
    <div class="divider"></div>
    <p class="tagline">Manage construction progress updates, publish site reports, and keep homeowners informed.</p>
    <span class="badge">GoodEarth Communities</span>
  </div>
</div>

<!-- Right panel -->
<div class="panel-right">
  <div class="form-card">
    <h1 class="welcome">Welcome back</h1>
    <p class="sub">Sign in to your CMS account</p>

    <?php if ($error): ?>
      <div class="alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif ?>

    <form method="POST" novalidate>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email"
               value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               autocomplete="email" autofocus required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn-login">Sign In</button>
    </form>

    <div class="form-footer">
      Forgot your password? Contact your administrator to reset it.
    </div>
  </div>
</div>

</body>
</html>
