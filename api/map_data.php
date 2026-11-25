<?php
require __DIR__.'/../includes/bootstrap.php';
require __DIR__.'/../includes/ourinhos.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
try {
  $regions=ourinhos_neighborhoods();
  $counts=array_fill_keys(array_keys($regions),0);
  $validStatuses=['encaminhada','em atendimento','em_atendimento'];
  foreach(read_store('denuncias') as $r) {
     $status=mb_strtolower(trim((string)($r['status']??'')));
     // Regra de Validação: apenas ocorrências validadas/assumidas por ONGs aparecem no mapa
     if(!in_array($status,$validStatuses,true))continue;
     if(($r['tipo_registro']??'')==='demonstracao'||($r['bairro_origem']??'')==='Demonstração')continue;
     $cidade=mb_strtolower(trim((string)($r['cidade']??'')));
     if($cidade!==''&&!str_contains($cidade,'ourinhos'))continue;
     $bairro=normalize_ourinhos_bairro((string)($r['bairro']??''));
     if($bairro!==null)$counts[$bairro]++;
  }
  $items=[];
  foreach($regions as $bairro=>$point) {
     if(!$counts[$bairro])continue;
     $items[]=['bairro'=>$bairro,'lat'=>$point['lat'],'lng'=>$point['lng'],'real_count'=>$counts[$bairro],'demo_count'=>0,'count'=>$counts[$bairro]];
  }
  echo json_encode(['cidade'=>'Ourinhos - SP','real'=>array_sum($counts),'demonstracao'=>0,'total'=>array_sum($counts),'atualizado'=>date('d/m/Y H:i:s'),'fonte'=>'Supabase — denúncias validadas em atendimento','regioes'=>$items],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch(Throwable $ex) {
  error_log('[Guardiao] Falha no mapa Supabase: '.$ex->getMessage());
  http_response_code(503);echo json_encode(['error'=>'Não foi possível consultar as denúncias no banco. Tente novamente.']);
}
