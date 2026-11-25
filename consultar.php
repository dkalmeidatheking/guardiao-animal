<?php
require __DIR__.'/includes/bootstrap.php';

$protocoloBuscado = strtoupper(trim((string)($_GET['protocolo'] ?? '')));
// Remove caracteres especiais exceto hífen e alfanuméricos
$protocoloBuscado = preg_replace('/[^A-Z0-9\-]/', '', $protocoloBuscado) ?? '';

$casoEncontrado = null;
$buscou = !empty($protocoloBuscado);

if ($buscou) {
    $denuncias = read_store('denuncias');
    foreach ($denuncias as $d) {
        $p = strtoupper(trim((string)($d['protocolo'] ?? '')));
        $id = (string)($d['id'] ?? '');
        if ($p === $protocoloBuscado || $id === $protocoloBuscado || ($p !== '' && str_ends_with($p, $protocoloBuscado))) {
            $casoEncontrado = $d;
            break;
        }
    }
}

$pageTitle = 'Acompanhar Denúncia';
require __DIR__.'/includes/header.php';

// Mapeamento dos passos do status
$statusAtual = (string)($casoEncontrado['status'] ?? 'Recebida');
$ongNomeAtendente = '';
if (!empty($casoEncontrado['ong_id'])) {
    foreach (read_store('ong_accounts') as $acc) {
        if ((int)$acc['id'] === (int)$casoEncontrado['ong_id']) {
            $ongNomeAtendente = $acc['nome'];
            break;
        }
    }
}

$passos = [
    'Recebida' => ['ordem' => 1, 'label' => 'Recebida', 'desc' => 'Ocorrência registrada e aguardando triagem.'],
    'Encaminhada' => ['ordem' => 2, 'label' => 'Encaminhada', 'desc' => $ongNomeAtendente ? 'Encaminhada para a ONG '.$ongNomeAtendente.' para atendimento.' : 'Encaminhada para uma organização parceira.'],
    'Em análise' => ['ordem' => 2, 'label' => 'Em análise', 'desc' => 'Triagem realizada pela administração.'],
    'Em atendimento' => ['ordem' => 3, 'label' => 'Em atendimento', 'desc' => $ongNomeAtendente ? 'A equipe da '.$ongNomeAtendente.' está atuando neste atendimento.' : 'Uma organização parceira está prestando atendimento ao animal.'],
    'Concluída' => ['ordem' => 4, 'label' => 'Concluída', 'desc' => 'Atendimento finalizado com sucesso pela rede.'],
];
$ordemAtual = $passos[$statusAtual]['ordem'] ?? (in_array($statusAtual, ['Arquivada','Recusada'], true) ? 99 : 1);
?>
<section class="page-hero">
  <div class="container">
    <span class="eyebrow">Transparência · Guardião Animal</span>
    <h1>Acompanhe o andamento da sua ocorrência.</h1>
    <p>Digite o número de protocolo recebido ao enviar sua denúncia para verificar em qual etapa do atendimento ela se encontra.</p>
  </div>
</section>

<section class="section">
  <div class="container form-shell" style="max-width:760px;margin:0 auto">
    <form method="get" action="<?=base_url('consultar.php')?>" class="search-protocol-form" style="margin-bottom:32px">
      <div class="field">
        <label for="input-protocolo"><strong>Código do protocolo</strong></label>
        <div style="display:flex;gap:12px;margin-top:8px;flex-wrap:wrap">
          <input type="text" id="input-protocolo" name="protocolo" value="<?=e($protocoloBuscado)?>" placeholder="Ex.: GA-FC24829E11" required style="flex:1;min-width:240px;font-family:monospace;font-size:16px;text-transform:uppercase" autocomplete="off">
          <button type="submit" class="btn btn-dark" style="padding:0 24px">Consultar</button>
        </div>
        <small style="color:var(--muted);display:block;margin-top:6px">Você encontra este código na tela de confirmação após enviar uma denúncia.</small>
      </div>
    </form>

    <?php if ($buscou && !$casoEncontrado): ?>
      <div class="sent-confirmation reveal is-visible" style="border-left:4px solid #b83a38;background:#fff5f5" role="alert">
        <div>
          <span class="eyebrow" style="color:#b83a38">Não encontrado</span>
          <h2 style="color:#781e1c">Nenhuma ocorrência encontrada com o protocolo "<?=e($protocoloBuscado)?>".</h2>
          <p>Verifique se digitou o código corretamente (incluindo o prefixo <code>GA-</code>). Se a denúncia foi enviada recentemente, ela pode levar alguns instantes para constar no sistema.</p>
          <div class="action-row">
            <a class="btn btn-outline" href="<?=base_url('consultar.php')?>">Nova consulta</a>
            <a class="btn btn-dark" href="<?=base_url('denuncia.php')?>">Registrar ocorrência</a>
          </div>
        </div>
      </div>
    <?php elseif ($casoEncontrado): ?>
      <div class="card reveal is-visible" style="background:#fff;border:1px solid #d9e5df;border-radius:20px;padding:32px;box-shadow:0 8px 24px rgba(0,0,0,0.04)">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;border-bottom:1px solid #eef3f0;padding-bottom:20px;margin-bottom:24px">
          <div>
            <span class="eyebrow">Protocolo de Atendimento</span>
            <h2 style="font-family:monospace;font-size:26px;margin:4px 0;letter-spacing:1px;color:var(--forest)"><?=e($casoEncontrado['protocolo'] ?? 'GA-'.e($casoEncontrado['id']))?></h2>
            <small style="color:var(--muted)">Registrado em <?=e(date('d/m/Y \à\s H:i', strtotime($casoEncontrado['created_at'] ?? 'now')))?></small>
          </div>
          <div>
            <span class="chip" style="font-size:14px;padding:6px 14px;background:var(--tint);color:var(--forest);font-weight:700">Situação: <?=e($statusAtual)?></span>
          </div>
        </div>

        <!-- Linha do tempo visual -->
        <?php if ($statusAtual === 'Arquivada'): ?>
          <div style="padding:16px;background:#f8f9fa;border-left:4px solid #6c757d;border-radius:8px;margin-bottom:24px">
            <strong style="color:#495057">Ocorrência arquivada</strong>
            <p style="margin:4px 0 0;font-size:14px;color:var(--muted)">Esta ocorrência foi revisada e arquivada pela equipe técnica. <?=!empty($casoEncontrado['motivo_arquivamento']) ? 'Motivo informado: <em>'.e($casoEncontrado['motivo_arquivamento']).'</em>' : ''?></p>
          </div>
        <?php elseif ($statusAtual === 'Recusada'): ?>
          <div style="padding:16px;background:#fff5f5;border-left:4px solid #d93025;border-radius:8px;margin-bottom:24px">
            <strong style="color:#c93b2b">Ocorrência não aceita</strong>
            <p style="margin:4px 0 0;font-size:14px;color:#781e1c">Esta denúncia foi avaliada e recusada pela administração. <?=!empty($casoEncontrado['motivo_recusa']) ? 'Motivo informado: <em>'.e($casoEncontrado['motivo_recusa']).'</em>' : ''?></p>
          </div>
        <?php else: ?>
          <div class="protocol-timeline" style="margin:30px 0 40px;position:relative">
            <div style="display:grid;grid-template-columns:repeat(4, 1fr);gap:12px;position:relative">
              <?php 
              $ordemPassos = [
                  'Recebida' => 1,
                  'Encaminhada' => 2,
                  'Em atendimento' => 3,
                  'Concluída' => 4,
              ];
              foreach ($ordemPassos as $chave => $ordemNum): 
                $p = $passos[$chave];
                $isPassado = $ordemNum < $ordemAtual;
                $isAtual = $ordemNum === $ordemAtual;
                $corCirculo = $isAtual ? '#1b4d3e' : ($isPassado ? '#2e7d5d' : '#d5dfda');
                $corTexto = $isAtual ? '#1b4d3e' : ($isPassado ? '#225a43' : '#889990');
              ?>
                <div style="text-align:center;position:relative">
                  <div style="width:36px;height:36px;border-radius:50%;background:<?=$corCirculo?>;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;margin-bottom:10px;box-shadow:<?=$isAtual?'0 0 0 4px rgba(27,77,62,0.2)':'none'?>">
                    <?=$isPassado ? '✓' : $ordemNum?>
                  </div>
                  <div style="font-weight:<?=$isAtual?'700':'600'?>;color:<?=$corTexto?>;font-size:14px;margin-bottom:4px"><?=e($p['label'])?></div>
                </div>
              <?php endforeach; ?>
            </div>
            <div style="margin-top:20px;padding:16px;background:var(--tint);border-radius:12px;font-size:14px;color:var(--forest);text-align:center">
              <strong>Etapa atual:</strong> <?=e($passos[$statusAtual]['desc'] ?? 'Em processamento pela rede.')?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Dados do Registro (sem dados pessoais) -->
        <h3 style="font-size:18px;margin-bottom:14px">Resumo da Ocorrência</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;background:#fbfdfc;border:1px solid #edf2ef;border-radius:12px;padding:16px;margin-bottom:20px">
          <div><small style="color:var(--muted);display:block">Espécie</small><strong><?=e($casoEncontrado['especie'] ?? 'Animal')?></strong></div>
          <div><small style="color:var(--muted);display:block">Urgência indicada</small><strong><?=e($casoEncontrado['urgencia'] ?? 'Não informada')?></strong></div>
          <div><small style="color:var(--muted);display:block">Bairro</small><strong><?=e($casoEncontrado['bairro'] ?? 'Ourinhos - SP')?></strong></div>
          <div><small style="color:var(--muted);display:block">Local ou ponto de referência</small><strong><?=e($casoEncontrado['local'] ?? 'Não informado')?></strong></div>
        </div>

        <div style="margin-bottom:20px">
          <small style="color:var(--muted);display:block;margin-bottom:4px">Relato do que foi visto</small>
          <div style="background:#fff;border:1px solid #e2ece6;border-radius:10px;padding:14px;font-size:15px;line-height:1.5;color:var(--ink)">
            <?=nl2br(e($casoEncontrado['descricao'] ?? 'Sem descrição adicional.'))?>
          </div>
        </div>

        <div style="border-top:1px solid #edf2ef;padding-top:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
          <small style="color:var(--muted)">Por segurança, dados de contato e do denunciante não são exibidos publicamente.</small>
          <div style="display:flex;gap:10px">
            <a class="btn btn-outline" href="<?=base_url('consultar.php')?>">Consultar outro protocolo</a>
            <a class="btn btn-dark" href="<?=base_url('mapa.php')?>">Ver mapa de casos</a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div style="background:#fff;border:1px solid #e1ece6;border-radius:16px;padding:24px;text-align:center">
        <p style="margin:0 0 16px;color:var(--muted)">Ainda não tem um número de protocolo?</p>
        <a class="btn btn-dark" href="<?=base_url('denuncia.php')?>">Fazer uma denúncia agora</a>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__.'/includes/footer.php'; ?>
