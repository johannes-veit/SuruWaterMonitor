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
requireCase($c['7d']===null && $c['30d']===null && $c['365d']===null, 'insufficient history is unknown, not zero');
requireCase($c['24h']===100.0,'24h still available with short history');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-2*$day,'Value'=>499.5]],$base,500.0);
requireCase($c['24h']===null,'48h gap must not be presented as 24h');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-7*$day+2*3600,'Value'=>498.0]],$base,500.0);
requireCase($c['7d']===2000.0,'daily sample near rolling boundary accepted as approximate');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-7*$day+20*3600,'Value'=>498.0]],$base,500.0);
requireCase($c['7d']===null,'missing boundary over 18h is unavailable');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-$day,'Value'=>500.0]],$base,500.0);
requireCase($c['24h']===0.0,'true zero consumption remains valid');
$c = SuruConsumption::calculate([['TimeStamp'=>$base-$day,'Value'=>501.0]],$base,500.0);
requireCase($c['24h']===null,'negative consumption or reset ignored');
echo "ALL CONSUMPTION TESTS PASSED\n";