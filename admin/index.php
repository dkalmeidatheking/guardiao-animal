<?php
require __DIR__.'/../includes/bootstrap.php';
require_admin();

$statuses = ['Recebida', 'Encaminhada', 'Em análise', 'Em atendimento', 'Concluída', 'Arquivada', 'Recusada'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = form_value('action', 40);

    try {
        if ($action === 'admin_approve') {
            require_principal_admin();
            $reqId=(int)($_POST['id']??0);
            $decision=form_value('decision',12);
            if (!in_array($decision,['Aprovada','Rejeitada'],true)) throw new RuntimeException('Decisão inválida.');
            $requested=null;
            foreach (read_store('admin_solicitacoes') as $candidate) {
                if ((int)($candidate['id']??0)===$reqId && ($candidate['status']??'')==='Pendente') $requested=$candidate;
            }
            if (!$requested) throw new RuntimeException('Solicitação pendente não encontrada.');
            if ($decision==='Aprovada') {
                foreach (read_store('admins') as $existing) {
                    if (strtolower((string)($existing['email']??''))===strtolower($requested['email']) || strtolower((string)($existing['username']??''))===strtolower($requested['username'])) throw new RuntimeException('Conta já existente.');
                }
                add_store('admins',[
                    'nome'=>$requested['nome'],'username'=>$requested['username'],'email'=>$requested['email'],
                    'password_hash'=>$requested['password_hash'],'tipo'=>'admin','status'=>'Ativo'
                ]);
            }
            update_store_row('admin_solicitacoes',$reqId,static function (&$r) use($decision) {
                $r['status']=$decision; $r['reviewed_at']=date('Y-m-d H:i:s');
                $r['reviewed_by']='Administrador principal';
                unset($r['password_hash']);
            });
            flash('ok',$decision==='Aprovada'?'Acesso aprovado. O usuário já pode entrar.':'Solicitação recusada.');
            redirect('admin/index.php?view=administradores');
        }
        if ($action === 'denuncia') {
            $id = (int)($_POST['id'] ?? 0);
            $actionType = form_value('action_type', 30);
            $caseOrigin = 'reais';
            $motivo = form_value('motivo', 500);
            $ongId = (int)($_POST['ong_id'] ?? 0);

            if ($actionType === 'encaminhar') {
                if ($ongId <= 0) {
                    throw new RuntimeException('Selecione uma ONG aprovada para encaminhar a ocorrência.');
                }
                $approvedOngs = array_filter(read_store('ong_accounts'), fn($o) => ($o['status'] ?? '') === 'Aprovada');
                $targetOng = null;
                foreach ($approvedOngs as $o) {
                    if ((int)$o['id'] === $ongId) { $targetOng = $o; break; }
                }
                if (!$targetOng) throw new RuntimeException('ONG selecionada não encontrada ou não aprovada.');

                $ok = update_store_row('denuncias', $id, static function (&$r) use ($ongId) {
                    $r['status'] = 'Encaminhada';
                    $r['ong_id'] = $ongId;
                    $r['encaminhada_em'] = date('Y-m-d H:i:s');
                    $r['updated_at'] = date('Y-m-d H:i:s');
                });
                if (!$ok) throw new RuntimeException('Denúncia não encontrada.');

                mutate_store('ong_case_status', static function (&$rows) use ($ongId, $id) {
                    foreach ($rows as &$row) {
                        if ((int)($row['ong_id'] ?? 0) === $ongId && (int)($row['denuncia_id'] ?? 0) === $id) {
                            $row['status'] = 'Encaminhada';
                            $row['updated_at'] = date('Y-m-d H:i:s');
                            return;
                        }
                    }
                    $rows[] = ['ong_id'=>$ongId, 'denuncia_id'=>$id, 'status'=>'Encaminhada', 'updated_at'=>date('Y-m-d H:i:s')];
                });

                ga_cache_clear();
                flash('ok', 'Ocorrência encaminhada com sucesso para ' . $targetOng['nome'] . '.');
            } elseif ($actionType === 'concluir') {
                $ok = update_store_row('denuncias', $id, static function (&$r) {
                    $r['status'] = 'Concluída';
                    $r['concluida_em'] = date('Y-m-d H:i:s');
                    $r['updated_at'] = date('Y-m-d H:i:s');
                });
                if (!$ok) throw new RuntimeException('Denúncia não encontrada.');
                mutate_store('ong_case_status', static function (&$rows) use ($id) {
                    foreach ($rows as &$row) {
                        if ((int)($row['denuncia_id'] ?? 0) === $id) {
                            $row['status'] = 'Concluída';
                            $row['updated_at'] = date('Y-m-d H:i:s');
                        }
                    }
                });
                ga_cache_clear();
                flash('ok', 'Denúncia marcada como Concluída.');
            } elseif ($actionType === 'arquivar') {
                if (mb_strlen($motivo) < 3) {
                    throw new RuntimeException('Informe o motivo para arquivar esta denúncia.');
                }
                $ok = update_store_row('denuncias', $id, static function (&$r) use ($motivo) {
                    $r['status'] = 'Arquivada';
                    $r['motivo_arquivamento'] = $motivo;
                    $r['arquivada_em'] = date('Y-m-d H:i:s');
                    $r['updated_at'] = date('Y-m-d H:i:s');
                });
                if (!$ok) throw new RuntimeException('Denúncia não encontrada.');
                mutate_store('ong_case_status', static function (&$rows) use ($id) {
                    foreach ($rows as &$row) {
                        if ((int)($row['denuncia_id'] ?? 0) === $id) {
                            $row['status'] = 'Arquivada';
                            $row['updated_at'] = date('Y-m-d H:i:s');
                        }
                    }
                });
                ga_cache_clear();
                flash('ok', 'Denúncia arquivada com sucesso.');
            } elseif ($actionType === 'recusar') {
                if (mb_strlen($motivo) < 3) {
                    throw new RuntimeException('Informe o motivo para recusar esta denúncia.');
                }
                $ok = update_store_row('denuncias', $id, static function (&$r) use ($motivo) {
                    $r['status'] = 'Recusada';
                    $r['motivo_recusa'] = $motivo;
                    $r['recusada_em'] = date('Y-m-d H:i:s');
                    $r['updated_at'] = date('Y-m-d H:i:s');
                });
                if (!$ok) throw new RuntimeException('Denúncia não encontrada.');
                ga_cache_clear();
                flash('ok', 'Denúncia recusada.');
            } elseif ($actionType === 'excluir') {
                try {
                    $denUUID = ga_ngo_uuid('denuncias', $id);
                    ga_db_request('DELETE', 'ong_denuncia_atendimentos', ['denuncia_id' => 'eq.' . $denUUID]);
                } catch (Throwable $ignore) {}
                ga_db_remove_row('denuncias', $id);
                ga_cache_clear();
                flash('ok', 'Ocorrência #' . $id . ' excluída com sucesso do banco de dados e removida do mapa.');
                redirect('admin/index.php?view=denuncias&origem=' . $caseOrigin);
            } else {
                throw new RuntimeException('Ação não reconhecida.');
            }

            redirect('admin/index.php?view=denuncias&origem='.$caseOrigin.'&case='.$id);
        }

        if ($action === 'ong') {
            $id = (int)($_POST['id'] ?? 0);
            $status = form_value('status', 40);

            if (!in_array($status, ['Pendente', 'Aprovada', 'Rejeitada'], true)) {
                throw new RuntimeException('Situação inválida.');
            }

            if (!update_store_row('ong_accounts', $id, static function (&$o) use ($status) {
                $o['status'] = $status;
                $o['reviewed_at'] = date('Y-m-d H:i:s');
            })) {
                throw new RuntimeException('ONG não encontrada.');
            }

            flash('ok', 'Cadastro da ONG atualizado.');
            redirect('admin/index.php?view=ongs&ong='.$id);
        }

        if ($action === 'sponsor_assign') {
            $id = (int)($_POST['id'] ?? 0);
            $ongId = (int)($_POST['ong_id'] ?? 0);
            $allowedOngIds = array_map('intval', array_column(array_filter(read_store('ong_accounts'),
                static fn($row) => ($row['status'] ?? '') === 'Aprovada'), 'id'));
            if ($ongId && !in_array($ongId, $allowedOngIds, true)) {
                throw new RuntimeException('Selecione uma ONG aprovada.');
            }
            $ok = update_store_row('apadrinhamentos', $id, static function (&$row) use ($ongId) {
                $row['ong_id'] = $ongId ?: null;
                $row['status'] = $ongId ? 'Encaminhado' : 'Aguardando encaminhamento';
                $row['updated_at'] = date('Y-m-d H:i:s');
            });
            if (!$ok) throw new RuntimeException('Apadrinhamento não encontrado.');
            flash('ok', $ongId ? 'Apadrinhamento encaminhado ao painel da ONG.' : 'Apadrinhamento devolvido à fila do administrador.');
            redirect('admin/index.php?view=formularios');
        }

        if ($action === 'change_password') {
            $old = (string)($_POST['senha_atual'] ?? '');
            $new = (string)($_POST['nova_senha'] ?? '');
            $again = (string)($_POST['confirmar_senha'] ?? '');

            if (strlen($new) < 12) {
                throw new RuntimeException('A senha nova precisa ter pelo menos 12 caracteres.');
            }
            if ($new !== $again) {
                throw new RuntimeException('As senhas novas não coincidem.');
            }

            $adminId = (int)($_SESSION['admin_id'] ?? 1);
            $changed = mutate_store('admins', static function (&$admins) use ($adminId, $old, $new) {
                foreach ($admins as &$admin) {
                    if ((int)($admin['id'] ?? 0) !== $adminId) {
                        continue;
                    }
                    if (!password_verify($old, $admin['password_hash'])) {
                        throw new RuntimeException('Senha atual incorreta.');
                    }
                    $admin['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
                    $admin['updated_at'] = date('Y-m-d H:i:s');
                    return true;
                }
                return false;
            });

            if (!$changed) {
                throw new RuntimeException('Administrador não encontrado.');
            }

            flash('ok', 'Senha alterada com sucesso. Guarde sua nova senha em local seguro.');
            redirect('admin/index.php?view=seguranca');
        }

        if ($action === 'home_photo') {
            $slot = form_value('slot', 20);
            if (!in_array($slot, ['hero', 'story', 'feature', 'dog1', 'dog2', 'dog3', 'dog4', 'dog5', 'dog6', 'dog7', 'dog8'], true)) {
                throw new RuntimeException('Posição inválida.');
            }

            $photo = save_uploaded_photo('foto_upload') ?: safe_photo_url(form_value('foto', 700));
            if (!$photo) {
                throw new RuntimeException('Envie uma foto ou uma URL pública HTTPS válida.');
            }

            mutate_store('home_photos', static function (&$rows) use ($slot, $photo) {
                $rows[$slot] = $photo;
            });

            flash('ok', 'Fotografia atualizada na Home.');
            redirect('admin/index.php?view=fotos');
        }

        throw new RuntimeException('Ação inválida.');
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}


$views = [
    'inicio' => 'Visão geral',
    'denuncias' => 'Denúncias',
    'ongs' => 'Organizações',
    'formularios' => 'Formulários',
    'fotos' => 'Fotos do site',
    'financeiro' => 'Financeiro (demo)',
    'seguranca' => 'Segurança',
    'administradores' => 'Aprovar administradores',
    'auditoria' => 'Histórico de alterações',
    'adocoes_historico' => 'Adoções históricas (legado)',
];
$view = (string)($_GET['view'] ?? 'inicio');
if (!isset($views[$view])) $view = 'inicio';

function ga_icon(string $name): string {
    $paths = [
        'home' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'alert' => '<path d="M10.3 3.6 2.3 17.4a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7-3L13.7 3.6a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h.01M15 7h.01M9 11h.01M15 11h.01M9 15h.01M15 15h.01M10 21v-3h4v3"/>',
        'forms' => '<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 2v4M15 2v4M9 11h6M9 15h6M9 19h3"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="15" rx="2"/><path d="M3 10h18M3 6V5a2 2 0 0 1 2-2h13"/><path d="M16 15h2"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'back' => '<path d="m15 18-6-6 6-6"/>',
        'external' => '<path d="M14 3h7v7M10 14 21 3"/><path d="M21 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h6"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close' => '<path d="M5 5l14 14M19 5 5 19"/>',
        'refresh' => '<path d="M20 12a8 8 0 1 1-2.2-5.5M20 4v6h-6"/>',
        'filter' => '<path d="M4 7h16M7 12h10M10 17h4"/>',
        'paw' => '<circle cx="7" cy="7" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="17" cy="7" r="1"/><circle cx="20" cy="12" r="1"/><path d="M12 11c2 0 3 2 4 4s1 5-4 5-5-3-4-5 2-4 4-4Z"/>',
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['forms']).'</svg>';
}
function ga_badge_class(string $status): string {
    return match ($status) {
        'Recebida', 'Pendente' => 'ga-warning',
        'Em análise' => 'ga-info',
        'Encaminhada', 'Em atendimento', 'Aprovada' => 'ga-progress',
        'Concluída' => 'ga-success',
        'Rejeitada', 'Arquivada' => 'ga-muted',
        default => 'ga-muted',
    };
}
function ga_is_demo(array $row): bool {
    $name = trim((string)($row['nome'] ?? ''));
    $description = mb_strtolower((string)($row['descricao'] ?? ''));
    return $name === 'Demonstração' && str_contains($description, 'registro fictício');
}
function ga_when($raw): string {
    if (empty($raw)) return 'Data indisponível';
    $stamp = strtotime((string)$raw);
    return $stamp ? date('d/m/Y · H:i', $stamp) : (string)$raw;
}

$den = read_store('denuncias');
$statusPriority = array_flip($statuses);
$urgencyPriority = ['Alta' => 0, 'Média' => 1, 'Baixa' => 2];
usort($den, static function ($a, $b) use ($statusPriority, $urgencyPriority) {
    $orderA = $statusPriority[$a['status'] ?? 'Recebida'] ?? 99;
    $orderB = $statusPriority[$b['status'] ?? 'Recebida'] ?? 99;
    if ($orderA !== $orderB) return $orderA <=> $orderB;
    $priorityA = $urgencyPriority[$a['urgencia'] ?? 'Baixa'] ?? 99;
    $priorityB = $urgencyPriority[$b['urgencia'] ?? 'Baixa'] ?? 99;
    if ($priorityA !== $priorityB) return $priorityA <=> $priorityB;
    return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
});
$demoDen = array_values(array_filter($den, 'ga_is_demo'));
$realDen = array_values(array_filter($den, fn($r) => !ga_is_demo($r)));
$realCounts = [];
foreach ($statuses as $s) $realCounts[$s] = count(array_filter($realDen, fn($r) => ($r['status'] ?? 'Recebida') === $s));
$realActive = count($realDen) - $realCounts['Concluída'] - $realCounts['Arquivada'];
$accounts = read_store('ong_accounts');
usort($accounts, static function ($a, $b) {
    $priority = ['Pendente' => 0, 'Aprovada' => 1, 'Rejeitada' => 2];
    $aPriority = $priority[$a['status'] ?? 'Pendente'] ?? 99;
    $bPriority = $priority[$b['status'] ?? 'Pendente'] ?? 99;
    return $aPriority !== $bPriority ? $aPriority <=> $bPriority : (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
});
$approved = array_values(array_filter($accounts, fn($r) => ($r['status'] ?? '') === 'Aprovada'));
$pending = array_values(array_filter($accounts, fn($r) => ($r['status'] ?? '') === 'Pendente'));
$counts = [];
foreach ($statuses as $item) $counts[$item] = count(array_filter($den, fn($r) => ($r['status'] ?? 'Recebida') === $item));
$activeCount = count($den) - $counts['Concluída'] - $counts['Arquivada'];
$urgentCount = count(array_filter($den, fn($r) => ($r['urgencia'] ?? '') === 'Alta' && !in_array($r['status'] ?? '', ['Concluída', 'Arquivada'], true)));
$adocoes = read_store('adocoes');
$doacoes = read_store('doacoes');
$contatos = read_store('contatos');
$apadrinhamentos = read_store('apadrinhamentos');
$allFormCount = count($adocoes) + count($doacoes) + count($contatos) + count($apadrinhamentos);

$origin = (string)($_GET['origem'] ?? 'reais');
$origin = 'reais';
$scopeDen = $realDen;
$scopeCounts = [];
foreach ($statuses as $s) $scopeCounts[$s] = count(array_filter($scopeDen, fn($r) => ($r['status'] ?? 'Recebida') === $s));
$selectedStatus = trim((string)($_GET['status'] ?? 'Todas'));
if ($selectedStatus !== 'Todas' && !in_array($selectedStatus, $statuses, true)) $selectedStatus = 'Todas';
$search = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 120);
$casesFiltered = array_values(array_filter($scopeDen, static function ($r) use ($search, $selectedStatus) {
    if ($selectedStatus !== 'Todas' && ($r['status'] ?? 'Recebida') !== $selectedStatus) return false;
    if ($search === '') return true;
    $haystack = mb_strtolower(implode(' ', [
        $r['id'] ?? '', $r['especie'] ?? '', $r['nome'] ?? '', $r['bairro'] ?? '', $r['cidade'] ?? '', $r['local'] ?? '', $r['descricao'] ?? '',
    ]));
    return str_contains($haystack, mb_strtolower($search));
}));
$casePage = max(1, (int)($_GET['page'] ?? 1));
$casePages = max(1, (int)ceil(count($casesFiltered) / 15));
$casePage = min($casePage, $casePages);
$casesPage = array_slice($casesFiltered, ($casePage - 1) * 15, 15);
$chosenCaseId = (int)($_GET['case'] ?? 0);
$chosenCase = null;
foreach ($casesPage as $r) {
    if ((int)($r['id'] ?? 0) === $chosenCaseId) $chosenCase = $r;
}
if (!$chosenCase && $casesPage) $chosenCase = $casesPage[0];
$caseHref = static function ($id = null, $page = null) use ($selectedStatus, $search, $casePage, $origin): string {
    $params = ['view' => 'denuncias', 'origem' => $origin];
    if ($selectedStatus !== 'Todas') $params['status'] = $selectedStatus;
    if ($search !== '') $params['q'] = $search;
    if ($page !== null) $params['page'] = $page;
    elseif ($casePage > 1) $params['page'] = $casePage;
    if ($id !== null) $params['case'] = $id;
    return '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

$photoSlots = [
    'hero' => 'Banner principal', 'story' => 'História da plataforma', 'feature' => 'Destaque',
    'dog1' => 'Animal 1', 'dog2' => 'Animal 2', 'dog3' => 'Animal 3', 'dog4' => 'Animal 4',
    'dog5' => 'Animal 5', 'dog6' => 'Animal 6', 'dog7' => 'Animal 7', 'dog8' => 'Animal 8',
];
$homePhotos = read_store('home_photos');

$hostingOngNames = array_values(array_map(fn($o) => $o['nome'], array_slice($ongs, 0, 5, true)));
foreach ($accounts as $account) {
    if (($account['status'] ?? '') === 'Aprovada' && !in_array($account['nome'], $hostingOngNames, true)) {
        $hostingOngNames[] = $account['nome'];
    }
}
$hostingReports = [];
$hostingStates = ['Pago (fictício)', 'Pago (fictício)', 'Em aberto (exemplo)', 'Atrasado (exemplo)', 'Em revisão (exemplo)'];
foreach (array_slice($hostingOngNames, 0, 5) as $oi => $orgName) {
    foreach ([2, 1, 0] as $offset) {
        $month = date('Y-m', strtotime('-'.$offset.' months'));
        $state = $offset === 0 ? ($oi % 2 === 0 ? 'Em aberto (exemplo)' : 'Em revisão (exemplo)') : $hostingStates[($oi + $offset) % count($hostingStates)];
        $hostingReports[] = ['ong' => $orgName, 'competencia' => $month, 'valor' => 79.90, 'status' => $state, 'referencia' => 'DEMO-'.str_pad((string)($oi + 1), 2, '0', STR_PAD_LEFT).'-'.str_replace('-', '', $month)];
    }
}

$formRows = [];
foreach ([
    ['Adoção', $adocoes], ['Doação', $doacoes], ['Contato', $contatos], ['Apadrinhamento', $apadrinhamentos],
] as [$type, $rows]) {
    foreach ($rows as $r) {
        $detail = match ($type) {
            'Adoção' => $r['animal'] ?? ($r['animal_nome'] ?? 'Animal não informado'),
            'Doação' => 'Valor registrado: R$ '.number_format((float)($r['valor'] ?? 0), 2, ',', '.').' · Não confirma pagamento',
            'Apadrinhamento' => 'Animal: '.($r['animal'] ?? ($r['animal_nome'] ?? 'Não informado')).' · Valor informado: R$ '.number_format((float)($r['valor_mensal'] ?? ($r['valor'] ?? 0)), 2, ',', '.'),
            default => ($r['assunto'] ?? '').' · '.($r['mensagem'] ?? ''),
        };
        $formRows[] = ['tipo' => $type, 'nome' => $r['nome'] ?? 'Não informado', 'email' => $r['email'] ?? '', 'data' => $r['created_at'] ?? '', 'detalhe' => $detail];
    }
}
usort($formRows, fn($a, $b) => strcmp((string)$b['data'], (string)$a['data']));

$menu = [
    ['inicio', 'Início', 'home', null],
    ['denuncias', 'Denúncias', 'alert', count($den)],
    ['ongs', 'Organizações', 'building', count($pending)],
    ['formularios', 'Formulários', 'forms', $allFormCount],
    ['fotos', 'Fotos do site', 'image', null],
    ['financeiro', 'Financeiro', 'wallet', null],
    ['seguranca', 'Segurança', 'shield', null],
    ...(is_principal_admin() ? [['administradores', 'Administradores', 'shield', count(array_filter(read_store('admin_solicitacoes'),static fn($r)=>($r['status']??'')==='Pendente'))], ['auditoria','Auditoria','shield',null], ['adocoes_historico','Histórico de adoções','forms',null]] : []),
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="color-scheme" content="light">
  <title><?=e($views[$view])?> · Administração Guardião Animal</title>
  <link rel="stylesheet" href="<?=base_url('assets/css/admin-dashboard-v28.css')?>?v=28">
</head>
<body class="ga-admin">
  <a href="#ga-main" class="ga-skip">Pular para conteúdo</a>
  <button type="button" class="ga-mobile-backdrop" data-menu-close aria-label="Fechar navegação" hidden></button>
  <div class="ga-layout">
    <aside class="ga-sidebar" id="ga-sidebar" aria-label="Menu principal">
      <div class="ga-brand">
        <img src="<?=base_url('assets/img/logo-guardiao-circular-final.png')?>" alt="Logo Guardião Animal" width="42" height="42">
        <div><strong>Guardião Animal</strong><small>Área administrativa</small></div>
      </div>
      <div class="ga-side-label">ESPAÇO DE TRABALHO</div>
      <nav class="ga-side-nav" aria-label="Seções do painel">
        <?php foreach ($menu as [$page, $label, $icon, $total]): ?>
          <a href="?view=<?=e($page)?>" class="ga-nav-link <?=$view === $page ? 'is-active' : ''?>" <?=$view === $page ? 'aria-current="page"' : ''?>>
            <?=ga_icon($icon)?><span><?=e($label)?></span>
            <?php if ($total !== null && $total > 0): ?><em><?=e((string)$total)?></em><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="ga-side-bottom">
        <div class="ga-account">
          <span class="ga-avatar">GA</span><div><strong><?=e($_SESSION['admin_name'] ?? 'Administrador')?></strong><small>Administrador</small></div>
        </div>
        <a href="<?=base_url('admin/logout.php')?>" class="ga-sidebar-logout"><?=ga_icon('logout')?> Encerrar sessão</a>
      </div>
    </aside>

    <div class="ga-content-area">
      <header class="ga-topbar">
        <div class="ga-top-left">
          <button type="button" class="ga-icon-button ga-menu-button" data-menu-toggle aria-expanded="false" aria-controls="ga-sidebar" aria-label="Abrir menu"><?=ga_icon('menu')?></button>
          <span class="ga-breadcrumb">Administração <span>/</span> <b><?=e($views[$view])?></b></span>
        </div>
        <div class="ga-top-actions"><a class="ga-top-link" href="<?=base_url('admin/integracoes.php')?>">Integração Supabase</a><span class="ga-sync" id="live-indicator"><i></i> Acompanhamento ativo</span><a class="ga-top-link" href="<?=base_url()?>" target="_blank" rel="noopener">Ver site <?=ga_icon('external')?></a></div>
      </header>
      <main class="ga-main" id="ga-main">
        <?php if ($error): ?><div class="ga-alert ga-alert-error" role="alert"><?=e($error)?></div><?php endif; ?>
        <?php if ($f = take_flash()): ?><div class="ga-alert ga-alert-success" role="status"><?=ga_icon('check')?> <?=e($f['msg'])?></div><?php endif; ?>

        <?php if ($view === 'inicio'): ?>
          <div class="ga-page-heading ga-home-heading">
            <div><span class="ga-kicker">RESUMO DA OPERAÇÃO</span><h1>Central de operações</h1><p>Tenha clareza sobre as solicitações reais e o que precisa de atenção.</p></div>
            <a class="ga-button ga-button-primary" href="?view=denuncias&origem=reais">Gerenciar denúncias <?=ga_icon('arrow')?></a>
          </div>
          <section class="ga-metrics" aria-label="Indicadores prioritários">
            <a href="?view=denuncias&origem=reais&status=Recebida" class="ga-metric ga-metric-attention"><div class="ga-metric-top"><span>Novas denúncias</span><?=ga_icon('alert')?></div><strong id="stat-novas"><?=e((string)$realCounts['Recebida'])?></strong><small>Precisam de análise</small></a>
            <a href="?view=denuncias" class="ga-metric"><div class="ga-metric-top"><span>Casos em andamento</span><?=ga_icon('clock')?></div><strong><?=e((string)($realActive - $realCounts['Recebida']))?></strong><small>Em análise ou atendimento</small></a>
            <a href="?view=ongs" class="ga-metric"><div class="ga-metric-top"><span>ONGs para aprovar</span><?=ga_icon('building')?></div><strong id="stat-pendentes"><?=e((string)count($pending))?></strong><small>Cadastros aguardando revisão</small></a>
          </section>

          <div class="ga-home-columns">
            <section class="ga-surface ga-work-queue">
              <div class="ga-surface-heading"><div><span class="ga-kicker">PRIORIDADES</span><h2>Próximas denúncias</h2></div><a class="ga-text-link" href="?view=denuncias">Ver todas <?=ga_icon('arrow')?></a></div>
              <?php
                $priorityCases = array_values(array_filter($realDen, fn($r) => ($r['status'] ?? 'Recebida') === 'Recebida'));
                if (!$priorityCases) $priorityCases = array_values(array_filter($realDen, fn($r) => !in_array($r['status'] ?? '', ['Concluída', 'Arquivada'], true)));
              ?>
              <?php if (!$priorityCases): ?><div class="ga-empty ga-empty-queue">Nenhuma denúncia ativa aguardando atendimento.</div><?php endif; ?>
              <?php foreach (array_slice($priorityCases, 0, 6) as $r): ?>
                <a class="ga-queue-row" href="?view=denuncias&case=<?=e((string)$r['id'])?>">
                  <span class="ga-queue-icon"><?=ga_icon('paw')?></span>
                  <span class="ga-queue-body"><strong><?=e($r['especie'] ?? 'Animal')?> · <?=e($r['bairro'] ?? 'Bairro não informado')?></strong><small>Caso #<?=e((string)$r['id'])?> · <?=e(ga_when($r['created_at'] ?? ''))?></small></span>
                  <span class="ga-urgency ga-urgency-<?=e(mb_strtolower(strtr($r['urgencia'] ?? 'Baixa', ['é'=>'e', 'á'=>'a'])))?>"><?=e($r['urgencia'] ?? 'Não informada')?></span>
                  <?=ga_icon('arrow')?>
                </a>
              <?php endforeach; ?>
            </section>
            <div class="ga-home-right">
              <section class="ga-surface ga-quick-actions"><div class="ga-surface-heading"><div><span class="ga-kicker">ACESSO RÁPIDO</span><h2>O que precisa fazer?</h2></div></div>
                <a href="?view=ongs"><?=ga_icon('building')?><span><strong>Revisar organizações</strong><small><?=count($pending)?> aguardando aprovação</small></span><?=ga_icon('arrow')?></a>
                <a href="?view=formularios"><?=ga_icon('forms')?><span><strong>Ver formulários</strong><small><?=e((string)$allFormCount)?> solicitações registradas</small></span><?=ga_icon('arrow')?></a>
                <a href="?view=fotos"><?=ga_icon('image')?><span><strong>Atualizar fotos</strong><small>Imagens exibidas no site</small></span><?=ga_icon('arrow')?></a>
              </section>
              
            </div>
          </div>

        <?php elseif ($view === 'denuncias'): ?>
          <div class="ga-page-heading"><div><span class="ga-kicker">GESTÃO DE CASOS</span><h1>Denúncias</h1><p>Selecione um caso, leia o relato e atualize o andamento ao lado.</p></div><span class="ga-total-tag"><?=count($realDen)?> denúncias cadastradas</span></div>
          <div class="ga-origin-switch" role="group" aria-label="Tipo de registro">
            <a href="?view=denuncias&origem=reais" <?=$origin === 'reais' ? 'aria-current="true"' : ''?>>Denúncias reais <span><?=count($realDen)?></span></a>
            <a href="?view=denuncias&origem=todos" <?=$origin === 'todos' ? 'aria-current="true"' : ''?>>Todas <span><?=count($den)?></span></a>
          </div>
          <div class="ga-case-toolbar">
            <form method="get" action="" class="ga-search" role="search"><input type="hidden" name="view" value="denuncias"><input type="hidden" name="origem" value="<?=e($origin)?>"><input type="hidden" name="status" value="<?=e($selectedStatus)?>">
              <?=ga_icon('search')?><input type="search" name="q" value="<?=e($search)?>" placeholder="Buscar por bairro, rua, ID..." aria-label="Buscar denúncias"><button type="submit">Buscar</button>
            </form>
            <div class="ga-filter" aria-label="Filtrar denúncias por situação">
              <span><?=ga_icon('filter')?> Situação</span>
              <select id="ga-status-filter" aria-label="Situação das denúncias" data-filter-url="?view=denuncias&status=">
                <option value="Todas" <?=$selectedStatus === 'Todas' ? 'selected' : ''?>>Todas (<?=count($scopeDen)?>)</option>
                <?php foreach ($statuses as $s): ?><option value="<?=e($s)?>" <?=$selectedStatus === $s ? 'selected' : ''?>><?=e($s)?> (<?=e((string)$scopeCounts[$s])?>)</option><?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="ga-case-workspace">
            <div class="ga-case-list" aria-label="Lista de denúncias">
              <div class="ga-case-list-top"><strong>Casos encontrados</strong><span><?=count($casesFiltered)?></span></div>
              <?php if (!$casesFiltered): ?><div class="ga-empty"><?= $origin === 'reais' ? 'Nenhuma denúncia real encontrada. Os casos fictícios estão na aba Demonstração.' : 'Nenhum caso encontrado com os filtros atuais.' ?> <a href="?view=denuncias&origem=<?=e($origin)?>">Limpar filtros</a></div><?php endif; ?>
              <?php foreach ($casesPage as $r): ?>
                <a class="ga-case-list-row <?=(int)($r['id'] ?? 0) === (int)($chosenCase['id'] ?? -1) ? 'is-selected' : ''?>" href="<?=e($caseHref((int)($r['id'] ?? 0)))?>" <?=(int)($r['id'] ?? 0) === (int)($chosenCase['id'] ?? -1) ? 'aria-current="true"' : ''?>>
                  <div class="ga-case-row-top"><span class="ga-small-id">#<?=e((string)($r['id'] ?? ''))?><?php if (ga_is_demo($r)): ?> · <b class="ga-demo-label">DEMO</b><?php endif; ?></span><span class="ga-status <?=e(ga_badge_class($r['status'] ?? 'Recebida'))?>"><?=e($r['status'] ?? 'Recebida')?></span></div>
                  <strong><?=e($r['especie'] ?? 'Animal')?> · <?=e($r['bairro'] ?? 'Não informado')?></strong>
                  <p><?=e($r['local'] ?? 'Endereço não informado')?></p>
                  <div class="ga-case-row-bottom"><span class="ga-urgency ga-urgency-<?=e(mb_strtolower(strtr($r['urgencia'] ?? 'Baixa', ['é'=>'e', 'á'=>'a'])))?>"><?=e($r['urgencia'] ?? 'Não informada')?></span><time><?=e(ga_when($r['created_at'] ?? ''))?></time></div>
                </a>
              <?php endforeach; ?>
              <?php if ($casePages > 1): ?>
                <div class="ga-pagination">
                  <a <?=$casePage <= 1 ? 'aria-disabled="true"' : ''?> href="<?=e($caseHref(null, max(1, $casePage - 1)))?>">Anterior</a><span><?=e((string)$casePage)?> de <?=e((string)$casePages)?></span><a <?=$casePage >= $casePages ? 'aria-disabled="true"' : ''?> href="<?=e($caseHref(null, min($casePages, $casePage + 1)))?>">Próxima</a>
                </div>
              <?php endif; ?>
            </div>
            <section class="ga-case-detail" id="detalhes" aria-label="Detalhes do caso selecionado">
              <?php if (!$chosenCase): ?>
                <div class="ga-empty ga-empty-detail">Selecione um caso para ver os detalhes e gerenciar o andamento.</div>
              <?php else: ?>
                <div class="ga-detail-top"><a class="ga-back-mobile" href="?view=denuncias&origem=<?=e($origin)?>"><?=ga_icon('back')?> Voltar à lista</a><span class="ga-kicker">CASO #<?=e((string)$chosenCase['id'])?></span><span class="ga-status <?=e(ga_badge_class($chosenCase['status'] ?? 'Recebida'))?>"><?=e($chosenCase['status'] ?? 'Recebida')?></span></div>
                <h2><?=e($chosenCase['especie'] ?? 'Animal')?> em <?=e($chosenCase['bairro'] ?? 'bairro não informado')?></h2>
                <div class="ga-detail-meta"><span><?=ga_icon('clock')?> <?=e(ga_when($chosenCase['created_at'] ?? ''))?></span><span class="ga-urgency ga-urgency-<?=e(mb_strtolower(strtr($chosenCase['urgencia'] ?? 'Baixa', ['é'=>'e', 'á'=>'a'])))?>">Urgência <?=e($chosenCase['urgencia'] ?? 'não informada')?></span></div>
                <?php if (ga_is_demo($chosenCase)): ?><div class="ga-inline-demo">Caso fictício utilizado para demonstração do projeto. Não representa uma ocorrência confirmada.</div><?php endif; ?>
                <div class="ga-detail-section"><h3>Localização informada</h3><p><?=e($chosenCase['local'] ?? 'Endereço não informado')?></p><p class="ga-detail-muted"><?=e($chosenCase['bairro'] ?? '')?><?=!empty($chosenCase['cidade']) ? ' · '.e($chosenCase['cidade']) : ''?></p></div>
                <div class="ga-detail-section"><h3>Relato da denúncia</h3><div class="ga-report"><?=nl2br(e($chosenCase['descricao'] ?? 'Sem descrição.'))?></div></div>
                <div class="ga-detail-section"><h3>Contato do denunciante</h3>
                  <dl class="ga-info-grid"><div><dt>Nome</dt><dd><?=e(($chosenCase['nome'] ?? '') ?: 'Não informado')?></dd></div><div><dt>Identificação</dt><dd><?=e($chosenCase['identificacao'] ?? 'Não')?></dd></div>
                  <div><dt>E-mail</dt><dd><?=e(($chosenCase['email'] ?? '') ?: 'Não informado')?></dd></div><div><dt>Telefone</dt><dd><?=e(($chosenCase['telefone'] ?? '') ?: 'Não informado')?></dd></div></dl>
                </div>
                <?php
                $allCaseStatuses = read_store('ong_case_status');
                $caseNgoAttendance = null;
                foreach ($allCaseStatuses as $att) {
                    if ((int)($att['denuncia_id'] ?? 0) === (int)$chosenCase['id']) {
                        $caseNgoAttendance = $att;
                        break;
                    }
                }
                $ngoAttendingName = '';
                if ($caseNgoAttendance) {
                    $attOid = (int)($caseNgoAttendance['ong_id'] ?? 0);
                    foreach ($accounts as $acc) {
                        if ((int)($acc['id'] ?? 0) === $attOid) {
                            $ngoAttendingName = $acc['nome'] ?? 'ONG parceira';
                            break;
                        }
                    }
                }
                ?>
                <div class="ga-detail-section">
                  <h3>Andamento na Rede de ONGs</h3>
                  <?php if ($caseNgoAttendance && $ngoAttendingName): ?>
                    <div style="background:#f4f9f6;border:1px solid #d4e7dd;border-radius:10px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                      <div>
                        <strong><?=e($ngoAttendingName)?></strong>
                        <div style="font-size:13px;color:var(--muted)">Situação na ONG: <b><?=e($caseNgoAttendance['status'] ?? 'Em atendimento')?></b> · Atualizado em <?=e(ga_when($caseNgoAttendance['updated_at'] ?? ''))?></div>
                      </div>
                      <span class="ga-status ga-progress"><?=e($caseNgoAttendance['status'] ?? 'Em atendimento')?></span>
                    </div>
                  <?php else: ?>
                    <p class="ga-detail-muted">Nenhuma organização parceira assumiu o atendimento específico desta ocorrência ainda. O relato segue aberto para toda a rede.</p>
                  <?php endif; ?>
                </div>
                <form class="ga-edit-form ga-action-card-image" method="post" action="<?=e(base_url('admin/index.php'))?>" style="background:#f7faf8;border:1px solid #dce8e1;border-radius:16px;padding:24px;margin-top:20px">
                  <input type="hidden" name="csrf" value="<?=csrf()?>">
                  <input type="hidden" name="action" value="denuncia">
                  <input type="hidden" name="origem" value="<?=e($origin)?>">
                  <input type="hidden" name="id" value="<?=e((string)$chosenCase['id'])?>">

                  <h3 style="font-size:18px;font-weight:700;margin:0 0 4px;color:#153c31">Atualizar atendimento</h3>
                  <p style="color:#5a7267;font-size:13px;margin:0 0 14px">Escolha uma ação. Encaminhar exige uma ONG aprovada; arquivar e recusar exigem um motivo.</p>

                  <div style="background:#eaf4ee;border:1px solid #cce5d5;border-radius:10px;padding:12px 14px;font-size:12.5px;line-height:1.5;color:#1e4c3a;margin-bottom:18px">
                    Fluxo: denúncias novas chegam primeiro ao administrador. Encaminhar atribui a ocorrência à ONG escolhida; concluir, arquivar e recusar retiram o caso do mapa ativo. O registro permanece no Supabase.
                  </div>

                  <div class="field" style="margin-bottom:14px">
                    <label for="ong_select" style="display:block;font-weight:600;font-size:13px;color:#224436;margin-bottom:6px">Encaminhar para qual ONG?</label>
                    <select id="ong_select" name="ong_id" style="width:100%;padding:10px 12px;border:1px solid #b7ccbe;border-radius:8px;font:inherit;background:#fff;color:#153c31">
                      <option value="">Selecione uma ONG aprovada</option>
                      <?php foreach ($approved as $org): ?>
                        <option value="<?=e((string)$org['id'])?>" <?=((int)($chosenCase['ong_id'] ?? 0) === (int)$org['id']) ? 'selected' : ''?>>
                          <?=e($org['nome'])?> · <?=e($org['bairro'] ?? 'Ourinhos')?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="field" style="margin-bottom:18px">
                    <label for="motivo_text" style="display:block;font-weight:600;font-size:13px;color:#224436;margin-bottom:6px">Motivo para arquivar ou recusar (obrigatório nessas ações)</label>
                    <textarea id="motivo_text" name="motivo" rows="2" placeholder="Ex.: denúncia duplicada, informação insuficiente..." style="width:100%;padding:10px 12px;border:1px solid #b7ccbe;border-radius:8px;font:inherit;resize:vertical;min-height:60px"><?=e($chosenCase['motivo_arquivamento'] ?? $chosenCase['motivo_recusa'] ?? '')?></textarea>
                  </div>

                  <div style="display:flex;flex-direction:column;gap:10px">
                    <button type="submit" name="action_type" value="encaminhar" style="background:#1b4d3e;color:#fff;border:none;border-radius:8px;padding:12px;font-weight:600;font-size:14.5px;cursor:pointer;transition:all 0.15s;text-align:center">Encaminhar à ONG</button>
                    <button type="submit" name="action_type" value="concluir" style="background:#eef3f0;color:#1b4d3e;border:1px solid #d3e1d9;border-radius:8px;padding:11px;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.15s;text-align:center">Concluir</button>
                    <button type="submit" name="action_type" value="arquivar" style="background:#eef3f0;color:#496156;border:1px solid #d3e1d9;border-radius:8px;padding:11px;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.15s;text-align:center">Arquivar</button>
                    <button type="submit" name="action_type" value="recusar" style="background:#fff;color:#c93b2b;border:1px solid #e8a59e;border-radius:8px;padding:11px;font-weight:600;font-size:14px;cursor:pointer;transition:all 0.15s;text-align:center">Recusar</button>
                    <div style="margin-top:6px;padding-top:12px;border-top:1px dashed #dce8e1">
                      <button type="submit" name="action_type" value="excluir" onclick="return confirm('ATENÇÃO: Deseja realmente excluir permanentemente a ocorrência #<?=e((string)$chosenCase['id'])?> do Supabase? Essa ação removerá a denúncia do mapa e dos relatórios imediatamente.');" style="width:100%;background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;border-radius:8px;padding:10px;font-weight:600;font-size:13.5px;cursor:pointer;transition:all 0.15s;text-align:center">🗑️ Deletar ocorrência permanentemente</button>
                    </div>
                  </div>
                </form>
              <?php endif; ?>
            </section>
          </div>

        <?php elseif ($view === 'ongs'): ?>
          <div class="ga-page-heading"><div><span class="ga-kicker">PARCERIAS</span><h1>Organizações</h1><p>Revise os cadastros e autorize ONGs antes de disponibilizar o acesso.</p></div><span class="ga-total-tag"><?=count($pending)?> aguardando aprovação</span></div>
          <div class="ga-mini-metrics"><span><b><?=count($accounts)?></b> cadastradas</span><span><b><?=count($pending)?></b> pendentes</span><span><b><?=count($approved)?></b> aprovadas</span></div>
          <div class="ga-network-notice"><strong>Acessos das cinco ONGs do catálogo provisionados automaticamente.</strong> Cada organização possui seu login; as denúncias são exibidas aos perfis aprovados. Contas adicionais continuam passando pela aprovação administrativa.</div>
          <div class="ga-surface ga-ong-list">
            <?php if (!$accounts): ?><div class="ga-empty">Ainda não há cadastros de organizações.</div><?php endif; ?>
            <?php foreach ($accounts as $o): ?>
              <article class="ga-ong-row"><div class="ga-ong-main"><div class="ga-ong-icon"><?=ga_icon('building')?></div><div><div class="ga-ong-top"><strong><?=e($o['nome'] ?? 'Organização sem nome')?></strong><span class="ga-status <?=e(ga_badge_class($o['status'] ?? 'Pendente'))?>"><?=e($o['status'] ?? 'Pendente')?></span></div><small><?=e($o['cidade'] ?? '')?><?=!empty($o['bairro']) ? ' · '.e($o['bairro']) : ''?><?=!empty($o['foco']) ? ' · '.e($o['foco']) : ''?></small></div></div>
              <details <?=((int)($o['id'] ?? 0) === (int)($_GET['ong'] ?? -1)) ? 'open' : ''?>><summary>Revisar cadastro <?=ga_icon('arrow')?></summary>
                <p><?=e($o['descricao'] ?? 'Sem descrição.')?></p><div class="ga-detail-section"><dl class="ga-info-grid"><div><dt>E-mail</dt><dd><?=e($o['email'] ?? 'Não informado')?></dd></div><div><dt>Telefone</dt><dd><?=e($o['telefone'] ?? 'Não informado')?></dd></div></dl></div>
                <form method="post" class="ga-ong-form"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="ong"><input type="hidden" name="id" value="<?=e((string)$o['id'])?>"><label>Situação<select name="status"><?php foreach (['Pendente', 'Aprovada', 'Rejeitada'] as $s): ?><option value="<?=e($s)?>" <?=$s === ($o['status'] ?? '') ? 'selected' : ''?>><?=e($s)?></option><?php endforeach; ?></select></label><button class="ga-button ga-button-primary" type="submit">Salvar decisão</button></form>
              </details></article>
            <?php endforeach; ?>
          </div>

        <?php elseif ($view === 'formularios'): ?>
          <div class="ga-page-heading"><div><span class="ga-kicker">SOLICITAÇÕES RECEBIDAS</span><h1>Formulários</h1><p>Acompanhe apadrinhamentos, encaminhe à ONG responsável e consulte os demais contatos.</p></div><span class="ga-total-tag"><?=e((string)$allFormCount)?> registros locais</span></div>
          <div class="ga-mini-metrics ga-mini-metrics-four"><span><b><?=count($adocoes)?></b> Adoções</span><span><b><?=count($apadrinhamentos)?></b> Apadrinhamentos</span><span><b><?=count($doacoes)?></b> Doações</span><span><b><?=count($contatos)?></b> Contatos</span></div>
          <section class="ga-surface ga-sponsor-assignments">
            <div class="ga-surface-heading"><div><span class="ga-kicker">DIRECIONAMENTO ÀS ONGS</span><h2>Apadrinhamentos aguardando atendimento</h2></div></div>
            <div class="ga-alert ga-alert-warning">Animais de demonstração que não pertencem a uma ONG cadastrada ficam na fila do administrador. Encaminhe o registro apenas à organização que confirmar a responsabilidade pelo animal.</div>
            <?php if (!$apadrinhamentos): ?><div class="ga-empty">Nenhum pedido de apadrinhamento recebido ainda.</div><?php endif; ?>
            <?php foreach (array_reverse($apadrinhamentos) as $request): ?>
              <form class="ga-sponsor-assign-row" method="post">
                <input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="sponsor_assign"><input type="hidden" name="id" value="<?=e((string)($request['id'] ?? ''))?>">
                <div><span class="ga-status <?=empty($request['ong_id'])?'ga-warning':'ga-progress'?>"><?=e($request['status'] ?? 'Aguardando encaminhamento')?></span><strong><?=e($request['animal'] ?? 'Animal não informado')?></strong><small>#<?=e((string)($request['id'] ?? ''))?> · <?=e($request['nome']??'')?> · <?=e($request['created_at']??'')?></small></div>
                <label>ONG responsável
                  <select name="ong_id"><option value="0">Aguardar análise do administrador</option>
                    <?php foreach ($approved as $org): ?><option value="<?=e((string)$org['id'])?>" <?=(int)($request['ong_id']??0)===(int)$org['id']?'selected':''?>><?=e($org['nome'])?></option><?php endforeach; ?>
                  </select>
                </label>
                <button class="ga-button ga-button-primary" type="submit">Salvar encaminhamento</button>
              </form>
            <?php endforeach; ?>
          </section>
          <section class="ga-surface"><div class="ga-table-wrap"><table class="ga-data-table"><thead><tr><th>Data</th><th>Tipo</th><th>Nome</th><th>Detalhes</th><th>Contato</th></tr></thead><tbody>
              <?php foreach ($formRows as $r): ?><tr><td><?=e(ga_when($r['data']))?></td><td><span class="ga-status ga-info"><?=e($r['tipo'])?></span></td><td><strong><?=e($r['nome'])?></strong></td><td><?=e($r['detalhe'])?></td><td><?=e($r['email'] ?: 'Não informado')?></td></tr><?php endforeach; ?>
              <?php if (!$formRows): ?><tr><td colspan="5" class="ga-empty">Nenhum formulário registrado até agora.</td></tr><?php endif; ?>
          </tbody></table></div></section>

        <?php elseif ($view === 'fotos'): ?>
          <div class="ga-page-heading"><div><span class="ga-kicker">CONTEÚDO DO SITE</span><h1>Fotos da página inicial</h1><p>Substitua uma imagem por vez. JPG, PNG e WebP de até 5 MB. Escolha um arquivo, confira a prévia e clique em Atualizar imagem.</p></div><span class="ga-total-tag"><?=count($photoSlots)?> posições</span></div>
          <section class="ga-photo-grid">
            <?php foreach ($photoSlots as $slot => $label): ?>
              <form class="ga-photo-card" method="post" enctype="multipart/form-data"><div class="ga-photo-preview"><img src="<?=e($homePhotos[$slot] ?? 'https://images.unsplash.com/photo-1552053831-71594a27632d?w=480&auto=format&fit=crop')?>" loading="lazy" alt="Prévia de <?=e($label)?>"></div>
                <div class="ga-photo-card-body"><h2><?=e($label)?></h2><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="home_photo"><input type="hidden" name="slot" value="<?=e($slot)?>"><label>Carregar arquivo<input type="file" name="foto_upload" accept="image/jpeg,image/png,image/webp"></label><label>Ou URL HTTPS<input type="url" name="foto" placeholder="https://..."></label><small class="ga-photo-feedback" aria-live="polite">Selecione um arquivo ou informe uma URL HTTPS.</small><button class="ga-button ga-button-outline" type="submit">Atualizar imagem</button></div>
              </form>
            <?php endforeach; ?>
          </section>

        <?php elseif ($view === 'financeiro'): ?>
          <div class="ga-page-heading"><div><span class="ga-kicker">ÁREA DE DEMONSTRAÇÃO</span><h1>Financeiro</h1><p>Simulação de relatórios mensais para apresentação do projeto.</p></div></div>
          <div class="ga-alert ga-alert-warning"><?=ga_icon('alert')?> Dados fictícios: nenhuma cobrança, pagamento ou recebimento desta tabela foi validado. Não use como relatório financeiro real.</div>
          <section class="ga-surface"><div class="ga-table-wrap"><table class="ga-data-table"><thead><tr><th>Competência</th><th>ONG</th><th>Referência</th><th>Mensalidade simulada</th><th>Situação fictícia</th></tr></thead><tbody>
          <?php foreach (array_reverse($hostingReports) as $r): ?><tr><td><?=e($r['competencia'])?></td><td><?=e($r['ong'])?></td><td><?=e($r['referencia'])?></td><td>R$ <?=number_format($r['valor'], 2, ',', '.')?></td><td><span class="ga-status ga-muted"><?=e($r['status'])?></span></td></tr><?php endforeach; ?>
          <?php if (!$hostingReports): ?><tr><td colspan="5" class="ga-empty">Não há registros demonstrativos.</td></tr><?php endif; ?>
          </tbody></table></div></section>

        <?php elseif ($view === 'administradores'): ?>
          <?php if (!is_principal_admin()): ?><div class="ga-alert ga-alert-error">Somente o administrador principal pode gerenciar novos acessos.</div>
          <?php else: ?>
          <div class="ga-page-heading"><div><span class="ga-kicker">CONTROLE DE ACESSO</span><h1>Solicitações de administradores</h1><p>Somente o administrador principal pode aprovar ou recusar novos acessos.</p></div></div>
          <section class="ga-surface" style="padding:24px"><h2>Pendentes</h2>
          <?php $requests=array_values(array_filter(read_store('admin_solicitacoes'),static fn($r)=>($r['status']??'')==='Pendente')); ?>
          <?php if (!$requests): ?><p>Nenhuma solicitação aguardando decisão.</p><?php endif;?>
          <div class="ga-table-wrap"><table class="ga-data-table"><thead><tr><th>Nome</th><th>Usuário</th><th>E-mail</th><th>Solicitado em</th><th>Decisão</th></tr></thead><tbody>
          <?php foreach($requests as $r): ?><tr><td><?=e($r['nome'])?></td><td><?=e($r['username'])?></td><td><?=e($r['email'])?></td><td><?=e($r['created_at'])?></td><td>
           <form method="post" style="display:flex;gap:8px;flex-wrap:wrap"><input type="hidden" name="csrf" value="<?=e(csrf())?>"><input type="hidden" name="action" value="admin_approve"><input type="hidden" name="id" value="<?=e($r['id'])?>">
           <button class="ga-button ga-button-primary" type="submit" name="decision" value="Aprovada">Aprovar</button><button class="ga-button" type="submit" name="decision" value="Rejeitada">Recusar</button></form></td></tr><?php endforeach;?>
          </tbody></table></div></section>
          <section class="ga-surface" style="padding:24px;margin-top:20px"><h2>Administradores ativos</h2><div class="ga-table-wrap"><table class="ga-data-table"><thead><tr><th>Nome</th><th>Usuário</th><th>E-mail</th><th>Permissão</th></tr></thead><tbody>
          <?php foreach(read_store('admins') as $u): ?><tr><td><?=e($u['nome']??'')?></td><td><?=e($u['username']??'')?></td><td><?=e($u['email']??'')?></td><td><?=e($u['tipo']??'admin')?></td></tr><?php endforeach;?></tbody></table></div></section>
          <?php endif; ?>
        <?php elseif ($view === 'auditoria'): ?>
          <?php if (!is_principal_admin()): ?><div class="ga-alert ga-alert-error">Apenas o administrador principal pode consultar a auditoria.</div>
          <?php else: ?>
          <div class="ga-page-heading"><div><span class="ga-kicker">RASTREABILIDADE</span><h1>Histórico de alterações</h1><p>Últimas operações gravadas na tabela auditoria do Supabase. Dados pessoais e senhas não fazem parte deste registro.</p></div></div>
          <?php
            $logs=[];$auditError='';
            try { $logs=ga_db_request('GET','auditoria',['select'=>'tipo_acao,entidade,entidade_id,detalhes,created_at','order'=>'created_at.desc','limit'=>100]); }
            catch(Throwable $ex) { $auditError='O histórico não pôde ser consultado no banco.'; error_log('[Guardiao] Auditoria: '.$ex->getMessage()); }
          ?>
          <?php if ($auditError): ?><div class="ga-alert ga-alert-error"><?=e($auditError)?></div><?php endif; ?>
          <section class="ga-surface"><div class="ga-table-wrap"><table class="ga-data-table"><thead><tr><th>Quando</th><th>Operação</th><th>Tabela / módulo</th><th>Registro</th><th>Executado por</th></tr></thead><tbody>
          <?php foreach($logs as $entry): $meta=is_array($entry['detalhes']??null)?$entry['detalhes']:[]; ?><tr><td><?=e((string)($entry['created_at']??''))?></td><td><?=e((string)($entry['tipo_acao']??''))?></td><td><?=e((string)($entry['entidade']??''))?></td><td><?=e((string)($entry['entidade_id']??''))?></td><td><?=e((string)($meta['papel']??'sistema'))?></td></tr><?php endforeach; ?>
          <?php if (!$logs): ?><tr><td colspan="5" class="ga-empty">Nenhuma alteração registrada até agora.</td></tr><?php endif; ?>
          </tbody></table></div></section>
          <?php endif; ?>
        <?php elseif ($view === 'adocoes_historico'): ?>
          <?php if (!is_principal_admin()): ?><div class="ga-alert ga-alert-error">Apenas o administrador principal pode consultar este histórico.</div>
          <?php else: ?>
          <div class="ga-page-heading"><div><span class="ga-kicker">DADOS HISTÓRICOS</span><h1>Solicitações de adoção anteriores</h1><p>Consulta somente leitura da tabela solicitacoes_adocao. O formulário de adoção continua desativado na versão atual.</p></div></div>
          <?php
            $history=[];$historyError='';
            try { $history=ga_db_request('GET','solicitacoes_adocao',['select'=>'id,animal_nome,status,created_at','order'=>'created_at.desc','limit'=>100]); }
            catch(Throwable $ex) { $historyError='A tabela de adoções antigas não pôde ser consultada.'; error_log('[Guardiao] Histórico adoção: '.$ex->getMessage()); }
          ?>
          <?php if ($historyError): ?><div class="ga-alert ga-alert-error"><?=e($historyError)?></div><?php endif; ?>
          <section class="ga-surface"><div class="ga-table-wrap"><table class="ga-data-table"><thead><tr><th>Data</th><th>Animal</th><th>Status anterior</th><th>ID</th></tr></thead><tbody>
          <?php foreach($history as $item): ?><tr><td><?=e((string)($item['created_at']??''))?></td><td><?=e((string)($item['animal_nome']??'Não informado'))?></td><td><?=e((string)($item['status']??''))?></td><td><code><?=e((string)($item['id']??''))?></code></td></tr><?php endforeach; ?>
          <?php if (!$history): ?><tr><td colspan="4" class="ga-empty">Nenhum histórico de adoções no Supabase.</td></tr><?php endif; ?>
          </tbody></table></div></section>
          <?php endif; ?>
        <?php elseif ($view === 'seguranca'): ?>
          <div class="ga-page-heading"><div><span class="ga-kicker">CONFIGURAÇÃO DE ACESSO</span><h1>Segurança</h1><p>Proteja sua conta administrativa com uma senha exclusiva.</p></div></div>
          <section class="ga-surface ga-security-card"><h2>Alterar senha</h2><p>A senha deve ter pelo menos 12 caracteres. Você precisará informar a senha atual.</p>
            <form method="post" class="ga-security-form"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="change_password"><label>Senha atual<input type="password" name="senha_atual" autocomplete="current-password" required></label><label>Nova senha<input type="password" name="nova_senha" autocomplete="new-password" minlength="12" required></label><label>Confirmar senha<input type="password" name="confirmar_senha" autocomplete="new-password" minlength="12" required></label><button class="ga-button ga-button-primary" type="submit"><?=ga_icon('shield')?> Atualizar senha</button></form>
          </section>
        <?php endif; ?>
      </main>
    </div>
  </div>
  <script>window.guardiaoAdmin={feed:<?=json_encode(base_url('api/admin_counts.php'))?>,denuncias:<?=count($den)?>,pendentes:<?=count($pending)?>};</script>
  <script src="<?=base_url('assets/js/admin-v29.js')?>?v=28" defer></script>
</body>
</html>
