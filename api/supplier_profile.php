<?php
header('Content-Type: application/json');
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../middleware/AuthMiddleware.php';
require_once __DIR__.'/../middleware/CsrfMiddleware.php';
require_once __DIR__.'/../helpers/ApiResponse.php';
require_once __DIR__.'/../models/Procurement.php';

$user=AuthMiddleware::requireAuth('supplier');
$uid=(int)$user['user_id'];
$db=getDB();
if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    $q=$db->prepare('SELECT * FROM supplier_profiles WHERE supplier_id=:id');$q->execute(['id'=>$uid]);
    $response=['status'=>'success','data'=>['profile'=>$q->fetch(),'performance'=>Procurement::supplierPerformance($uid)]];
}else{
    CsrfMiddleware::verify();$input=json_decode(file_get_contents('php://input'),true)?:$_POST;
    $name=trim((string)($input['business_name']??''));$lead=(int)($input['lead_time_days']??1);
    if(strlen($name)<3||$lead<0||$lead>365)$response=['status'=>'error','message'=>'Nama usaha dan lead time tidak valid.'];
    else{$q=$db->prepare('INSERT INTO supplier_profiles(supplier_id,business_name,contact_name,phone,address,lead_time_days,is_active) VALUES(:id,:name,:contact,:phone,:address,:lead,:active) ON DUPLICATE KEY UPDATE business_name=VALUES(business_name),contact_name=VALUES(contact_name),phone=VALUES(phone),address=VALUES(address),lead_time_days=VALUES(lead_time_days),is_active=VALUES(is_active)');$q->execute(['id'=>$uid,'name'=>$name,'contact'=>trim((string)($input['contact_name']??''))?:null,'phone'=>trim((string)($input['phone']??''))?:null,'address'=>trim((string)($input['address']??''))?:null,'lead'=>$lead,'active'=>!empty($input['is_active'])?1:0]);$response=['status'=>'success','message'=>'Profil supplier diperbarui.'];}
}
$response=ApiResponse::normalize($response);http_response_code(ApiResponse::codeFor($response));echo json_encode($response);
