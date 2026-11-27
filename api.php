<?php
@ini_set('display_errors','0');
require __DIR__.'/lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function out($a,$code=200){ http_response_code($code); echo json_encode($a,JSON_UNESCAPED_UNICODE); exit; }
set_exception_handler(function($e){ error_log('planner api: '.$e->getMessage()); out(['error'=>'server'],500); });

if(($_SERVER['REQUEST_METHOD']??'')!=='POST') out(['error'=>'post only'],405);
if(($_SERVER['HTTP_X_REQUESTED_WITH']??'')!=='planner') out(['error'=>'forbidden'],403);

$ct=$_SERVER['CONTENT_TYPE']??'';
$in=stripos($ct,'application/json')!==false ? (json_decode(file_get_contents('php://input'),true)?:[]) : $_POST;
$a=$_GET['a']??'';
$pdo=db();
ensure_role($pdo);
ensure_theme($pdo);
function role_of($in){ return (($in['mode']??'')==='teacher')?'teacher':'student'; }
$now=time();

const AVATARS=['apple','chili','melon','potato','tomato','pumpkin','carrot','eggplant','strawberry','lemon'];
function clean_name($s){
  $s=preg_replace('/[\x00-\x1F\x7F<>]+/u','',(string)$s);
  $s=trim(preg_replace('/\s+/u',' ',$s));
  return mb_substr($s,0,40);
}
function user_out($u){
  return ['id'=>(int)$u['id'],'email'=>$u['email'],'name'=>$u['name'],'avatar'=>in_array($u['avatar'],AVATARS,true)?$u['avatar']:'','theme'=>(string)($u['theme']??'')];
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
    $pdo->prepare('INSERT INTO users(email,name,avatar,created_at,last_login,role) VALUES(?,?,?,?,?,?)')->execute([$email,$nm,'',$now,$now,role_of($in)]);
    $st->execute([$email]); $u=$st->fetch(); $new=true;
  } else { $pdo->prepare('UPDATE users SET last_login=?'.(isset($in['mode'])?',role=?':'').' WHERE id=?')->execute(isset($in['mode'])?[$now,role_of($in),$u['id']]:[$now,$u['id']]); $new=false; }
  $t=bin2hex(random_bytes(32));
  $pdo->prepare('INSERT INTO sessions(token_hash,user_id,created_at) VALUES(?,?,?)')->execute([hash('sha256',$t),$u['id'],$now]);
  if(random_int(1,20)===1) $pdo->prepare('DELETE FROM sessions WHERE created_at<?')->execute([$now-86400*180]);
  out(['ok'=>1,'t'=>$t,'new'=>$new,'user'=>user_out($u)]);
}

function blog_admin($in){
  $want=(string)(cfg()['admin_key']??'');
  if($want===''||strpos($want,'CHANGE-ME')===0||!hash_equals($want,(string)($in['key']??''))){ usleep(400000); out(['error'=>'key'],403); }
}
function blog_text($s,$max){ return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u','',(string)$s)),0,$max); }

function about_clean($in){
  $s=function($v,$m){ return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u','',(string)$v)),0,$m); };
  $url=$s($in['btnUrl']??'',300);
  $ce=$s($in['contactEmail']??'',120); if($ce!==''&&!filter_var($ce,FILTER_VALIDATE_EMAIL)) $ce='';
  if($url!==''&&!preg_match('~^(https?://|/|#|mailto:)~i',$url)) $url='';
  $tl=[];
  foreach(array_slice(is_array($in['timeline']??null)?$in['timeline']:[],0,40) as $t){
    if(!is_array($t)) continue;
    $r=['when'=>$s($t['when']??'',40),'title'=>$s($t['title']??'',120),'text'=>$s($t['text']??'',400)];
    if($r['when']===''&&$r['title']===''&&$r['text']==='') continue;
    $tl[]=$r;
  }
  return ['name'=>$s($in['name']??'',80),'eyebrow'=>$s($in['eyebrow']??'',80),'intro'=>$s($in['intro']??'',600),'studying'=>$s($in['studying']??'',120),'dept'=>$s($in['dept']??'',120),'btnText'=>$s($in['btnText']??'',60),'btnUrl'=>$url,'contactEmail'=>$ce,'timeline'=>$tl];
}
if(strpos($a,'about_')===0){
  ensure_about($pdo);
  if($a==='about_get'){
    $r=$pdo->query("SELECT v FROM site_kv WHERE k='about'")->fetch();
    $ab=$r?json_decode($r['v'],true):null; if(is_array($ab)) unset($ab['contactEmail']);
    out(['ok'=>1,'about'=>$ab]);
  }
  if($a==='about_contact'){
    if((string)($in['website']??'')!=='') out(['ok'=>1]);
    $nm=trim(preg_replace('/\s+/u',' ',blog_text($in['name']??'',80))); $em=blog_text($in['email']??'',120); $msg=blog_text($in['message']??'',2000);
    if($nm===''||$msg===''||!filter_var($em,FILTER_VALIDATE_EMAIL)) out(['error'=>'invalid'],422);
    ensure_contact($pdo);
    $ip=sha1((string)($_SERVER['REMOTE_ADDR']??'')); $now=time();
    $c=$pdo->prepare('SELECT COUNT(*) FROM contact_msgs WHERE ip=? AND created_at>?'); $c->execute([$ip,$now-3600]);
    if((int)$c->fetchColumn()>=3) out(['error'=>'rate'],429);
    $pdo->prepare('INSERT INTO contact_msgs(name,email,body,ip,created_at) VALUES(?,?,?,?,?)')->execute([$nm,$em,$msg,$ip,$now]);
    $r=$pdo->query("SELECT v FROM site_kv WHERE k='about'")->fetch(); $ab=$r?json_decode($r['v'],true):[];
    $to=(string)(($ab['contactEmail']??'')?:(cfg()['contact_email']??''));
    if($to!==''&&filter_var($to,FILTER_VALIDATE_EMAIL)){
      $host=preg_replace('/[^a-z0-9.\-]/i','',(string)($_SERVER['HTTP_HOST']??'localhost'));
      @mail($to,'=?UTF-8?B?'.base64_encode('Planner contact: '.$nm).'?=',$msg."\n\n-- ".$nm.' <'.$em.'>',"MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: noreply@".$host."\r\nReply-To: ".$em);
    }
    out(['ok'=>1]);
  }
  blog_admin($in);
  if($a==='about_admin_get'){
    blog_admin($in);
    $r=$pdo->query("SELECT v FROM site_kv WHERE k='about'")->fetch(); $ab=$r?json_decode($r['v'],true):[];
    out(['ok'=>1,'contactEmail'=>(string)(($ab['contactEmail']??'')?:(cfg()['contact_email']??''))]);
  }
  if($a==='about_save'){
    $d=about_clean(is_array($in['about']??null)?$in['about']:[]);
    if($d['name']==='') out(['error'=>'empty'],422);
    $pdo->prepare("DELETE FROM site_kv WHERE k='about'")->execute();
    $pdo->prepare("INSERT INTO site_kv(k,v,updated_at) VALUES('about',?,?)")->execute([json_encode($d,JSON_UNESCAPED_UNICODE),$now]);
    out(['ok'=>1,'about'=>$d]);
  }
  out(['error'=>'unknown'],404);
}

function blog_imgs($j){ $x=json_decode((string)$j,true); if(!is_array($x)) return []; $o=[]; foreach($x as $n){ if(is_string($n)&&preg_match('/^b_[a-f0-9]{12}\.(jpg|png|webp|gif)$/',$n)) $o[]=$n; } return array_slice($o,0,12); }
function blog_url($u){ $u=blog_text($u,300); return ($u!==''&&preg_match('~^(https?://|/|#|mailto:)~i',$u))?$u:''; }
function blog_rm_files($old,$keep){ foreach(blog_imgs($old) as $n){ if(!in_array($n,$keep,true)) @unlink(__DIR__.'/uploads/'.$n); } }
if(strpos($a,'blog_')===0){
  ensure_blog($pdo); ensure_blog2($pdo);
  if($a==='blog_list'){
    $ps=$pdo->query('SELECT slug,title,excerpt,images,created_at FROM posts WHERE published=1 ORDER BY created_at DESC,id DESC LIMIT 100')->fetchAll();
    foreach($ps as &$pp){ $pp['images']=blog_imgs($pp['images']); } unset($pp);
    out(['ok'=>1,'posts'=>$ps]);
  }
  if($a==='blog_get'){
    $slug=(string)($in['slug']??'');
    if(!preg_match('/^[a-z0-9-]{1,120}$/',$slug)) out(['error'=>'notfound'],404);
    $st=$pdo->prepare('SELECT slug,title,excerpt,body,images,btn_text,btn_url,created_at FROM posts WHERE slug=? AND published=1'); $st->execute([$slug]); $r=$st->fetch();
    if(!$r) out(['error'=>'notfound'],404);
    $r['images']=blog_imgs($r['images']);
    out(['ok'=>1,'post'=>$r]);
  }
  blog_admin($in);
  if($a==='blog_admin_list'){
    out(['ok'=>1,'posts'=>$pdo->query('SELECT id,slug,title,published,created_at FROM posts ORDER BY created_at DESC,id DESC LIMIT 300')->fetchAll()]);
  }
  if($a==='blog_admin_get'){
    $st=$pdo->prepare('SELECT * FROM posts WHERE id=?'); $st->execute([(int)($in['id']??0)]); $r=$st->fetch();
    if(!$r) out(['error'=>'notfound'],404);
    $r['images']=blog_imgs($r['images']);
    out(['ok'=>1,'post'=>$r]);
  }
  if($a==='blog_upload'){
    $f=$_FILES['file']??null;
    if(!$f||$f['error']!==UPLOAD_ERR_OK) out(['error'=>($f&&$f['error']===UPLOAD_ERR_INI_SIZE)?'size':'upload'],422);
    if($f['size']>3145728) out(['error'=>'size'],413);
    $info=@getimagesize($f['tmp_name']);
    $map=[IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp',IMAGETYPE_GIF=>'gif'];
    if(!$info||!isset($map[$info[2]])||$info[0]>8000||$info[1]>8000) out(['error'=>'type'],415);
    $name='b_'.bin2hex(random_bytes(6)).'.'.$map[$info[2]];
    if(!move_uploaded_file($f['tmp_name'],__DIR__.'/uploads/'.$name)) out(['error'=>'upload'],500);
    @chmod(__DIR__.'/uploads/'.$name,0644);
    out(['ok'=>1,'name'=>$name]);
  }
  if($a==='blog_save'){
    $id=(int)($in['id']??0);
    $title=blog_text($in['title']??'',200); $body=blog_text($in['body']??'',200000);
    if($title===''||$body==='') out(['error'=>'empty'],422);
    $excerpt=blog_text($in['excerpt']??'',400);
    if($excerpt==='') $excerpt=mb_substr(trim(preg_replace('/\s+/u',' ',preg_replace('/[#*>\[\]()_`-]+/u',' ',$body))),0,160);
    $pub=!empty($in['published'])?1:0;
    $imgs=blog_imgs(json_encode(is_array($in['images']??null)?array_values($in['images']):[])); $bt=blog_text($in['btnText']??'',60); $bu=blog_url($in['btnUrl']??'');
    $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',(string)($in['slug']??'')),'-'));
    $given=($slug!=='');
    if($slug==='') $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$title),'-'));
    if($slug==='') $slug='post-'.date('ymdHis');
    $slug=substr($slug,0,110);
    $chk=$pdo->prepare('SELECT id FROM posts WHERE slug=? AND id<>?'); $base=$slug; $n=1;
    while(true){
      $chk->execute([$slug,$id]);
      if(!$chk->fetch()) break;
      if($given) out(['error'=>'slug'],409);
      $slug=$base.'-'.(++$n);
    }
    if($id){
      $o=$pdo->prepare('SELECT images FROM posts WHERE id=?'); $o->execute([$id]); $oldimg=$o->fetchColumn();
      $st=$pdo->prepare('UPDATE posts SET slug=?,title=?,excerpt=?,body=?,published=?,images=?,btn_text=?,btn_url=?,updated_at=? WHERE id=?');
      $st->execute([$slug,$title,$excerpt,$body,$pub,json_encode($imgs),$bt,$bu,$now,$id]);
      if($oldimg) blog_rm_files($oldimg,$imgs);
      if(!$st->rowCount()){ $ex=$pdo->prepare('SELECT 1 FROM posts WHERE id=?'); $ex->execute([$id]); if(!$ex->fetch()) out(['error'=>'notfound'],404); }
    } else {
      $pdo->prepare('INSERT INTO posts(slug,title,excerpt,body,published,images,btn_text,btn_url,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$slug,$title,$excerpt,$body,$pub,json_encode($imgs),$bt,$bu,$now,$now]);
      $id=(int)$pdo->lastInsertId();
    }
    out(['ok'=>1,'id'=>$id,'slug'=>$slug]);
  }
  if($a==='blog_delete'){
    $did=(int)($in['id']??0); $o=$pdo->prepare('SELECT images FROM posts WHERE id=?'); $o->execute([$did]); $oldimg=$o->fetchColumn();
    $pdo->prepare('DELETE FROM posts WHERE id=?')->execute([$did]);
    if($oldimg) blog_rm_files($oldimg,[]);
    out(['ok'=>1]);
  }
  out(['error'=>'unknown'],404);
}

$u=auth($in);

if($a==='theme_set'){ $t=(($in['theme']??'')==='dark')?'dark':'light'; $pdo->prepare('UPDATE users SET theme=? WHERE id=?')->execute([$t,$u['id']]); out(['ok'=>1,'theme'=>$t]); }
if($a==='role_set'){ $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([role_of($in),$u['id']]); out(['ok'=>1]); }

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

if($a==='avatar_set'){
  $id=(string)($in['id']??'');
  if($id!==''&&!in_array($id,AVATARS,true)) out(['error'=>'avatar'],422);
  $pdo->prepare('UPDATE users SET avatar=? WHERE id=?')->execute([$id,$u['id']]);
  $u['avatar']=$id; out(['ok'=>1,'user'=>user_out($u)]);
}

out(['error'=>'unknown'],404);
