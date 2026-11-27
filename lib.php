<?php
function cfg(){ static $c=null; if($c===null) $c=require __DIR__.'/config.php'; return $c; }

function db(){
  static $p=null; if($p) return $p;
  $c=cfg();
  if(($c['driver']??'mysql')==='sqlite'){ $p=new PDO('sqlite:'.$c['sqlite_path']); }
  else { $p=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_pass']); }
  $p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
  $flag=__DIR__.'/uploads/.schema_ok';
  if(!file_exists($flag)){ migrate($p,$c); @file_put_contents($flag,'1'); }
  return $p;
}

function migrate($p,$c){
  $lite=(($c['driver']??'mysql')==='sqlite');
  $pk=$lite?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY';
  $p->exec("CREATE TABLE IF NOT EXISTS users(
    id $pk, email VARCHAR(190) NOT NULL UNIQUE, name VARCHAR(80) NOT NULL DEFAULT '',
    avatar VARCHAR(120) NOT NULL DEFAULT '', created_at INT NOT NULL, last_login INT NOT NULL)");
  $p->exec("CREATE TABLE IF NOT EXISTS sessions(
    token_hash CHAR(64) PRIMARY KEY, user_id INT NOT NULL, created_at INT NOT NULL)");
  $p->exec("CREATE TABLE IF NOT EXISTS planners(
    user_id INT PRIMARY KEY, data ".($lite?'TEXT':'MEDIUMTEXT')." NOT NULL, updated_at BIGINT NOT NULL)");
}

function ensure_blog($p){
  $flag=__DIR__.'/uploads/.blog_ok';
  if(file_exists($flag)) return;
  $lite=((cfg()['driver']??'mysql')==='sqlite');
  $pk=$lite?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY';
  $p->exec("CREATE TABLE IF NOT EXISTS posts(
    id $pk, slug VARCHAR(120) NOT NULL UNIQUE, title VARCHAR(200) NOT NULL, excerpt VARCHAR(400) NOT NULL DEFAULT '',
    body ".($lite?'TEXT':'MEDIUMTEXT')." NOT NULL, published TINYINT NOT NULL DEFAULT 1, created_at INT NOT NULL, updated_at INT NOT NULL)");
  @file_put_contents($flag,'1');
}

function ensure_about($p){
  $flag=__DIR__.'/uploads/.about_ok'; if(file_exists($flag)) return;
  $p->exec("CREATE TABLE IF NOT EXISTS site_kv(k VARCHAR(40) NOT NULL PRIMARY KEY, v ".(((cfg()['driver']??'mysql')==='sqlite')?'TEXT':'MEDIUMTEXT')." NOT NULL, updated_at INT NOT NULL)");
  @file_put_contents($flag,'1'); }

function ensure_contact($p){
  $flag=__DIR__.'/uploads/.contact_ok'; if(file_exists($flag)) return;
  $p->exec("CREATE TABLE IF NOT EXISTS contact_msgs(id ".(((cfg()['driver']??'mysql')==='sqlite')?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY').", name VARCHAR(80) NOT NULL, email VARCHAR(120) NOT NULL, body TEXT NOT NULL, ip VARCHAR(40) NOT NULL, created_at INT NOT NULL)");
  @file_put_contents($flag,'1'); }

function ensure_role($p){
  $flag=__DIR__.'/uploads/.role_ok'; if(file_exists($flag)) return;
  try{ $p->exec("ALTER TABLE users ADD COLUMN role VARCHAR(10) NOT NULL DEFAULT 'student'"); }
  catch(Throwable $e){ $c=$p->query('SELECT * FROM users LIMIT 0'); $has=false;
    for($i=0;$i<$c->columnCount();$i++){ $m=$c->getColumnMeta($i); if(($m['name']??'')==='role') $has=true; }
    if(!$has) throw $e; }
  @file_put_contents($flag,'1'); }

function ensure_theme($p){
  $flag=__DIR__.'/uploads/.theme_ok'; if(file_exists($flag)) return;
  try{ $p->exec("ALTER TABLE users ADD COLUMN theme VARCHAR(8) NOT NULL DEFAULT ''"); }
  catch(Throwable $e){ $c=$p->query('SELECT * FROM users LIMIT 0'); $has=false;
    for($i=0;$i<$c->columnCount();$i++){ $m=$c->getColumnMeta($i); if(($m['name']??'')==='theme') $has=true; }
    if(!$has) throw $e; }
  @file_put_contents($flag,'1'); }

function ensure_blog2($p){
  $flag=__DIR__.'/uploads/.blog2_ok'; if(file_exists($flag)) return;
  $c=$p->query('SELECT * FROM posts LIMIT 0'); $have=[];
  for($i=0;$i<$c->columnCount();$i++){ $m=$c->getColumnMeta($i); $have[$m['name']??'']=1; }
  if(!isset($have['images']))   $p->exec("ALTER TABLE posts ADD COLUMN images TEXT NULL");
  if(!isset($have['btn_text'])) $p->exec("ALTER TABLE posts ADD COLUMN btn_text VARCHAR(60) NOT NULL DEFAULT ''");
  if(!isset($have['btn_url']))  $p->exec("ALTER TABLE posts ADD COLUMN btn_url VARCHAR(300) NOT NULL DEFAULT ''");
  @file_put_contents($flag,'1'); }

