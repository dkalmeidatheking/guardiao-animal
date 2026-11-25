<?php
require __DIR__.'/../includes/bootstrap.php';
if (!is_admin()) {http_response_code(403);header('Content-Type: application/json');echo json_encode(['error'=>'Acesso negado']);exit;}
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
$d=read_store('denuncias');$o=read_store('ong_accounts');
echo json_encode(['denuncias'=>count($d),'pendentes'=>count(array_filter($o,fn($x)=>($x['status']??'')==='Pendente')),'latest'=>max(array_column($d,'id')?:[0]),'latestOng'=>max(array_column($o,'id')?:[0])]);
