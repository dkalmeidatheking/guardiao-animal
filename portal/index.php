<?php
require __DIR__.'/../includes/bootstrap.php';
require_ong();
$ong = current_ong();
if (!$ong) { unset($_SESSION['role'], $_SESSION['ong_id']); redirect('portal/login.php'); }
$oid = (int)$ong['id'];
$error = '';
$caseStatuses = ['Recebida', 'Em atendimento', 'Concluída', 'Arquivada'];
$sponsorStatuses = ['Encaminhado', 'Em análise', 'Em contato', 'Concluído', 'Recusado'];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf();
    $action = form_value('action', 30);
    try {
        if ($action === 'profile') {
            $name = form_value('nome', 120);
            if (mb_strlen($name) < 3) throw new RuntimeException('Informe o nome da ONG.');
            $photo = save_uploaded_photo('foto_upload') ?: safe_photo_url(form_value('foto', 500));
            $ok=update_store_row('ong_accounts', $oid, static function (&$a) use ($photo, $name) {
                $a['nome'] = $name;
                $a['telefone'] = form_value('telefone', 40);
                $a['cidade'] = form_value('cidade', 100);
                $a['bairro'] = form_value('bairro', 90);
                $a['foco'] = form_value('foco', 100);
                $a['descricao'] = form_value('descricao', 1200);
                $a['historia'] = form_value('historia', 2000);
                if ($photo) $a['foto'] = $photo;
            });
            if (!$ok) throw new RuntimeException('ONG não encontrada.');
            flash('ok', 'Perfil atualizado com sucesso.');
            redirect('portal/index.php?view=perfil');
        }
        if ($action === 'case') {
            $id = (int)($_POST['id'] ?? 0);
            $status = form_value('status', 40);
            if (!in_array($status, $caseStatuses, true)) throw new RuntimeException('Situação inválida.');
            if (!array_filter(read_store('denuncias'), static fn($r) => (int)($r['id'] ?? 0) === $id)) {
                throw new RuntimeException('Denúncia não encontrada.');
            }
            // Situação é individual de cada ONG: uma organização não altera o atendimento das demais.
            mutate_store('ong_case_status', static function (&$rows) use ($oid, $id, $status) {
                foreach ($rows as &$row) {
                    if ((int)($row['ong_id'] ?? 0) === $oid && (int)($row['denuncia_id'] ?? 0) === $id) {
                        $row['status'] = $status;
                        $row['updated_at'] = date('Y-m-d H:i:s');
                        return;
                    }
                }
                $rows[] = ['ong_id'=>$oid, 'denuncia_id'=>$id, 'status'=>$status, 'updated_at'=>date('Y-m-d H:i:s')];
            });
            // Atualiza a denúncia global para que o Admin e o Cidadão acompanhem
            if (in_array($status,['Em atendimento','Concluída','Arquivada'],true)) {
                update_store_row('denuncias',$id,static function (&$r) use ($status, $oid) {
                    $r['status']=$status;
                    if (empty($r['ong_id'])) $r['ong_id'] = $oid;
                    $r['updated_at']=date('Y-m-d H:i:s');
                    if ($status === 'Concluída') $r['concluida_em'] = date('Y-m-d H:i:s');
                });
            }
            ga_cache_clear();
            flash('ok', 'Andamento da denúncia atualizado com sucesso.');
            $origin = in_array((string)($_POST['origem'] ?? ''), ['reais','demo'], true) ? (string)$_POST['origem'] : 'reais';
            redirect('portal/index.php?view=ocorrencias&origem='.$origin.'&case='.$id);
        }
        if ($action === 'sponsorship') {
            $id = (int)($_POST['id'] ?? 0);
            $status = form_value('status', 40);
            if (!in_array($status, $sponsorStatuses, true)) throw new RuntimeException('Situação inválida.');
            $found = false;
            foreach (read_store('apadrinhamentos') as $a) if ((int)($a['id'] ?? 0) === $id && (int)($a['ong_id'] ?? 0) === $oid) { $found = true; break; }
            if (!$found) throw new RuntimeException('Solicitação não atribuída à sua ONG.');
            update_store_row('apadrinhamentos', $id, static function (&$row) use ($oid, $status) {
                if ((int)($row['ong_id'] ?? 0) !== $oid) throw new RuntimeException('Sem permissão para alterar o apadrinhamento.');
                $row['status'] = $status;
                $row['updated_at'] = date('Y-m-d H:i:s');
            });
            flash('ok', 'Apadrinhamento atualizado.');
            redirect('portal/index.php?view=apadrinhamentos');
        }
        if ($action === 'animal') {
            $name = form_value('nome', 120);
            if (mb_strlen($name) < 2) throw new RuntimeException('Informe o nome do animal.');
            $photo = save_uploaded_photo('foto_upload') ?: safe_photo_url(form_value('foto', 500));
            if (!$photo) throw new RuntimeException('Adicione uma foto (arquivo ou URL HTTPS).');
            $status = form_value('status', 30);
            if (!in_array($status, ['Disponível', 'Apadrinhamento'], true)) throw new RuntimeException('Modalidade inválida.');
            add_store('ong_animals', [
                'ong_id'=>$oid, 'nome'=>$name,
                'especie'=>in_array(form_value('especie'), ['Cão', 'Gato', 'Outro'], true) ? form_value('especie') : 'Outro',
                'sexo'=>form_value('sexo', 30), 'idade'=>form_value('idade', 60), 'porte'=>form_value('porte', 30),
                'local'=>form_value('local', 200), 'temperamento'=>form_value('temperamento', 500),
                'historia'=>form_value('historia', 1300), 'status'=>$status, 'foto'=>$photo
            ]);
            flash('ok', 'Animal publicado no perfil da ONG.');
            redirect('portal/index.php?view=animais');
        }
        throw new RuntimeException('Ação inválida.');
    } catch (Throwable $ex) { $error = $ex->getMessage(); }
}

$ong = current_ong();
$views = ['inicio'=>'Visão geral', 'ocorrencias'=>'Denúncias', 'apadrinhamentos'=>'Apadrinhamentos', 'doacoes'=>'Doações', 'animais'=>'Animais', 'perfil'=>'Minha ONG', 'financeiro'=>'Financeiro (demo)'];
$view = (string)($_GET['view'] ?? 'inicio');
if (!isset($views[$view])) $view = 'inicio';
// A rede compartilha todas as denúncias com ONGs aprovadas; sincroniza andamento com a rede.
$allOngAtendimentos = read_store('ong_case_status');
$myCaseStatuses = [];
$otherOngAttending = [];
foreach ($allOngAtendimentos as $state) {
    $cId = (int)($state['denuncia_id'] ?? 0);
    $cOid = (int)($state['ong_id'] ?? 0);
    if ($cOid === $oid) {
        $myCaseStatuses[$cId] = $state['status'];
    } elseif (in_array(($state['status'] ?? ''), ['Em atendimento', 'Concluída'], true)) {
        $otherOngAttending[$cId] = $state['status'];
    }
}
$allNetworkCases = read_store('denuncias');
foreach ($allNetworkCases as &$c) {
    $cid = (int)($c['id'] ?? 0);
    $globalStatus = (string)($c['status'] ?? 'Recebida');
    if (isset($myCaseStatuses[$cid])) {
        $c['_ong_status'] = $myCaseStatuses[$cid];
    } elseif (in_array($globalStatus, ['Concluída', 'Arquivada'], true)) {
        $c['_ong_status'] = $globalStatus;
    } elseif (!empty($otherOngAttending[$cid])) {
        $c['_ong_status'] = 'Em atendimento (outra ONG)';
    } else {
        $c['_ong_status'] = $globalStatus;
    }
    // Relato e localização são compartilhados, mas não dados pessoais de denunciantes.
    unset($c['nome'], $c['email'], $c['telefone']);
}
unset($c);
$demoNetworkCases = array_values(array_filter($allNetworkCases, static fn($r) => ($r['bairro_origem'] ?? '') === 'Demonstração'));
$realNetworkCases = array_values(array_filter($allNetworkCases, static fn($r) => ($r['bairro_origem'] ?? '') !== 'Demonstração'));
$caseOrigin = (string)($_GET['origem'] ?? 'reais');
$caseOrigin='reais';
$publicOngId = (int)($ong['public_ong_id'] ?? 0);
// Exibe estritamente as denúncias encaminhadas pela administração para esta ONG específica
$myAssignedCases = array_values(array_filter($realNetworkCases, static function($r) use ($oid, $publicOngId) {
    $cOid = (int)($r['ong_id'] ?? 0);
    return $cOid > 0 && ($cOid === $oid || ($publicOngId > 0 && $cOid === $publicOngId));
}));
$cases = $myAssignedCases;
usort($cases, fn($a,$b)=>(int)($b['id']??0) <=> (int)($a['id']??0));
$sponsorships = array_values(array_filter(read_store('apadrinhamentos'), fn($r)=>(int)($r['ong_id'] ?? 0) === $oid));
usort($sponsorships, fn($a,$b)=>(int)($b['id']??0) <=> (int)($a['id']??0));
$myAnimals = array_values(array_filter(read_store('ong_animals'), fn($r)=>(int)($r['ong_id'] ?? 0) === $oid));
usort($myAnimals, fn($a,$b)=>(int)($b['id']??0) <=> (int)($a['id']??0));
$publicOngId = (int)($ong['public_ong_id'] ?? 0) ?: 1000 + $oid;
$myDonations = array_values(array_filter(read_store('doacoes'), fn($r) => (int)($r['ong_account_id'] ?? 0) === $oid || ((int)($r['ong_id'] ?? 0) === $publicOngId && empty($r['ong_account_id']))));
usort($myDonations, fn($a,$b)=>(int)($b['id']??0) <=> (int)($a['id']??0));
$donationGross = array_sum(array_map(fn($r)=>(float)($r['valor']??0), $myDonations));
$donationFee = array_sum(array_map(fn($r)=>(float)($r['comissao_plataforma']??round((float)($r['valor']??0)*.10,2)), $myDonations));
$donationNet = array_sum(array_map(fn($r)=>(float)($r['valor_ong']??round((float)($r['valor']??0)*.90,2)), $myDonations));
$pendingCases = count(array_filter($cases,fn($r)=>!in_array(($r['_ong_status']??'Recebida'),['Concluída','Arquivada','Em atendimento (outra ONG)'],true) && !in_array(($r['bairro_origem']??''),['Demonstração'],true)));
$pendingSponsors = count(array_filter($sponsorships,fn($r)=>!in_array(($r['status']??''),['Concluído','Recusado'],true)));
$myName=$ong['nome'] ?? 'Minha organização';
function ngo_when($raw): string {
    if (empty($raw)) return 'Data indisponível';
    $stamp = strtotime((string)$raw);
    return $stamp ? date('d/m/Y · H:i', $stamp) : (string)$raw;
}
function ngo_icon(string $name): string {
    $paths=[
        'home'=>'<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'alert'=>'<path d="M10.3 3.6 2.3 17.4a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'heart'=>'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'wallet'=>'<rect x="3" y="6" width="18" height="15" rx="2"/><path d="M3 10h18M3 6V5a2 2 0 0 1 2-2h13"/>',
        'paw'=>'<circle cx="7" cy="7" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="17" cy="7" r="1"/><circle cx="20" cy="12" r="1"/><path d="M12 11c2 0 3 2 4 4s1 5-4 5-5-3-4-5 2-4 4-4Z"/>',
        'building'=>'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h.01M15 7h.01M9 11h.01M15 11h.01M10 21v-4h4v4"/>',
        'menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>',
        'logout'=>'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'external'=>'<path d="M14 3h7v7M10 14 21 3"/><path d="M21 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6"/>',
        'arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['home']).'</svg>';
}
$menu=[
 ['inicio','Início','home',null],['ocorrencias','Denúncias','alert',count($cases)],
 ['apadrinhamentos','Apadrinhamentos','heart',count($sponsorships)],
 ['doacoes','Doações','wallet',count($myDonations)],['animais','Animais','paw',count($myAnimals)],
 ['perfil','Minha ONG','building',null],['financeiro','Financeiro (demo)','wallet',null],
];
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light">
<title><?=e($views[$view])?> · Portal da ONG | Guardião Animal</title>
<link rel="stylesheet" href="<?=base_url('assets/css/admin-dashboard-v28.css')?>?v=30">
<link rel="stylesheet" href="<?=base_url('assets/css/ong-dashboard-v30.css')?>?v=30">
</head><body class="ga-admin ga-ngo">
<a class="ga-skip" href="#ga-main">Ir para o conteúdo</a>
<button type="button" class="ga-mobile-backdrop" data-menu-close aria-label="Fechar navegação" hidden></button>
<div class="ga-layout">
<aside class="ga-sidebar" id="ga-sidebar" aria-label="Navegação da ONG">
  <div class="ga-brand"><img src="<?=base_url('assets/img/logo-guardiao-circular-final.png')?>" alt="Guardião Animal"><div><strong>Guardião Animal</strong><small>Painel da ONG</small></div></div>
  <div class="ga-side-label">ESPAÇO DA ORGANIZAÇÃO</div>
  <nav class="ga-side-nav" aria-label="Seções da organização">
   <?php foreach($menu as [$section,$label,$icon,$number]): ?>
     <a class="ga-nav-link <?=$view===$section?'is-active':''?>" href="?view=<?=e($section)?>" <?=$view===$section?'aria-current="page"':''?>><?=ngo_icon($icon)?><span><?=e($label)?></span><?php if($number!==null&&$number>0):?><em><?=$number?></em><?php endif;?></a>
   <?php endforeach; ?>
  </nav>
  <div class="ga-side-bottom"><div class="ga-account"><span class="ga-avatar">ONG</span><div><strong><?=e($myName)?></strong><small>Organização aprovada</small></div></div><a class="ga-sidebar-logout" href="<?=base_url('portal/logout.php')?>"><?=ngo_icon('logout')?> Encerrar sessão</a></div>
</aside>
<div class="ga-content-area">
<header class="ga-topbar"><div class="ga-top-left"><button type="button" class="ga-icon-button ga-menu-button" data-menu-toggle aria-expanded="false" aria-controls="ga-sidebar" aria-label="Abrir menu"><?=ngo_icon('menu')?></button><span class="ga-breadcrumb">Minha ONG <span>/</span> <b><?=e($views[$view])?></b></span></div><div class="ga-top-actions"><a class="ga-top-link" href="<?=base_url('ong.php?id='.$publicOngId)?>" target="_blank" rel="noopener">Ver perfil <?=ngo_icon('external')?></a></div></header>
<main class="ga-main" id="ga-main">
<div class="ga-network-update" id="ga-network-update" role="status" hidden>Há novas denúncias na rede. <a href="?view=ocorrencias&origem=reais">Ver denúncias recebidas</a></div>
<?php if($error):?><div class="ga-alert ga-alert-error" role="alert"><?=e($error)?></div><?php endif;?>
<?php if($notice=take_flash()):?><div class="ga-alert ga-alert-success" role="status"><?=ngo_icon('check')?> <?=e($notice['msg'])?></div><?php endif;?>

<?php if($view==='inicio'): ?>
<div class="ga-page-heading ga-home-heading"><div><span class="ga-kicker">PAINEL DA ORGANIZAÇÃO</span><h1>Bem-vinda, <?=e($myName)?></h1><p>Acompanhe o que chegou para sua equipe e organize seus próximos atendimentos.</p></div><a class="ga-button ga-button-primary" href="?view=ocorrencias">Ver denúncias <?=ngo_icon('arrow')?></a></div>
<section class="ga-metrics" aria-label="Resumo da ONG">
  <a class="ga-metric ga-metric-attention" href="?view=ocorrencias"><div class="ga-metric-top"><span>Denúncias encaminhadas</span><?=ngo_icon('alert')?></div><strong><?=count($cases)?></strong><small><?=$pendingCases?> em andamento</small></a>
  <a class="ga-metric" href="?view=apadrinhamentos"><div class="ga-metric-top"><span>Apadrinhamentos</span><?=ngo_icon('heart')?></div><strong><?=count($sponsorships)?></strong><small><?=$pendingSponsors?> solicitações em acompanhamento</small></a>
  <a class="ga-metric" href="?view=doacoes"><div class="ga-metric-top"><span>Intenções de doação</span><?=ngo_icon('wallet')?></div><strong><?=count($myDonations)?></strong><small>Registros; não representam pagamentos</small></a>
</section>
<div class="ga-home-columns">
 <section class="ga-surface"><div class="ga-surface-heading"><div><span class="ga-kicker">ATENDIMENTOS</span><h2>Denúncias encaminhadas</h2></div><a class="ga-text-link" href="?view=ocorrencias">Ver todas <?=ngo_icon('arrow')?></a></div>
  <?php if(!$cases):?><div class="ga-empty">Nenhuma denúncia encaminhada para a sua organização no momento. Quando o administrador encaminhar uma ocorrência para sua ONG, ela aparecerá aqui para atendimento.</div><?php endif;?>
  <?php foreach(array_slice($cases,0,5) as $c):?><a class="ga-queue-row" href="?view=ocorrencias&case=<?=(int)$c['id']?>"><span class="ga-queue-icon"><?=ngo_icon('paw')?></span><span class="ga-queue-body"><strong><?=e($c['especie']??'Animal')?> · <?=e($c['bairro']??'Bairro não informado')?></strong><small>#<?=e($c['id'])?> · <?=e($c['_ong_status']??'Nova')?></small></span><?=ngo_icon('arrow')?></a><?php endforeach; ?>
 </section>
 <section class="ga-surface ga-quick-actions"><div class="ga-surface-heading"><div><span class="ga-kicker">ACESSO RÁPIDO</span><h2>O que deseja fazer?</h2></div></div>
  <a href="?view=apadrinhamentos"><?=ngo_icon('heart')?><span><strong>Ver apadrinhamentos</strong><small><?=count($sponsorships)?> solicitação(ões) vinculada(s)</small></span><?=ngo_icon('arrow')?></a>
  <a href="?view=doacoes"><?=ngo_icon('wallet')?><span><strong>Consultar doações</strong><small><?=count($myDonations)?> intenção(ões) registrada(s)</small></span><?=ngo_icon('arrow')?></a>
  <a href="?view=animais"><?=ngo_icon('paw')?><span><strong>Publicar animal</strong><small>Adoção ou apadrinhamento</small></span><?=ngo_icon('arrow')?></a>
 </section>
</div>

<?php elseif($view==='ocorrencias'): ?>
<div class="ga-page-heading"><div><span class="ga-kicker">ATENDIMENTO ANIMAL</span><h1>Denúncias encaminhadas</h1><p>Ocorrências atribuídas à sua organização pela administração para atendimento e acompanhamento.</p></div><span class="ga-total-tag"><?=count($cases)?> caso(s)</span></div>

<div class="ga-case-workspace">
 <div class="ga-case-list"><div class="ga-case-list-top"><strong>Caixa de entrada</strong><span><?=count($cases)?></span></div>
  <?php if(!$cases):?><div class="ga-empty">Nenhuma denúncia encaminhada para a sua organização até o momento.</div><?php endif;?>
  <?php $selectedId=(int)($_GET['case']??($cases[0]['id']??0)); $chosen=null; foreach($cases as $c) if((int)$c['id']===$selectedId){$chosen=$c;break;} if(!$chosen) $chosen=$cases[0]??null; ?>
  <?php foreach($cases as $c):?><a class="ga-case-list-row <?=(int)$c['id']===(int)($chosen['id']??0)?'is-selected':''?>" href="?view=ocorrencias&origem=<?=e($caseOrigin)?>&case=<?=(int)$c['id']?>#detalhes"><div class="ga-case-row-top"><span class="ga-small-id">#<?=e($c['id'])?><?=($c['bairro_origem']??'')==='Demonstração'?' · Exemplo':''?></span><span class="ga-status ga-progress"><?=e($c['_ong_status']??'Recebida')?></span></div><strong><?=e($c['especie']??'Animal')?> · <?=e($c['bairro']??'Bairro')?></strong><p><?=e($c['local']??'Local não informado')?></p><div class="ga-case-row-bottom"><span class="ga-urgency"><?=e($c['urgencia']??'Não informada')?></span><time><?=e(ngo_when($c['created_at']??''))?></time></div></a><?php endforeach; ?>
 </div>
 <section class="ga-case-detail" id="detalhes" aria-label="Gerenciar denúncia">
  <?php if(!$chosen):?><div class="ga-empty">Selecione uma denúncia na lista.</div><?php else:?>
  <?php if((int)($chosen['ong_id'] ?? 0) === $oid): ?>
    <div style="background:#eaf7ee;border:1px solid #bde4cb;border-radius:10px;padding:12px 16px;margin-bottom:16px;color:#185338;font-size:14px;display:flex;align-items:center;gap:10px">
      <span style="font-size:18px">🎯</span>
      <div><strong>Ocorrência encaminhada para a sua organização</strong> pela administração para atendimento prioritário.</div>
    </div>
  <?php endif; ?>
  <div class="ga-detail-top"><span class="ga-kicker">CASO #<?=e($chosen['id'])?><?=($chosen['bairro_origem']??'')==='Demonstração'?' · DEMONSTRAÇÃO':''?></span><span class="ga-status ga-progress"><?=e($chosen['_ong_status']??'Recebida')?></span></div><h2><?=e($chosen['especie']??'Animal')?> em <?=e($chosen['bairro']??'bairro não informado')?></h2><div class="ga-detail-meta"><?=e(ngo_when($chosen['created_at']??''))?></div>
  <div class="ga-detail-section"><h3>Localização</h3><p><?=e($chosen['local']??'Não informado')?></p></div><div class="ga-detail-section"><h3>Relato</h3><div class="ga-report"><?=nl2br(e($chosen['descricao']??''))?></div></div><div class="ga-detail-section"><h3>Informações para atendimento</h3><dl class="ga-info-grid"><div><dt>Urgência</dt><dd><?=e($chosen['urgencia']??'Não informada')?></dd></div><div><dt>Local</dt><dd><?=e($chosen['local']??'Não informado')?></dd></div></dl><p class="ga-privacy-note">A identificação e os contatos particulares do denunciante ficam restritos ao administrador.</p></div>
  <form method="post" class="ga-edit-form"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="case"><input type="hidden" name="origem" value="<?=e($caseOrigin)?>"><input type="hidden" name="id" value="<?=e($chosen['id'])?>"><h3>Atualizar atendimento</h3><p>O andamento salvo aqui pertence somente à sua ONG.</p><label>Situação<select name="status"><?php foreach($caseStatuses as $st):?><option value="<?=e($st)?>" <?=$st===($chosen['_ong_status']??'Recebida')?'selected':''?>><?=e($st)?></option><?php endforeach;?></select></label><button class="ga-button ga-button-primary" type="submit">Salvar andamento</button></form>
  <?php endif;?>
 </section>
</div>

<?php elseif($view==='apadrinhamentos'): ?>
<div class="ga-page-heading"><div><span class="ga-kicker">CUIDADO CONTÍNUO</span><h1>Apadrinhamentos</h1><p>Interesses vinculados aos animais da sua ONG ou atribuídos pela administração.</p></div><span class="ga-total-tag"><?=count($sponsorships)?> solicitação(ões)</span></div>
<section class="ga-surface ga-list-padding">
  <?php if(!$sponsorships):?><div class="ga-empty">Nenhum apadrinhamento foi encaminhado à sua ONG. Pedidos de animais demonstrativos ou sem ONG cadastrada ficam com o administrador até serem atribuídos.</div><?php endif; ?>
  <?php foreach($sponsorships as $s):?><article class="ngo-request"><div class="ngo-request-main"><span class="ga-status ga-info"><?=e($s['status']??'Em análise')?></span><span class="ga-small-id">Solicitação #<?=e($s['id'])?> · <?=e($s['created_at']??'')?></span><h2><?=e($s['animal']??'Animal não informado')?></h2><p><strong>Interessado:</strong> <?=e($s['nome']??'Não informado')?> · <?=e($s['email']??'Não informado')?></p><p><strong>Valor mensal pretendido:</strong> R$ <?=number_format((float)($s['valor']??0),2,',','.')?> (sem cobrança)</p><?php if(!empty($s['mensagem'])):?><div class="ga-report"><?=nl2br(e($s['mensagem']))?></div><?php endif;?></div>
  <form method="post" class="ngo-request-actions"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="sponsorship"><input type="hidden" name="id" value="<?=e($s['id'])?>"><label>Situação<select name="status"><?php foreach($sponsorStatuses as $st):?><option value="<?=e($st)?>" <?=$st===($s['status']??'')?'selected':''?>><?=e($st)?></option><?php endforeach;?></select></label><button type="submit" class="ga-button ga-button-primary">Salvar andamento</button></form></article><?php endforeach; ?>
</section>

<?php elseif($view==='doacoes'): ?>
<div class="ga-page-heading"><div><span class="ga-kicker">INTERESSE DE CONTRIBUIÇÃO</span><h1>Doações</h1><p>Registros direcionados à sua ONG. O site não processa nem confirma pagamentos.</p></div><span class="ga-total-tag"><?=count($myDonations)?> intenção(ões)</span></div>
<div class="ga-alert ga-alert-warning">Os valores abaixo são intenções demonstrativas, não saldo recebido. Nenhum pagamento ou repasse foi confirmado.</div>
<section class="ga-metrics"><div class="ga-metric"><div class="ga-metric-top">Valor registrado</div><strong>R$ <?=number_format($donationGross,2,',','.')?></strong><small>Sem pagamento confirmado</small></div><div class="ga-metric"><div class="ga-metric-top">Taxa demonstrativa (10%)</div><strong>R$ <?=number_format($donationFee,2,',','.')?></strong><small>Somente cálculo ilustrativo</small></div><div class="ga-metric"><div class="ga-metric-top">Destinado à ONG (90%)</div><strong>R$ <?=number_format($donationNet,2,',','.')?></strong><small>Estimativa, não repasse</small></div></section>
<section class="ga-surface"><div class="ga-table-wrap"><table class="ga-data-table"><thead><tr><th>Data</th><th>Nome</th><th>E-mail</th><th>Valor</th><th>Status</th></tr></thead><tbody>
<?php foreach($myDonations as $d):?><tr><td><?=e($d['created_at']??'')?></td><td><?=e($d['nome']??'')?></td><td><?=e($d['email']??'')?></td><td>R$ <?=number_format((float)($d['valor']??0),2,',','.')?></td><td><span class="ga-status ga-info"><?=e($d['status']??'Intenção registrada')?></span></td></tr><?php endforeach; ?>
<?php if(!$myDonations):?><tr><td colspan="5" class="ga-empty">Nenhuma intenção de doação vinculada a esta ONG.</td></tr><?php endif; ?></tbody></table></div></section>

<?php elseif($view==='animais'): ?>
<div class="ga-page-heading"><div><span class="ga-kicker">PUBLICAÇÕES DA ONG</span><h1>Meus animais</h1><p>Publique um novo animal para adoção ou apadrinhamento; ele aparecerá no perfil público da sua organização.</p></div><span class="ga-total-tag"><?=count($myAnimals)?> publicados</span></div>
<div class="ngo-content-split">
<section class="ga-surface ngo-animal-list"><div class="ga-surface-heading"><div><h2>Animais publicados</h2></div></div><?php if(!$myAnimals):?><div class="ga-empty">Ainda não há animais publicados.</div><?php endif;?><?php foreach($myAnimals as $a):?><div class="ngo-animal-item"><img src="<?=e($a['foto']??'')?>" alt="Fotografia de <?=e($a['nome']??'animal')?>"><div><strong><?=e($a['nome']??'Animal')?></strong><small><?=e($a['especie']??'')?> · <?=e($a['status']??'')?></small></div></div><?php endforeach;?></section>
<section class="ga-surface ngo-form-section"><h2>Publicar animal</h2><p>Envie um JPG, PNG ou WebP de até 5 MB ou informe um link HTTPS.</p><form method="post" enctype="multipart/form-data" class="ngo-form"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="animal">
<div class="ngo-form-grid"><label>Nome *<input name="nome" minlength="2" maxlength="120" required></label><label>Espécie<select name="especie"><option>Cão</option><option>Gato</option><option>Outro</option></select></label><label>Finalidade<select name="status"><option value="Disponível">Disponível para adoção</option><option value="Apadrinhamento">Precisa de apadrinhamento</option></select></label><label>Sexo<select name="sexo"><option>Fêmea</option><option>Macho</option><option>Não informado</option></select></label><label>Idade aproximada<input name="idade" maxlength="60" placeholder="Ex.: 2 anos"></label><label>Porte<select name="porte"><option>Pequeno</option><option>Médio</option><option>Grande</option></select></label><label>Local do resgate<input name="local" maxlength="200"></label><label>Temperamento<input name="temperamento" maxlength="500"></label><label class="ngo-span-all">História<textarea name="historia" maxlength="1300"></textarea></label><label>Foto do animal<input type="file" name="foto_upload" accept="image/jpeg,image/png,image/webp"></label><label>Ou URL HTTPS<input type="url" name="foto" placeholder="https://..."></label></div><button type="submit" class="ga-button ga-button-primary">Publicar animal</button></form></section>
</div>

<?php elseif($view==='perfil'): ?>
<div class="ga-page-heading"><div><span class="ga-kicker">PERFIL PÚBLICO</span><h1>Minha ONG</h1><p>Mantenha seus dados atualizados para que as pessoas encontrem sua organização.</p></div></div>
<section class="ga-surface ngo-form-section ngo-profile"><h2>Editar informações</h2><form method="post" enctype="multipart/form-data" class="ngo-form"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="profile"><div class="ngo-form-grid"><label>Nome<input name="nome" value="<?=e($ong['nome']??'')?>" required></label><label>Telefone<input name="telefone" value="<?=e($ong['telefone']??'')?>"></label><label>Cidade<input name="cidade" value="<?=e($ong['cidade']??'')?>"></label><label>Bairro<input name="bairro" value="<?=e($ong['bairro']??'')?>"></label><label>Foco de atuação<input name="foco" value="<?=e($ong['foco']??'')?>"></label><label class="ngo-span-all">Apresentação<textarea name="descricao"><?=e($ong['descricao']??'')?></textarea></label><label class="ngo-span-all">História<textarea name="historia"><?=e($ong['historia']??'')?></textarea></label><label>Nova foto<input type="file" name="foto_upload" accept="image/jpeg,image/png,image/webp"></label><label>Ou URL pública HTTPS<input type="url" name="foto" placeholder="https://..."></label></div><button class="ga-button ga-button-primary" type="submit">Salvar perfil</button></form></section>

<?php elseif($view==='financeiro'): ?>
<div class="ga-page-heading"><div><span class="ga-kicker">ÁREA DE DEMONSTRAÇÃO</span><h1>Financeiro</h1><p>Ilustração de mensalidades da plataforma. Nenhuma cobrança aqui é real.</p></div></div>
<div class="ga-alert ga-alert-warning">Esta área é fictícia. Para consultar as intenções de doação recebidas, use a seção “Doações”.</div>
<?php $hostingFee=79.90; $hostingMonths=[date('Y-m',strtotime('-2 months')),date('Y-m',strtotime('-1 month')),date('Y-m')]; ?>
<section class="ga-surface"><div class="ga-table-wrap"><table class="ga-data-table"><thead><tr><th>Competência</th><th>Valor demonstrativo</th><th>Situação fictícia</th></tr></thead><tbody><?php foreach($hostingMonths as $i=>$m):?><tr><td><?=e($m)?></td><td>R$ <?=number_format($hostingFee,2,',','.')?></td><td><?=e($i===2?'Em aberto (exemplo)':'Quitação fictícia')?></td></tr><?php endforeach;?></tbody></table></div></section>
<?php endif;?>
</main></div></div>
<script>window.gaOngFeed={url:<?=json_encode(base_url('api/ong_feed.php'))?>,count:<?=count($cases)?>,latest:<?=max(array_map(static fn($r)=>(int)($r['id']??0),$cases)?:[0])?>};</script>
<script src="<?=base_url('assets/js/ong-dashboard-v30.js')?>?v=31" defer></script>
</body></html>
