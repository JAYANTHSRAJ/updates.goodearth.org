<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_admin();

$pdo        = db();
$current    = get_cms_user();
$tab        = $_GET['tab']    ?? 'staff';
$filter_proj = $_GET['project'] ?? '';
$filter_stat = $_GET['status']  ?? '';

// ── POST Actions (PRG pattern) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Add staff
    if ($action === 'add_staff') {
        $name  = trim($_POST['name']  ?? '');
        $email = trim($_POST['email'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $role  = in_array($_POST['role'] ?? '', ['admin','staff']) ? $_POST['role'] : 'staff';

        if (!$name || !$email || !$pass) {
            set_flash('All fields are required to add a staff member.', 'error');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('Invalid email address.', 'error');
        } elseif (strlen($pass) < 6) {
            set_flash('Password must be at least 6 characters.', 'error');
        } else {
            try {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, display_name, password_hash, role, assigned_projects, is_active, created_by) VALUES (:e,:n,:h,:r,'[]',1,:cb)");
                $stmt->execute([':e'=>$email,':n'=>$name,':h'=>$hash,':r'=>$role,':cb'=>$current['id']]);
                set_flash('Staff member added successfully.', 'success');
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    set_flash('A user with that email already exists.', 'error');
                } else {
                    set_flash('Database error: ' . $ex->getMessage(), 'error');
                }
            }
        }
        header('Location: admin.php?tab=staff');
        exit;
    }

    // Assign projects
    if ($action === 'assign_projects') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $proj = $_POST['projects'] ?? [];
        // Validate project keys
        $valid = array_keys(PROJECTS);
        $proj  = array_filter($proj, fn($p) => in_array($p, $valid));
        $proj  = array_values($proj);
        $json  = json_encode($proj);
        $stmt  = $pdo->prepare("UPDATE users SET assigned_projects = :p WHERE id = :id");
        $stmt->execute([':p'=>$json,':id'=>$uid]);
        set_flash('Projects assigned.', 'success');
        header('Location: admin.php?tab=staff');
        exit;
    }

    // Reset password
    if ($action === 'reset_password') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $pass = $_POST['new_password'] ?? '';
        if (strlen($pass) < 6) {
            set_flash('Password must be at least 6 characters.', 'error');
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :id");
            $stmt->execute([':h'=>$hash,':id'=>$uid]);
            set_flash('Password reset successfully.', 'success');
        }
        header('Location: admin.php?tab=staff');
        exit;
    }

    // Toggle active
    if ($action === 'toggle_active') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        // Prevent admin from deactivating themselves
        if ($uid === $current['id']) {
            set_flash('You cannot deactivate your own account.', 'error');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = :id");
            $stmt->execute([':id'=>$uid]);
            set_flash('User status updated.', 'success');
        }
        header('Location: admin.php?tab=staff');
        exit;
    }

    // Delete blog
    if ($action === 'delete_blog') {
        $bid = (int)($_POST['blog_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM blogs WHERE id = :id");
        $stmt->execute([':id'=>$bid]);
        set_flash('Blog post deleted.', 'success');
        header('Location: admin.php?tab=blogs');
        exit;
    }

    // Fallback
    header('Location: admin.php');
    exit;
}

// ── Fetch Data ──────────────────────────────────────────────────────────────

// Staff list
$users = $pdo->query("SELECT * FROM users ORDER BY role DESC, display_name ASC")->fetchAll();

// Staff summary
$total_staff  = count($users);
$active_staff = count(array_filter($users, fn($u) => $u['is_active']));

// Blogs list
$blog_sql = "SELECT * FROM blogs WHERE 1=1";
$params   = [];
if ($filter_proj && array_key_exists($filter_proj, PROJECTS)) {
    $blog_sql .= " AND project = :proj";
    $params[':proj'] = $filter_proj;
}
if ($filter_stat && in_array($filter_stat, ['draft','published'])) {
    $blog_sql .= " AND status = :stat";
    $params[':stat'] = $filter_stat;
}
$blog_sql .= " ORDER BY updated_at DESC";
$bstmt = $pdo->prepare($blog_sql);
$bstmt->execute($params);
$blogs = $bstmt->fetchAll();

$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard — GoodEarth CMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --ge-blue:#0053a4;--ge-gold:#d69100;--ge-maroon:#874545;--ge-olive:#666b4a;
  --ge-ivory:#f5f1eb;--ge-blue-dark:#003d7a;--ge-ivory-deep:#ece8e0;--ge-slate:#3a3a3a;
  --radius-xl:28px;--radius-pill:50px;--sidebar-w:240px;
}
body{font-family:'Karla',sans-serif;background:var(--ge-ivory);color:var(--ge-slate);display:flex;min-height:100vh}

/* Sidebar */
.sidebar{width:var(--sidebar-w);background:var(--ge-blue);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;padding:0}
.sidebar-logo{padding:1.5rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar-logo img{height:32px;filter:brightness(0) invert(1)}
.sidebar-logo .logo-text{color:rgba(255,255,255,.7);font-size:.78rem;font-family:'Manrope',sans-serif;font-weight:600;margin-top:.4rem;display:block}
.sidebar-nav{flex:1;padding:1rem 0}
.nav-label{font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.4);padding:.75rem 1.25rem .4rem}
.nav-item{display:flex;align-items:center;gap:.6rem;padding:.65rem 1.25rem;color:rgba(255,255,255,.75);text-decoration:none;font-size:.9rem;font-family:'Manrope',sans-serif;font-weight:600;border-radius:0;transition:background .15s,color .15s;position:relative}
.nav-item:hover,.nav-item.active{background:rgba(255,255,255,.12);color:#fff}
.nav-item.active::before{content:'';position:absolute;left:0;top:20%;height:60%;width:3px;background:var(--ge-gold);border-radius:0 2px 2px 0}
.nav-icon{font-size:1.05rem;width:20px;text-align:center}
.sidebar-footer{padding:1rem 1.25rem;border-top:1px solid rgba(255,255,255,.1)}
.sidebar-user{display:flex;align-items:center;gap:.6rem;margin-bottom:.75rem}
.sidebar-avatar{width:32px;height:32px;border-radius:50%;background:var(--ge-gold);display:flex;align-items:center;justify-content:center;font-family:'Manrope',sans-serif;font-weight:800;font-size:.85rem;color:#fff}
.sidebar-user-info .name{font-family:'Manrope',sans-serif;font-weight:700;font-size:.85rem;color:#fff}
.sidebar-user-info .role{font-size:.72rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.06em}
.btn-logout{display:block;width:100%;padding:.5rem;text-align:center;background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.15);border-radius:8px;text-decoration:none;font-size:.82rem;font-family:'Manrope',sans-serif;font-weight:600;transition:background .15s}
.btn-logout:hover{background:rgba(255,255,255,.15);color:#fff}

/* Main */
.main{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column}
.topbar{background:#fff;border-bottom:1px solid #e8e5e0;padding:.9rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.topbar-title{font-family:'Manrope',sans-serif;font-weight:800;font-size:1.2rem;color:var(--ge-blue)}
.topbar-actions{display:flex;gap:.75rem}
.content{padding:2rem;flex:1}

/* Cards */
.stat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem;margin-bottom:2rem}
.stat-card{background:#fff;border-radius:18px;padding:1.4rem 1.5rem;border:1px solid #eee}
.stat-label{font-size:.78rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#999;margin-bottom:.4rem}
.stat-val{font-family:'Manrope',sans-serif;font-weight:800;font-size:2rem;color:var(--ge-blue)}

/* Tabs */
.tab-bar{display:flex;gap:.25rem;background:#fff;border-radius:16px;padding:.35rem;margin-bottom:1.75rem;border:1px solid #eee;width:fit-content}
.tab-btn{padding:.5rem 1.35rem;border-radius:12px;text-decoration:none;font-family:'Manrope',sans-serif;font-weight:700;font-size:.9rem;color:#888;transition:background .15s,color .15s}
.tab-btn.active{background:var(--ge-blue);color:#fff}

/* Table */
.table-wrap{background:#fff;border-radius:18px;border:1px solid #eee;overflow:hidden}
.table-header{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.5rem;border-bottom:1px solid #f0ede8}
.table-header h3{font-family:'Manrope',sans-serif;font-weight:800;font-size:1rem;color:var(--ge-slate)}
table{width:100%;border-collapse:collapse}
th{font-family:'Manrope',sans-serif;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#aaa;padding:.75rem 1.25rem;text-align:left;border-bottom:1px solid #f0ede8;background:#fafaf9}
td{padding:.85rem 1.25rem;border-bottom:1px solid #f5f3f0;vertical-align:middle;font-size:.9rem}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafaf8}

/* Badges */
.badge{display:inline-flex;align-items:center;padding:.25rem .65rem;border-radius:var(--radius-pill);font-size:.75rem;font-family:'Manrope',sans-serif;font-weight:700}
.badge-admin{background:#e8f0fb;color:var(--ge-blue)}
.badge-staff{background:#f0ede8;color:#666}
.badge-active{background:#d4edda;color:#155724}
.badge-inactive{background:#f8d7da;color:#721c24}
.badge-published{background:#d4edda;color:#155724}
.badge-draft{background:#fff3cd;color:#856404}

/* Project pills */
.proj-pills{display:flex;flex-wrap:wrap;gap:.3rem}
.proj-pill{display:inline-block;padding:.2rem .55rem;border-radius:var(--radius-pill);font-size:.72rem;font-family:'Manrope',sans-serif;font-weight:700;color:#fff}

/* Action buttons */
.actions{display:flex;gap:.35rem;flex-wrap:wrap}
.btn-sm{display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .7rem;border-radius:8px;font-size:.78rem;font-family:'Manrope',sans-serif;font-weight:700;border:none;cursor:pointer;text-decoration:none;transition:opacity .15s}
.btn-sm:hover{opacity:.8}
.btn-primary{background:var(--ge-blue);color:#fff}
.btn-gold{background:var(--ge-gold);color:#fff}
.btn-danger{background:var(--ge-maroon);color:#fff}
.btn-outline{background:transparent;border:1.5px solid #ddd;color:var(--ge-slate)}
.btn-success{background:#2a7a4b;color:#fff}

/* Main CTA button */
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.25rem;border-radius:var(--radius-pill);font-family:'Manrope',sans-serif;font-weight:700;font-size:.88rem;border:none;cursor:pointer;text-decoration:none;transition:background .15s}
.btn-blue{background:var(--ge-blue);color:#fff}
.btn-blue:hover{background:var(--ge-blue-dark)}

/* Flash */
.flash{border-radius:12px;padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.9rem;display:flex;align-items:center;gap:.6rem}
.flash-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724}
.flash-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24}

/* Filter bar */
.filter-bar{display:flex;gap:.75rem;flex-wrap:wrap;margin-bottom:1.25rem;align-items:center}
.filter-bar select,.filter-bar a{padding:.45rem .9rem;border-radius:10px;border:1.5px solid #ddd;font-family:'Karla',sans-serif;font-size:.88rem;color:var(--ge-slate);text-decoration:none;background:#fff}
.filter-bar select:focus{outline:none;border-color:var(--ge-blue)}
.filter-bar .clear-link{color:var(--ge-blue);font-weight:600;border:none;background:none;padding:.45rem .5rem;cursor:pointer;font-size:.88rem;font-family:'Karla',sans-serif}

/* Modal overlay */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal{background:#fff;border-radius:var(--radius-xl);padding:2rem;width:100%;max-width:480px;position:relative;max-height:90vh;overflow-y:auto}
.modal h3{font-family:'Manrope',sans-serif;font-weight:800;font-size:1.2rem;color:var(--ge-blue);margin-bottom:1.25rem}
.modal-close{position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.25rem;cursor:pointer;color:#999;line-height:1}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-size:.85rem;font-weight:600;margin-bottom:.3rem;color:var(--ge-slate)}
.form-group input,.form-group select{width:100%;padding:.65rem .9rem;border:1.5px solid #ddd;border-radius:10px;font-family:'Karla',sans-serif;font-size:.9rem;color:var(--ge-slate)}
.form-group input:focus,.form-group select:focus{outline:none;border-color:var(--ge-blue)}
.form-group .help{font-size:.78rem;color:#999;margin-top:.25rem}

/* Project checkbox grid */
.proj-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-top:.5rem}
.proj-check{display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;border-radius:10px;border:1.5px solid #eee;cursor:pointer;transition:border-color .15s}
.proj-check:hover{border-color:var(--ge-blue)}
.proj-check input[type=checkbox]{accent-color:var(--ge-blue)}
.proj-check label{font-size:.85rem;font-weight:600;cursor:pointer;color:var(--ge-slate)}
.proj-check.checked{border-color:var(--ge-blue);background:#f0f5fb}

/* No data */
.empty-row td{text-align:center;color:#aaa;padding:2.5rem;font-style:italic}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <img src="../assets/goodearth-logo.png" alt="GoodEarth" onerror="this.style.display='none'">
    <span class="logo-text">CMS Admin</span>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Navigation</div>
    <a href="admin.php?tab=staff" class="nav-item <?= $tab==='staff'?'active':'' ?>">
      <span class="nav-icon">👥</span> Staff
    </a>
    <a href="admin.php?tab=blogs" class="nav-item <?= $tab==='blogs'?'active':'' ?>">
      <span class="nav-icon">📝</span> Blogs
    </a>
    <a href="dashboard.php" class="nav-item">
      <span class="nav-icon">🏠</span> My Dashboard
    </a>
    <a href="create-blog.php" class="nav-item">
      <span class="nav-icon">✏️</span> New Blog
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?= strtoupper(substr($current['display_name'],0,1)) ?></div>
      <div class="sidebar-user-info">
        <div class="name"><?= sanitize($current['display_name']) ?></div>
        <div class="role"><?= sanitize($current['role']) ?></div>
      </div>
    </div>
    <a href="logout.php" class="btn-logout">Sign Out</a>
  </div>
</aside>

<!-- Main -->
<main class="main">
  <div class="topbar">
    <div class="topbar-title">Admin Dashboard</div>
    <div class="topbar-actions">
      <?php if ($tab === 'staff'): ?>
        <button class="btn btn-blue" onclick="openModal('addStaffModal')">+ Add Staff</button>
      <?php else: ?>
        <a href="create-blog.php" class="btn btn-blue">+ New Blog</a>
      <?php endif ?>
    </div>
  </div>

  <div class="content">

    <!-- Flash message -->
    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
        <?= sanitize($flash['message']) ?>
      </div>
    <?php endif ?>

    <!-- Tabs -->
    <div class="tab-bar">
      <a href="admin.php?tab=staff" class="tab-btn <?= $tab==='staff'?'active':'' ?>">Staff</a>
      <a href="admin.php?tab=blogs" class="tab-btn <?= $tab==='blogs'?'active':'' ?>">Blogs</a>
    </div>

    <!-- ═══════════ STAFF TAB ═══════════ -->
    <?php if ($tab === 'staff'): ?>

      <!-- Stats -->
      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-label">Total Staff</div>
          <div class="stat-val"><?= $total_staff ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Active Staff</div>
          <div class="stat-val"><?= $active_staff ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Projects</div>
          <div class="stat-val"><?= count(PROJECTS) ?></div>
        </div>
      </div>

      <!-- Staff table -->
      <div class="table-wrap">
        <div class="table-header">
          <h3>Staff Members</h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Assigned Projects</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr class="empty-row"><td colspan="6">No staff members found.</td></tr>
            <?php else: foreach ($users as $u):
              $assigned = json_decode_safe($u['assigned_projects'] ?? '[]');
            ?>
            <tr>
              <td><strong><?= sanitize($u['display_name']) ?></strong></td>
              <td><?= sanitize($u['email']) ?></td>
              <td><span class="badge badge-<?= $u['role'] ?>"><?= strtoupper($u['role']) ?></span></td>
              <td>
                <div class="proj-pills">
                  <?php if (empty($assigned)): ?>
                    <span style="color:#bbb;font-size:.8rem">None</span>
                  <?php else: foreach ($assigned as $pid): ?>
                    <span class="proj-pill" style="background:<?= sanitize(project_badge_color($pid)) ?>"><?= sanitize(project_name($pid)) ?></span>
                  <?php endforeach; endif ?>
                </div>
              </td>
              <td><span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
              <td>
                <div class="actions">
                  <button class="btn-sm btn-primary" onclick="openAssign(<?= $u['id'] ?>, <?= htmlspecialchars(json_encode($assigned), ENT_QUOTES, 'UTF-8') ?>)">Assign Projects</button>
                  <button class="btn-sm btn-gold" onclick="openReset(<?= $u['id'] ?>, '<?= sanitize($u['display_name']) ?>')">Reset Password</button>
                  <?php if ($u['id'] !== $current['id']): ?>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Toggle active status for <?= sanitize($u['display_name']) ?>?')">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="btn-sm <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                        <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                      </button>
                    </form>
                  <?php endif ?>
                </div>
              </td>
            </tr>
            <?php endforeach; endif ?>
          </tbody>
        </table>
      </div>

    <!-- ═══════════ BLOGS TAB ═══════════ -->
    <?php else: ?>

      <!-- Filters -->
      <form class="filter-bar" method="GET">
        <input type="hidden" name="tab" value="blogs">
        <select name="project" onchange="this.form.submit()">
          <option value="">All Projects</option>
          <?php foreach (PROJECTS as $pid => $pdata): ?>
            <option value="<?= $pid ?>" <?= $filter_proj===$pid?'selected':'' ?>><?= sanitize($pdata['name']) ?></option>
          <?php endforeach ?>
        </select>
        <select name="status" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <option value="published" <?= $filter_stat==='published'?'selected':'' ?>>Published</option>
          <option value="draft" <?= $filter_stat==='draft'?'selected':'' ?>>Draft</option>
        </select>
        <?php if ($filter_proj || $filter_stat): ?>
          <a href="admin.php?tab=blogs" class="clear-link">Clear filters</a>
        <?php endif ?>
      </form>

      <!-- Blogs table -->
      <div class="table-wrap">
        <div class="table-header">
          <h3>Blog Posts (<?= count($blogs) ?>)</h3>
        </div>
        <table>
          <thead>
            <tr>
              <th>Project</th>
              <th>Title</th>
              <th>Status</th>
              <th>Author</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($blogs)): ?>
              <tr class="empty-row"><td colspan="6">No blog posts found.</td></tr>
            <?php else: foreach ($blogs as $b): ?>
            <tr>
              <td>
                <span class="proj-pill" style="background:<?= sanitize(project_badge_color($b['project'])) ?>">
                  <?= sanitize(project_name($b['project'])) ?>
                </span>
              </td>
              <td>
                <strong><?= sanitize($b['title'] ?: '(Untitled)') ?></strong>
                <?php if ($b['subtitle']): ?>
                  <div style="font-size:.8rem;color:#999;margin-top:.15rem"><?= sanitize($b['subtitle']) ?></div>
                <?php endif ?>
              </td>
              <td><span class="badge badge-<?= $b['status'] ?>"><?= strtoupper($b['status']) ?></span></td>
              <td><?= sanitize($b['author_name'] ?: 'Unknown') ?></td>
              <td><?= $b['blog_date'] ? sanitize(format_date($b['blog_date'])) : '<span style="color:#bbb">—</span>' ?></td>
              <td>
                <div class="actions">
                  <a href="blog-view.php?id=<?= $b['id'] ?>" class="btn-sm btn-outline" target="_blank">View</a>
                  <a href="create-blog.php?id=<?= $b['id'] ?>" class="btn-sm btn-primary">Edit</a>
                  <form method="POST" style="display:inline" onsubmit="return confirm('Delete this blog post? This cannot be undone.')">
                    <input type="hidden" name="action" value="delete_blog">
                    <input type="hidden" name="blog_id" value="<?= $b['id'] ?>">
                    <button type="submit" class="btn-sm btn-danger">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; endif ?>
          </tbody>
        </table>
      </div>

    <?php endif ?>
  </div><!-- /content -->
</main>

<!-- ═══ Modal: Add Staff ═══ -->
<div class="modal-overlay" id="addStaffModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('addStaffModal')">✕</button>
    <h3>Add Staff Member</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add_staff">
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" name="name" required placeholder="e.g. Priya Sharma">
      </div>
      <div class="form-group">
        <label>Email Address</label>
        <input type="email" name="email" required placeholder="priya@goodearth.in">
      </div>
      <div class="form-group">
        <label>Temporary Password</label>
        <input type="password" name="password" required minlength="6" placeholder="Min 6 characters">
        <div class="help">Staff member should change this on first login.</div>
      </div>
      <div class="form-group">
        <label>Role</label>
        <select name="role">
          <option value="staff">Staff</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      <button type="submit" class="btn btn-blue" style="width:100%">Add Staff Member</button>
    </form>
  </div>
</div>

<!-- ═══ Modal: Assign Projects ═══ -->
<div class="modal-overlay" id="assignModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('assignModal')">✕</button>
    <h3>Assign Projects</h3>
    <form method="POST" id="assignForm">
      <input type="hidden" name="action" value="assign_projects">
      <input type="hidden" name="user_id" id="assignUserId">
      <div class="proj-grid" id="projCheckGrid">
        <?php foreach (PROJECTS as $pid => $pdata): ?>
          <div class="proj-check" id="projcheck_<?= $pid ?>" onclick="toggleCheck('<?= $pid ?>')">
            <input type="checkbox" name="projects[]" value="<?= $pid ?>" id="chk_<?= $pid ?>">
            <label for="chk_<?= $pid ?>"><?= sanitize($pdata['name']) ?></label>
          </div>
        <?php endforeach ?>
      </div>
      <button type="submit" class="btn btn-blue" style="width:100%;margin-top:1.25rem">Save Assignments</button>
    </form>
  </div>
</div>

<!-- ═══ Modal: Reset Password ═══ -->
<div class="modal-overlay" id="resetModal">
  <div class="modal">
    <button class="modal-close" onclick="closeModal('resetModal')">✕</button>
    <h3>Reset Password — <span id="resetUserName"></span></h3>
    <form method="POST">
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="user_id" id="resetUserId">
      <div class="form-group">
        <label>New Password</label>
        <input type="password" name="new_password" required minlength="6" placeholder="Min 6 characters">
      </div>
      <button type="submit" class="btn btn-blue" style="width:100%">Reset Password</button>
    </form>
  </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('show') }
function closeModal(id){ document.getElementById(id).classList.remove('show') }

// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(function(el){
  el.addEventListener('click', function(e){ if(e.target===el) el.classList.remove('show') })
})

function openAssign(uid, assigned){
  document.getElementById('assignUserId').value = uid
  // Reset all checkboxes
  document.querySelectorAll('#projCheckGrid input[type=checkbox]').forEach(function(cb){
    cb.checked = assigned.indexOf(cb.value) > -1
    var wrap = document.getElementById('projcheck_' + cb.value)
    if(wrap) wrap.classList.toggle('checked', cb.checked)
  })
  openModal('assignModal')
}

function toggleCheck(pid){
  var cb = document.getElementById('chk_' + pid)
  cb.checked = !cb.checked
  var wrap = document.getElementById('projcheck_' + pid)
  if(wrap) wrap.classList.toggle('checked', cb.checked)
}

// Prevent double-toggle when clicking label directly
document.querySelectorAll('.proj-check input').forEach(function(cb){
  cb.addEventListener('click', function(e){ e.stopPropagation() })
  cb.addEventListener('change', function(){
    var wrap = document.getElementById('projcheck_' + cb.value)
    if(wrap) wrap.classList.toggle('checked', cb.checked)
  })
})

function openReset(uid, name){
  document.getElementById('resetUserId').value = uid
  document.getElementById('resetUserName').textContent = name
  openModal('resetModal')
}
</script>
</body>
</html>
