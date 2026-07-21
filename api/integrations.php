<?php
header('Content-Type: application/json');
require_once __DIR__.'/../services/IntegrationService.php';
require_once __DIR__.'/../middleware/AuthMiddleware.php';
require_once __DIR__.'/../helpers/ApiResponse.php';

$action=(string)($_GET['action']??'status');$raw=file_get_contents('php://input');$input=json_decode($raw,true)?:[];
$webhooks=['smartbank_payment_callback','logistics_shipment_event'];
if(in_array($action,$webhooks,true)){
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);echo json_encode(['status'=>'error','message'=>'Method tidak diizinkan.']);exit;}
    if(!IntegrationService::verifySignature($raw)){http_response_code(401);echo json_encode(['status'=>'error','message'=>'Signature webhook tidak valid atau secret belum dikonfigurasi.']);exit;}
    $response=$action==='smartbank_payment_callback'?IntegrationService::smartBankCallback($input):IntegrationService::logisticsEvent($input);
}else{
    $user=AuthMiddleware::requireAuth('integrator');
    if($action==='status')$response=['status'=>'success','data'=>IntegrationService::configuration()];
    elseif($action==='outbox'){$db=getDB();$limit=min(100,max(1,(int)($_GET['limit']??25)));$q=$db->prepare("SELECT event_id,aggregate_type,aggregate_id,event_type,event_version,status,attempts,created_at FROM outbox_events ORDER BY id DESC LIMIT {$limit}");$q->execute();$response=['status'=>'success','data'=>$q->fetchAll()];}
    elseif($action==='insight_procurement_summary')$response=['status'=>'success','data'=>IntegrationService::insightSummary()];
    else $response=['status'=>'error','message'=>'Action integrasi tidak dikenali.'];
}
$response=ApiResponse::normalize($response);http_response_code(ApiResponse::codeFor($response));echo json_encode($response);
