<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$pdo     = db();
$current = get_cms_user();
$flash   = get_flash();
$today   = date('l, F j, Y');

// Get full user record for assigned_projects
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$stmt->execute([':id' => $current['id']]);
$user = $stmt->fetch();

$assigned_projects = json_decode_safe($user['assigned_projects'] ?? '[]');
$is_admin          = ($current['role'] === 'admin');

// If admin, show all projects
$my_projects = $is_admin ? array_keys(PROJECTS) : $assigned_projects;

// Blog counts per project
$blog_counts = [];
if (!empty($my_projects)) {
    $placeholders = implode(',', array_fill(0, count($my_projects), '?'));
    $cstmt = $pdo->prepare("SELECT project, COUNT(*) as cnt FROM blogs WHERE project IN ($placeholders) GROUP BY project");
    $cstmt->execute($my_projects);
    foreach ($cstmt->fetchAll() as $row) {
        $blog_counts[$row['project']] = (int)$row['cnt'];
    }
}

// Recent blogs
$recent_sql = $is_admin
    ? "SELECT * FROM blogs ORDER BY updated_at DESC LIMIT 10"
    : "SELECT * FROM blogs WHERE author_id = :uid ORDER BY updated_at DESC LIMIT 10";
$rstmt = $pdo->prepare($recent_sql);
if (!$is_admin) {
    $rstmt->execute([':uid' => $current['id']]);
} else {
    $rstmt->execute();
}
$recent_blogs = $rstmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — GoodEarth CMS</title>
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
.sidebar{width:var(--sidebar-w);background:var(--ge-blue);display:flex;flex-direction:column;position:fixed;top:0;left:0;height:100vh;z-index:100;padding:0}
.sidebar-logo{padding:1.5rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.1)}
.sidebar-logo img{height:32px;filter:brightness(0) invert(1)}
.sidebar-logo .logo-text{color:rgba(255,255,255,.7);font-size:.78rem;font-family:'Manrope',sans-serif;font-weight:600;margin-top:.4rem;display:block}
.sidebar-nav{flex:1;padding:1rem 0}
.nav-label{font-size:.68rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.4);padding:.75rem 1.25rem .4rem}
.nav-item{display:flex;align-items:center;gap:.6rem;padding:.65rem 1.25rem;color:rgba(255,255,255,.75);text-decoration:none;font-size:.9rem;font-family:'Manrope',sans-serif;font-weight:600;transition:background .15s,color .15s;position:relative}
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
.main{margin-left:var(--sidebar-w);flex:1;min-height:100vh;display:flex;flex-direction:column}
.topbar{background:#fff;border-bottom:1px solid #e8e5e0;padding:.9rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.topbar-title{font-family:'Manrope',sans-serif;font-weight:800;font-size:1.2rem;color:var(--ge-blue)}
.content{padding:2rem;flex:1}
.welcome-card{background:linear-gradient(135deg,var(--ge-blue),var(--ge-blue-dark));border-radius:var(--radius-xl);padding:2rem 2.25rem;margin-bottom:2rem;position:relative;overflow:hidden}
.welcome-card::after{content:'';position:absolute;right:-20px;top:-20px;width:160px;height:160px;background:radial-gradient(circle,rgba(214,145,0,.25),transparent 70%);pointer-events:none}
.welcome-card h1{font-family:'Manrope',sans-serif;font-weight:800;font-size:1.65rem;color:#fff;margin-bottom:.35rem}
.welcome-card .date-str{color:rgba(255,255,255,.65);font-size:.92rem}
.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.25rem;border-radius:var(--radius-pill);font-family:'Manrope',sans-serif;font-weight:700;font-size:.88rem;border:none;cursor:pointer;text-decoration:none;transition:background .15s,color .15s}
.btn-white{background:#fff;color:var(--ge-blue)}
.btn-white:hover{background:var(--ge-ivory)}
.btn-blue{background:var(--ge-blue);color:#fff}
.btn-blue:hover{background:var(--ge-blue-dark)}
.section-title{font-family:'Manrope',sans-serif;font-weight:800;font-size:1.05rem;color:var(--ge-slate);margin-bottom:1rem}
.projects-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.1rem;margin-bottom:2.5rem}
.project-card{background:#fff;border-radius:18px;border:1px solid #eee;padding:1.4rem;display:flex;flex-direction:column;gap:.75rem;transition:box-shadow .2s,transform .2s}
.project-card:hover{box-shadow:0 6px 24px rgba(0,83,164,.1);transform:translateY(-2px)}
.proj-accent{height:4px;border-radius:4px;width:36px}
.proj-card-name{font-family:'Manrope',sans-serif;font-weight:800;font-size:.98rem;color:var(--ge-slate)}
.proj-card-loc{font-size:.8rem;color:#999}
.proj-card-count{font-size:.8rem;color:#aaa;font-weight:500}
.proj-card-count span{font-weight:700;color:var(--ge-blue)}
.proj-card-action{display:inline-flex;align-items:center;gap:.35rem;padding:.4rem .9rem;border-radius:var(--radius-pill);background:var(--ge-blue);color:#fff;text-decoration:none;font-family:'Manrope',sans-serif;font-weight:700;font-size:.8rem;margin-top:.25rem;transition:background .15s}
.proj-card-action:hover{background:var(--ge-blue-dark)}
.table-wrap{background:#fff;border-radius:18px;border:1px solid #eee;overflow:hidden}
.table-header{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.5rem;border-bottom:1px solid #f0ede8}
.table-header h3{font-family:'Manrope',sans-serif;font-weight:800;font-size:1rem;color:var(--ge-slate)}
table{width:100%;border-collapse:collapse}
th{font-family:'Manrope',sans-serif;font-size:.75rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#aaa;padding:.75rem 1.25rem;text-align:left;border-bottom:1px solid #f0ede8;background:#fafaf9}
td{padding:.85rem 1.25rem;border-bottom:1px solid #f5f3f0;vertical-align:middle;font-size:.9rem}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafaf8}
.badge{display:inline-flex;align-items:center;padding:.25rem .65rem;border-radius:var(--radius-pill);font-size:.75rem;font-family:'Manrope',sans-serif;font-weight:700}
.badge-published{background:#d4edda;color:#155724}
.badge-draft{background:#fff3cd;color:#856404}
.proj-pill{display:inline-block;padding:.2rem .55rem;border-radius:var(--radius-pill);font-size:.72rem;font-family:'Manrope',sans-serif;font-weight:700;color:#fff}
.actions{display:flex;gap:.35rem}
.btn-sm{display:inline-flex;align-items:center;gap:.25rem;padding:.3rem .7rem;border-radius:8px;font-size:.78rem;font-family:'Manrope',sans-serif;font-weight:700;border:none;cursor:pointer;text-decoration:none;transition:opacity .15s}
.btn-sm:hover{opacity:.8}
.btn-primary{background:var(--ge-blue);color:#fff}
.btn-outline{background:transparent;border:1.5px solid #ddd;color:var(--ge-slate)}
.empty-row td{text-align:center;color:#aaa;padding:2.5rem;font-style:italic}
.flash{border-radius:12px;padding:.85rem 1.1rem;margin-bottom:1.25rem;font-size:.9rem}
.flash-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724}
.flash-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24}
.no-projects{background:#fff;border-radius:18px;border:1px solid #eee;padding:2.5rem;text-align:center;color:#aaa;margin-bottom:2rem}
.no-projects strong{display:block;font-family:'Manrope',sans-serif;font-size:1rem;color:#888;margin-bottom:.35rem}
</style>
</head>
<body>
<aside class="sidebar">
  <div class="sidebar-logo">
    <img src="../assets/goodearth-logo.png" alt="GoodEarth" onerror="this.style.display='none'">
    <span class="logo-text">CMS Portal</span>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Navigation</div>
    <a href="dashboard.php" class="nav-item active">
      <span class="nav-icon">🏠</span> My Dashboard
    </a>
    <a href="create-blog.php" class="nav-item">
      <span class="nav-icon">✏️</span> New Blog
    </a>
    <?php if ($is_admin): ?>
    <a href="admin.php" class="nav-item">
      <span class="nav-icon">⚙️</span> Admin Panel
    </a>
    <?php endif ?>
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

<main class="main">
  <div class="topbar">
    <div class="topbar-title">My Dashboard</div>
    <a href="create-blog.php" class="btn btn-blue">+ New Blog Post</a>
  </div>

  <div class="content">
    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
    <?php endif ?>

    <!-- Welcome -->
    <div class="welcome-card">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem">
        <div>
          <h1>Hello, <?= sanitize($current['display_name']) ?>!</h1>
          <div class="date-str"><?= sanitize($today) ?></div>
        </div>
        <a href="create-blog.php" class="btn btn-white">+ New Blog Post</a>
      </div>
    </div>

    <!-- My Projects -->
    <div class="section-title">My Projects</div>

    <?php if (empty($my_projects)): ?>
      <div class="no-projects">
        <strong>No projects assigned yet</strong>
        Contact an administrator to get projects assigned to your account.
      </div>
    <?php else: ?>
      <div class="projects-grid">
        <?php foreach ($my_projects as $pid):
          if (!isset(PROJECTS[$pid])) continue;
          $pdata = PROJECTS[$pid];
          $count = $blog_counts[$pid] ?? 0;
          $color = project_badge_color($pid);
        ?>
        <div class="project-card">
          <div class="proj-accent" style="background:<?= sanitize($color) ?>"></div>
          <div>
            <div class="proj-card-name"><?= sanitize($pdata['name']) ?></div>
            <div class="proj-card-loc"><?= sanitize($pdata['location']) ?></div>
          </div>
          <div class="proj-card-count"><span><?= $count ?></span> blog<?= $count === 1 ? '' : 's' ?> published</div>
          <a href="create-blog.php?project=<?= urlencode($pid) ?>" class="proj-card-action">+ New Blog</a>
        </div>
        <?php endforeach ?>
      </div>
    <?php endif ?>

    <!-- Recent blogs -->
    <div class="table-wrap">
      <div class="table-header">
        <h3><?= $is_admin ? 'All Recent Blogs' : 'My Recent Blogs' ?></h3>
        <a href="admin.php?tab=blogs" class="btn-sm btn-outline" style="text-decoration:none"><?= $is_admin ? 'View All' : '' ?></a>
      </div>
      <table>
        <thead>
          <tr>
            <th>Project</th>
            <th>Title</th>
            <th>Status</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recent_blogs)): ?>
            <tr class="empty-row"><td colspan="5">No blogs yet. <a href="create-blog.php" style="color:var(--ge-blue)">Create your first one!</a></td></tr>
          <?php else: foreach ($recent_blogs as $b): ?>
          <tr>
            <td>
              <span class="proj-pill" style="background:<?= sanitize(project_badge_color($b['project'])) ?>">
                <?= sanitize(project_name($b['project'])) ?>
              </span>
            </td>
            <td>
              <strong><?= sanitize($b['title'] ?: '(Untitled)') ?></strong>
              <?php if ($b['subtitle']): ?>
                <div style="font-size:.78rem;color:#aaa;margin-top:.1rem"><?= sanitize($b['subtitle']) ?></div>
              <?php endif ?>
            </td>
            <td><span class="badge badge-<?= $b['status'] ?>"><?= strtoupper($b['status']) ?></span></td>
            <td><?= $b['blog_date'] ? sanitize(format_date($b['blog_date'])) : '<span style="color:#bbb">—</span>' ?></td>
            <td>
              <div class="actions">
                <a href="create-blog.php?id=<?= $b['id'] ?>" class="btn-sm btn-primary">Edit</a>
                <a href="blog-view.php?id=<?= $b['id'] ?>" class="btn-sm btn-outline" target="_blank">View</a>
              </div>
            </td>
          </tr>
          <?php endforeach; endif ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</body>
</html>
