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
