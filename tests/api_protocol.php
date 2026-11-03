<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/SuruApi.php';
require_once __DIR__ . '/../libs/Meter.php';
function verify(bool $condition, string $description): void {
    if (!$condition) throw new RuntimeException('FAIL ' . $description);
    echo 'PASS ' . $description . "\n";
}
class FakeCloud extends SuruApi {
    public array $calls=[];
    public bool $denyReading=false;
    public bool $expiredOnce=false;
    private bool $expired=false;
    private int $auths=0;
    public function __construct() { parent::__construct('unit@example.com','pw'); }
    protected function request(string $url, string $method, ?array $data, array $headers): array {
        $this->calls[]=['url'=>$url,'method'=>$method,'data'=>$data];
        if ($method==='POST') {
            if (str_ends_with((string)end($headers),'InitiateAuth')) return [
                'ChallengeName'=>'PASSWORD_VERIFIER',
                'ChallengeParameters'=>[
                    'USER_ID_FOR_SRP'=>'testunit','SALT'=>'a1b2c3d4',
                    'SRP_B'=>str_repeat('ff',64), 'SECRET_BLOCK'=>'c2VjcmV0'
                ]
            ];
            if (str_ends_with((string)end($headers),'RespondToAuthChallenge')) {
                $this->auths++;
                return ['AuthenticationResult'=>['AccessToken'=>'SYNTHETIC-'.$this->auths,'ExpiresIn'=>3600]];
            }
            throw new RuntimeException('Unexpected auth route');
        }
        if ($this->expiredOnce && !$this->expired) {
            $this->expired=true;
            throw new SuruException('Expired session',401);
        }
        if (str_ends_with($url,'/units')) return ['units'=>[['id'=>'UNIT-1']]];
        if (str_contains($url,'/reading')) {
            if ($this->denyReading) throw new SuruException('Not allowed by role',403);
            return ['reading'=>['reading'=>1.234,'measuredAt'=>'2026-10-27T12:00:00Z']];
        }
        throw new RuntimeException('Unexpected endpoint: '.$url);
    }
}
$fake=new FakeCloud();
$units=$fake->units();
verify($units[0]['id']==='UNIT-1','login and discover units');
$read=$fake->reading('UNIT-1');
verify(abs(SuruMeter::parseReading($read)['value']-1.234)<0.00001,'read the cumulative meter');
$gets=array_filter($fake->calls,fn($v)=>$v['method']==='GET');
verify(count($gets)===2,'two GET calls for initial read');
verify(count(array_filter($fake->calls,fn($v)=>$v['method']==='POST'))===2,'initial Cognito login uses two POST calls');
verify(count(array_filter($gets,fn($v)=>str_contains($v['url'],'/consumption')))===0,'no BASIC premium endpoint');
$fake->expiredOnce=true;
$fake->units();
verify(count(array_filter($fake->calls,fn($v)=>$v['method']==='POST'))===4,'automatic token renewal on 401');
$fake->denyReading=true;
try { $fake->reading('UNIT-1'); throw new RuntimeException('FAIL 403 test'); }
catch (SuruException $e) { verify($e->httpStatus===403,'403 surfaced as optional access restriction'); }
echo "ALL API PROTOCOL TESTS PASSED\n";
