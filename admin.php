<?php
require __DIR__.'/lib.php';
$k=(string)($_GET['key']??'');
$want=(string)(cfg()['admin_key']??'');
if($want===''||strpos($want,'CHANGE-ME')===0||!hash_equals($want,$k)){ http_response_code(403); header('Content-Type:text/plain'); exit('Forbidden'); }
header('X-Robots-Tag: noindex'); header('Cache-Control: no-store');
$rows=db()->query('SELECT u.id,u.email,u.name,u.created_at,u.last_login,(p.user_id IS NOT NULL) AS has_plan FROM users u LEFT JOIN planners p ON p.user_id=u.id ORDER BY u.id DESC')->fetchAll();
if(isset($_GET['csv'])){
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="planner-emails.csv"');
  echo "\xEF\xBB\xBF"; $o=fopen('php://output','w');
  fputcsv($o,['email','name','joined','last_login']);
  foreach($rows as $r) fputcsv($o,[ltrim($r['email'],"=+-@\t\r"),ltrim($r['name'],"=+-@\t\r"),date('Y-m-d H:i',$r['created_at']),date('Y-m-d H:i',$r['last_login'])]);
  exit;
}
function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
?><!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Planner users</title>
<style>body{font:14px system-ui,sans-serif;margin:0;padding:20px;background:#eeeef0;color:#2c2b30}h1{font-size:20px;margin:0 0 4px}a.b{display:inline-block;background:#f58f7c;color:#2c2b30;padding:8px 14px;border-radius:8px;text-decoration:none;font-weight:600;margin:10px 0}table{border-collapse:collapse;width:100%;background:#fff;border-radius:10px;overflow:hidden}th,td{text-align:left;padding:9px 12px;border-bottom:1px solid #e3e3e6;white-space:nowrap}th{background:#2c2b30;color:#fff}div{overflow-x:auto}</style>
<h1>Planner users (<?=count($rows)?>)</h1>
<a class="b" href="?key=<?=h(urlencode($k))?>&csv=1">CSV download</a>
<div><table><tr><th>#</th><th>Email</th><th>Name</th><th>Joined</th><th>Last login</th><th>Planner</th></tr>
<?php foreach($rows as $r): ?><tr><td><?=h($r['id'])?></td><td><?=h($r['email'])?></td><td><?=h($r['name'])?></td><td><?=h(date('d M Y H:i',$r['created_at']))?></td><td><?=h(date('d M Y H:i',$r['last_login']))?></td><td><?=$r['has_plan']?'✓':'—'?></td></tr><?php endforeach; ?></table></div>
