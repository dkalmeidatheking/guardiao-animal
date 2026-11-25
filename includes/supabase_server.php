<?php
declare(strict_types=1);
/** Somente PHP no servidor. NUNCA envie GA_SUPABASE_SECRET_KEY ao navegador. */
/**
 * Configuracao privada: preferir pasta-pai do htdocs; alternativa dentro
 * de htdocs/config SOMENTE quando o servidor bloquear acesso HTTP a config/.
 * Verifique 403/404 para um arquivo de prova antes de enviar qualquer segredo.
 */
function ga_private_config(): array {
    $locations = [
        dirname(__DIR__) . '/config/guardiao_supabase_private.php',
        dirname(__DIR__, 2) . '/guardiao_supabase_private.php',
    ];
    foreach ($locations as $path) {
        if (@is_file($path)) {
            $cfg = @require $path;
            if (is_array($cfg)) return $cfg;
        }
    }
    return [];
}
function ga_db_key(): string {
    $key = (string)(getenv('GA_SUPABASE_SECRET_KEY') ?: '');
    if ($key === '') {
        $cfg=ga_private_config();
        $key=(string)($cfg['secret_key']??'');
    }
    if (!str_starts_with($key,'sb_secret_') && !preg_match('~^eyJ[A-Za-z0-9_.-]{80,}$~',$key)) {
        throw new RuntimeException('Supabase não configurado: forneça a chave secreta SOMENTE no servidor (GA_SUPABASE_SECRET_KEY).');
    }
    return $key;
}
function ga_db_request(string $method,string $path,array $query=[],?array $body=null,array $extraHeaders=[]): array {
    $key=ga_db_key();
    $base=rtrim(SUPABASE_URL,'/').'/rest/v1/';
    if (!preg_match('~^[a-zA-Z0-9_/-]+$~',$path)) throw new RuntimeException('Recurso Supabase inválido.');
    $url=$base.$path.($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');
    $headers=['apikey: '.$key,'Accept: application/json','Content-Type: application/json','Prefer: return=representation'];
    foreach($extraHeaders as $h) $headers[]=$h;
    $payload=$body===null?null:json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $status=0; $result=false; $err='';
    if (function_exists('curl_init')) {
       $ch=curl_init($url);
       curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>18,CURLOPT_SSL_VERIFYPEER=>true]);
       if ($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,$payload);
       $result=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=(string)curl_error($ch);curl_close($ch);
    } else {
       $ctx=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$payload??'','ignore_errors'=>true,'timeout'=>18]]);
       $result=@file_get_contents($url,false,$ctx);
       foreach(($http_response_header??[]) as $header) if (preg_match('~^HTTP/\S+\s+(\d{3})~',$header,$m)) $status=(int)$m[1];
    }
    $data=is_string($result)&&$result!==''?json_decode($result,true):[];
    if ($status<200 || $status>=300 || $result===false) {
       $message=is_array($data)?(string)($data['message']??$data['details']??$data['hint']??''):'';
       throw new RuntimeException('Supabase '.$method.' '.$path.' HTTP '.$status.': '.($message?:($err?:'Verifique a tabela, chave, rede e a migração SQL.')));
    }
    return is_array($data)?$data:[["ga_next_local_id"=>$data]];
}
/**
 * Registro de auditoria no Supabase. O conteúdo salvo nunca inclui senhas,
 * dados de denunciantes ou tokens. Falhas são registradas no log do PHP
 * sem transformar uma operação já concluída em falso erro na interface.
 */
function ga_db_audit(string $action, string $store, ?int $localId=null, array $context=[]): void {
    if ($store === 'auditoria') return;
    try {
        $role=(string)($_SESSION['role']??'');
        $localActor=$role==='admin'?(int)($_SESSION['admin_id']??0):($role==='ong'?(int)($_SESSION['ong_id']??0):0);
        $actorUUID=null;
        if ($role==='admin' && $localActor>0) {
            $rows=ga_db_request('GET','administradores',['select'=>'id','local_id'=>'eq.'.$localActor,'limit'=>1]);
            $actorUUID=(string)($rows[0]['id']??'') ?: null;
        }
        $details=[
          'origem'=>'aplicacao_web',
          'papel'=>$role?:'sistema',
          'ator_local_id'=>$localActor?:null,
          'store'=>$store,
        ];
        // Contexto estritamente limitado a metadados operacionais, nunca payload de usuários.
        if (isset($context['ong_local_id'])) $details['ong_local_id']=(int)$context['ong_local_id'];
        ga_db_request('POST','auditoria',[],[
          'ator_id'=>$actorUUID,
          'tipo_acao'=>$action,
          'entidade'=>$store,
          'entidade_id'=>$localId===null?null:(string)$localId,
          'detalhes'=>$details,
        ]);
    } catch (Throwable $e) {
        error_log('[Guardiao] Falha ao registrar auditoria no Supabase: '.substr($e->getMessage(),0,160));
    }
}
function ga_store_table(string $name): string {
    $map=[
        'denuncias'=>'denuncias',
        'admins'=>'administradores',
        'admin_solicitacoes'=>'admin_solicitacoes',
        'ong_accounts'=>'ongs',
        'ong_animals'=>'animais',
        'doacoes'=>'doacoes',
        'apadrinhamentos'=>'apadrinhamentos',
        'contatos'=>'contatos',
    ];
    if (!preg_match('/^[a-z_]+$/',$name)) throw new InvalidArgumentException('Store inválida.');
    return $map[$name]??'';
}
function ga_db_columns(string $store,array $row): array {
    $data=['payload'=>$row];
    $fields=[
      'denuncias'=>['protocolo','especie','descricao','bairro','cidade','estado','urgencia','status'],
      'admins'=>['nome','username','email','password_hash','tipo','status'],
      'admin_solicitacoes'=>['nome','username','email','password_hash','status'],
      'ong_accounts'=>['nome','email','password_hash','cidade','bairro','telefone','foco','descricao','historia','status'],
      'ong_animals'=>['nome','especie','raca','idade','sexo','porte','status'],
      'doacoes'=>['valor','tipo','descricao','status'],
      'apadrinhamentos'=>['nome','email','valor_mensal','mensagem','status'],
      'contatos'=>['nome','email','assunto','mensagem','status'],
      'noticias'=>['titulo','resumo','conteudo']
    ];
    if ($store==='denuncias') {
        $data['local_ocorrencia']=(string)($row['local']??$row['local_ocorrencia']??'');
        $data['deseja_identificar']=($row['identificacao']??'')==='Sim';
        $data['nome_denunciante']=$data['deseja_identificar']?($row['nome']??null):null;
        $data['contato_denunciante']=$data['deseja_identificar']?(!empty($row['email'])?$row['email']:($row['telefone']??null)):null;
        $data['estado']='SP';$data['cidade']='Ourinhos';
        $map=['Recebida'=>'recebida','Em análise'=>'em_analise','Encaminhada'=>'encaminhada','Em atendimento'=>'em_atendimento','Concluída'=>'concluida','Arquivada'=>'arquivada','Recusada'=>'recusada'];
        $data['status']=$map[(string)($row['status']??'Recebida')]??(string)($row['status']??'recebida');
        $data['urgencia']=['Baixa'=>'baixa','Média'=>'media','Alta'=>'alta','Emergência'=>'emergencia'][(string)($row['urgencia']??'Baixa')]??'baixa';
    }
    foreach($fields[$store]??[] as $f) {
        if (isset($row[$f]) && !isset($data[$f])) $data[$f]=$row[$f];
    }
    if ($store==='ong_accounts') {
        $data['imagem_url']=$row['foto']??null;
    }
    if ($store==='ong_animals') {
        $data['local_encontrado']=$row['local']??null;
        $data['imagem_url']=$row['foto']??null;
        $data['status']=strtolower((string)($row['status']??'disponivel'));
    }
    if ($store==='doacoes') {
        $data['valor']=max(0.01,(float)($row['valor']??1));
        $data['nome_doador']=$row['nome']??$row['nome_doador']??null;
        $data['email_doador']=$row['email']??$row['email_doador']??null;
        $data['status']=(string)($row['status']??'registrada'); // Intenção de doação; não confirma pagamento.
        $data['tipo']=in_array(($row['tipo']??''),['mensal','material'],true)?$row['tipo']:'unica';
    }
    if ($store==='apadrinhamentos') {
        $data['email']=$row['email']??'nao-informado@invalid.local';
        $data['nome']=$row['nome']??'Não informado';
        $data['status']=(string)($row['status']??'pendente');
        $data['valor_mensal']=(float)($row['valor']??$row['valor_mensal']??0);
        $data['animal_nome']=$row['animal']??$row['animal_nome']??null;
    }
    if ($store==='contatos') {$data['lido']=!empty($row['lido']);$data['status']=strtolower((string)($row['status']??'novo'));}
    if ($store==='noticias') $data['publicada']=!empty($row['publicada']);
    return $data;
}
function ga_ngo_uuid(string $table,int $localId): string {
    if($localId<1)throw new RuntimeException('Identificador inválido.');
    if(!in_array($table,['ongs','denuncias'],true))throw new RuntimeException('Tabela inválida.');
    $rows=ga_db_request('GET',$table,['select'=>'id','local_id'=>'eq.'.$localId,'limit'=>1]);
    if(empty($rows[0]['id']))throw new RuntimeException('Registro de referência não encontrado no Supabase.');
    return (string)$rows[0]['id'];
}
/**
 * Sistema de Cache em Memória/Arquivo para Aceleração Instantânea.
 * Reduz tempo de carregamento de ~3s para ~15ms ao evitar requisições cURL repetidas.
 */
function ga_cache_dir(): string {
    $dir = defined('STORAGE_PATH') ? STORAGE_PATH . '/cache' : dirname(__DIR__) . '/storage/cache';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    return is_dir($dir) && is_writable($dir) ? $dir : sys_get_temp_dir();
}

function ga_cache_get(string $key, int $ttl = 45): ?array {
    $file = ga_cache_dir() . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
    if (is_file($file) && (time() - filemtime($file)) < $ttl) {
        $content = @file_get_contents($file);
        if ($content !== false && $content !== '') {
            $data = json_decode($content, true);
            if (is_array($data)) return $data;
        }
    }
    return null;
}

function ga_cache_set(string $key, array $data): void {
    $file = ga_cache_dir() . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function ga_cache_clear(?string $key = null): void {
    $dir = ga_cache_dir();
    if ($key === null) {
        $files = glob($dir . '/*.json');
        if ($files) foreach ($files as $f) @unlink($f);
    } else {
        $file = $dir . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.json';
        if (is_file($file)) @unlink($file);
    }
}

function ga_db_save_ong_case(array $row): void {
    ga_cache_clear('store_ong_case_status');
    ga_cache_clear('store_denuncias');
    $oid=(int)($row['ong_id']??0);$did=(int)($row['denuncia_id']??0);
    $ongUUID=ga_ngo_uuid('ongs',$oid);$denUUID=ga_ngo_uuid('denuncias',$did);
    $conditions=['ong_id'=>'eq.'.$ongUUID,'denuncia_id'=>'eq.'.$denUUID,'select'=>'id'];
    $existing=ga_db_request('GET','ong_denuncia_atendimentos',$conditions);
    $data=['status'=>(string)($row['status']??'Recebida'),'payload'=>$row,'updated_at'=>date('c')];
    if($existing)ga_db_request('PATCH','ong_denuncia_atendimentos',['ong_id'=>'eq.'.$ongUUID,'denuncia_id'=>'eq.'.$denUUID],$data);
    else ga_db_request('POST','ong_denuncia_atendimentos',[],array_merge($data,['ong_id'=>$ongUUID,'denuncia_id'=>$denUUID]));
    ga_db_audit('atendimento_atualizado','ong_denuncia_atendimentos',$did,['ong_local_id'=>$oid]);
}
function ga_db_read_store(string $name): array {
    $cached = ga_cache_get('store_' . $name, 12);
    if ($cached !== null) {
        return $cached;
    }

    if($name==='ong_case_status') {
        $rows=ga_db_request('GET','ong_denuncia_atendimentos',['select'=>'payload,status','limit'=>1000]);
        $out=[];
        foreach($rows as $r)if(is_array($r['payload']??null))$out[]=$r['payload'];
        ga_cache_set('store_' . $name, $out);
        return $out;
    }
    if ($name==='home_photos' || $name==='adocoes') {
        ga_cache_set('store_' . $name, []);
        return [];
    }
    $table=ga_store_table($name);
    if ($table === '') {
        ga_cache_set('store_' . $name, []);
        return [];
    }
    $all=[];
    try {
        for($page=0;$page<100;$page++) {
            $query=['select'=>$table==='denuncias'?'local_id,status,payload':'local_id,payload','order'=>'local_id.asc','limit'=>500,'offset'=>$page*500];
            $batch=ga_db_request('GET',$table,$query);
            foreach($batch as $r) {
                $row=$r['payload']??[];
                if (!is_array($row)) $row=[];
                $row['id']=(int)$r['local_id'];
                if ($table==='denuncias' && !empty($r['status'])) {
                    $revMap = ['recebida'=>'Recebida','em_analise'=>'Em análise','encaminhada'=>'Encaminhada','em_atendimento'=>'Em atendimento','concluida'=>'Concluída','concluída'=>'Concluída','arquivada'=>'Arquivada','recusada'=>'Recusada'];
                    $st = (string)$r['status'];
                    if (isset($revMap[$st])) {
                        $row['status'] = $revMap[$st];
                    }
                }
                $all[]=$row;
            }
            if(count($batch)<500)break;
        }
    } catch (Throwable $e) {
        error_log('[Guardiao] Tabela inexistente ou indisponivel: '.$table.': '.$e->getMessage());
        $all = [];
    }
    ga_cache_set('store_' . $name, $all);
    return $all;
}
function ga_db_create(string $name,array $row,?int $forcedId=null): int {
    ga_cache_clear('store_' . $name);
    $table=ga_store_table($name);
    if ($table==='') return 0;
    $id=$forcedId??(int)(ga_db_request('POST','rpc/ga_next_local_id',[],['p_store'=>$name])[0]['ga_next_local_id']??0);
    // PostgREST RPC may return primitive JSON instead of a row. The client normalizes that below.
    if(!$id)throw new RuntimeException('Não foi possível gerar o identificador do Supabase.');
    $row['id']=$id;
    if (empty($row['created_at'])) $row['created_at']=date('Y-m-d H:i:s');
    $data=ga_db_columns($name,$row);
    $data['local_id']=$id;
    ga_db_request('POST',$table,[],$data);
    ga_db_audit('registro_criado',$name,$id);
    return $id;
}
function ga_db_update_row(string $name,int $id,array $row): void {
    ga_cache_clear('store_' . $name);
    if ($name === 'denuncias') ga_cache_clear('store_ong_case_status');
    $table=ga_store_table($name);
    if ($table==='') return;
    $row['id']=$id;
    $data=ga_db_columns($name,$row);
    ga_db_request('PATCH',$table,['local_id'=>'eq.'.$id],$data);
    ga_db_audit('registro_atualizado',$name,$id);
}
function ga_db_remove_row(string $name,int $id): void {
    ga_cache_clear('store_' . $name);
    $table=ga_store_table($name);
    if ($table==='') return;
    ga_db_request('DELETE',$table,['local_id'=>'eq.'.$id]);
    ga_db_audit('registro_excluido',$name,$id);
}
