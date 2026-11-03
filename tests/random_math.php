<?php
require_once __DIR__.'/../libs/Cognito.php';
$src=file_get_contents(__DIR__.'/../libs/Cognito.php');
preg_match('/private const N =([\s\S]*?);\s*private SuruBigMath/', $src, $matches);
preg_match_all("/'([0-9A-F]+)'/", $matches[1], $parts);
$n=implode('',$parts[1]);
$m=new SuruBigMath($n);
$case=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
foreach($case as $row){
  $a=SuruBigMath::fromHex($row['a']);$b=SuruBigMath::fromHex($row['b']);
  $out=['pow'=>SuruBigMath::toHex($m->modPow($a,$b)),'mul'=>SuruBigMath::toHex($m->modMul($a,$b))];
  echo json_encode($out),"\n";
}
