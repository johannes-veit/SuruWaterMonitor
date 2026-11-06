<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/Consumption.php';
function requireCase(bool $ok, string $label): void { if (!$ok) throw new RuntimeException('FAIL: ' . $label); echo 'PASS: ' . $label . "\n"; }
$base = 1793700000;
$day = 86400;
$c = SuruConsumption::calculate([['TimeStamp'=>$base, 'Value'=>2.0]], $base, 2.0);
requireCase(count(array_filter($c, static fn($v) => $v !== null))===0,'initial value leaves all periods unavailable');
$rows = [];
for ($d=0;$d<=366;$d++) {
    $rows[] = ['TimeStamp'=>$base-$d*$day, 'Value'=>500.0 - $d * 0.1];
}
$c = SuruConsumption::calculate($rows,$base,500.0);
requireCase(abs($c['24h'] - 100) < .001,'24h from last pair');
requireCase(abs($c['7d'] - 700) < .001,'rolling 7d');
requireCase(abs($c['30d'] - 3000) < .001,'rolling 30d');
requireCase(abs($c['365d'] - 36500) < .001,'rolling 365d');
$c = SuruConsumption::calculate(array_slice($rows,0,6),$base,500.0);
requireCase($c['7d']===500.0 && $c['30d']===500.0 && $c['365d']===500.0, 'partial 5-day history populates 7/30/365-day sums');
requireCase($c['24h']===100.0,'24h still available with short history');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-2*$day,'Value'=>499.5]],$base,500.0);
requireCase($c['24h']===null,'48h gap must not be presented as 24h');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-7*$day+2*3600,'Value'=>498.0]],$base,500.0);
requireCase($c['7d']===null,'single 7-day gap cannot be counted as a daily value');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-7*$day+20*3600,'Value'=>498.0]],$base,500.0);
requireCase($c['7d']===null,'missing boundary over 18h is unavailable');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-$day,'Value'=>500.0]],$base,500.0);
requireCase($c['24h']===0.0,'true zero consumption remains valid');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-$day,'Value'=>501.0]],$base,500.0);
requireCase($c['24h']===null,'negative consumption or reset ignored');

$short = array_slice($rows,0,11); // newest + ten earlier daily readings
$c = SuruConsumption::calculate($short,$base,500.0);
requireCase($c['24h']===100.0 && $c['7d']===700.0, 'ten-day history: latest day and last seven days');
requireCase($c['30d']===1000.0 && $c['365d']===1000.0, 'ten-day history: 30/365d are available ten-day totals');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-$day,'Value'=>499.95]],$base,500.0);
requireCase($c['24h']===50.0 && $c['7d']===50.0 && $c['30d']===50.0 && $c['365d']===50.0, 'one measured day populates every window');
$ghost = [
    ['TimeStamp'=>$base-$day,'Value'=>17.150],
    ['TimeStamp'=>$base-$day+6*3600,'Value'=>0.0],
    ['TimeStamp'=>$base-2*$day,'Value'=>17.100],
];
$c = SuruConsumption::calculate($ghost,$base,17.200);
requireCase($c['24h']===50.0 && $c['7d']===100.0 && $c['365d']===100.0, 'archive default zero not mistaken for daily consumption');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-2*$day,'Value'=>499.9]],$base,500.0);
requireCase($c['24h']===null && $c['7d']===null, '48h gap does not invent missing day');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-$day,'Value'=>500.0]],$base,500.0);
requireCase($c['24h']===0.0 && $c['30d']===0.0, 'genuine null consumption remains zero');

// Installation reference is valid for the owner's known new meter only.
// Two daily measurements = 138+96 L; earlier water use = 1715 L.
$zone=new DateTimeZone('Europe/Berlin');
$dt=function(string $date)use($zone):int {return (new DateTimeImmutable($date,$zone))->getTimestamp();};
$latest=$dt('2026-11-05 13:51');
$history=[
 ['TimeStamp'=>$dt('2026-11-03 14:25'),'Value'=>1.715],
 ['TimeStamp'=>$dt('2026-11-04 14:25'),'Value'=>1.853],
];
$opening=['timestamp'=>$dt('2026-10-27 12:00'),'value'=>0.0];
$before=SuruConsumption::calculate($history,$latest,1.949);
requireCase($before['24h']===96.0 && $before['7d']===234.0 && $before['30d']===234.0,'before install baseline: correct partial sums');
$after=SuruConsumption::calculate($history,$latest,1.949,$opening);
requireCase($after['24h']===96.0 && $after['7d']===234.0,'install baseline does not change 24h and 7d');
requireCase($after['30d']===1949.0 && $after['365d']===1949.0,'install baseline completes 30d/365d without double count');
requireCase(SuruConsumption::calculate($history,$latest,1.949,['timestamp'=>$dt('2026-11-04 12:00'),'value'=>0.0])['30d']===234.0,'reject baseline after first cloud reading');
requireCase(SuruConsumption::calculate($history,$latest,1.949,['timestamp'=>$dt('2026-10-23 12:00'),'value'=>5.0])['30d']===234.0,'reject start value bigger than current reading');
$future=$dt('2027-07-01 12:00');
requireCase(SuruConsumption::calculate([], $future, 12.0, $opening)['30d']===null,'start outside 30d cannot count as 30d');
requireCase(SuruConsumption::calculate([], $future, 12.0, $opening)['365d']===12000.0,'start still inside 365d retains total');
requireCase(SuruConsumption::calculate([], $latest, 1.949, $opening)['30d']===1949.0,'known start may be used without archive');
// Regression: opening is only effective while the sliding window contains 27 Oct 2026.
$expired30=$dt('2026-11-26 14:25');
$expired365=$dt('2027-10-27 14:25');
$after30=SuruConsumption::calculate([], $expired30, 1.949, $opening);
requireCase($after30['30d']===null && $after30['365d']===1949.0, '30d start offset expires, 365d still includes installation');
$afterYear=SuruConsumption::calculate([], $expired365, 1.949, $opening);
requireCase($afterYear['365d']===null, '365d start offset expires automatically');
// After the baseline leaves 30d, retain real daily intervals, without reusing installation litres.
$rowsAfter=[];
$baseMeasured=$dt('2026-11-03 14:25');
for($d=0;$d<=23;$d++) {
    $rowsAfter[]=['TimeStamp'=>$baseMeasured+$d*86400, 'Value'=>1.715+$d*0.100];
}
$latestFromRows=end($rowsAfter);
$after30WithHistory=SuruConsumption::calculate($rowsAfter, (int)$latestFromRows['TimeStamp'], (float)$latestFromRows['Value'], $opening);
requireCase($after30WithHistory['30d']===2300.0, 'expired offset not included in 30d, observed daily sums remain');
requireCase($after30WithHistory['365d']===4015.0, '365d still uses full install-to-now counter with no double count');
echo "ALL CONSUMPTION TESTS PASSED\n";
