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
function IPS_GetObject(int $var):array{return ['ObjectIsHidden'=>($GLOBALS['hiddenVars'][$var]??false)];}
function IPS_GetObjectIDByIdent(string $ident,int $parent):int|false{return $ident==='Dashboard' && $GLOBALS['legacyDashboard'] ? 1234:false;}
function IPS_GetInstanceListByModuleID(string $id):array{return [19];}
function AC_GetLoggingStatus(int $id,int $var):bool{return !empty($GLOBALS['archived']);}
function AC_GetAggregationType(int $id,int $var):int{return $GLOBALS['archiveAggregation'];}
function AC_SetLoggingStatus(int $id,int $var,bool $state):bool{$GLOBALS['archived']=true;return true;}
function AC_SetAggregationType(int $id,int $var,int $type):bool{$GLOBALS['archiveAggregation']=$type;return true;}
$GLOBALS['mockInstances'] = [];
$GLOBALS['migrationApplyCount'] = 0;
function IPS_SetProperty(int $id, string $key, mixed $value): bool {
  if (!isset($GLOBALS['mockInstances'][$id])) return false;
  $GLOBALS['mockInstances'][$id]->props[$key] = $value;
  return true;
}
function IPS_ApplyChanges(int $id):void {
  if (!isset($GLOBALS['mockInstances'][$id])) return; // Archive Control
  if (++$GLOBALS['migrationApplyCount'] > 10) throw new RuntimeException('unexpected recursive ApplyChanges');
  $GLOBALS['mockInstances'][$id]->ApplyChanges();
}
function AC_AddLoggedValues(int $id,int $var,array $v):bool {if (!empty($GLOBALS['archiveFail'])) return false;$GLOBALS['archiveEvents']=array_merge($GLOBALS['archiveEvents'],$v);return true;}
function AC_ReAggregateVariable(int $id,int $var):void{}
function AC_GetLoggedValues(int $id,int $var,int $start,int $end,int $limit):array {
  if (!empty($GLOBALS['archiveReadFail'])) throw new RuntimeException('Mock archive temporarily unreadable');
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
t($GLOBALS['hiddenVars'][$weeklyID]===true,'unavailable period is suppressed to avoid false zero');
// Integration regression: a spurious zero logged at activation must not be
// subtracted from the cumulative meter. 10 days of readings also populate
// partial 30/365-day variables without waiting months.
$GLOBALS['archiveEvents'] = [];
$m4=new MockModule(); $m4->Create();
$m4->props['Email']='me@example.org'; $m4->props['Password']='fake'; $m4->ApplyChanges();
for($d=1;$d<=10;$d++) {
    $GLOBALS['archiveEvents'][]=['TimeStamp'=>$today-$d*86400,'Value'=>17.200-$d*.05];
}
$GLOBALS['archiveEvents'][]=['TimeStamp'=>$today-86400+6*3600,'Value'=>0.0];
$m4->mock->r=['reading'=>17.200,'measuredAt'=>gmdate('c',$today)];
$m4->RequestAction('Poll',true);
t(abs($m4->values['Consumption24h']-50.0)<.001,'24h cannot equal the 17.2 m3 meter total because of a zero archive artifact');
t(abs($m4->values['Consumption7d']-350.0)<.001,'7-day value includes exactly seven existing daily deltas');
t(abs($m4->values['Consumption30d']-500.0)<.001,'30-day value uses the ten available daily deltas');
t(abs($m4->values['Consumption365d']-500.0)<.001,'365-day value uses the same ten available daily deltas');
t($GLOBALS['hiddenVars'][(crc32('Consumption30d')&0x7fffffff)]===false,'30-day variable automatically available after one valid interval');
// Update must also repair stale 0/total values from the previous version using
// the stored archive, without changing account registration or archived rows.
$m4->values['Consumption24h']=17200.0;
$m4->values['Consumption30d']=0.0;
$countBefore=count($GLOBALS['archiveEvents']);
$m4->ApplyChanges();
t(abs($m4->values['Consumption24h']-50.0)<.001 && abs($m4->values['Consumption30d']-500.0)<.001,'upgrading recalculates existing wrong values without a new reading');
t(count($GLOBALS['archiveEvents'])===$countBefore,'no changes to original archive on upgrade');


// Real-world upgrade scenario: meter installed at zero ~14 days before monitoring.
// Three genuine cloud readings 03, 04, 05 Nov: 1.715, 1.853, 1.949 m3.
$GLOBALS['archiveEvents']=[];
$m5=new MockModule();$m5->Create();
$m5->props['Email']='me@example.org';$m5->props['Password']='fake';
$tz=new DateTimeZone('Europe/Berlin');
$time=function($day, $hour)use($tz){return (new DateTimeImmutable($day.' '.$hour,$tz))->getTimestamp();};
$last=$time('2026-11-05','13:51:00');
$GLOBALS['archiveEvents']=[
 ['TimeStamp'=>$time('2026-11-03','14:25:00'),'Value'=>1.715],
 ['TimeStamp'=>$time('2026-11-04','14:25:00'),'Value'=>1.853],
];
$m5->props['UseInstallReading']=true;
$m5->props['InstallDate']='2026-10-27';
$m5->props['InstallReadingM3']=0.0;
$m5->ApplyChanges();
$m5->mock->r=['reading'=>1.949,'measuredAt'=>gmdate('c',$last)];
$m5->RequestAction('Poll',true);
t(abs($m5->values['Consumption24h']-96)<0.001,'real case: 24h is 96 litres, not total');
t(abs($m5->values['Consumption7d']-234)<0.001,'real case: 7d stays 234 litres');
t(abs($m5->values['Consumption30d']-1949)<0.001,'real case: 30d recovers 1949 litres since installation');
t(abs($m5->values['Consumption365d']-1949)<0.001,'real case: 365d recovers 1949 litres since installation');
t(str_contains($m5->values['Report'],'Berechnung 30d: Einbaustand'),'live report explains installation basis');
$before=count($GLOBALS['archiveEvents']);
$m5->ApplyChanges();
t(abs($m5->values['Consumption30d']-1949)<0.001,'new baseline persists after ApplyChanges');
t(count($GLOBALS['archiveEvents'])===$before,'installation reference never inserted into archive');
// Invalid installation date does not change normal live measurements.
$m5->props['InstallDate']='2026-02-30';$m5->ApplyChanges();
t(abs($m5->values['Consumption30d']-234)<.001,'invalid date rejected, archive-only fallback');
$m5->props['UseInstallReading']=false;$m5->ApplyChanges();
t(abs($m5->values['Consumption30d']-234)<.001,'opt-out restores archive-only totals');
// Never take a newly configured baseline as an invented most-recent daily sample.
t(abs($m5->values['Consumption24h']-96)<.001,'opt-out preserves 24h measurement');


// 0.1.9: 24h consumption must match consecutive confirmed cloud snapshots,
// and must NOT show a stale daily value after a missed-day gap.
$GLOBALS['archiveEvents']=[];
$dayMock=new MockModule();$dayMock->Create();
$dayMock->props['Email']='me@example.org';$dayMock->props['Password']='fake';
$dayMock->ApplyChanges();
$d0=time()-5*86400;
$feed=function(MockModule $m,float $counter,int $ts):void{
    $m->mock->r=['reading'=>$counter,'measuredAt'=>gmdate('c',$ts)];
    $m->RequestAction('Poll',true);
};
$feed($dayMock,1.000,$d0);
$feed($dayMock,1.100,$d0+86400);
$dayVar=crc32('Consumption24h')&0x7fffffff;
t(abs($dayMock->values['Consumption24h']-100.0)<.00001,'two real daily cloud readings = 100 L');
t($GLOBALS['hiddenVars'][$dayVar]===false,'valid daily variable is visible');
t(str_contains($dayMock->attrs['ConsumptionBasis'],'direkte Cloud-Differenz'),'basis identifies direct cloud data');
$dayMock->RequestAction('Poll',true);
t(abs($dayMock->values['Consumption24h']-100.0)<.00001,'duplicate does not change daily value');
// A 48h interval cannot be presented as a 24h reading: hide it, never set 0.
$feed($dayMock,1.280,$d0+3*86400);
t(abs($dayMock->values['Delta']-180.0)<.00001,'missed day: 180 L since last cloud reading');
t($GLOBALS['hiddenVars'][$dayVar]===true,'48h gap automatically hides misleading 24h display');
t($dayMock->attrs['PeriodReadiness']!=='' && json_decode($dayMock->attrs['PeriodReadiness'],true)['24h']===false,'48h gap marked unavailable internally');
t(abs($dayMock->values['Consumption7d']-280.0)<.00001,'7d preserves 2-day interval accurately');
// When another actual 24h interval arrives, display returns to service.
$feed($dayMock,1.335,$d0+4*86400);
t(abs($dayMock->values['Consumption24h']-55.0)<.00001,'valid following day restores exact 55 L');
t($GLOBALS['hiddenVars'][$dayVar]===false,'temporarily hidden day restored');
// A manually hidden variable remains hidden after future accepted readings.
IPS_SetHidden($dayVar,true);
$feed($dayMock,1.390,$d0+5*86400);
t($GLOBALS['hiddenVars'][$dayVar]===true,'manual hide remains respected even with new daily reading');
// The daily figure remains exact when historical archive reads are temporarily unavailable.
$GLOBALS['archiveReadFail']=true;
$dayMock->ApplyChanges();
t(abs($dayMock->values['Consumption24h']-55.0)<.00001,'direct cloud-pair daily value survives archive outage');
t(json_decode($dayMock->attrs['PeriodReadiness'],true)['24h']===true,'direct daily validity retained without archive');
$GLOBALS['archiveReadFail']=false;
$dayMock->ApplyChanges();
// When false archive readings exist, they must not override the true cloud pair.
$GLOBALS['archiveEvents'][]=['TimeStamp'=>$d0+4*86400+5*3600,'Value'=>0.0];
$dayMock->ApplyChanges();
t(abs($dayMock->values['Consumption24h']-55.0)<.00001,'archive startup zero never overrides accepted cloud daily pair');


// v0.1.10: the owner's existing instance migrates itself on the update timer.
// Crucially, matching numerical InstanceID alone does not trigger a change.
$tz = new DateTimeZone('Europe/Berlin');
$ts = static fn(string $t): int => (new DateTimeImmutable($t, $tz))->getTimestamp();
$GLOBALS['archiveEvents'] = [
 ['TimeStamp'=>$ts('2026-11-03 14:25:00'),'Value'=>1.715],
 ['TimeStamp'=>$ts('2026-11-04 14:25:00'),'Value'=>1.853],
];
$auto=new MockModule();$auto->InstanceID=35654;$auto->Create();
$auto->props['Email']='me@example.org';$auto->props['Password']='fake';
$auto->attrs['LastTimestamp']=$ts('2026-11-05 13:51:00');
$auto->attrs['LastValue']=1.949;
$GLOBALS['mockInstances'][35654]=$auto;
$before=count($GLOBALS['archiveEvents']);
$auto->ApplyChanges();
t($auto->timers['InstallAutoMigration']===2000,'on update, known instance schedules a native migration timer');
t($auto->props['UseInstallReading']===false, 'migration waits for native timer, not reentrant in ApplyChanges');
$auto->RequestAction('InstallAutoMigration',true);
t($auto->props['UseInstallReading']===true,'automatic install baseline activated without user script');
t($auto->props['InstallDate']==='2026-10-27','known exact installation date selected automatically');
t($auto->props['InstallReadingM3']===0.0,'exact installed zero saved');
t(abs($auto->values['Consumption30d']-1949.0)<.001 && abs($auto->values['Consumption365d']-1949.0)<.001,'30d and 365d repaired at once');
t(abs($auto->values['Consumption7d']-234.0)<.001,'short 7-day period remains genuine partial history with no fabricated date');
t($auto->timers['InstallAutoMigration']===0,'successful migration cancels its own timer');
t(count($GLOBALS['archiveEvents'])===$before,'no historical archive modifications on auto migration');
$applyBefore=$GLOBALS['migrationApplyCount'];
$auto->ApplyChanges();
t($auto->timers['InstallAutoMigration']===0 && $GLOBALS['migrationApplyCount']===$applyBefore, 'idempotent: no endless reapply');
// Other instances and manually configured instances must never be modified.
$other=new MockModule();$other->Create();$other->props['Email']='me@example.org';$other->props['Password']='fake';
$other->attrs['LastTimestamp']=$ts('2026-11-05 13:51:00');$other->attrs['LastValue']=1.949;
$other->ApplyChanges();
t($other->timers['InstallAutoMigration']===0,'different Symcon instance never receives known opening baseline');
$wrong=new MockModule();$wrong->InstanceID=35654;$wrong->Create();
$wrong->props['Email']='me@example.org';$wrong->props['Password']='fake';
$wrong->attrs['LastTimestamp']=$ts('2026-11-05 13:51:00');$wrong->attrs['LastValue']=1.949;
$GLOBALS['mockInstances'][35654]=$wrong;
$GLOBALS['archiveEvents'][0]['Value']=1.714;
$wrong->ApplyChanges();
t($wrong->timers['InstallAutoMigration']===0,'incorrect 03 Nov archive fingerprint blocks automatic changes');
$GLOBALS['archiveEvents'][0]['Value']=1.715;
$wrong->props['InstallDate']='2026-10-26'; // user supplied different date: do not overwrite
$wrong->ApplyChanges();
t($wrong->timers['InstallAutoMigration']===0,'user-configured installation date always wins');
unset($GLOBALS['mockInstances'][35654]);
echo "ALL MODULE TESTS PASSED\n";