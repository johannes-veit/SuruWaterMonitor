<?php
error_reporting(E_ALL);
class IPSModuleStrict {
  public int $InstanceID=12597;
  public array $props=[],$attrs=[],$values=[],$timers=[];
  public function Create():void {} public function ApplyChanges():void {}
  protected function RegisterPropertyString(string $a,string $b):void{$this->props[$a]=$b;}
  protected function RegisterPropertyInteger(string $a,int $b):void{$this->props[$a]=$b;}
  protected function RegisterPropertyBoolean(string $a,bool $b):void{$this->props[$a]=$b;}
  protected function RegisterPropertyFloat(string $a,float $b):void{$this->props[$a]=$b;}
  protected function RegisterAttributeString(string $a,string $b):void{$this->attrs[$a]=$b;}
  protected function RegisterAttributeInteger(string $a,int $b):void{$this->attrs[$a]=$b;}
  protected function RegisterAttributeFloat(string $a,float $b):void{$this->attrs[$a]=$b;}
  protected function ReadPropertyString(string $a):string{return $this->props[$a];}
  protected function ReadPropertyInteger(string $a):int{return $this->props[$a];}
  protected function ReadPropertyBoolean(string $a):bool{return $this->props[$a];}
  protected function ReadPropertyFloat(string $a):float{return $this->props[$a];}
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
  protected function UnregisterVariable(string $a):bool{$GLOBALS['legacyDashboard']=false;unset($this->values[$a]);return true;}
}
$GLOBALS['archiveEvents']=[];
$GLOBALS['hiddenVars']=[];
$GLOBALS['archiveAvailable']=true;
$GLOBALS['archiveAggregation']=0;
$GLOBALS['semaphoreLocked']=false;
$GLOBALS['legacyDashboard']=false;
function IPS_SemaphoreEnter(string $name,int $timeout):bool {if ($GLOBALS['semaphoreLocked']) return false; $GLOBALS['semaphoreLocked']=true;return true;}
function IPS_SemaphoreLeave(string $name):void {$GLOBALS['semaphoreLocked']=false;}

function IPS_SetHidden(int $var,bool $hidden):void{$GLOBALS['hiddenVars'][$var]=$hidden;}
function IPS_GetObjectIDByIdent(string $ident,int $parent):int|false{return $ident==='Dashboard' && $GLOBALS['legacyDashboard'] ? 1234:false;}
function IPS_GetInstanceListByModuleID(string $id):array{return [19];}
function AC_GetLoggingStatus(int $id,int $var):bool{return !empty($GLOBALS['archived']);}
function AC_GetAggregationType(int $id,int $var):int{return $GLOBALS['archiveAggregation'];}
function AC_SetLoggingStatus(int $id,int $var,bool $state):bool{$GLOBALS['archived']=true;return true;}
function AC_SetAggregationType(int $id,int $var,int $type):bool{$GLOBALS['archiveAggregation']=$type;return true;}
function IPS_ApplyChanges(int $id):void{}
function AC_AddLoggedValues(int $id,int $var,array $v):bool {if (!empty($GLOBALS['archiveFail'])) return false;$GLOBALS['archiveEvents']=array_merge($GLOBALS['archiveEvents'],$v);return true;}
function AC_ReAggregateVariable(int $id,int $var):void{}
function AC_GetLoggedValues(int $id,int $var,int $start,int $end,int $limit):array {
  $rows=array_values(array_filter($GLOBALS['archiveEvents'], fn($r)=>$r['TimeStamp'] >= $start && $r['TimeStamp'] <= $end));
  usort($rows, fn($a,$b)=>$b['TimeStamp']<=>$a['TimeStamp']);
  return $limit>0 ? array_slice($rows,0,$limit) : array_slice($rows,0,10000);
}
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
$m=new MockModule();$m->Create();
t(!isset($m->values['Dashboard']), 'no HTML/dashboard variable on new install');
$GLOBALS['legacyDashboard']=true; // Upgrade from the old 0.1.4 HTML variable
$m->ApplyChanges();
t($GLOBALS['legacyDashboard']===false, 'legacy HTML variable removed on upgrade');
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
// Version upgrade never resets existing archive and retains numeric variables.
$oldArchiveCount=count($GLOBALS['archiveEvents']);
t(!isset($m->values['Dashboard']), 'only native variables are generated');
// Check daily time series using the existing archived counter without any extra API call.
$GLOBALS['archiveEvents']=[];
$m2=new MockModule();$m2->Create();$m2->props['Email']='me@example.org';$m2->props['Password']='fake';$m2->ApplyChanges();
$today=time()-300;
for($d=1;$d<=365;$d++) $GLOBALS['archiveEvents'][]=['TimeStamp'=>$today-$d*86400,'Value'=>1000.0-$d*.005];
$m2->mock->r=['reading'=>1000.0,'measuredAt'=>gmdate('c',$today)];
$m2->RequestAction('Poll',true);
t(abs($m2->values['Consumption24h']-5.0)<.001,'daily consumption from archive');
t(abs($m2->values['Consumption7d']-35.0)<.001,'weekly consumption from archive');
t(abs($m2->values['Consumption30d']-150.0)<.001,'30-day consumption from archive');
t(abs($m2->values['Consumption365d']-1825.0)<.001,'365-day consumption from archive');
t($GLOBALS['hiddenVars'][(crc32('Consumption365d')&0x7fffffff)]===false,'complete history exposes annual numeric variable');
t($m2->values['Meter']===1000.0,'latest meter is a native numeric variable');
t($m2->values['MeasuredAt']===date('d.m.Y H:i:s',$today),'measurement date and time available as native string');
// Manual hiding remains effective after later refresh or ApplyChanges.
$hiddenID=crc32('Consumption365d')&0x7fffffff;
IPS_SetHidden($hiddenID,true);
$m2->ApplyChanges();
t($GLOBALS['hiddenVars'][$hiddenID]===true,'manual visibility stays unchanged after refresh');
// Missing historical samples start hidden; no automatic visibility overwrite during refresh.
$GLOBALS['archiveEvents']=[];
$m3=new MockModule();$m3->Create();$m3->props['Email']='me@example.org';$m3->props['Password']='fake';$m3->ApplyChanges();
$m3->mock->r=['reading'=>12.345,'measuredAt'=>gmdate('c',$today)];$m3->RequestAction('Poll',true);
t($GLOBALS['hiddenVars'][(crc32('Consumption7d')&0x7fffffff)]===true,'short history hides unavailable week');
// User chooses which native variables to display, even when not enough history exists.
$weeklyID=crc32('Consumption7d')&0x7fffffff;
IPS_SetHidden($weeklyID,false);
$m3->ApplyChanges();
t($GLOBALS['hiddenVars'][$weeklyID]===false,'manual unhide is respected for unknown periods');
// End-to-end: test an incorrect archive startup zero and a 10-day history.
$GLOBALS['archiveEvents']=[];
$m4=new MockModule();$m4->Create();
$m4->props['Email']='me@example.org';$m4->props['Password']='fake';$m4->ApplyChanges();
for($d=1;$d<=10;$d++) {
  $GLOBALS['archiveEvents'][]=['TimeStamp'=>$today-$d*86400,'Value'=>17.200-$d*.05];
}
$GLOBALS['archiveEvents'][]=['TimeStamp'=>$today-86400+6*3600,'Value'=>0.0];
$m4->mock->r=['reading'=>17.200,'measuredAt'=>gmdate('c',$today)];
$m4->RequestAction('Poll',true);
t(abs($m4->values['Consumption24h']-50.0)<.001,'daily value excludes false 0 in archive');
t(abs($m4->values['Consumption7d']-350.0)<.001,'7d correctly includes seven available days');
t(abs($m4->values['Consumption30d']-500.0)<.001,'30d includes all ten days');
t(abs($m4->values['Consumption365d']-500.0)<.001,'365d includes all ten days');
$m4->values['Consumption24h']=17200.0;
$m4->values['Consumption30d']=0.0;
$before=count($GLOBALS['archiveEvents']);
$m4->ApplyChanges();
t(abs($m4->values['Consumption24h']-50.0)<.001 && abs($m4->values['Consumption30d']-500.0)<.001,'module update repairs existing bad values');
t(count($GLOBALS['archiveEvents'])===$before,'archive untouched after re-computation');

// Full integration regression for the newly installed meter (0 -> 1.949 m3).
$GLOBALS['archiveEvents']=[];
$baselineModule=new MockModule();$baselineModule->Create();
$baselineModule->props['Email']='me@example.org';
$baselineModule->props['Password']='fake';
$baselineModule->props['UseInstallReading']=true;
$baselineModule->props['InstallDate']='2026-10-23';
$baselineModule->props['InstallReadingM3']=0.0;
$loc=new DateTimeZone('Europe/Berlin');
$time=function(string $s)use($loc):int {return (new DateTimeImmutable($s,$loc))->getTimestamp();};
$latest=$time('2026-11-05 13:51');
$GLOBALS['archiveEvents']=[
 ['TimeStamp'=>$time('2026-11-03 14:25'),'Value'=>1.715],
 ['TimeStamp'=>$time('2026-11-04 14:25'),'Value'=>1.853],
];
$baselineModule->ApplyChanges();
$baselineModule->mock->r=['reading'=>1.949,'measuredAt'=>gmdate('c',$latest)];
$baselineModule->RequestAction('Poll',true);
t(abs($baselineModule->values['Consumption24h']-96.0)<.001,'real case 24h 96 L');
t(abs($baselineModule->values['Consumption7d']-234.0)<.001,'real case 7d 234 L');
t(abs($baselineModule->values['Consumption30d']-1949.0)<.001,'real case 30d 1949 L');
t(abs($baselineModule->values['Consumption365d']-1949.0)<.001,'real case 365d 1949 L');
$rawRows=count($GLOBALS['archiveEvents']);
$baselineModule->ApplyChanges();
t(count($GLOBALS['archiveEvents'])===$rawRows,'known starting counter never backdated into archive');
t(abs($baselineModule->values['Consumption30d']-1949.0)<.001,'baseline survives ApplyChanges');
$baselineModule->props['UseInstallReading']=false;
$baselineModule->ApplyChanges();
t(abs($baselineModule->values['Consumption30d']-234.0)<.001,'opt-out returns to archive-only partial total');
echo "ALL MODULE TESTS PASSED\n";
