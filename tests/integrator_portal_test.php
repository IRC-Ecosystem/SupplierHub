<?php
ini_set('session.save_path', sys_get_temp_dir());
require_once __DIR__.'/../controllers/AuthController.php';
require_once __DIR__.'/../services/IntegrationService.php';
function assertIntegrator($condition,$message){if(!$condition)throw new RuntimeException($message);}
$login=AuthController::login(['email'=>'integrator@b2blink.com','password'=>'password123']);
assertIntegrator($login['status']==='success','Login integrator gagal.');
assertIntegrator(($login['data']['role']??'')==='integrator','Role integrator salah.');
assertIntegrator(str_contains($login['data']['redirect'],'p=integrator'),'Redirect integrator salah.');
$config=IntegrationService::configuration();
assertIntegrator(isset($config['smartbank'],$config['logistikita'],$config['umkm_insight'],$config['outbox']),'Status integrasi tidak lengkap.');
$summary=IntegrationService::insightSummary();
assertIntegrator(isset($summary['orders'],$summary['receipts'],$summary['generated_at']),'Insight summary tidak lengkap.');
$body=json_encode(['order_id'=>1,'status'=>'succeeded','payment_reference'=>'TEST']);
putenv('INTEGRATION_WEBHOOK_SECRET=test-integrator-secret-32-characters');
$_SERVER['HTTP_X_B2BLINK_SIGNATURE']='sha256='.hash_hmac('sha256',$body,'test-integrator-secret-32-characters');
assertIntegrator(IntegrationService::verifySignature($body),'Signature valid ditolak.');
$_SERVER['HTTP_X_B2BLINK_SIGNATURE']='sha256=invalid';
assertIntegrator(!IntegrationService::verifySignature($body),'Signature invalid diterima.');
putenv('INTEGRATION_WEBHOOK_SECRET');unset($_SERVER['HTTP_X_B2BLINK_SIGNATURE']);
echo "INTEGRATOR_PORTAL_OK\n";
