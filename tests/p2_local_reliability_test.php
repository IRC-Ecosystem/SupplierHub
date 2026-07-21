<?php
require_once __DIR__.'/../services/ReliabilityService.php';
require_once __DIR__.'/../models/Procurement.php';
function ok2($condition,$message){if(!$condition)throw new RuntimeException($message);}
$db=getDB();$orderIds=[];$eventIds=[];$inboxId='p2-test-'.bin2hex(random_bytes(4));
try{
  $supplier=(int)$db->query("SELECT id FROM users WHERE role='supplier' ORDER BY id LIMIT 1")->fetchColumn();
  $umkm=(int)$db->query("SELECT id FROM users WHERE role='umkm' ORDER BY id LIMIT 1")->fetchColumn();
  $material=(int)$db->query("SELECT id FROM materials WHERE supplier_id={$supplier} ORDER BY id LIMIT 1")->fetchColumn();
  ok2($supplier&&$umkm&&$material,'Seed P2 tidak tersedia.');
  $code='TEST-P2-'.strtoupper(bin2hex(random_bytes(4)));
  $q=$db->prepare("INSERT INTO orders(order_code,umkm_id,supplier_id,status,payment_status,subtotal,fee_supplier,total,paid_at) VALUES(:c,:u,:s,'paid','paid',10000,300,10300,NOW())");$q->execute(['c'=>$code,'u'=>$umkm,'s'=>$supplier]);$order=(int)$db->lastInsertId();$orderIds[]=$order;
  $db->prepare('INSERT INTO order_items(order_id,material_id,qty,price_at_order) VALUES(:o,:m,1,10000)')->execute(['o'=>$order,'m'=>$material]);
  $eid='p2-worker-'.bin2hex(random_bytes(4));$eventIds[]=$eid;
  $db->prepare("INSERT INTO outbox_events(event_id,event_type,aggregate_type,aggregate_id,payload,status,max_attempts,available_at) VALUES(:e,'P2_TEST_EVENT','supplier_order',:a,:p,'pending',2,NOW())")->execute(['e'=>$eid,'a'=>(string)$order,'p'=>json_encode(['test'=>true])]);
  $run=ReliabilityService::runWorker(10);ok2($run['published']>=1,'Worker mock tidak mempublikasikan event.');
  $status=$db->prepare('SELECT status FROM outbox_events WHERE event_id=:e');$status->execute(['e'=>$eid]);ok2($status->fetchColumn()==='published','Status outbox bukan published.');
  $a=ReliabilityService::recordInbox('p2-test',$inboxId,'P2_TEST',['ok'=>1]);$b=ReliabilityService::recordInbox('p2-test',$inboxId,'P2_TEST',['ok'=>1]);ok2(empty($a['idempotent_replay'])&&!empty($b['idempotent_replay']),'Inbox dedup gagal.');
  $recon=ReliabilityService::reconcile();ok2($recon['status']==='success','Rekonsiliasi gagal.');
  $refund=Procurement::requestRefund($order,$umkm,'p2-refund-'.bin2hex(random_bytes(4)),'Barang tidak sesuai dan perlu pengembalian dana');ok2($refund['status']==='success','Refund request lokal gagal: '.json_encode($refund));
  $refundCode=$refund['data']['refund_code'];$rq=$db->prepare('SELECT id FROM refund_requests WHERE refund_code=:c');$rq->execute(['c'=>$refundCode]);$rid=(int)$rq->fetchColumn();$done=Procurement::completeMockRefund($rid,true,'MOCK-P2-REF');ok2($done['status']==='success','Mock refund gagal.');
  echo "P2_LOCAL_RELIABILITY_OK\n";
}finally{
  $db->prepare('DELETE FROM inbox_events WHERE event_id=:e')->execute(['e'=>$inboxId]);
  foreach($eventIds as $e)$db->prepare('DELETE FROM outbox_events WHERE event_id=:e')->execute(['e'=>$e]);
  foreach($orderIds as $id){$db->prepare('DELETE FROM refund_requests WHERE order_id=:id')->execute(['id'=>$id]);$db->prepare('DELETE FROM outbox_events WHERE aggregate_id=:id')->execute(['id'=>(string)$id]);$db->prepare('DELETE FROM order_items WHERE order_id=:id')->execute(['id'=>$id]);$db->prepare('DELETE FROM orders WHERE id=:id')->execute(['id'=>$id]);}
}
