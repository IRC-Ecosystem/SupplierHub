<?php
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../controllers/OrderController.php';
require_once __DIR__.'/../models/Procurement.php';
require_once __DIR__.'/ReliabilityService.php';

class IntegrationService {
    public static function configuration(): array {
        $secret=(string)(getenv('INTEGRATION_WEBHOOK_SECRET')?:'');
        return [
            'smartbank'=>['status'=>$secret!==''?'ready':'configuration_required','callback'=>'api/integrations.php?action=smartbank_payment_callback'],
            'logistikita'=>['status'=>$secret!==''?'ready':'configuration_required','callback'=>'api/integrations.php?action=logistics_shipment_event'],
            'umkm_insight'=>['status'=>'local_ready','endpoint'=>'api/integrations.php?action=insight_procurement_summary'],
            'outbox'=>['status'=>'local_ready','endpoint'=>'api/integrations.php?action=outbox']
        ];
    }

    public static function verifySignature(string $raw): bool {
        $secret=(string)(getenv('INTEGRATION_WEBHOOK_SECRET')?:'');
        $provided=(string)($_SERVER['HTTP_X_B2BLINK_SIGNATURE']??'');
        if($secret===''||$provided==='')return false;
        $provided=str_starts_with($provided,'sha256=')?substr($provided,7):$provided;
        return hash_equals(hash_hmac('sha256',$raw,$secret),$provided);
    }

    public static function eventId(): string { return trim((string)($_SERVER['HTTP_X_B2BLINK_EVENT_ID']??'')); }

    public static function smartBankCallback(array $data): array {
        $orderId=(int)($data['order_id']??0);$status=(string)($data['status']??'');$reference=trim((string)($data['payment_reference']??''));
        if(!$orderId||!in_array($status,['succeeded','failed'],true))return ['status'=>'error','message'=>'Payload callback SmartBank tidak valid.'];
        $eventId=self::eventId();if($eventId==='')return ['status'=>'error','message'=>'X-B2BLink-Event-Id wajib diisi.'];
        $inbox=ReliabilityService::recordInbox('smartbank',$eventId,'PAYMENT_VERIFICATION',$data);if(!empty($inbox['idempotent_replay']))return ['status'=>'success','message'=>'Callback SmartBank sudah diproses.','data'=>['idempotent_replay'=>true]];
        $response=OrderController::verifySmartBankPayment($orderId,$status,$reference?:null);if($response['status']!=='success')ReliabilityService::recordWebhook('smartbank',$eventId,(string)json_encode($data),(string)($_SERVER['HTTP_X_B2BLINK_SIGNATURE']??''),'failed',$response['message']??'Callback gagal');return $response;
    }

    public static function logisticsEvent(array $data): array {
        $orderId=(int)($data['order_id']??0);$event=(string)($data['event']??'');$reference=trim((string)($data['tracking_reference']??''));
        if(!$orderId||$event!=='shipment.created'||$reference==='')return ['status'=>'error','message'=>'Payload event LogistiKita tidak valid.'];
        $eventId=self::eventId();if($eventId==='')return ['status'=>'error','message'=>'X-B2BLink-Event-Id wajib diisi.'];$inbox=ReliabilityService::recordInbox('logistikita',$eventId,'SHIPMENT_CREATED',$data);if(!empty($inbox['idempotent_replay']))return ['status'=>'success','message'=>'Shipment event sudah diproses.','data'=>['idempotent_replay'=>true]];
        $db=getDB();$db->beginTransaction();
        try{$q=$db->prepare('SELECT * FROM orders WHERE id=:id FOR UPDATE');$q->execute(['id'=>$orderId]);$o=$q->fetch();if(!$o)throw new DomainException('Pesanan tidak ditemukan.');if(!in_array($o['status'],['paid','processing'],true))throw new DomainException('Order belum dapat dikirim.');$db->prepare("UPDATE orders SET status='shipped',resi_pengiriman=:ref,shipped_at=NOW() WHERE id=:id")->execute(['ref'=>$reference,'id'=>$orderId]);Order::addStatusHistory($orderId,$o['status'],'shipped',null,'Shipment dibuat oleh LogistiKita',$db);Procurement::enqueue($db,$orderId,'SUPPLIER_ORDER_SHIPPED',['order_id'=>$orderId,'tracking_reference'=>$reference,'source'=>'logistikita']);$db->commit();return ['status'=>'success','message'=>'Shipment event diterima.','data'=>['order_status'=>'shipped']];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();return ['status'=>'error','message'=>$e->getMessage()];}
    }

    public static function insightSummary(): array {
        $db=getDB();
        $orders=$db->query("SELECT COUNT(*) total_orders,COALESCE(SUM(total),0) gross_procurement,SUM(status='received') received_orders,SUM(status='partially_received') partial_orders,SUM(status='cancelled') cancelled_orders FROM orders")->fetch();
        $receipts=$db->query("SELECT COALESCE(SUM(gri.accepted_qty),0) accepted_qty,COALESCE(SUM(gri.rejected_qty),0) rejected_qty,MAX(gr.created_at) data_freshness FROM goods_receipt_items gri JOIN goods_receipts gr ON gr.id=gri.goods_receipt_id")->fetch();
        return ['orders'=>$orders,'receipts'=>$receipts,'generated_at'=>date(DATE_ATOM),'scope'=>'supplierhub_local'];
    }
}
