<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

session_init();

$blog_id = (int)($_GET['id'] ?? 0);
if ($blog_id < 1) {
    http_response_code(404);
    die('Blog post not found.');
}

$pdo  = db();
$stmt = $pdo->prepare("SELECT * FROM blogs WHERE id = :id");
$stmt->execute([':id' => $blog_id]);
$blog = $stmt->fetch();

if (!$blog) {
    http_response_code(404);
    die('Blog post not found.');
}

// Draft access control: only author or admin can view drafts
$is_draft    = ($blog['status'] === 'draft');
$current     = get_cms_user();
$is_admin    = ($current && $current['role'] === 'admin');
$is_author   = ($current && (int)$current['id'] === (int)$blog['author_id']);
$can_preview = ($is_admin || $is_author);

if ($is_draft && !$can_preview) {
    http_response_code(403);
    die('This post is not yet published.');
}

// ── Decode JSON fields ─────────────────────────────────────────────────────
$highlights = json_decode_safe($blog['highlights'] ?? '[]');
$gallery    = json_decode_safe($blog['gallery']    ?? '[]');

// ── Hero image ────────────────────────────────────────────────────────────
$hero_image = !empty($gallery) ? $gallery[0] : '';
// Project default colours
$project_color = project_badge_color($blog['project']);

// ── Recent updates for sidebar ─────────────────────────────────────────────
$recent_stmt = $pdo->prepare("
    SELECT id, title, subtitle, blog_date, project
    FROM blogs
    WHERE project = :proj AND status = 'published' AND id != :id
    ORDER BY blog_date DESC, created_at DESC
    LIMIT 5
");
$recent_stmt->execute([':proj' => $blog['project'], ':id' => $blog_id]);
$recent_blogs = $recent_stmt->fetchAll();

// ── Formatted data ─────────────────────────────────────────────────────────
$formatted_date     = $blog['blog_date']    ? format_date($blog['blog_date'])    : '';
$published_date     = $blog['published_at'] ? format_date(substr($blog['published_at'],0,10)) : '';
$proj_name          = project_name($blog['project']);
$proj_location      = project_location($blog['project']);
$proj_listing_url   = project_listing_url($blog['project']);
$page_title         = ($blog['title'] && $blog['subtitle'])
    ? sanitize($blog['title']) . ' — ' . sanitize($blog['subtitle'])
    : sanitize($blog['title'] ?: $proj_name . ' Update');
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $page_title ?> | GoodEarth</title>
<meta name="description" content="<?= sanitize($blog['progress_overview'] ? substr(strip_tags($blog['progress_overview']),0,160) : $proj_name . ' construction update') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Karla:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --ge-blue:#0053a4;--ge-gold:#d69100;--ge-maroon:#874545;--ge-olive:#666b4a;
  --ge-ivory:#f5f1eb;--ge-blue-dark:#003d7a;--ge-ivory-deep:#ece8e0;--ge-slate:#3a3a3a;
  --radius-xl:28px;--radius-pill:50px;
  --proj-color:<?= sanitize($project_color) ?>;
}
html{scroll-behavior:smooth}
body{font-family:'Karla',sans-serif;background:var(--ge-ivory);color:var(--ge-slate);font-size:16px;line-height:1.7}

/* ── Draft banner ── */
.draft-banner{background:var(--ge-gold);color:#fff;text-align:center;padding:.65rem 1rem;font-family:'Manrope',sans-serif;font-weight:700;font-size:.88rem;position:sticky;top:0;z-index:200}
.draft-banner a{color:#fff;text-decoration:underline;margin-left:.75rem}

/* ── Site header ── */
.site-header{background:#fff;border-bottom:1px solid #e8e5e0;position:sticky;top:<?= $is_draft && $can_preview ? '40px' : '0' ?>;z-index:100}
.header-inner{max-width:1200px;margin:0 auto;padding:.85rem 2rem;display:flex;align-items:center;justify-content:space-between}
.header-logo img{height:36px}
.header-nav{display:flex;gap:1.5rem;align-items:center}
.header-nav a{text-decoration:none;font-family:'Manrope',sans-serif;font-weight:600;font-size:.88rem;color:var(--ge-slate);transition:color .15s}
.header-nav a:hover{color:var(--ge-blue)}
.btn-back{display:inline-flex;align-items:center;gap:.4rem;padding:.4rem 1rem;border-radius:var(--radius-pill);background:var(--ge-blue);color:#fff;text-decoration:none;font-family:'Manrope',sans-serif;font-weight:700;font-size:.82rem;transition:background .15s}
.btn-back:hover{background:var(--ge-blue-dark)}

/* ── Hero ── */
.hero{position:relative;height:520px;overflow:hidden;background:linear-gradient(135deg,var(--ge-blue-dark),#1a4a7a)}
@media(max-width:768px){.hero{height:320px}}
.hero-img{width:100%;height:100%;object-fit:cover;display:block;opacity:.7}
.hero-overlay{position:absolute;inset:0;background:linear-gradient(to bottom,rgba(0,0,0,.15) 0%,rgba(0,0,0,.6) 100%)}
.hero-content{position:absolute;bottom:0;left:0;right:0;padding:2.5rem 2rem 2.5rem;max-width:1200px;margin:0 auto}
@media(min-width:1200px){.hero-content{padding:2.5rem calc((100vw - 1200px)/2 + 2rem) 2.5rem}}
.breadcrumb{display:flex;align-items:center;gap:.4rem;margin-bottom:1rem;flex-wrap:wrap}
.breadcrumb a,.breadcrumb span{font-size:.82rem;color:rgba(255,255,255,.7);text-decoration:none;font-family:'Manrope',sans-serif;font-weight:600}
.breadcrumb a:hover{color:#fff}
.breadcrumb .sep{color:rgba(255,255,255,.4)}
.hero-tag{display:inline-flex;align-items:center;gap:.4rem;background:var(--proj-color);color:#fff;padding:.3rem .85rem;border-radius:var(--radius-pill);font-family:'Manrope',sans-serif;font-size:.75rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;margin-bottom:.85rem}
.hero h1{font-family:'Manrope',sans-serif;font-weight:800;font-size:clamp(1.6rem,4vw,2.8rem);color:#fff;line-height:1.15;margin-bottom:.5rem;text-shadow:0 2px 12px rgba(0,0,0,.3)}
.hero-sub{font-size:1.05rem;color:rgba(255,255,255,.85);font-style:italic;margin-bottom:.75rem}
.hero-meta{display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap}
.hero-date,.hero-author{font-size:.82rem;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:.35rem}
.hero-date svg,.hero-author svg{opacity:.7}

/* ── Main layout ── */
.page-wrap{max-width:1200px;margin:0 auto;padding:3rem 2rem;display:grid;grid-template-columns:1fr 300px;gap:3rem}
@media(max-width:900px){.page-wrap{grid-template-columns:1fr;padding:2rem 1.25rem}.sidebar{display:none}}

/* ── Article ── */
article{}
.section-block{margin-bottom:2.75rem}
.section-block h2{font-family:'Manrope',sans-serif;font-weight:800;font-size:1.25rem;color:var(--ge-blue);margin-bottom:1rem;padding-bottom:.6rem;border-bottom:2px solid var(--ge-ivory-deep);display:flex;align-items:center;gap:.5rem}
.section-block h2 .icon{font-size:1.1rem}
.section-block p{color:var(--ge-slate);line-height:1.75;margin-bottom:1rem}
.section-block p:last-child{margin-bottom:0}

/* Highlights */
.highlights-wrap{background:#fff;border-radius:20px;border:1px solid #e8e5e0;overflow:hidden}
.highlights-list{}
.highlight-item{display:grid;grid-template-columns:220px 1fr;border-bottom:1px solid #f0ede8}
.highlight-item:last-child{border-bottom:none}
.hl-label{background:var(--ge-ivory-deep);padding:1rem 1.25rem;font-family:'Manrope',sans-serif;font-weight:800;font-size:.88rem;color:var(--ge-blue);display:flex;align-items:center}
.hl-text{padding:1rem 1.25rem;font-size:.9rem;color:var(--ge-slate);line-height:1.65;display:flex;align-items:center}
@media(max-width:600px){.highlight-item{grid-template-columns:1fr}.hl-label{border-bottom:1px solid #e8e5e0}}

/* Video embed */
.video-section{margin-bottom:1.5rem}
.video-section h3{font-family:'Manrope',sans-serif;font-weight:700;font-size:1rem;color:var(--ge-slate);margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem}
.video-wrap{position:relative;width:100%;padding-bottom:56.25%;border-radius:16px;overflow:hidden;background:#000}
.video-wrap iframe{position:absolute;inset:0;width:100%;height:100%;border:none}

/* Gallery */
.gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.75rem}
.gallery-thumb-link{display:block;border-radius:12px;overflow:hidden;aspect-ratio:1;cursor:pointer;transition:transform .2s,box-shadow .2s}
.gallery-thumb-link:hover{transform:scale(1.02);box-shadow:0 6px 20px rgba(0,0,0,.15)}
.gallery-thumb-link img{width:100%;height:100%;object-fit:cover;display:block}

/* Team quote */
.quote-block{background:var(--ge-ivory-deep);border-left:4px solid var(--proj-color);border-radius:0 16px 16px 0;padding:1.5rem 1.75rem;margin:1.5rem 0}
.quote-block p{font-size:1.05rem;font-style:italic;color:var(--ge-slate);line-height:1.75;margin-bottom:.5rem}
.quote-block cite{font-size:.82rem;color:#999;font-style:normal;font-family:'Manrope',sans-serif;font-weight:700}

/* What's next */
.whats-next-block{background:linear-gradient(135deg,var(--ge-blue),var(--ge-blue-dark));border-radius:20px;padding:1.75rem 2rem;color:#fff}
.whats-next-block h2{font-family:'Manrope',sans-serif;font-weight:800;font-size:1.15rem;color:#fff;margin-bottom:.75rem;border:none;padding:0;display:flex;align-items:center;gap:.5rem}
.whats-next-block p{color:rgba(255,255,255,.85);line-height:1.75;margin-bottom:0}

/* ── Sidebar ── */
.sidebar{}
.sidebar-widget{background:#fff;border-radius:20px;border:1px solid #e8e5e0;padding:1.5rem;margin-bottom:1.5rem}
.sidebar-widget h3{font-family:'Manrope',sans-serif;font-weight:800;font-size:.92rem;color:var(--ge-blue);margin-bottom:1rem;padding-bottom:.6rem;border-bottom:1px solid #f0ede8}
.recent-item{display:block;text-decoration:none;padding:.6rem 0;border-bottom:1px solid #f5f3f0}
.recent-item:last-child{border-bottom:none;padding-bottom:0}
.recent-item:hover .ri-title{color:var(--ge-blue);text-decoration:underline}
.ri-date{font-size:.75rem;color:#aaa;font-family:'Manrope',sans-serif;font-weight:600;margin-bottom:.2rem}
.ri-title{font-size:.88rem;font-weight:600;color:var(--ge-slate);line-height:1.4}
.ri-sub{font-size:.78rem;color:#aaa;margin-top:.15rem;font-style:italic}
.nav-links{display:flex;flex-direction:column;gap:.5rem}
.nav-link{display:flex;align-items:center;gap:.5rem;padding:.6rem .85rem;border-radius:10px;background:var(--ge-ivory);text-decoration:none;font-family:'Manrope',sans-serif;font-weight:700;font-size:.85rem;color:var(--ge-slate);transition:background .15s,color .15s}
.nav-link:hover{background:var(--ge-blue);color:#fff}
.nav-link .arrow{margin-left:auto;font-size:.8rem;opacity:.5}

/* ── Lightbox ── */
.lb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:1000;align-items:center;justify-content:center}
.lb-overlay.open{display:flex}
.lb-img-wrap{position:relative;max-width:90vw;max-height:90vh;display:flex;align-items:center;justify-content:center}
.lb-img-wrap img{max-width:90vw;max-height:90vh;border-radius:8px;object-fit:contain;display:block}
.lb-close{position:fixed;top:1.25rem;right:1.5rem;color:#fff;font-size:1.75rem;cursor:pointer;background:none;border:none;line-height:1;opacity:.75;transition:opacity .15s}
.lb-close:hover{opacity:1}
.lb-nav{position:fixed;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.12);border:none;color:#fff;font-size:1.5rem;cursor:pointer;padding:1rem .75rem;border-radius:8px;transition:background .15s}
.lb-nav:hover{background:rgba(255,255,255,.2)}
.lb-prev{left:.75rem}
.lb-next{right:.75rem}
.lb-counter{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.6);font-size:.82rem;font-family:'Manrope',sans-serif}

/* ── Footer ── */
.site-footer{background:var(--ge-slate);color:rgba(255,255,255,.6);text-align:center;padding:2rem;font-size:.82rem;margin-top:3rem}
.site-footer a{color:rgba(255,255,255,.6);text-decoration:none}
.site-footer a:hover{color:#fff}
.site-footer .footer-logo{height:28px;filter:brightness(0) invert(1);opacity:.5;margin-bottom:.75rem}
</style>
</head>
<body>

<?php if ($is_draft && $can_preview): ?>
<div class="draft-banner">
  Draft Preview — This post is not published yet.
  <a href="create-blog.php?id=<?= $blog_id ?>">Edit Post</a>
  <?php if ($is_admin): ?>
    <a href="admin.php?tab=blogs">Admin Panel</a>
  <?php endif ?>
</div>
<?php endif ?>

<!-- Header -->
<header class="site-header">
  <div class="header-inner">
    <a href="../index.html" class="header-logo">
      <img src="../assets/goodearth-logo.png" alt="GoodEarth" onerror="this.style.display='none'">
    </a>
    <nav class="header-nav">
      <a href="<?= sanitize($proj_listing_url) ?>">← All Updates</a>
      <?php if (is_logged_in()): ?>
        <a href="dashboard.php">Dashboard</a>
      <?php endif ?>
    </nav>
  </div>
</header>

<!-- Hero -->
<section class="hero">
  <?php if ($hero_image): ?>
    <img src="<?= sanitize($hero_image) ?>" alt="<?= sanitize($blog['title'] ?? '') ?>" class="hero-img">
  <?php endif ?>
  <div class="hero-overlay"></div>

  <div class="hero-content">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
      <a href="../index.html">Home</a>
      <span class="sep">›</span>
      <a href="<?= sanitize($proj_listing_url) ?>"><?= sanitize($proj_name) ?></a>
      <span class="sep">›</span>
      <span>Construction Update</span>
    </nav>

    <div class="hero-tag"><?= sanitize($proj_name) ?></div>

    <?php if ($blog['title']): ?>
      <h1>
        <?= sanitize($blog['title']) ?>
        <?php if ($blog['subtitle']): ?>
          <br><span style="font-weight:400;font-size:.65em;opacity:.9"><?= sanitize($blog['subtitle']) ?></span>
        <?php endif ?>
      </h1>
    <?php endif ?>

    <div class="hero-meta">
      <?php if ($formatted_date): ?>
        <span class="hero-date">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <?= sanitize($formatted_date) ?>
        </span>
      <?php endif ?>
      <?php if ($blog['author_name']): ?>
        <span class="hero-author">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <?= sanitize($blog['author_name']) ?>
        </span>
      <?php endif ?>
    </div>
  </div>
</section>

<!-- Main content -->
<div class="page-wrap">
  <article>

    <!-- Progress Overview -->
    <?php if ($blog['progress_overview']): ?>
    <div class="section-block">
      <h2><span class="icon">📋</span>Progress Overview</h2>
      <?php foreach (explode("\n\n", $blog['progress_overview']) as $para): ?>
        <?php $p = trim($para); if ($p): ?>
          <p><?= nl2br(sanitize($p)) ?></p>
        <?php endif ?>
      <?php endforeach ?>
    </div>
    <?php endif ?>

    <!-- Key Highlights -->
    <?php if (!empty($highlights)): ?>
    <div class="section-block">
      <h2><span class="icon">✨</span>Key Highlights</h2>
      <div class="highlights-wrap">
        <div class="highlights-list">
          <?php foreach ($highlights as $hl):
            if (!$hl['bold'] && !$hl['text']) continue;
          ?>
          <div class="highlight-item">
            <div class="hl-label"><?= sanitize($hl['bold'] ?? '') ?></div>
            <div class="hl-text"><?= nl2br(sanitize($hl['text'] ?? '')) ?></div>
          </div>
          <?php endforeach ?>
        </div>
      </div>
    </div>
    <?php endif ?>

    <!-- Drone Video -->
    <?php if ($blog['drone_url']): ?>
    <div class="section-block">
      <h2><span class="icon">🚁</span>Drone's Eye View</h2>
      <div class="video-section">
        <div class="video-wrap">
          <iframe src="<?= sanitize($blog['drone_url']) ?>"
                  title="Drone view of <?= sanitize($proj_name) ?>"
                  loading="lazy"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                  allowfullscreen></iframe>
        </div>
      </div>
    </div>
    <?php endif ?>

    <!-- 360 View -->
    <?php if ($blog['view360_url']): ?>
    <div class="section-block">
      <h2><span class="icon">🔄</span>360° View</h2>
      <div class="video-section">
        <div class="video-wrap">
          <iframe src="<?= sanitize($blog['view360_url']) ?>"
                  title="360 view of <?= sanitize($proj_name) ?>"
                  loading="lazy"
                  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                  allowfullscreen></iframe>
        </div>
      </div>
    </div>
    <?php endif ?>

    <!-- Gallery -->
    <?php if (!empty($gallery)): ?>
    <div class="section-block">
      <h2><span class="icon">📷</span>Site Gallery</h2>
      <div class="gallery-grid" id="gallery">
        <?php foreach ($gallery as $i => $img): ?>
        <a href="<?= sanitize($img) ?>" class="gallery-thumb-link" onclick="openLightbox(<?= $i ?>,event)">
          <img src="<?= sanitize($img) ?>" alt="Site photo <?= $i+1 ?>" loading="lazy">
        </a>
        <?php endforeach ?>
      </div>
    </div>
    <?php endif ?>

    <!-- Team Quote -->
    <?php if ($blog['team_quote']): ?>
    <div class="section-block">
      <h2><span class="icon">💬</span>From the Site Team</h2>
      <div class="quote-block">
        <p>"<?= nl2br(sanitize($blog['team_quote'])) ?>"</p>
        <cite>— <?= sanitize($blog['author_name'] ?: 'GoodEarth Site Team') ?></cite>
      </div>
    </div>
    <?php endif ?>

    <!-- What's Next -->
    <?php if ($blog['whats_next']): ?>
    <div class="whats-next-block">
      <h2>
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        What's Next
      </h2>
      <?php foreach (explode("\n\n", $blog['whats_next']) as $para): ?>
        <?php $p = trim($para); if ($p): ?>
          <p><?= nl2br(sanitize($p)) ?></p>
        <?php endif ?>
      <?php endforeach ?>
    </div>
    <?php endif ?>

  </article>

  <!-- Sidebar -->
  <aside class="sidebar">

    <?php if (!empty($recent_blogs)): ?>
    <div class="sidebar-widget">
      <h3>Recent Updates</h3>
      <?php foreach ($recent_blogs as $rb): ?>
      <a href="blog-view.php?id=<?= $rb['id'] ?>" class="recent-item">
        <div class="ri-date"><?= $rb['blog_date'] ? sanitize(format_date($rb['blog_date'])) : '' ?></div>
        <div class="ri-title"><?= sanitize($rb['title'] ?: 'Untitled Update') ?></div>
        <?php if ($rb['subtitle']): ?>
          <div class="ri-sub"><?= sanitize($rb['subtitle']) ?></div>
        <?php endif ?>
      </a>
      <?php endforeach ?>
    </div>
    <?php endif ?>

    <div class="sidebar-widget">
      <h3>Project Navigation</h3>
      <div class="nav-links">
        <a href="<?= sanitize($proj_listing_url) ?>" class="nav-link">
          All <?= sanitize($proj_name) ?> Updates
          <span class="arrow">→</span>
        </a>
        <?php foreach (PROJECTS as $pid => $pdata):
          if ($pid === $blog['project']) continue;
        ?>
        <a href="<?= sanitize(project_listing_url($pid)) ?>" class="nav-link">
          <?= sanitize($pdata['name']) ?>
          <span class="arrow">→</span>
        </a>
        <?php endforeach ?>
      </div>
    </div>

    <div class="sidebar-widget" style="background:var(--ge-blue);border-color:var(--ge-blue)">
      <h3 style="color:#fff;border-color:rgba(255,255,255,.15)">About <?= sanitize($proj_name) ?></h3>
      <p style="font-size:.85rem;color:rgba(255,255,255,.8);line-height:1.6;margin-bottom:0"><?= sanitize($proj_location) ?></p>
    </div>

  </aside>
</div><!-- /page-wrap -->

<!-- Lightbox -->
<div class="lb-overlay" id="lightbox" onclick="closeLightboxOnOverlay(event)">
  <button class="lb-close" onclick="closeLightbox()">✕</button>
  <button class="lb-nav lb-prev" onclick="lbNav(-1)">‹</button>
  <div class="lb-img-wrap">
    <img id="lbImg" src="" alt="">
  </div>
  <button class="lb-nav lb-next" onclick="lbNav(1)">›</button>
  <div class="lb-counter" id="lbCounter"></div>
</div>

<!-- Footer -->
<footer class="site-footer">
  <img src="../assets/goodearth-logo.png" alt="GoodEarth" class="footer-logo" onerror="this.style.display='none'"><br>
  &copy; <?= date('Y') ?> GoodEarth. All rights reserved.
  &nbsp;·&nbsp;
  <a href="<?= sanitize($proj_listing_url) ?>">Project Updates</a>
</footer>

<script>
// ── Lightbox ──
var lbImages = <?= json_encode(array_values($gallery)) ?>;
var lbIndex  = 0;

function openLightbox(i, e){
  if(e){ e.preventDefault(); }
  lbIndex = i;
  showLbImage();
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox(){
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
function closeLightboxOnOverlay(e){
  if(e.target === document.getElementById('lightbox')) closeLightbox();
}
function showLbImage(){
  document.getElementById('lbImg').src = lbImages[lbIndex];
  document.getElementById('lbCounter').textContent = (lbIndex+1) + ' / ' + lbImages.length;
}
function lbNav(dir){
  lbIndex = (lbIndex + dir + lbImages.length) % lbImages.length;
  showLbImage();
}
document.addEventListener('keydown', function(e){
  var lb = document.getElementById('lightbox');
  if(!lb.classList.contains('open')) return;
  if(e.key === 'Escape') closeLightbox();
  if(e.key === 'ArrowLeft')  lbNav(-1);
  if(e.key === 'ArrowRight') lbNav(1);
});
</script>
</body>
</html>
