<?php
require __DIR__.'/lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function out($a,$code=200){ http_response_code($code); echo json_encode($a,JSON_UNESCAPED_UNICODE); exit; }
set_exception_handler(function($e){ error_log('planner api: '.$e->getMessage()); out(['error'=>'server'],500); });

if(($_SERVER['REQUEST_METHOD']??'')!=='POST') out(['error'=>'post only'],405);
if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='planner') out(['error'=>'forbidden'],403); // cross-site form block

$ct=$_SERVER['CONTENT_TYPE']??'';
$in=stripos($ct,'application/json')!==false ? (json_decode(file_get_contents('php://input'),true)?:[]) : $_POST;
$a=$_GET['a']??'';
$pdo=db();
$now=time();

function clean_name($s){
  $s=preg_replace('/[\x00-\x1F\x7F<>]+/u','',(string)$s);
  $s=trim(preg_replace('/\s+/u',' ',$s));
  return mb_substr($s,0,40);
}
function user_out($u){
  return ['id'=>(int)$u['id'],'email'=>$u['email'],'name'=>$u['name'],'avatar'=>$u['avatar']?('uploads/'.$u['avatar']):''];
}
function auth($in){
  global $pdo,$now;
  $t=(string)($in['t']??'');
  if(!preg_match('/^[a-f0-9]{64}$/',$t)) out(['error'=>'auth'],401);
  $st=$pdo->prepare('SELECT u.* FROM sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=?');
  $st->execute([hash('sha256',$t)]);
  $u=$st->fetch();
  if(!$u) out(['error'=>'auth'],401);
  return $u;
}

if($a==='login'){
  $email=strtolower(trim((string)($in['email']??'')));
  if(strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL)) out(['error'=>'email'],422);
  $st=$pdo->prepare('SELECT * FROM users WHERE email=?'); $st->execute([$email]); $u=$st->fetch();
  if(!$u){
    $nm=clean_name($in['name']??'');
    if($nm==='') $nm=clean_name(ucfirst(preg_replace('/[._\-+0-9]+/',' ',explode('@',$email)[0])));
    if($nm==='') $nm='Student';
    $pdo->prepare('INSERT INTO users(email,name,avatar,created_at,last_login) VALUES(?,?,?,?,?)')->execute([$email,$nm,'',$now,$now]);
    $st->execute([$email]); $u=$st->fetch(); $new=true;
  } else { $pdo->prepare('UPDATE users SET last_login=? WHERE id=?')->execute([$now,$u['id']]); $new=false; }
  $t=bin2hex(random_bytes(32));
  $pdo->prepare('INSERT INTO sessions(token_hash,user_id,created_at) VALUES(?,?,?)')->execute([hash('sha256',$t),$u['id'],$now]);
  if(random_int(1,20)===1) $pdo->prepare('DELETE FROM sessions WHERE created_at<?')->execute([$now-86400*180]);
  out(['ok'=>1,'t'=>$t,'new'=>$new,'user'=>user_out($u)]);
}

$u=auth($in);

if($a==='me') out(['ok'=>1,'user'=>user_out($u)]);

if($a==='logout'){
  $pdo->prepare('DELETE FROM sessions WHERE token_hash=?')->execute([hash('sha256',(string)$in['t'])]);
  out(['ok'=>1]);
}

if($a==='planner_get'){
  $st=$pdo->prepare('SELECT data,updated_at FROM planners WHERE user_id=?'); $st->execute([$u['id']]); $r=$st->fetch();
  if(!$r) out(['ok'=>1,'data'=>null,'updated_at'=>0]);
  out(['ok'=>1,'data'=>json_decode($r['data'],true),'updated_at'=>(int)$r['updated_at']]);
}

if($a==='planner_put'){
  $data=$in['data']??null;
  if(!is_array($data)) out(['error'=>'data'],422);
  $json=json_encode($data,JSON_UNESCAPED_UNICODE);
  if($json===false||strlen($json)>900000) out(['error'=>'too big'],413);
  $base=(int)($in['base']??0);
  $st=$pdo->prepare('SELECT data,updated_at FROM planners WHERE user_id=?'); $st->execute([$u['id']]); $r=$st->fetch();
  if($r && (int)$r['updated_at']>$base){
    // another device saved newer data meanwhile
    out(['ok'=>0,'conflict'=>1,'data'=>json_decode($r['data'],true),'updated_at'=>(int)$r['updated_at']]);
  }
  $ts=max((int)round(microtime(true)*1000), $r?((int)$r['updated_at']+1):0);
  if($r) $pdo->prepare('UPDATE planners SET data=?,updated_at=? WHERE user_id=?')->execute([$json,$ts,$u['id']]);
  else   $pdo->prepare('INSERT INTO planners(user_id,data,updated_at) VALUES(?,?,?)')->execute([$u['id'],$json,$ts]);
  out(['ok'=>1,'updated_at'=>$ts]);
}

if($a==='profile'){
  $nm=clean_name($in['name']??'');
  if($nm==='') out(['error'=>'name'],422);
  $pdo->prepare('UPDATE users SET name=? WHERE id=?')->execute([$nm,$u['id']]);
  $u['name']=$nm; out(['ok'=>1,'user'=>user_out($u)]);
}

if($a==='avatar'){
  $f=$_FILES['file']??null;
  if(!$f||$f['error']!==UPLOAD_ERR_OK) out(['error'=>($f&&$f['error']===UPLOAD_ERR_INI_SIZE)?'size':'upload'],422);
  if($f['size']>1048576) out(['error'=>'size'],413);
  $info=@getimagesize($f['tmp_name']);
  $map=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp',IMAGETYPE_GIF=>'gif'];
  if(!$info||!isset($map[$info[2]])||$info[0]>6000||$info[1]>6000) out(['error'=>'type'],415);
  $name='a'.$u['id'].'_'.bin2hex(random_bytes(6)).'.'.$map[$info[2]];
  $dir=__DIR__.'/uploads/';
  if(!move_uploaded_file($f['tmp_name'],$dir.$name)) out(['error'=>'upload'],500);
  @chmod($dir.$name,0644);
  if($u['avatar']&&preg_match('/^a\d+_[a-f0-9]+\.\w+$/',$u['avatar'])) @unlink($dir.$u['avatar']);
  $pdo->prepare('UPDATE users SET avatar=? WHERE id=?')->execute([$name,$u['id']]);
  $u['avatar']=$name; out(['ok'=>1,'user'=>user_out($u)]);
}

if($a==='avatar_remove'){
  if($u['avatar']&&preg_match('/^a\d+_[a-f0-9]+\.\w+$/',$u['avatar'])) @unlink(__DIR__.'/uploads/'.$u['avatar']);
  $pdo->prepare("UPDATE users SET avatar='' WHERE id=?")->execute([$u['id']]);
  $u['avatar']=''; out(['ok'=>1,'user'=>user_out($u)]);
}

out(['error'=>'unknown'],404);
