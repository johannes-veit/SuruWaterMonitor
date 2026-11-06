<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/Consumption.php';
function ck(bool $ok, string $why): void { if (!$ok) throw new RuntimeException('FAIL: ' . $why); echo 'PASS ' . $why . PHP_EOL; }
function near(?float $actual, float $expected, float $eps = .005): bool { return $actual !== null && abs($actual - $expected) <= $eps; }
$sec = 86400;
$base = 1793700000;
$run = static fn(array $rows, float $last=500.0, ?array $install=null): array => SuruConsumption::calculate($rows, $base, $last, $install);
ck($run([],17.15) === ['24h'=>null,'7d'=>null,'30d'=>null,'365d'=>null], 'first reading cannot be mistaken for 24h consumption');
$rows=[];
for($i=1;$i<=366;$i++) $rows[]=['TimeStamp'=>$base-$i*$sec,'Value'=>500-$i*.1];
$r=$run($rows);
ck(near($r['24h'],100) && near($r['7d'],700) && near($r['30d'],3000) && near($r['365d'],36500),'all four complete rolling windows');
$r=$run(array_slice($rows,0,10));
ck(near($r['24h'],100) && near($r['7d'],700) && near($r['30d'],1000) && near($r['365d'],1000),'ten-day history: partial 30/365, full 7');
$r=$run(array_slice($rows,0,1));
ck(near($r['24h'],100) && near($r['7d'],100) && near($r['30d'],100) && near($r['365d'],100),'one daily interval fills partial windows');
$r=$run([['TimeStamp'=>$base-2*$sec,'Value'=>499.5]]);
ck($r['24h']===null && near($r['7d'],500) && near($r['30d'],500),'48h gap never becomes daily rate but known two-day delta counts in longer windows');
$r=$run([['TimeStamp'=>$base-$sec,'Value'=>500.0]]);
ck($r===['24h'=>0.,'7d'=>0.,'30d'=>0.,'365d'=>0.],'genuine zero water consumption');
$r=$run([['TimeStamp'=>$base-$sec,'Value'=>501.0]]);
ck($r['24h']===null && $r['7d']===null,'counter decrease rejected');
$r=$run([['TimeStamp'=>$base-$sec,'Value'=>499.9],['TimeStamp'=>$base-3*$sec,'Value'=>499.7],['TimeStamp'=>$base-4*$sec,'Value'=>499.6]]);
ck(near($r['24h'],100) && near($r['7d'],400),'known two-day interval retained even when a daily upload is missed');
$r=$run([['TimeStamp'=>$base-$sec+3600,'Value'=>499.95]]);
ck(near($r['24h'],50),'23h upload difference');
$r=$run([['TimeStamp'=>$base-$sec-3600,'Value'=>499.95]]);
ck(near($r['24h'],50),'25h DST difference');
$r=$run([['TimeStamp'=>$base-36*3600,'Value'=>499.95]]);
ck($r['24h']===null && near($r['7d'],50),'36h interval counts in 7d, not 24h');
$r=$run([['TimeStamp'=>$base-$sec,'Value'=>499.95],['TimeStamp'=>$base-$sec,'Value'=>499.90]]);
ck($r['24h']===null,'conflicting readings at same timestamp ignored');
$r=$run([['TimeStamp'=>$base-$sec,'Value'=>17.150],['TimeStamp'=>$base-$sec+6*3600,'Value'=>0.0],['TimeStamp'=>$base-2*$sec,'Value'=>17.100]],17.200);
ck(near($r['24h'],50) && near($r['7d'],100),'startup zero between authentic readings ignored');
$r=$run([['TimeStamp'=>$base-$sec,'Value'=>0.0],['TimeStamp'=>$base-2*$sec,'Value'=>17.100]],17.200);
ck($r['24h']===null && near($r['7d'],100),'earlier startup zero cannot imply whole meter consumed in one day');
$r=$run([['TimeStamp'=>$base-$sec,'Value'=>17.15],['TimeStamp'=>$base-2*$sec,'Value'=>999.0],['TimeStamp'=>$base-3*$sec,'Value'=>17.05]],17.2);
ck(near($r['24h'],50) && near($r['7d'],150),'implausible archive spike ignored');

$zone=new DateTimeZone('Europe/Berlin');
$t=static fn(string $v):int => (new DateTimeImmutable($v,$zone))->getTimestamp();
$start=$t('2026-10-27 12:00:00');
$reading1=$t('2026-11-03 14:25:00');
$reading2=$t('2026-11-04 14:25:00');
$reading3=$t('2026-11-05 13:51:00');
$installation=['timestamp'=>$start,'value'=>0.0];
$history=[['TimeStamp'=>$reading1,'Value'=>1.715],['TimeStamp'=>$reading2,'Value'=>1.853]];
$values=SuruConsumption::calculate($history,$reading3,1.949,$installation);
ck(near($values['24h'],96)&&near($values['7d'],234)&&near($values['30d'],1949)&&near($values['365d'],1949),'real installation: 96/234/1949/1949 litres on Nov 5');
$detail=SuruConsumption::calculateDetailed($history,$reading3,1.949,$installation);
ck(str_contains($detail['basis']['30d'],'vollständiger')&&!str_contains($detail['basis']['7d'],'Einbau'),'long window confirmed, week stays measured');
ck(near(SuruConsumption::calculate($history,$reading3,1.949)['30d'],234),'disabling correction returns partial archive total');
ck(near(SuruConsumption::calculate($history,$reading3,1.949,['timestamp'=>$reading2,'value'=>0.])['30d'],234),'baseline after first recorded measurement is invalid');
ck(near(SuruConsumption::calculate($history,$reading3,1.949,['timestamp'=>$start,'value'=>5.])['30d'],234),'baseline larger than first measurement invalid');

// Model the real unknown interval, with no fabricated daily consumption values:
// Oct 27 0 m3 -> Nov 3 1.715 m3. For test future samples increase by 0.100 m3/day.
function synthetic(int $time, int $firstTime, float $firstValue): array {
    $n=(int) floor(($time-$firstTime)/86400);
    $rows=[];
    for($i=0;$i<=$n;$i++) $rows[]=['TimeStamp'=>$firstTime+$i*86400,'Value'=>$firstValue+$i*.1];
    return ['history'=>array_slice($rows,0,-1),'latest'=>end($rows)];
}
$lastDate=$t('2026-11-26 14:25:00');
$x=synthetic($lastDate,$reading1,1.715);
$nowTime=$x['latest']['TimeStamp'];$nowMeter=$x['latest']['Value'];
$calc=SuruConsumption::calculateDetailed($x['history'],$nowTime,$nowMeter,$installation);
$border=$nowTime-30*86400;
$estimatedInGap=1.715*($border-$start)/($reading1-$start);
$expected=($nowMeter-$estimatedInGap)*1000;
ck($border>$start && $border<$reading1,'30d boundary actually inside unknown installation gap');
ck(near($calc['values']['30d'],$expected),'30d offset decreases proportionally when 27 Oct leaves window');
ck(str_contains($calc['basis']['30d'],'Schaetzung'),'estimate is explicitly identified');
$firstNov30=$t('2026-12-03 14:25:00');
$x=synthetic($firstNov30,$reading1,1.715);
$calc=SuruConsumption::calculateDetailed($x['history'],$x['latest']['TimeStamp'],$x['latest']['Value'],$installation);
ck(near($calc['values']['30d'],3000),'30d missing-install interval fully disappears by 3 Dec; only observed 30-day use');
ck(!str_contains($calc['basis']['30d'],'Schaetzung'),'30d no longer uses installation estimate after Nov 3 leaves window');
// Leap/calendar offsets are measured as 365*24h exactly.
$yearStartOut=$t('2027-10-28 14:25:00');
$x=synthetic($yearStartOut,$reading1,1.715);
$calc=SuruConsumption::calculateDetailed($x['history'],$x['latest']['TimeStamp'],$x['latest']['Value'],$installation);
ck(str_contains($calc['basis']['365d'],'Schaetzung'),'365d start fades during Oct 2027 missing-data interval');
$yearGapOut=$reading1+365*86400;
$x=synthetic($yearGapOut,$reading1,1.715);
$calc=SuruConsumption::calculateDetailed($x['history'],$x['latest']['TimeStamp'],$x['latest']['Value'],$installation);
ck(near($calc['values']['365d'],36500),'365d install offset completely gone when 3 Nov 2026 ages out');
ck(!str_contains($calc['basis']['365d'],'Schaetzung'),'annual value no longer includes installation estimate');
// Reading 0 at startup must not override the confirmed installation date.
$ghost=$history;
$ghost[]=['TimeStamp'=>$t('2026-11-02 18:00:00'),'Value'=>0.0];
$r=SuruConsumption::calculate($ghost,$reading3,1.949,$installation);
ck(near($r['24h'],96)&&near($r['30d'],1949),'archive activation ghost zero cannot inflate or overwrite estimated period');
echo "ALL CONSUMPTION TESTS PASSED\n";