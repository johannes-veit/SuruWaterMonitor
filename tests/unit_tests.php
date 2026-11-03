<?php
require_once __DIR__.'/../libs/SuruApi.php';
require_once __DIR__.'/../libs/Meter.php';
function chk(bool $ok,string $msg):void {if (!$ok) throw new Exception('FAIL: '.$msg); echo 'PASS: '.$msg.PHP_EOL;}
$srp=new SuruCognito('u@example.com','Secret1!','12345');
$ch=['USER_ID_FOR_SRP'=>'uid-1','SALT'=>'a1b2c3d4','SRP_B'=>str_repeat('ff',64),'SECRET_BLOCK'=>'c2VjcmV0'];
$r=$srp->challenge($ch,new DateTimeImmutable('2026-06-25T12:00:01Z'));
chk(str_starts_with($srp->authParameters()['SRP_A'],'70a5e2dcadb95d26'),'SRP public value matches independent Python reference');
chk($r['PASSWORD_CLAIM_SIGNATURE']==='4+1L8da/3P4LM6+pS5zCalmqlL2FAZqmigN8tstWBjM=','Cognito signed challenge matches Python reference');
chk($r['TIMESTAMP']==='Thu Jun 25 12:00:01 UTC 2026','Cognito timestamp');
$now=time()-180;
$p=SuruMeter::parseReading(['reading'=>0.025,'measuredAt'=>gmdate('c',$now)]);
chk($p['timestamp']===$now && $p['value']===0.025,'correct SURU reading structure');
chk(SuruMeter::evaluate(5,$now,null,0)['kind']==='initial','first meter reading');
chk(SuruMeter::evaluate(5,$now,5,$now)['kind']==='duplicate','same timestamp and value');
chk(SuruMeter::evaluate(6,$now,5,$now)['kind']==='conflict','same timestamp different value');
chk(SuruMeter::evaluate(6,$now-100,5,$now)['kind']==='stale','stale reading');
chk(SuruMeter::evaluate(3,$now,4,$now-3600)['kind']==='decrease','decreasing value');
chk(SuruMeter::evaluate(800,$now,4,$now-3600)['kind']==='jump','extreme jump');
chk(SuruMeter::evaluate(4.008,$now,4.000,$now-60)['kind']==='new','plausible consumption');
try {SuruMeter::parseReading(['reading'=>'invalid','measuredAt'=>gmdate('c')]); throw new Exception('FAIL invalid accepted');}
catch(InvalidArgumentException $ok) {echo 'PASS: rejects invalid OCR/API reading'.PHP_EOL;}
try {SuruMeter::parseReading(['reading'=>1,'measuredAt'=>'2000-01-01T00:00:00+00:00']); echo "PASS: boundary timestamp accepted\n";}
catch(Throwable $e) {throw new Exception('Unexpected boundary timestamp failure');}
try {SuruMeter::parseReading(['reading'=>-1,'measuredAt'=>gmdate('c')]);throw new Exception('FAIL negative accepted');}
catch(InvalidArgumentException $ok){echo "PASS: negative rejected\n";}
class BasicFake extends SuruApi {
  public array $visited=[];
  public function __construct() {}
  public function get(string $path,array $query=[]):array {
    $this->visited[]=$path;
    if(str_contains($path,'/consumption'))throw new SuruException('Basic premium endpoint forbidden',403);
    if($path==='units')return ['units'=>[['id'=>'1']]];
    if(str_contains($path,'/reading'))return ['reading'=>1.0,'measuredAt'=>gmdate('c')];
    return [];
  }
}
$f=new BasicFake();$f->units();$f->reading('1');
chk(!array_filter($f->visited, fn($p)=>str_contains($p,'consumption')),'BASIC integration does not request paid endpoints');
echo "ALL UNIT TESTS PASSED\n";
