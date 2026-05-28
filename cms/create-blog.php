<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$pdo     = db();
$current = get_cms_user();
$is_admin = ($current['role'] === 'admin');

// Get full user record for assigned_projects
$ustmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
$ustmt->execute([':id' => $current['id']]);
$user = $ustmt->fetch();
$assigned = json_decode_safe($user['assigned_projects'] ?? '[]');
$allowed_projects = $is_admin ? array_keys(PROJECTS) : $assigned;

// Edit mode?
$blog_id  = (int)($_GET['id'] ?? 0);
$blog     = null;
$edit_mode = false;

if ($blog_id > 0) {
    $bstmt = $pdo->prepare("SELECT * FROM blogs WHERE id = :id");
    $bstmt->execute([':id' => $blog_id]);
    $blog = $bstmt->fetch();

    if (!$blog) {
        set_flash('Blog post not found.', 'error');
        header('Location: dashboard.php');
        exit;
    }
    // Only author or admin can edit
    if (!$is_admin && (int)$blog['author_id'] !== $current['id']) {
        set_flash('You do not have permission to edit this post.', 'error');
        header('Location: dashboard.php');
        exit;
    }
    $edit_mode = true;
}

// Pre-select project from URL param
$preselect_project = $_GET['project'] ?? ($blog['project'] ?? '');

// ── Handle POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action          = $_POST['action'] ?? 'save_draft'; // save_draft | publish
    $project         = $_POST['project']         ?? '';
    $title           = trim($_POST['title']       ?? '');
    $subtitle        = trim($_POST['subtitle']    ?? '');
    $blog_date       = $_POST['blog_date']        ?? date('Y-m-d');
    $progress_overview = trim($_POST['progress_overview'] ?? '');
    $drone_url       = trim($_POST['drone_url']   ?? '');
    $view360_url     = trim($_POST['view360_url'] ?? '');
    $team_quote      = trim($_POST['team_quote']  ?? '');
    $whats_next      = trim($_POST['whats_next']  ?? '');

    // Validate project
    if (!array_key_exists($project, PROJECTS) || (!$is_admin && !in_array($project, $allowed_projects))) {
        set_flash('Invalid project selected.', 'error');
        header('Location: ' . ($edit_mode ? "create-blog.php?id=$blog_id" : 'create-blog.php'));
        exit;
    }

    // Highlights: bold[] + desc[]
    $hl_bolds = $_POST['hl_bold'] ?? [];
    $hl_descs = $_POST['hl_desc'] ?? [];
    $highlights = [];
    for ($i = 0; $i < count($hl_bolds); $i++) {
        $bold = trim($hl_bolds[$i] ?? '');
        $desc = trim($hl_descs[$i] ?? '');
        if ($bold !== '' || $desc !== '') {
            $highlights[] = ['bold' => $bold, 'text' => $desc];
        }
    }

    // Gallery: existing (from hidden inputs) + new (from JS-uploaded hidden inputs)
    $existing_gallery = [];
    if ($edit_mode && $blog) {
        $existing_gallery = json_decode_safe($blog['gallery'] ?? '[]');
    }
    // Kept existing images
    $kept_gallery = $_POST['gallery_existing'] ?? [];
    // New images uploaded via AJAX
    $new_gallery  = $_POST['gallery_new']      ?? [];
    // Filter valid
    $gallery = array_values(array_filter(array_merge(
        array_filter($kept_gallery, fn($u) => strpos($u, '/') !== false),
        array_filter($new_gallery,  fn($u) => strpos($u, '/') !== false)
    )));

    // Convert YouTube URLs to embed
    $drone_embed   = youtube_embed_url($drone_url);
    $view360_embed = youtube_embed_url($view360_url);

    // Status
    $status = ($action === 'publish') ? 'published' : 'draft';

    $proj_name = PROJECTS[$project]['name'];

    if ($edit_mode) {
        // Preserve published_at if already set
        $pub_at = $blog['published_at'];
        if ($status === 'published' && !$pub_at) {
            $pub_at_sql = date('Y-m-d H:i:s');
        } else {
            $pub_at_sql = $pub_at;
        }
        $stmt = $pdo->prepare("UPDATE blogs SET
            project=:proj, project_name=:pname, title=:title, subtitle=:sub,
            blog_date=:bdate, progress_overview=:po, highlights=:hl,
            drone_url=:du, view360_url=:v3, gallery=:gal,
            team_quote=:tq, whats_next=:wn, status=:st,
            published_at=:pa
            WHERE id=:id
        ");
        $stmt->execute([
            ':proj'  => $project, ':pname' => $proj_name, ':title' => $title,
            ':sub'   => $subtitle, ':bdate' => $blog_date, ':po'   => $progress_overview,
            ':hl'    => json_encode($highlights), ':du'  => $drone_embed,
            ':v3'    => $view360_embed, ':gal'  => json_encode($gallery),
            ':tq'    => $team_quote, ':wn'   => $whats_next, ':st'  => $status,
            ':pa'    => $pub_at_sql, ':id'   => $blog_id,
        ]);
        set_flash('Blog post updated successfully.', 'success');
        header('Location: blog-view.php?id=' . $blog_id);
        exit;
    } else {
        $stmt = $pdo->prepare("INSERT INTO blogs
            (project, project_name, title, subtitle, blog_date, progress_overview,
             highlights, drone_url, view360_url, gallery, team_quote, whats_next,
             status, author_id, author_name, published_at)
            VALUES
            (:proj,:pname,:title,:sub,:bdate,:po,:hl,:du,:v3,:gal,:tq,:wn,:st,:aid,:aname,:pa)
        ");
        $pub_at_sql = ($status === 'published') ? date('Y-m-d H:i:s') : null;
        $stmt->execute([
            ':proj'  => $project, ':pname' => $proj_name, ':title' => $title,
            ':sub'   => $subtitle, ':bdate' => $blog_date, ':po'   => $progress_overview,
            ':hl'    => json_encode($highlights), ':du'  => $drone_embed,
            ':v3'    => $view360_embed, ':gal'  => json_encode($gallery),
            ':tq'    => $team_quote, ':wn'   => $whats_next, ':st'  => $status,
            ':aid'   => $current['id'], ':aname' => $current['display_name'], ':pa' => $pub_at_sql,
        ]);
        $new_id = (int)$pdo->lastInsertId();
        set_flash('Blog post ' . ($status === 'published' ? 'published' : 'saved as draft') . ' successfully.', 'success');
        header('Location: blog-view.php?id=' . $new_id);
        exit;
    }
}

// ── Page data for form ────────────────────────────────────────────────────────
$form = [
    'project'          => $preselect_project,
    'title'            => $blog['title']            ?? '',
    'subtitle'         => $blog['subtitle']          ?? '',
    'blog_date'        => $blog['blog_date']         ?? date('Y-m-d'),
    'progress_overview'=> $blog['progress_overview'] ?? '',
    'drone_url'        => '', // Store embed but show original - reconstruct from embed
    'view360_url'      => '',
    'team_quote'       => $blog['team_quote']        ?? '',
    'whats_next'       => $blog['whats_next']        ?? '',
    'gallery'          => json_decode_safe($blog['gallery']    ?? '[]'),
    'highlights'       => json_decode_safe($blog['highlights'] ?? '[]'),
];

// For edit mode, we show embed URL in the field (users can re-enter if needed)
if ($blog) {
    $form['drone_url']   = $blog['drone_url']   ?? '';
    $form['view360_url'] = $blog['view360_url'] ?? '';
}

// Default title prefixes
$title_prefixes = [];
foreach (PROJECTS as $pid => $pdata) {
    $title_prefixes[$pid] = $pdata['name'] . ' Construction Progress';
}

$flash = get_flash();
$page_title = $edit_mode ? 'Edit Blog Post' : 'New Blog Post';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= sanitize($page_title) ?> — GoodEarth CMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Karla:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --ge-blue:#0053a4;--ge-gold:#d69100;--ge-maroon:#874545;--ge-olive:#666b4a;
  --ge-ivory:#f5f1eb;--ge-blue-dark:#003d7a;--ge-ivory-deep:#ece8e0;--ge-slate:#3a3a3a;
  --radius-xl:28px;--radius-pill:50px;
}
body{font-family:'Karla',sans-serif;background:var(--ge-ivory);color:var(--ge-slate);min-height:100vh;padding-bottom:80px}
.topbar{background:var(--ge-blue);padding:.85rem 2rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100}
.topbar a{color:rgba(255,255,255,.75);text-decoration:none;font-family:'Manrope',sans-serif;font-weight:700;font-size:.88rem;display:flex;align-items:center;gap:.4rem}
.topbar a:hover{color:#fff}
.topbar-title{font-family:'Manrope',sans-serif;font-weight:800;font-size:1rem;color:#fff}
.page-grid{display:grid;grid-template-columns:1fr 280px;gap:1.75rem;max-width:1100px;margin:2rem auto;padding:0 1.5rem}
@media(max-width:900px){.page-grid{grid-template-columns:1fr}.sidebar-col{display:none}}

/* Form sections */
.section-card{background:#fff;border-radius:var(--radius-xl);border:1px solid #eee;margin-bottom:1.25rem;overflow:hidden}
.section-head{display:flex;align-items:center;gap:.75rem;padding:1.1rem 1.5rem;border-bottom:1px solid #f0ede8}
.section-num{width:28px;height:28px;border-radius:50%;background:var(--ge-blue);color:#fff;font-family:'Manrope',sans-serif;font-weight:800;font-size:.85rem;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.section-head h2{font-family:'Manrope',sans-serif;font-weight:800;font-size:.98rem;color:var(--ge-slate)}
.section-body{padding:1.25rem 1.5rem}
label{display:block;font-size:.85rem;font-weight:600;color:var(--ge-slate);margin-bottom:.35rem}
label .opt{font-weight:400;color:#aaa;font-size:.8rem}
input[type=text],input[type=url],input[type=date],select,textarea{
  width:100%;padding:.68rem 1rem;border:1.5px solid #dde0e8;border-radius:10px;
  font-family:'Karla',sans-serif;font-size:.92rem;color:var(--ge-slate);background:#fff;
  transition:border-color .2s
}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--ge-blue)}
textarea{resize:vertical;min-height:100px;line-height:1.6}
.title-row{display:flex;align-items:center;gap:.5rem}
.title-sep{color:#bbb;font-size:1.1rem;flex-shrink:0;padding-top:.1rem}
.title-main{flex:2}
.title-sub{flex:3}

/* Highlights */
.highlight-list{display:flex;flex-direction:column;gap:.75rem}
.highlight-row{display:grid;grid-template-columns:180px 1fr 32px;gap:.5rem;align-items:start;background:#fafaf9;border-radius:12px;padding:.75rem}
.highlight-row input{margin-bottom:0}
.highlight-row textarea{min-height:60px;margin-bottom:0}
.btn-remove{background:none;border:none;cursor:pointer;color:#ccc;font-size:1.1rem;padding:.2rem;border-radius:6px;display:flex;align-items:center;justify-content:center;transition:color .15s}
.btn-remove:hover{color:var(--ge-maroon)}
.btn-add-hl{display:inline-flex;align-items:center;gap:.35rem;margin-top:.75rem;padding:.4rem .9rem;border-radius:var(--radius-pill);background:transparent;border:1.5px dashed var(--ge-blue);color:var(--ge-blue);font-family:'Manrope',sans-serif;font-weight:700;font-size:.82rem;cursor:pointer;transition:background .15s}
.btn-add-hl:hover{background:#f0f5fb}

/* Gallery */
.gallery-upload-zone{border:2px dashed #dde0e8;border-radius:14px;padding:1.75rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;position:relative}
.gallery-upload-zone:hover,.gallery-upload-zone.drag{border-color:var(--ge-blue);background:#f5f8fd}
.gallery-upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
.gallery-upload-zone .upload-icon{font-size:2rem;margin-bottom:.5rem}
.gallery-upload-zone p{font-size:.88rem;color:#aaa}
.gallery-upload-zone .upload-note{font-size:.75rem;color:#bbb;margin-top:.35rem}
.gallery-preview{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:.65rem;margin-top:1rem}
.gallery-thumb{position:relative;border-radius:10px;overflow:hidden;aspect-ratio:1;background:#f0ede8}
.gallery-thumb img{width:100%;height:100%;object-fit:cover}
.gallery-thumb .remove-thumb{position:absolute;top:.3rem;right:.3rem;width:20px;height:20px;border-radius:50%;background:rgba(0,0,0,.55);border:none;color:#fff;font-size:.75rem;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1}
.gallery-thumb .upload-progress{position:absolute;inset:0;background:rgba(0,83,164,.6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;font-weight:700}

/* Sidebar checklist */
.sidebar-col{position:sticky;top:70px;height:fit-content}
.checklist-card{background:#fff;border-radius:var(--radius-xl);border:1px solid #eee;padding:1.5rem}
.checklist-card h3{font-family:'Manrope',sans-serif;font-weight:800;font-size:.95rem;color:var(--ge-blue);margin-bottom:1rem}
.check-item{display:flex;align-items:center;gap:.6rem;padding:.4rem 0;border-bottom:1px solid #f5f3f0;font-size:.85rem;color:#777}
.check-item:last-child{border-bottom:none}
.check-icon{width:18px;height:18px;border-radius:50%;background:#eee;display:flex;align-items:center;justify-content:center;font-size:.6rem;flex-shrink:0;transition:background .2s}
.check-icon.done{background:#2a7a4b;color:#fff}

/* Bottom action bar */
.action-bar{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #e8e5e0;padding:.9rem 2rem;display:flex;align-items:center;justify-content:flex-end;gap:.75rem;z-index:100}
.action-bar .save-info{flex:1;font-size:.82rem;color:#aaa}
.btn-draft{background:#e8e5e0;color:var(--ge-slate);border:none;border-radius:var(--radius-pill);padding:.7rem 1.5rem;font-family:'Manrope',sans-serif;font-weight:700;cursor:pointer;font-size:.9rem;transition:background .15s}
.btn-draft:hover{background:#d8d4cc}
.btn-publish{background:var(--ge-blue);color:#fff;border:none;border-radius:var(--radius-pill);padding:.7rem 1.75rem;font-family:'Manrope',sans-serif;font-weight:800;cursor:pointer;font-size:.9rem;transition:background .15s}
.btn-publish:hover{background:var(--ge-blue-dark)}
.btn-publish:disabled{opacity:.6;cursor:not-allowed}

.flash{border-radius:12px;padding:.85rem 1.1rem;margin-bottom:1rem;font-size:.9rem}
.flash-success{background:#d4edda;border:1px solid #c3e6cb;color:#155724}
.flash-error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24}
</style>
</head>
<body>

<div class="topbar">
  <a href="dashboard.php">← Dashboard</a>
  <span class="topbar-title"><?= sanitize($page_title) ?></span>
  <?php if ($edit_mode): ?>
    <a href="blog-view.php?id=<?= $blog_id ?>" target="_blank">Preview ↗</a>
  <?php else: ?>
    <span></span>
  <?php endif ?>
</div>

<form method="POST" id="blogForm" autocomplete="off">
  <?php if ($edit_mode): ?>
    <input type="hidden" name="blog_id" value="<?= $blog_id ?>">
  <?php endif ?>
  <input type="hidden" name="action" id="formAction" value="save_draft">

  <div class="page-grid">
    <!-- ═══ Main Column ═══ -->
    <div class="main-col">

      <?php if ($flash): ?>
        <div class="flash flash-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= sanitize($flash['message']) ?></div>
      <?php endif ?>

      <!-- 1. Project -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-num">1</div>
          <h2>Project</h2>
        </div>
        <div class="section-body">
          <label for="project">Select Project</label>
          <select name="project" id="project" required onchange="onProjectChange(this.value)">
            <option value="">— Select a project —</option>
            <?php foreach (PROJECTS as $pid => $pdata):
              if (!in_array($pid, $allowed_projects)) continue;
            ?>
            <option value="<?= sanitize($pid) ?>" <?= $form['project']===$pid?'selected':'' ?>>
              <?= sanitize($pdata['name']) ?>
            </option>
            <?php endforeach ?>
          </select>
        </div>
      </div>

      <!-- 2. Title -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-num">2</div>
          <h2>Blog Title</h2>
        </div>
        <div class="section-body">
          <div class="title-row">
            <div class="title-main">
              <label for="title">Title</label>
              <input type="text" id="title" name="title"
                     value="<?= sanitize($form['title']) ?>"
                     placeholder="e.g. Ochre Construction Progress"
                     oninput="updateChecklist()">
            </div>
            <div class="title-sep">—</div>
            <div class="title-sub">
              <label for="subtitle">Subtitle</label>
              <input type="text" id="subtitle" name="subtitle"
                     value="<?= sanitize($form['subtitle']) ?>"
                     placeholder="Second Floors Rise as All Plots Advance"
                     oninput="updateChecklist()">
            </div>
          </div>
        </div>
      </div>

      <!-- 3. Published Date -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-num">3</div>
          <h2>Published Date</h2>
        </div>
        <div class="section-body">
          <label for="blog_date">Date</label>
          <input type="date" id="blog_date" name="blog_date"
                 value="<?= sanitize($form['blog_date']) ?>"
                 oninput="updateChecklist()">
        </div>
      </div>

      <!-- 4. Progress Overview -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-num">4</div>
          <h2>Progress Overview</h2>
        </div>
        <div class="section-body">
          <label for="progress_overview">Describe the overall construction progress this period</label>
          <textarea id="progress_overview" name="progress_overview" rows="5"
                    placeholder="Summarise the construction activity across all plots this update period. Describe what stage work is at, what milestones have been reached, and the general pace of progress."
                    oninput="updateChecklist()"><?= sanitize($form['progress_overview']) ?></textarea>
        </div>
      </div>

      <!-- 5. Key Highlights -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-num">5</div>
          <h2>Key Highlights</h2>
        </div>
        <div class="section-body">
          <div class="highlight-list" id="hlList">
            <?php
            $default_highlights = [['bold'=>'','text'=>''],['bold'=>'','text'=>''],['bold'=>'','text'=>'']];
            $hl_rows = !empty($form['highlights']) ? $form['highlights'] : $default_highlights;
            foreach ($hl_rows as $i => $hl):
            ?>
            <div class="highlight-row" id="hl_<?= $i ?>">
              <input type="text" name="hl_bold[]"
                     value="<?= sanitize($hl['bold']) ?>"
                     placeholder="Bold label (e.g. Eastern Homes)"
                     oninput="updateChecklist()">
              <textarea name="hl_desc[]" rows="2"
                        placeholder="Description of this highlight..."
                        oninput="updateChecklist()"><?= sanitize($hl['text']) ?></textarea>
              <button type="button" class="btn-remove" onclick="removeHighlight('hl_<?= $i ?>')" title="Remove">✕</button>
            </div>
            <?php endforeach ?>
          </div>
          <button type="button" class="btn-add-hl" onclick="addHighlight()">+ Add Highlight</button>
        </div>
      </div>

      <!-- 6. Drone's Eye View -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-num">6</div>
          <h2>Drone's Eye View</h2>
        </div>
        <div class="section-body">
          <label for="drone_url">YouTube URL <span class="opt">(paste any YouTube link)</span></label>
          <input type="url" id="drone_url" name="drone_url"
                 value="<?= sanitize($form['drone_url']) ?>"
                 placeholder="https://www.youtube.com/watch?v=..."
                 oninput="updateChecklist()">
        </div>
      </div>

      <!-- 7. 360° View -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-num">7</div>
          <h2>360° View</h2>
        </div>
        <div class="section-body">
          <label for="view360_url">YouTube URL <span class="opt">(paste any YouTube link)</span></label>
          <input type="url" id="view360_url" name="view360_url"
                 value="<?= sanitize($form['view360_url']) ?>"
                 placeholder="https://www.youtube.com/watch?v=..."
                 oninput="updateChecklist()">
        </div>
      </div>

      <!-- 8. Gallery -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-num">8</div>
          <h2>Gallery Images</h2>
        </div>
        <div class="section-body">
          <!-- Existing images (edit mode) -->
          <div id="existingGallery">
            <?php foreach ($form['gallery'] as $gurl): ?>
              <div class="gallery-thumb" data-url="<?= sanitize($gurl) ?>" id="existing_<?= md5($gurl) ?>">
                <img src="<?= sanitize($gurl) ?>" alt="Gallery image" loading="lazy">
                <input type="hidden" name="gallery_existing[]" value="<?= sanitize($gurl) ?>" class="existing-hidden">
                <button type="button" class="remove-thumb" onclick="removeExisting('<?= md5($gurl) ?>')">✕</button>
              </div>
            <?php endforeach ?>
          </div>

          <!-- New uploads -->
          <div class="gallery-upload-zone" id="uploadZone">
            <input type="file" id="galleryInput" multiple accept="image/jpeg,image/jpg,image/png,image/webp,image/gif"
                   onchange="handleFileSelect(event)">
            <div class="upload-icon">📷</div>
            <p>Click to select images or drag &amp; drop here</p>
            <p class="upload-note">JPG, PNG, WebP, GIF — Max 5MB each</p>
          </div>

          <div class="gallery-preview" id="newGalleryPreview"></div>
          <!-- New URLs appended here as hidden inputs by JS -->
          <div id="newGalleryHidden"></div>
        </div>
      </div>

      <!-- 9. Team Quote -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-num">9</div>
          <h2>Team Quote <span class="opt">(Optional)</span></h2>
        </div>
        <div class="section-body">
          <label for="team_quote">A quote from the site team <span class="opt">(optional)</span></label>
          <textarea id="team_quote" name="team_quote" rows="3"
                    placeholder="e.g. 'Structures across all plots are now standing tall and we are on track to meet the floor completion targets for this quarter.'"><?= sanitize($form['team_quote']) ?></textarea>
        </div>
      </div>

      <!-- 10. What's Next -->
      <div class="section-card">
        <div class="section-head">
          <div class="section-num">10</div>
          <h2>What's Next</h2>
        </div>
        <div class="section-body">
          <label for="whats_next">Upcoming milestones for the next period</label>
          <textarea id="whats_next" name="whats_next" rows="4"
                    placeholder="Describe what work is planned for the coming weeks — upcoming floor slabs, finishing work, inspections, etc."
                    oninput="updateChecklist()"><?= sanitize($form['whats_next']) ?></textarea>
        </div>
      </div>

    </div><!-- /main-col -->

    <!-- ═══ Sidebar ═══ -->
    <div class="sidebar-col">
      <div class="checklist-card">
        <h3>Completion Checklist</h3>

        <div class="check-item" id="chk_project">
          <div class="check-icon" id="ci_project">✓</div>
          <span>Project selected</span>
        </div>
        <div class="check-item" id="chk_title">
          <div class="check-icon" id="ci_title">✓</div>
          <span>Title added</span>
        </div>
        <div class="check-item" id="chk_date">
          <div class="check-icon" id="ci_date">✓</div>
          <span>Date set</span>
        </div>
        <div class="check-item" id="chk_overview">
          <div class="check-icon" id="ci_overview">✓</div>
          <span>Progress overview</span>
        </div>
        <div class="check-item" id="chk_highlights">
          <div class="check-icon" id="ci_highlights">✓</div>
          <span>Key highlights</span>
        </div>
        <div class="check-item" id="chk_drone">
          <div class="check-icon" id="ci_drone">✓</div>
          <span>Drone video</span>
        </div>
        <div class="check-item" id="chk_view360">
          <div class="check-icon" id="ci_view360">✓</div>
          <span>360° video</span>
        </div>
        <div class="check-item" id="chk_gallery">
          <div class="check-icon" id="ci_gallery">✓</div>
          <span>Gallery images</span>
        </div>
        <div class="check-item" id="chk_whats_next">
          <div class="check-icon" id="ci_whats_next">✓</div>
          <span>What's next</span>
        </div>
      </div>
    </div>

  </div><!-- /page-grid -->

  <!-- Fixed action bar -->
  <div class="action-bar">
    <div class="save-info" id="saveInfo">&nbsp;</div>
    <button type="button" class="btn-draft" onclick="submitForm('save_draft')">Save Draft</button>
    <button type="button" class="btn-publish" id="btnPublish" onclick="submitForm('publish')">
      <?= $edit_mode && $blog && $blog['status'] === 'published' ? 'Update Published Post' : 'Publish' ?>
    </button>
  </div>

</form>

<script>
// ── Project title prefix ───────────────────────────────────────────────────
var titlePrefixes = <?= json_encode($title_prefixes) ?>;
var currentProject = <?= json_encode($form['project']) ?>;

function onProjectChange(val){
  currentProject = val;
  var titleEl = document.getElementById('title');
  if(val && titlePrefixes[val] && !titleEl.value){
    titleEl.value = titlePrefixes[val];
  }
  updateChecklist();
}

// ── Highlights ─────────────────────────────────────────────────────────────
var hlCount = <?= count($hl_rows) ?>;
function addHighlight(){
  var list = document.getElementById('hlList');
  var id = 'hl_new_' + (++hlCount);
  var row = document.createElement('div');
  row.className = 'highlight-row';
  row.id = id;
  row.innerHTML = '<input type="text" name="hl_bold[]" placeholder="Bold label (e.g. Eastern Homes)" oninput="updateChecklist()">'
    + '<textarea name="hl_desc[]" rows="2" placeholder="Description of this highlight..." oninput="updateChecklist()"></textarea>'
    + '<button type="button" class="btn-remove" onclick="removeHighlight(\'' + id + '\')" title="Remove">✕</button>';
  list.appendChild(row);
  updateChecklist();
}
function removeHighlight(id){
  var el = document.getElementById(id);
  if(el) el.remove();
  updateChecklist();
}

// ── Gallery upload ─────────────────────────────────────────────────────────
var selectedProject = document.getElementById('project').value;
document.getElementById('project').addEventListener('change', function(){
  selectedProject = this.value;
});

function handleFileSelect(e){
  var files = Array.from(e.target.files);
  // Reset the input so the same file can be re-selected if removed
  e.target.value = '';
  files.forEach(function(file){
    if(file.size > 5*1024*1024){ alert('File ' + file.name + ' exceeds 5MB limit.'); return; }
    uploadFile(file);
  });
}

// Drag & drop
var zone = document.getElementById('uploadZone');
zone.addEventListener('dragover', function(e){ e.preventDefault(); zone.classList.add('drag'); });
zone.addEventListener('dragleave', function(){ zone.classList.remove('drag'); });
zone.addEventListener('drop', function(e){
  e.preventDefault();
  zone.classList.remove('drag');
  Array.from(e.dataTransfer.files).forEach(function(file){ uploadFile(file); });
});

function uploadFile(file){
  var proj = document.getElementById('project').value;
  if(!proj){ alert('Please select a project before uploading images.'); return; }

  // Create thumb with progress
  var thumbId = 'thumb_' + Date.now() + '_' + Math.random().toString(36).substr(2,5);
  var preview = document.getElementById('newGalleryPreview');
  var thumb   = document.createElement('div');
  thumb.className = 'gallery-thumb';
  thumb.id = thumbId;
  thumb.innerHTML = '<div class="upload-progress">Uploading...</div>';
  preview.appendChild(thumb);

  // Read file for preview
  var reader = new FileReader();
  reader.onload = function(ev){ thumb.style.backgroundImage = 'url(' + ev.target.result + ')'; };
  reader.readAsDataURL(file);

  var fd = new FormData();
  fd.append('image', file);
  fd.append('project', proj);

  fetch('upload-image.php', { method:'POST', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(data){
      if(data.success){
        thumb.innerHTML = '<img src="' + data.url + '" alt="' + file.name + '" loading="lazy">'
          + '<button type="button" class="remove-thumb" onclick="removeNewThumb(\'' + thumbId + '\', \'' + data.url + '\')">✕</button>';
        // Store URL as hidden input
        var hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'gallery_new[]';
        hidden.value = data.url;
        hidden.id = 'hidden_' + thumbId;
        document.getElementById('newGalleryHidden').appendChild(hidden);
        updateChecklist();
      } else {
        thumb.remove();
        alert('Upload failed: ' + (data.error || 'Unknown error'));
      }
    })
    .catch(function(err){
      thumb.remove();
      alert('Upload error: ' + err);
    });
}

function removeNewThumb(thumbId, url){
  var thumb = document.getElementById(thumbId);
  if(thumb) thumb.remove();
  var hidden = document.getElementById('hidden_' + thumbId);
  if(hidden) hidden.remove();
  updateChecklist();
}

function removeExisting(hash){
  var el = document.getElementById('existing_' + hash);
  if(el){
    // Remove the hidden input so the URL isn't submitted
    var inp = el.querySelector('.existing-hidden');
    if(inp) inp.remove();
    el.remove();
  }
  updateChecklist();
}

// ── Checklist ──────────────────────────────────────────────────────────────
function updateChecklist(){
  function mark(id, done){
    var ci = document.getElementById('ci_' + id);
    if(ci){ ci.classList.toggle('done', done); ci.textContent = done ? '✓' : ''; }
  }
  mark('project', !!document.getElementById('project').value);
  mark('title', !!(document.getElementById('title').value || document.getElementById('subtitle').value));
  mark('date', !!document.getElementById('blog_date').value);
  mark('overview', document.getElementById('progress_overview').value.trim().length > 20);

  // Highlights: at least one non-empty
  var bolds = document.querySelectorAll('[name="hl_bold[]"]');
  var hasHl = Array.from(bolds).some(function(el){ return el.value.trim() !== ''; });
  mark('highlights', hasHl);

  mark('drone', !!document.getElementById('drone_url').value);
  mark('view360', !!document.getElementById('view360_url').value);

  // Gallery: existing OR new thumbs
  var hasGallery = document.querySelectorAll('#existingGallery .gallery-thumb, #newGalleryPreview .gallery-thumb').length > 0;
  mark('gallery', hasGallery);

  mark('whats_next', document.getElementById('whats_next').value.trim().length > 10);
}

// ── Submit form ────────────────────────────────────────────────────────────
function submitForm(action){
  document.getElementById('formAction').value = action;
  document.getElementById('saveInfo').textContent = action === 'publish' ? 'Publishing...' : 'Saving draft...';
  document.getElementById('blogForm').submit();
}

// ── Init ───────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function(){
  updateChecklist();
  // Build existing gallery UI if needed (already rendered by PHP)
  if(currentProject){
    // do nothing extra; titles already pre-filled
  }
});
</script>
</body>
</html>
