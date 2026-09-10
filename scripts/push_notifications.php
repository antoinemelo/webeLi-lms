<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
if(is_file($root.'/storage/maintenance.flag'))exit;
require $root.'/app/bootstrap.php';
// Refresh every minute through cron, even if the website receives no visits.
file_put_contents($root.'/storage/push-cron.heartbeat',(string)time());
try{echo json_encode(messaging_push_batch(100),JSON_THROW_ON_ERROR).PHP_EOL;}
catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
