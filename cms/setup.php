<?php
/**
 * GoodEarth CMS — One-Time Database Setup
 * =========================================
 * !!  DELETE THIS FILE AFTER SETUP IS COMPLETE  !!
 * =========================================
 */

require_once __DIR__ . '/includes/config.php';

$error   = '';
$success = '';
$already = false;

// ── Handle POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host  = trim($_POST['db_host']  ?? DB_HOST);
    $db_name  = trim($_POST['db_name']  ?? '');
    $db_user  = trim($_POST['db_user']  ?? '');
    $db_pass  = $_POST['db_pass']  ?? '';
    $adm_email  = trim($_POST['adm_email']  ?? '');
    $adm_name   = trim($_POST['adm_name']   ?? '');
    $adm_pass   = $_POST['adm_pass']   ?? '';

    // Basic validation
    if (!$db_name || !$db_user || !$adm_email || !$adm_name || !$adm_pass) {
        $error = 'All fields are required.';
    } elseif (!filter_var($adm_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid admin email address.';
    } elseif (strlen($adm_pass) < 8) {
        $error = 'Admin password must be at least 8 characters.';
    } else {
        // Attempt DB connection with supplied credentials
        try {
            $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // ── Create tables ────────────────────────────────────────────────
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id              INT AUTO_INCREMENT PRIMARY KEY,
                    email           VARCHAR(255) UNIQUE NOT NULL,
                    display_name    VARCHAR(255) NOT NULL,
                    password_hash   VARCHAR(255) NOT NULL,
                    role            ENUM('admin','staff') DEFAULT 'staff',
                    assigned_projects JSON DEFAULT ('[]'),
                    is_active       TINYINT(1) DEFAULT 1,
                    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    created_by      INT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS blogs (
                    id               INT AUTO_INCREMENT PRIMARY KEY,
                    project          VARCHAR(50) NOT NULL,
                    project_name     VARCHAR(255),
                    title            VARCHAR(255),
                    subtitle         VARCHAR(255),
                    blog_date        DATE,
                    progress_overview TEXT,
                    highlights       JSON DEFAULT ('[]'),
                    drone_url        VARCHAR(500),
                    view360_url      VARCHAR(500),
                    gallery          JSON DEFAULT ('[]'),
                    team_quote       TEXT,
                    whats_next       TEXT,
                    status           ENUM('draft','published') DEFAULT 'draft',
                    author_id        INT,
                    author_name      VARCHAR(255),
                    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    published_at     TIMESTAMP NULL,
                    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // ── Check if admin already exists ─────────────────────────────
            $check = $pdo->prepare("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
            $check->execute();
            $cnt = (int) $check->fetch()['cnt'];

            if ($cnt > 0) {
                $already = true;
                $success = 'Database tables already set up and an admin account already exists.';
            } else {
                // ── Create admin user ─────────────────────────────────────
                $hash = password_hash($adm_pass, PASSWORD_DEFAULT);
                $ins  = $pdo->prepare("
                    INSERT INTO users (email, display_name, password_hash, role, assigned_projects, is_active)
                    VALUES (:email, :name, :hash, 'admin', '[]', 1)
                ");
                $ins->execute([
                    ':email' => $adm_email,
                    ':name'  => $adm_name,
                    ':hash'  => $hash,
                ]);

                $success = 'Setup complete! Admin account created successfully.';
            }

        } catch (PDOException $e) {
            $error = 'Database error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>GoodEarth CMS — Setup</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --ge-blue:#0053a4;--ge-gold:#d69100;--ge-ivory:#f5f1eb;
  --ge-slate:#3a3a3a;--ge-blue-dark:#003d7a;--radius-xl:28px;
}
body{font-family:'Karla',sans-serif;background:var(--ge-ivory);color:var(--ge-slate);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
.card{background:#fff;border-radius:var(--radius-xl);box-shadow:0 8px 40px rgba(0,83,164,.12);max-width:560px;width:100%;padding:2.5rem}
.logo{display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem}
.logo img{height:40px}
.logo span{font-family:'Manrope',sans-serif;font-weight:800;font-size:1.1rem;color:var(--ge-blue)}
h1{font-family:'Manrope',sans-serif;font-weight:800;font-size:1.6rem;color:var(--ge-blue);margin-bottom:.25rem}
p.sub{color:#666;font-size:.95rem;margin-bottom:1.75rem}
.warning{background:#fff3cd;border:1px solid #ffc107;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.75rem;font-size:.9rem;color:#856404;line-height:1.5}
.warning strong{display:block;font-size:1rem;margin-bottom:.25rem}
.section-label{font-family:'Manrope',sans-serif;font-weight:700;font-size:.8rem;letter-spacing:.08em;text-transform:uppercase;color:#888;margin:1.5rem 0 .75rem;padding-bottom:.4rem;border-bottom:1px solid #eee}
label{display:block;font-size:.88rem;font-weight:600;color:var(--ge-slate);margin-bottom:.3rem}
input[type=text],input[type=email],input[type=password]{width:100%;padding:.7rem 1rem;border:1.5px solid #ddd;border-radius:10px;font-family:'Karla',sans-serif;font-size:.95rem;color:var(--ge-slate);transition:border-color .2s;margin-bottom:1rem}
input:focus{outline:none;border-color:var(--ge-blue)}
.btn{display:inline-block;width:100%;padding:.85rem;background:var(--ge-blue);color:#fff;border:none;border-radius:50px;font-family:'Manrope',sans-serif;font-weight:700;font-size:1rem;cursor:pointer;transition:background .2s;margin-top:.5rem}
.btn:hover{background:var(--ge-blue-dark)}
.alert{border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.25rem;font-size:.93rem}
.alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
.alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.success-links{margin-top:1.25rem;display:flex;gap:.75rem;flex-wrap:wrap}
.success-links a{display:inline-block;padding:.55rem 1.25rem;border-radius:50px;font-family:'Manrope',sans-serif;font-weight:700;font-size:.9rem;text-decoration:none;background:var(--ge-blue);color:#fff}
.success-links a.outline{background:transparent;border:2px solid var(--ge-blue);color:var(--ge-blue)}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <img src="../assets/goodearth-logo.png" alt="GoodEarth" onerror="this.style.display='none'">
    <span>GoodEarth CMS</span>
  </div>

  <?php if ($success): ?>
    <h1>Setup <?= $already ? 'Skipped' : 'Complete' ?>!</h1>
    <p class="sub">Your CMS is ready to use.</p>
    <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="warning">
      <strong>Security Notice</strong>
      Please delete <code>setup.php</code> from your server immediately via cPanel File Manager or FTP to prevent unauthorized access.
    </div>
    <div class="success-links">
      <a href="login.php">Go to Login</a>
      <a href="login.php" class="outline">Admin Dashboard</a>
    </div>

  <?php else: ?>
    <h1>Database Setup</h1>
    <p class="sub">Configure your database and create the first admin account.</p>

    <div class="warning">
      <strong>⚠ Delete This File After Setup</strong>
      This script is a security risk if left on your server. Once setup is complete, delete <code>setup.php</code> via cPanel File Manager or FTP immediately.
    </div>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif ?>

    <form method="POST" autocomplete="off">
      <div class="section-label">Database Credentials</div>

      <label for="db_host">Database Host</label>
      <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost', ENT_QUOTES, 'UTF-8') ?>" required>

      <label for="db_name">Database Name</label>
      <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? DB_NAME, ENT_QUOTES, 'UTF-8') ?>" required>

      <label for="db_user">Database Username</label>
      <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? DB_USER, ENT_QUOTES, 'UTF-8') ?>" required>

      <label for="db_pass">Database Password</label>
      <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? DB_PASS, ENT_QUOTES, 'UTF-8') ?>" required>

      <div class="section-label">Admin Account</div>

      <label for="adm_email">Admin Email</label>
      <input type="email" id="adm_email" name="adm_email" value="<?= htmlspecialchars($_POST['adm_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

      <label for="adm_name">Admin Display Name</label>
      <input type="text" id="adm_name" name="adm_name" value="<?= htmlspecialchars($_POST['adm_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

      <label for="adm_pass">Admin Password (min 8 characters)</label>
      <input type="password" id="adm_pass" name="adm_pass" minlength="8" required>

      <button type="submit" class="btn">Run Setup</button>
    </form>
  <?php endif ?>
</div>
</body>
</html>
