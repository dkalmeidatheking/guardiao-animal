<?php
require __DIR__.'/../includes/bootstrap.php';
require_admin();
$error='';$rows=[];
try {$rows=read_store('denuncias');}catch(Throwable $ex){$error=$ex->getMessage();}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico Supabase | Guardião Animal</title><link rel="stylesheet" href="<?=e(base_url('assets/css/style.css'))?>"></head>
<body><main class="container" style="padding:40px 0;min-height:75vh"><p><a href="<?=e(base_url('admin/index.php'))?>">← Voltar ao painel</a></p><h1>Conexão com o Supabase</h1>
<p>Projeto: <code><?=e(SUPABASE_URL)?></code></p>
<?php if($error):?><p class="auth-error">Banco indisponível: <?=e($error)?></p><?php else:?>
<p role="status">Conexão confirmada. <strong><?=count($rows)?></strong> denúncias cadastradas na tabela <code>public.denuncias</code>.</p>
<p>O mapa e os painéis leem este mesmo banco. Casos concluídos e arquivados não compõem o mapa ativo.</p>
<p><a href="<?=e(base_url('api/map_data.php'))?>">Conferir API do mapa</a></p>
<?php endif;?></main></body></html>
