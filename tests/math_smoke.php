<?php
require __DIR__.'/../libs/BigMath.php';
$m=new SuruBigMath('ffffffffffffffc5');
foreach([['02','12345'],['ff2345','ef'],['ffee','fabbc']] as [$b,$e]) {
  echo json_encode([$b,$e,SuruBigMath::toHex($m->modPow(SuruBigMath::fromHex($b),SuruBigMath::fromHex($e)))]),"\n";
}
