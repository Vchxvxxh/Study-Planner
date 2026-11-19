<?php
// Copy to config.php and fill in. config.php is git-ignored.
return [
  'driver'    => 'mysql',               // 'mysql' or 'sqlite'
  'db_host'   => 'localhost',
  'db_name'   => 'planner',
  'db_user'   => 'planner',
  'db_pass'   => '',
  // 'sqlite_path' => __DIR__.'/uploads/planner.sqlite',  // only if driver = sqlite
  'admin_key' => '',                    // long random string, e.g. `openssl rand -hex 24`
];
