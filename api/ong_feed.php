<?php
require __DIR__.'/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
$ong = current_ong();
if (!$ong) {
    http_response_code(403);
    echo json_encode(['error'=>'Acesso restrito a ONGs autorizadas.']);
    exit;
}
$oid = (int)$ong['id'];
$publicId = (int)($ong['public_ong_id'] ?? 0);
$reports = array_values(array_filter(read_store('denuncias'), static function($r) use ($oid, $publicId) {
    if (($r['bairro_origem'] ?? '') === 'Demonstração') return false;
    $cOid = (int)($r['ong_id'] ?? 0);
    return ($cOid > 0 && ($cOid === $oid || ($publicId > 0 && $cOid === $publicId)));
}));
echo json_encode([
    'count'=>count($reports),
    'latest'=>max(array_map(static fn($r) => (int)($r['id'] ?? 0), $reports) ?: [0]),
]);
