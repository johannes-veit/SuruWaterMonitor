<?php
require_once __DIR__.'/../libs/Cognito.php';
$t=microtime(true);
$s=new SuruCognito('u@example.com','Secret1!','12345');
$c=['USER_ID_FOR_SRP'=>'uid-1','SALT'=>'a1b2c3d4','SRP_B'=>str_repeat('ff',64),'SECRET_BLOCK'=>'c2VjcmV0'];
$result=$s->challenge($c,new DateTimeImmutable('2026-06-25T12:00:01+00:00'));
echo json_encode(['A'=>$s->authParameters()['SRP_A'],'signature'=>$result['PASSWORD_CLAIM_SIGNATURE'],'timestamp'=>$result['TIMESTAMP'],'seconds'=>round(microtime(true)-$t,4)]),"\n";
