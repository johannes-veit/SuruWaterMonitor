<?php
error_reporting(E_ALL);
class IPSModuleStrict {
  public int $InstanceID=12597;
  public array $props=[],$attrs=[],$values=[],$timers=[];
  public function Create():void {} public function ApplyChanges():void {}
  protected function RegisterPropertyString(string $a,string $b):void{$this->props[$a]=$b;}
  protected function RegisterPropertyInteger(string $a,int $b):void{$this->props[$a]=$b;}
  protected function RegisterAttributeString(string $a,string $b):void{$this->attrs[$a]=$b;}
  protected function RegisterAttributeInteger(string $a,int $b):void{$this->attrs[$a]=$b;}
  protected function RegisterAttributeFloat(string $a,float $b):void{$this->attrs[$a]=$b;}
  protected function ReadPropertyString(string $a):string{return $this->props[$a];}
  protected function ReadPropertyInteger(string $a):int{return $this->props[$a];}
  protected function ReadAttributeString(string $a):string{return $this->attrs[$a];}
  protected function ReadAttributeInteger(string $a):int{return $this->attrs[$a];}
  protected function ReadAttributeFloat(string $a):float{return $this->attrs[$a];}
  protected function WriteAttributeString(string $a,string $b):void{$this->attrs[$a]=$b;}
  protected function WriteAttributeInteger(string $a,int $b):void{$this->attrs[$a]=$b;}
  protected function WriteAttributeFloat(string $a,float $b):void{$this->attrs[$a]=$b;}
  protected function RegisterTimer(string $a,int $b,string $c):void{$this->timers[$a]=$b;}
  protected function SetTimerInterval(string $a,int $b):void{$this->timers[$a]=$b;}
  protected function RegisterVariableFloat(string $a,string $b,array $c,int $d):bool{$this->values[$a]=0.0;return true;}
  protected function RegisterVariableString(string $a,string $b,array $c,int $d):bool{$this->values[$a]='';return true;}
  protected function SetValue(string $a,mixed $b):void{$this->values[$a]=$b;}
  protected function GetValue(string $a):mixed{return $this->values[$a];}
  protected function GetIDForIdent(string $a):int{return crc32($a)&0x7fffffff;}
}
$GLOBALS['archiveEvents']=[];
$GLOBALS['archiveAvailable']=true;
$GLOBALS['archiveAggregation']=0;
$GLOBALS['semaphoreLocked']=false;
function IPS_SemaphoreEnter(string $name,int $timeout):bool {if ($GLOBALS['semaphoreLocked']) return false; $GLOBALS['semaphoreLocked']=true;return true;}
function IPS_SemaphoreLeave(string $name):void {$GLOBALS['semaphoreLocked']=false;}

function IPS_SetHidden(int $var,bool $hidden):void{}
function IPS_GetInstanceListByModuleID(string $id):array{return [19];}
function AC_GetLoggingStatus(int $id,int $var):bool{return !empty($GLOBALS['archived']);}
function AC_GetAggregationType(int $id,int $var):int{return $GLOBALS['archiveAggregation'];}
function AC_SetLoggingStatus(int $id,int $var,bool $state):bool{$GLOBALS['archived']=true;return true;}
function AC_SetAggregationType(int $id,int $var,int $type):bool{$GLOBALS['archiveAggregation']=$type;return true;}
function IPS_ApplyChanges(int $id):void{}
function AC_AddLoggedValues(int $id,int $var,array $v):bool {if (!empty($GLOBALS['archiveFail'])) return false;$GLOBALS['archiveEvents']=array_merge($GLOBALS['archiveEvents'],$v);return true;}
function AC_ReAggregateVariable(int $id,int $var):void{}
require_once __DIR__.'/../SuruWaterMonitor/module.php';
class MockApi extends SuruApi {
  public array $r=[];
  public ?SuruException $failUnits=null;
  public array $returnedUnits=[['id'=>'first']];
  public function __construct(){}
  public function units():array{if($this->failUnits!==null)throw $this->failUnits;return $this->returnedUnits;}
  public function reading(string $id):array{return $this->r;}
}
class MockModule extends SuruWaterMonitor {
  public MockApi $mock;
  public function __construct(){ $this->mock=new MockApi(); }
  protected function makeApi(): SuruApi {return $this->mock;}
}
function t(bool $v,string $msg):void{if(!$v)throw new Exception('FAIL '.$msg);echo "PASS $msg\n";}
$m=new MockModule();$m->Create();$m->ApplyChanges();
t(str_contains($m->values['Report'],'Cognito SRP: OK'),'offline crypto selftest');
t($m->props['MeterSerial']==='','no personal serial exposed in public module');
t($m->values['State']==='WARTE AUF WATERMONITOR BASIC KONTO','pending config state');
$m->props['Email']='me@example.org';$m->props['Password']='fake';$m->ApplyChanges();
t($m->timers['FirstPoll']===10000,'automatic first test scheduled');
$now=time()-60;
$m->mock->r=['reading'=>1.225,'measuredAt'=>gmdate('c',$now)];$m->RequestAction('Poll',true);
t($m->values['Meter']===1.225,'initial reading');
t(count($GLOBALS['archiveEvents'])===1,'first event logged once');
$m->RequestAction('Poll',true);t(count($GLOBALS['archiveEvents'])===1,'duplicate not logged');
$m->mock->r=['reading'=>1.227,'measuredAt'=>gmdate('c',$now+40)];$m->RequestAction('Poll',true);
t(abs($m->values['Delta']-2)<0.00001,'next delta 2 litres');
t(count($GLOBALS['archiveEvents'])===2,'second event archived');
$m->mock->r=['reading'=>1.200,'measuredAt'=>gmdate('c',$now+41)];$m->RequestAction('Poll',true);
t($m->values['Meter']===1.227,'decrease rejected');
t(str_contains($m->values['State'],'PRÜFUNG'),'invalid reading flagged');
t(count($GLOBALS['archiveEvents'])===2,'invalid event not archived');
$form=json_decode($m->GetConfigurationForm(),true,512,JSON_THROW_ON_ERROR);
t($form['elements'][2]['type']==='PasswordTextBox','masked password field');
t(str_starts_with($form['actions'][0]['caption'],'Aktueller Status:'),'dynamic form status');
t(str_contains($form['actions'][1]['caption'],'Selbsttest: OK'),'automatic test result on form');
$m->props['MeterSerial']='OTHER';$m->ApplyChanges();
t($m->attrs['UnitID']==='','meter binding invalidated when serial changed');
// Simulate temporary archive failure: latest known values must not advance.
$prevValue=$m->values['Meter'];$prevTs=$m->attrs['LastTimestamp'];
$GLOBALS['archiveFail']=true;
$now2=time()-8;
$m->mock->r=['reading'=>1.228,'measuredAt'=>gmdate('c',$now2)];
$m->RequestAction('Poll',true);
t($m->values['State']==='ARCHIV NICHT BEREIT','archive failure is visible');
t($m->attrs['LastTimestamp']===$prevTs && $m->values['Meter']===$prevValue,'archive failure does not advance last accepted reading');
t($m->timers['Retry']===600000,'archive failure schedules 10m retry');
$GLOBALS['archiveFail']=false;
$m->RequestAction('Poll',true);
t($m->attrs['LastTimestamp']===$now2,'retry accepts pending original timestamp');
t($m->timers['Retry']===0,'successful polling cancels retry');
// A different account / unit may not contaminate the archive.
$m->props['Email']='other-account@example.org';$m->ApplyChanges();
$eventCount=count($GLOBALS['archiveEvents']);
$m->RequestAction('Poll',true);
t($m->values['State']==='ZÄHLERZUORDNUNG GEÄNDERT','account change blocked');
t(count($GLOBALS['archiveEvents'])===$eventCount,'account change does not add archive rows');
// The atomic lock also protects against a manual poll at the same time.
$GLOBALS['semaphoreLocked']=true;
$m->RequestAction('Poll',true);
t(count($GLOBALS['archiveEvents'])===$eventCount,'parallel polling skipped');
$GLOBALS['semaphoreLocked']=false;
// API restrictions and network outages: distinguish authentication/access from transient errors.
$m->props['Email']='me@example.org';$m->ApplyChanges();
$m->mock->failUnits=new SuruException('Access denied',403);
$m->RequestAction('Poll',true);
t($m->values['State']==='BASIC-ZUGRIFF VERWEIGERT','BASIC role restriction reported clearly');
t($m->timers['Retry']===0,'403 does not cause retry loop');
$m->mock->failUnits=new SuruException('Server unavailable',503);
$m->RequestAction('Poll',true);
t($m->values['State']==='CLOUD NICHT VERFÜGBAR','temporary cloud outage reported');
t($m->timers['Retry']===600000,'temporary outage schedules retry');
$m->mock->failUnits=new SuruException('Rate limit',429);
$m->RequestAction('Poll',true);
t($m->values['State']==='ABFRAGELIMIT','429 rate limit reported');
t($m->timers['Retry']===0,'429 does not cause extra requests');
$m->mock->failUnits=null;
$m->mock->r=['reading'=>1.228,'measuredAt'=>gmdate('c',$now2)];
$m->RequestAction('Poll',true);
t($m->values['State']==='BETRIEBSBEREIT – BASIC','automatic recovery after transient outage');
$m->mock->returnedUnits=[['id'=>'another-unit']];
$m->RequestAction('Poll',true);
t($m->values['State']==='ZÄHLERZUORDNUNG GEÄNDERT','replacement meter blocked to preserve history');
echo "ALL MODULE TESTS PASSED\n";
