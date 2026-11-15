<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
$pdo=db();$token=(string)($_GET['token']??'');$photoId=(int)($_GET['photo']??0);
if(!preg_match('/^[a-f0-9]{64}$/',$token)||$photoId<1){http_response_code(404);exit('Not found.');}
$q=$pdo->prepare("SELECT g.id,g.sale_id,po.total_amount,po.amount_paid,s.total AS sale_total,s.balance AS sale_balance
 FROM final_delivery_galleries g JOIN photo_orders po ON po.id=g.photo_order_id
 LEFT JOIN sales s ON s.id=g.sale_id WHERE g.access_token=? AND g.status='published' LIMIT 1");
$q->execute([$token]);$g=$q->fetch(PDO::FETCH_ASSOC);
if(!$g){http_response_code(404);exit('Not found.');}
$paid=($g['sale_id']!==null)
 ? ((float)$g['sale_balance']<=0.00001 && (float)$g['sale_total']>0)
 : ((float)$g['amount_paid'] >= (float)$g['total_amount'] && (float)$g['total_amount']>0);
if(!$paid){http_response_code(403);exit('Payment must be completed before downloading.');}
$p=$pdo->prepare("SELECT original_name,stored_name,mime_type FROM final_delivery_photos WHERE id=? AND gallery_id=? LIMIT 1");
$p->execute([$photoId,$g['id']]);$photo=$p->fetch(PDO::FETCH_ASSOC);
if(!$photo){http_response_code(404);exit('Photo not found.');}
$base=realpath(__DIR__.'/../storage/final-delivery/'.(int)$g['id']);
$file=$base ? realpath($base.'/'.$photo['stored_name']) : false;
if(!$file||strpos($file,$base.DIRECTORY_SEPARATOR)!==0||!is_file($file)){http_response_code(404);exit('File unavailable.');}
$ext=strtolower(pathinfo($photo['stored_name'],PATHINFO_EXTENSION));
$allowed=['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'];
if(!isset($allowed[$ext])){http_response_code(415);exit('Unsupported file.');}
header('Content-Type: '.$allowed[$ext]);header('Content-Length: '.filesize($file));
header('Content-Disposition: attachment; filename="'.str_replace('"','',basename($photo['original_name'])).'"');
header('X-Content-Type-Options: nosniff');readfile($file);exit;
