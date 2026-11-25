<?php
require __DIR__.'/../includes/bootstrap.php';
// Acesso exige código de migração; usuários logados não ficam dispensados.

$message='';$summary=[];
if($_SERVER['REQUEST_METHOD']==='POST') {
 check_csrf();
 try {
  $cfg=ga_private_config();
  $token=(string)(getenv('GA_MIGRATION_TOKEN')?:($cfg['migration_token']??''));
  if(strlen($token)<24 || !hash_equals($token,(string)($_POST['codigo']??'')))throw new RuntimeException('Configure GA_MIGRATION_TOKEN no servidor.');
  foreach(['denuncias','admins','admin_solicitacoes','ong_accounts','ong_animals','ong_case_status','doacoes','contatos','apadrinhamentos','home_photos','adocoes','noticias'] as $store) {
    $file=STORAGE_PATH.'/'.$store.'.json';
    if(!is_file($file))continue;
    $rows=json_decode((string)file_get_contents($file),true);
    if(!is_array($rows))continue;
    $existing=read_store($store);$count=0;
    if($store==='ong_case_status'){
      foreach($rows as $state) {
        if(!is_array($state))continue;
        ga_db_save_ong_case($state);
        $count++;
      }
      $summary[$store]=$count;continue;
    }
    if($store==='home_photos'){
      mutate_store('home_photos',static function (&$current) use($rows) {$current=array_replace($rows,$current);});
      $summary[$store]=count($rows);continue;
    }
    foreach($rows as $row) {
      if(!is_array($row))continue;
      $already=false;$matching=null;
      foreach($existing as $r) {
        if($store==='denuncias'&&!empty($row['protocolo'])&&($r['protocolo']??null)===$row['protocolo']){$already=true;$matching=$r;break;}
        if(in_array($store,['admins','ong_accounts','admin_solicitacoes'],true)&&!empty($row['email'])&&strtolower((string)($r['email']??''))===strtolower((string)$row['email'])){$already=true;$matching=$r;break;}
        if(!empty($row['id'])&&(int)($r['id']??0)===(int)$row['id']){$already=true;$matching=$r;break;}
      }
      if($already){
        // ONGs demonstrativas importadas antes pelo SQL V35 usam IDs 900001...900005.
        // Reconcilia com o ID do PHP antigo para manter referências e histórico sem duplicar.
        if($store==='ong_accounts' && is_array($matching) && !empty($row['id'])
          && (int)$matching['id'] !== (int)$row['id']
          && !empty($row['email']) && strtolower((string)($matching['email']??''))===strtolower((string)$row['email'])) {
          $oldId=(int)$matching['id'];$newId=(int)$row['id'];
          $merged=array_replace($row,$matching);
          $merged['id']=$newId;
          $merged['public_ong_id']=(int)($row['public_ong_id']??$newId);
          // Não substitui hash e status já definidos no banco por valores possivelmente desatualizados.
          $data=ga_db_columns($store,$merged);$data['local_id']=$newId;
          ga_db_request('PATCH','ongs',['local_id'=>'eq.'.$oldId],$data);
        }
        continue;
      }
      $oldId=(int)($row['id']??0);
      ga_db_create($store,$row,$oldId>0?$oldId:null);
      $existing[]=$row;$count++;
    }
    $summary[$store]=$count;
  }
  $message='Importação concluída. Preserve os JSONs antigos como backup offline. Não serão usados pela versão V36.';
 }catch(Throwable $e){$message='Importação interrompida: '.$e->getMessage();}
}
?><!doctype html><html lang="pt-BR"><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta charset="utf-8"><title>Migrar registros | Guardião Animal</title><link rel="stylesheet" href="<?=base_url('assets/css/style.css')?>"></head><body><main class="container" style="padding:35px 0"><h1>Importar registros antigos para Supabase</h1><p>Somente administrador principal. Faça backup do banco e da pasta storage antes de importar. Este processo não importa o arquivo fictício de bairros.</p><p><?=e($message)?></p><?php foreach($summary as $k=>$v):?><p><?=e($k)?>: <?=e($v)?> registros importados</p><?php endforeach;?>
<form method="post"><input type="hidden" name="csrf" value="<?=csrf()?>"><label>Código de migração do servidor</label><input name="codigo" type="password" required><button class="btn btn-dark">Importar dados locais existentes</button></form></main></body></html>
