<?php
require_once __DIR__.'/../models/Procurement.php';

function ok($condition,$message){if(!$condition)throw new RuntimeException($message);}
$db=getDB();$orderIds=[];
try{
    $supplier=(int)$db->query("SELECT id FROM users WHERE role='supplier' ORDER BY id LIMIT 1")->fetchColumn();
    $umkm=(int)$db->query("SELECT id FROM users WHERE role='umkm' ORDER BY id LIMIT 1")->fetchColumn();
    $material=(int)$db->query("SELECT id FROM materials WHERE supplier_id={$supplier} ORDER BY id LIMIT 1")->fetchColumn();
    ok($supplier&&$umkm&&$material,'Seed user/material tidak tersedia.');
    $make=function(string $status,string $payment)use($db,$supplier,$umkm,$material,&$orderIds){$code='TEST-P1-'.strtoupper(bin2hex(random_bytes(4)));$q=$db->prepare("INSERT INTO orders(order_code,umkm_id,supplier_id,status,payment_status,subtotal,fee_supplier,total) VALUES(:code,:u,:s,:status,:payment,10000,300,10300)");$q->execute(['code'=>$code,'u'=>$umkm,'s'=>$supplier,'status'=>$status,'payment'=>$payment]);$id=(int)$db->lastInsertId();$orderIds[]=$id;$db->prepare('INSERT INTO order_items(order_id,material_id,qty,price_at_order) VALUES(:o,:m,10,1000)')->execute(['o'=>$id,'m'=>$material]);return $id;};

    $receiptOrder=$make('shipped','paid');$itemId=(int)$db->query("SELECT id FROM order_items WHERE order_id={$receiptOrder}")->fetchColumn();
    $stockBefore=(int)$db->query("SELECT stock FROM materials WHERE id={$material}")->fetchColumn();
    $partial=Procurement::receive($receiptOrder,$umkm,[['order_item_id'=>$itemId,'accepted_qty'=>4,'rejected_qty'=>0]],'test-p1-partial-'.$receiptOrder,'Tahap pertama');
    ok($partial['status']==='success'&&$partial['data']['order_status']==='partially_received','Partial receipt gagal.');
    $replay=Procurement::receive($receiptOrder,$umkm,[['order_item_id'=>$itemId,'accepted_qty'=>4,'rejected_qty'=>0]],'test-p1-partial-'.$receiptOrder,'Replay');
    ok(!empty($replay['data']['idempotent_replay']),'Receipt replay tidak idempotent.');
    $over=Procurement::receive($receiptOrder,$umkm,[['order_item_id'=>$itemId,'accepted_qty'=>7,'rejected_qty'=>0]],'test-p1-over-'.$receiptOrder);
    ok($over['status']==='error','Over receipt seharusnya ditolak.');
    $full=Procurement::receive($receiptOrder,$umkm,[['order_item_id'=>$itemId,'accepted_qty'=>5,'rejected_qty'=>1,'rejection_reason'=>'Kemasan rusak']],'test-p1-full-'.$receiptOrder);
    ok($full['status']==='success'&&$full['data']['order_status']==='received','Full receipt gagal.');
    $stockAfter=(int)$db->query("SELECT stock FROM materials WHERE id={$material}")->fetchColumn();ok($stockBefore===$stockAfter,'Receipt lokal tidak boleh menulis stok Inventory/katalog.');
    $eventCount=(int)$db->query("SELECT COUNT(*) FROM outbox_events WHERE aggregate_id='{$receiptOrder}' AND event_type='RESTOCK_COMPLETED'")->fetchColumn();ok($eventCount===1,'RESTOCK_COMPLETED harus dibuat tepat satu kali.');

    $cancelOrder=$make('submitted','unpaid');$cancel=Procurement::cancel($cancelOrder,$umkm,'Kebutuhan berubah');ok($cancel['status']==='success','Cancel order lokal gagal.');
    $paidCancel=$make('paid','paid');$blocked=Procurement::cancel($paidCancel,$umkm,'Ingin membatalkan');ok($blocked['status']==='error','Order paid tidak boleh dibatalkan tanpa refund SmartBank.');
    $pendingCancel=$make('pending_payment','pending');$pendingBlocked=Procurement::cancel($pendingCancel,$umkm,'Ingin membatalkan');ok($pendingBlocked['status']==='error','Payment pending tidak boleh dibatalkan sebelum hasil SmartBank.');

    $disputeOrder=$make('shipped','paid');$dispute=Procurement::openDispute($disputeOrder,$umkm,'damaged','Sebagian kemasan barang rusak');ok($dispute['status']==='success','Dispute lokal gagal.');$duplicate=Procurement::openDispute($disputeOrder,$umkm,'quality','Kualitas barang tidak sesuai');ok($duplicate['status']==='error','Dispute terbuka ganda harus ditolak.');
    $unauthorized=Procurement::receive($disputeOrder,999999,[['order_item_id'=>(int)$db->query("SELECT id FROM order_items WHERE order_id={$disputeOrder}")->fetchColumn(),'accepted_qty'=>1,'rejected_qty'=>0]],'test-p1-owner-'.$disputeOrder);ok($unauthorized['status']==='error','Cross-owner receipt harus ditolak.');
    echo "P1_LOCAL_PROCUREMENT_OK\n";
}finally{
    foreach($orderIds as $id){$db->prepare("DELETE FROM outbox_events WHERE aggregate_type='supplier_order' AND aggregate_id=:id")->execute(['id'=>(string)$id]);$db->prepare('DELETE FROM orders WHERE id=:id')->execute(['id'=>$id]);}
}
