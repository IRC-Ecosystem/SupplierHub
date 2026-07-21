<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Order.php';

class Procurement {
    private static function addStatusHistory(int $orderId, ?string $from, string $to, ?int $actor, string $reason, PDO $db): void {
        Order::addStatusHistory($orderId, $from, $to, $actor, $reason, $db);
    }
    private static function uuid(): string {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private static function outbox(PDO $db, int $orderId, string $type, array $payload): void {
        $stmt = $db->prepare("INSERT INTO outbox_events(event_id,aggregate_type,aggregate_id,event_type,payload) VALUES(:eid,'supplier_order',:aid,:type,:payload)");
        $stmt->execute(['eid'=>self::uuid(),'aid'=>(string)$orderId,'type'=>$type,'payload'=>json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    }

    public static function enqueue(PDO $db, int $orderId, string $type, array $payload): void {
        self::outbox($db,$orderId,$type,$payload);
    }

    private static function transition(PDO $db, array $order, string $to, int $actor, string $reason, array $allowed): void {
        if (!in_array($order['status'], $allowed, true)) throw new DomainException('Transisi status tidak valid dari '.$order['status'].' ke '.$to.'.');
        $stmt = $db->prepare("UPDATE orders SET status=:to WHERE id=:id AND status=:from");
        $stmt->execute(['to'=>$to,'id'=>$order['id'],'from'=>$order['status']]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Status pesanan berubah. Muat ulang halaman.');
        Order::addStatusHistory($order['id'],$order['status'],$to,$actor,$reason,$db);
    }

    public static function supplierUpdate(int $orderId, int $supplierId, string $action, array $data): array {
        $db=getDB(); $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM orders WHERE id=:id FOR UPDATE'); $q->execute(['id'=>$orderId]); $o=$q->fetch();
            if(!$o || (int)$o['supplier_id']!==$supplierId) throw new DomainException('Pesanan tidak ditemukan.');
            if($action==='processing') {
                self::transition($db,$o,'processing',$supplierId,'Supplier mulai menyiapkan pesanan',['paid']);
                self::outbox($db,$orderId,'SUPPLIER_ORDER_PROCESSING',['order_id'=>$orderId,'order_code'=>$o['order_code']]);
            } elseif($action==='shipped') {
                $tracking=trim((string)($data['tracking_reference']??''));
                if($tracking==='' || strlen($tracking)>100) throw new DomainException('Referensi pengiriman wajib diisi.');
                self::transition($db,$o,'shipped',$supplierId,'Barang dikirim oleh supplier',['paid','processing']);
                $db->prepare('UPDATE orders SET resi_pengiriman=:ref,shipped_at=NOW() WHERE id=:id')->execute(['ref'=>$tracking,'id'=>$orderId]);
                self::outbox($db,$orderId,'SUPPLIER_ORDER_SHIPPED',['order_id'=>$orderId,'order_code'=>$o['order_code'],'tracking_reference'=>$tracking,'integration_status'=>'local_only']);
            } elseif($action==='estimate') {
                if($o['status']!=='submitted') throw new DomainException('Estimasi hanya dapat diubah sebelum pesanan dikonfirmasi.');
                $eta=trim((string)($data['fulfillment_eta']??''));
                $dt=DateTime::createFromFormat('Y-m-d\TH:i',$eta) ?: DateTime::createFromFormat('Y-m-d H:i:s',$eta);
                if(!$dt || $dt<=new DateTime()) throw new DomainException('Estimasi pemenuhan harus berada di masa depan.');
                $db->prepare('UPDATE orders SET fulfillment_eta=:eta WHERE id=:id')->execute(['eta'=>$dt->format('Y-m-d H:i:s'),'id'=>$orderId]);
                self::outbox($db,$orderId,'SUPPLIER_FULFILLMENT_ESTIMATED',['order_id'=>$orderId,'fulfillment_eta'=>$dt->format(DATE_ATOM)]);
            } else throw new DomainException('Aksi supplier tidak dikenal.');
            $db->commit(); return ['status'=>'success','message'=>'Pesanan berhasil diperbarui.'];
        } catch(Throwable $e){if($db->inTransaction())$db->rollBack();return ['status'=>'error','message'=>$e->getMessage()];}
    }

    public static function receive(int $orderId, int $umkmId, array $items, string $key, ?string $note=null): array {
        if($key==='' || strlen($key)>100) return ['status'=>'error','message'=>'Idempotency key receipt wajib diisi.'];
        if(!$items) return ['status'=>'error','message'=>'Item penerimaan wajib diisi.'];
        $db=getDB(); $db->beginTransaction();
        try {
            $old=$db->prepare('SELECT gr.*,o.status AS order_status FROM goods_receipts gr JOIN orders o ON o.id=gr.order_id WHERE gr.idempotency_key=:k');$old->execute(['k'=>$key]);
            if($prior=$old->fetch()){ $db->rollBack(); return ['status'=>'success','message'=>'Penerimaan sebelumnya dikembalikan.','data'=>['receipt_code'=>$prior['receipt_code'],'order_status'=>$prior['order_status'],'idempotent_replay'=>true]]; }
            $q=$db->prepare('SELECT * FROM orders WHERE id=:id FOR UPDATE');$q->execute(['id'=>$orderId]);$o=$q->fetch();
            if(!$o || (int)$o['umkm_id']!==$umkmId) throw new DomainException('Pesanan tidak ditemukan.');
            if(!in_array($o['status'],['shipped','partially_received'],true)) throw new DomainException('Barang hanya dapat diterima setelah dikirim.');
            $lines=$db->prepare("SELECT id,qty FROM order_items WHERE order_id=:oid FOR UPDATE");$lines->execute(['oid'=>$orderId]);
            $sum=$db->prepare("SELECT COALESCE(SUM(accepted_qty+rejected_qty),0) processed FROM goods_receipt_items WHERE order_item_id=:iid");
            $available=[];foreach($lines->fetchAll() as $line){$sum->execute(['iid'=>$line['id']]);$available[(int)$line['id']]=['qty'=>(int)$line['qty'],'processed'=>(int)$sum->fetchColumn()];}
            $normalized=[];
            foreach($items as $item){$id=(int)($item['order_item_id']??0);$accepted=(int)($item['accepted_qty']??0);$rejected=(int)($item['rejected_qty']??0);if(!isset($available[$id])||$accepted<0||$rejected<0||$accepted+$rejected<1)throw new DomainException('Data item penerimaan tidak valid.');if($available[$id]['processed']+$accepted+$rejected>$available[$id]['qty'])throw new DomainException('Jumlah penerimaan melebihi quantity pesanan.');$normalized[]=['id'=>$id,'accepted'=>$accepted,'rejected'=>$rejected,'reason'=>trim((string)($item['rejection_reason']??''))];$available[$id]['processed']+=$accepted+$rejected;}
            $full=true;foreach($available as $v)if($v['processed']<$v['qty']){$full=false;break;}
            $code='GR-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8));
            $db->prepare("INSERT INTO goods_receipts(receipt_code,order_id,idempotency_key,received_by,note,receipt_status) VALUES(:code,:oid,:k,:uid,:note,:status)")->execute(['code'=>$code,'oid'=>$orderId,'k'=>$key,'uid'=>$umkmId,'note'=>$note,'status'=>$full?'full':'partial']);$rid=(int)$db->lastInsertId();
            $ins=$db->prepare('INSERT INTO goods_receipt_items(goods_receipt_id,order_item_id,accepted_qty,rejected_qty,rejection_reason) VALUES(:rid,:iid,:a,:r,:reason)');foreach($normalized as $n)$ins->execute(['rid'=>$rid,'iid'=>$n['id'],'a'=>$n['accepted'],'r'=>$n['rejected'],'reason'=>$n['reason']?:null]);
            $to=$full?'received':'partially_received';self::transition($db,$o,$to,$umkmId,$full?'Seluruh quantity telah diterima':'Sebagian quantity telah diterima',['shipped','partially_received']);
            if($full)$db->prepare('UPDATE orders SET received_at=NOW(),completed_at=NOW() WHERE id=:id')->execute(['id'=>$orderId]);
            self::outbox($db,$orderId,$full?'RESTOCK_COMPLETED':'GOODS_PARTIALLY_RECEIVED',['order_id'=>$orderId,'receipt_id'=>$rid,'receipt_code'=>$code,'inventory_sync_status'=>'pending']);
            $db->commit();return ['status'=>'success','message'=>$full?'Penerimaan penuh tercatat. Menunggu sinkronisasi Inventory.':'Penerimaan sebagian tercatat.','data'=>['receipt_code'=>$code,'order_status'=>$to,'inventory_sync_status'=>'pending']];
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();return ['status'=>'error','message'=>$e->getMessage()];}
    }

    public static function cancel(int $orderId,int $umkmId,string $reason):array{
        if(strlen(trim($reason))<5)return ['status'=>'error','message'=>'Alasan pembatalan minimal 5 karakter.'];$db=getDB();$db->beginTransaction();try{$q=$db->prepare('SELECT * FROM orders WHERE id=:id FOR UPDATE');$q->execute(['id'=>$orderId]);$o=$q->fetch();if(!$o||(int)$o['umkm_id']!==$umkmId)throw new DomainException('Pesanan tidak ditemukan.');if($o['payment_status']==='paid')throw new DomainException('Order yang sudah dibayar memerlukan refund SmartBank dan belum dapat dibatalkan lokal.');if($o['payment_status']==='pending')throw new DomainException('Payment request sedang diverifikasi SmartBank dan belum dapat dibatalkan.');self::transition($db,$o,'cancelled',$umkmId,'Dibatalkan UMKM: '.trim($reason),['submitted','pending_payment','payment_failed']);$db->prepare('UPDATE orders SET cancellation_reason=:r WHERE id=:id')->execute(['r'=>trim($reason),'id'=>$orderId]);self::outbox($db,$orderId,'SUPPLIER_ORDER_CANCELLED',['order_id'=>$orderId,'reason'=>trim($reason),'refund_required'=>false]);$db->commit();return ['status'=>'success','message'=>'Pesanan dibatalkan.'];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();return ['status'=>'error','message'=>$e->getMessage()];}}

    public static function openDispute(int $orderId,int $umkmId,string $category,string $description):array{
        $allowed=['shortage','damaged','quality','late','other'];if(!in_array($category,$allowed,true)||strlen(trim($description))<10)return ['status'=>'error','message'=>'Kategori dan uraian sengketa minimal 10 karakter wajib diisi.'];$db=getDB();$db->beginTransaction();try{$q=$db->prepare('SELECT * FROM orders WHERE id=:id FOR UPDATE');$q->execute(['id'=>$orderId]);$o=$q->fetch();if(!$o||(int)$o['umkm_id']!==$umkmId)throw new DomainException('Pesanan tidak ditemukan.');if(!in_array($o['status'],['shipped','partially_received','received'],true))throw new DomainException('Sengketa hanya dapat dibuat setelah barang dikirim.');$check=$db->prepare("SELECT id FROM procurement_disputes WHERE order_id=:oid AND status='open'");$check->execute(['oid'=>$orderId]);if($check->fetch())throw new DomainException('Masih ada sengketa terbuka untuk pesanan ini.');$code='DSP-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8));$db->prepare("INSERT INTO procurement_disputes(dispute_code,order_id,opened_by,category,description) VALUES(:code,:oid,:uid,:cat,:description)")->execute(['code'=>$code,'oid'=>$orderId,'uid'=>$umkmId,'cat'=>$category,'description'=>trim($description)]);self::outbox($db,$orderId,'PROCUREMENT_DISPUTE_OPENED',['order_id'=>$orderId,'dispute_code'=>$code,'category'=>$category]);$db->commit();return ['status'=>'success','message'=>'Sengketa berhasil dibuat.','data'=>['dispute_code'=>$code]];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();return ['status'=>'error','message'=>$e->getMessage()];}}

    public static function supplierPerformance(int $supplierId):array{$db=getDB();$q=$db->prepare("SELECT COUNT(*) total_orders,SUM(status IN ('received','completed')) completed_orders,SUM(status='rejected') rejected_orders,SUM(status='cancelled') cancelled_orders,AVG(CASE WHEN shipped_at IS NOT NULL AND paid_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR,paid_at,shipped_at) END) avg_fulfillment_hours FROM orders WHERE supplier_id=:sid");$q->execute(['sid'=>$supplierId]);$d=$q->fetch();$dq=$db->prepare("SELECT COUNT(*) disputes,SUM(d.status='open') open_disputes FROM procurement_disputes d JOIN orders o ON o.id=d.order_id WHERE o.supplier_id=:sid");$dq->execute(['sid'=>$supplierId]);return array_merge($d?:[],$dq->fetch()?:[]);}

    public static function resolveDispute(int $disputeId,int $supplierId,string $resolution,string $note):array{$allowed=['resolved','rejected'];if(!in_array($resolution,$allowed,true)||strlen(trim($note))<10)return ['status'=>'error','message'=>'Resolusi dan catatan minimal 10 karakter wajib diisi.'];$db=getDB();$q=$db->prepare("SELECT d.*,o.order_code,o.supplier_id FROM procurement_disputes d JOIN orders o ON o.id=d.order_id WHERE d.id=:id FOR UPDATE");$q->execute(['id'=>$disputeId]);$d=$q->fetch();if(!$d||(int)$d['supplier_id']!==$supplierId)return ['status'=>'error','message'=>'Dispute tidak ditemukan.'];if($d['status']!=='open')return ['status'=>'error','message'=>'Dispute sudah diproses.'];$db->beginTransaction();try{$db->prepare("UPDATE procurement_disputes SET status=:status,resolution_note=:note,resolved_by=:uid,resolved_at=NOW() WHERE id=:id AND status='open'")->execute(['status'=>$resolution,'note'=>trim($note),'uid'=>$supplierId,'id'=>$disputeId]);self::outbox($db,(int)$d['order_id'],'PROCUREMENT_DISPUTE_'.strtoupper($resolution),['order_id'=>(int)$d['order_id'],'dispute_id'=>$disputeId,'resolution'=>$resolution]);$db->commit();return ['status'=>'success','message'=>'Dispute berhasil diperbarui.'];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();return ['status'=>'error','message'=>$e->getMessage()];}}

    public static function requestRefund(int $orderId,int $umkmId,string $key,string $reason):array{$db=getDB();$db->beginTransaction();try{$existing=$db->prepare('SELECT * FROM refund_requests WHERE idempotency_key=:key');$existing->execute(['key'=>$key]);if($r=$existing->fetch()){$db->rollBack();return ['status'=>'success','message'=>'Refund request sebelumnya dikembalikan.','data'=>['refund_code'=>$r['refund_code'],'status'=>$r['status'],'idempotent_replay'=>true]];}$q=$db->prepare('SELECT * FROM orders WHERE id=:id FOR UPDATE');$q->execute(['id'=>$orderId]);$o=$q->fetch();if(!$o||(int)$o['umkm_id']!==$umkmId)throw new DomainException('Pesanan tidak ditemukan.');if($o['payment_status']!=='paid'||!in_array($o['status'],['paid','processing','shipped','partially_received','received'],true))throw new DomainException('Order belum memenuhi syarat refund.');if(strlen(trim($reason))<10)throw new DomainException('Alasan refund minimal 10 karakter.');$code='RF-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8));$db->prepare('INSERT INTO refund_requests(refund_code,order_id,requested_by,idempotency_key,amount,reason) VALUES(:code,:oid,:uid,:key,:amount,:reason)')->execute(['code'=>$code,'oid'=>$orderId,'uid'=>$umkmId,'key'=>$key,'amount'=>$o['total'],'reason'=>trim($reason)]);$db->prepare("UPDATE orders SET status='refund_pending' WHERE id=:id")->execute(['id'=>$orderId]);self::addStatusHistory($orderId,$o['status'],'refund_pending',$umkmId,'Refund request lokal menunggu SmartBank',$db);self::outbox($db,$orderId,'REFUND_REQUESTED',['order_id'=>$orderId,'refund_code'=>$code,'amount'=>$o['total'],'integration_status'=>'pending_smartbank']);$db->commit();return ['status'=>'success','message'=>'Refund request tercatat dan menunggu SmartBank.','data'=>['refund_code'=>$code,'status'=>'pending']];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();return ['status'=>'error','message'=>$e->getMessage()];}}

    public static function completeMockRefund(int $refundId,bool $success,string $reference=''):array{$db=getDB();$db->beginTransaction();try{$q=$db->prepare('SELECT r.*,o.status order_status,o.order_code FROM refund_requests r JOIN orders o ON o.id=r.order_id WHERE r.id=:id FOR UPDATE');$q->execute(['id'=>$refundId]);$r=$q->fetch();if(!$r)throw new DomainException('Refund tidak ditemukan.');if($r['status']!=='pending'){$db->rollBack();return ['status'=>'success','message'=>'Refund sudah diproses.','data'=>['status'=>$r['status'],'idempotent_replay'=>true]];}$newOrder=$success?'refunded':'refund_failed';$db->prepare("UPDATE refund_requests SET status=:status,external_reference=:ref,failure_reason=:error WHERE id=:id")->execute(['status'=>$success?'succeeded':'failed','ref'=>$reference?:null,'error'=>$success?null:'Mock SmartBank menolak refund','id'=>$refundId]);$db->prepare('UPDATE orders SET status=:status,payment_status=:payment WHERE id=:id')->execute(['status'=>$newOrder,'payment'=>$success?'refunded':'paid','id'=>$r['order_id']]);self::addStatusHistory($r['order_id'],'refund_pending',$newOrder,null,$success?'Refund mock berhasil':'Refund mock gagal',$db);self::outbox($db,(int)$r['order_id'],$success?'REFUND_SUCCEEDED':'REFUND_FAILED',['order_id'=>(int)$r['order_id'],'refund_code'=>$r['refund_code'],'external_reference'=>$reference?:null,'delivery_mode'=>'local_mock']);$db->commit();return ['status'=>'success','message'=>$success?'Mock refund berhasil diproses.':'Mock refund gagal diproses.','data'=>['status'=>$success?'succeeded':'failed']];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();return ['status'=>'error','message'=>$e->getMessage()];}}
}
