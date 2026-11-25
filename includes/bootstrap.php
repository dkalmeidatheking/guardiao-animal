<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    @ini_set('session.gc_maxlifetime', '604800');
    @ini_set('session.cookie_lifetime', '604800');
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 604800,
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
        'path' => '/'
    ]);
    @session_start();
}
require_once __DIR__.'/data.php';
// Compatibilidade com PHP que não tenha extensão mbstring habilitada.
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s): int { return iconv_strlen($s, 'UTF-8') ?: strlen($s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr(string $s,int $start,int $length): string { $val=iconv_substr($s,$start,$length,'UTF-8'); return $val===false?substr($s,$start,$length):$val; }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $s,string $encoding='UTF-8'): string { return strtolower($s); }
}

define('ROOT_PATH', dirname(__DIR__));
define('STORAGE_PATH', ROOT_PATH.'/storage');
require_once ROOT_PATH.'/config/supabase.php';
if (!is_dir(STORAGE_PATH)) @mkdir(STORAGE_PATH, 0775, true);
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function brand_logo_url(): string { return 'https://i.postimg.cc/jdQShqMz/Captura-de-tela-2026-10-27-081739-removebg-preview.png'; }
function base_url(string $path=''): string {
    static $base = null;
    if ($base === null) {
        $root=realpath(ROOT_PATH); $doc=realpath($_SERVER['DOCUMENT_ROOT'] ?? ''); $base='';
        if ($root && $doc && ($root===$doc || str_starts_with(str_replace('\\','/',$root),rtrim(str_replace('\\','/',$doc),'/').'/'))) {
            $base='/'.trim(str_replace('\\','/',substr($root,strlen($doc))),'/');
        }
    }
    return rtrim($base,'/').'/'.ltrim($path,'/');
}
// V36: nenhuma operação de dados operacionais usa arquivos JSON locais.
require_once __DIR__.'/supabase_server.php';
function read_store(string $name): array { return ga_db_read_store($name); }
function add_store(string $name,array $row): int { return ga_db_create($name,$row); }
function update_store_row(string $name,int $id,callable $update): bool {
    $items=read_store($name);
    foreach($items as $row) if((int)($row['id']??0)===$id) {
        $update($row);ga_db_update_row($name,$id,$row);return true;
    }
    return false;
}
function mutate_store(string $name,callable $action) {
    if($name==='ong_case_status') {
        $old=read_store($name);$rows=$old;$result=$action($rows);
        $index=[];
        foreach($old as $r)$index[((int)($r['ong_id']??0)).':'.((int)($r['denuncia_id']??0))]=$r;
        foreach($rows as $r) {
            if(!is_array($r))continue;
            $key=((int)($r['ong_id']??0)).':'.((int)($r['denuncia_id']??0));
            if(!isset($index[$key])||$index[$key]!==$r)ga_db_save_ong_case($r);
        }
        return $result;
    }
    if ($name==='home_photos') {
        $before=read_store('home_photos');$after=$before;$result=$action($after);
        ga_cache_clear('store_home_photos');
        return $result;
    }
    $before=read_store($name);$after=$before;$result=$action($after);
    $old=[];$new=[];
    foreach($before as $r) $old[(int)$r['id']]=$r;
    foreach($after as $r) {
        $id=(int)($r['id']??0);
        if($id>0)$new[$id]=$r;
        else ga_db_create($name,$r);
    }
    foreach($new as $id=>$r) {
        if(!isset($old[$id])) ga_db_create($name,$r,$id);
        elseif($r!==$old[$id]) ga_db_update_row($name,$id,$r);
    }
    foreach($old as $id=>$r) if(!isset($new[$id])) ga_db_remove_row($name,$id);
    return $result;
}
function csrf(): string {
    $salt = 'guardiao_animal_tcc_senai_csrf_salt_2026';
    $tw = (int)floor(time() / 43200);
    $token = hash_hmac('sha256', 'ga_form_' . $tw, $salt);
    $_SESSION['csrf'] = $token;
    return $token;
}
function ga_csrf_valid(?string $token): bool {
    if (!$token || !is_string($token)) return false;
    if (!empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token)) return true;
    $salt = 'guardiao_animal_tcc_senai_csrf_salt_2026';
    $currentTw = (int)floor(time() / 43200);
    foreach ([$currentTw, $currentTw - 1, $currentTw - 2, $currentTw - 3] as $tw) {
        if (hash_equals(hash_hmac('sha256', 'ga_form_' . $tw, $salt), $token)) return true;
    }
    return false;
}
function php_ini_size_bytes(string $size): int {
    $size=trim($size);
    if ($size === '') return 0;
    $value=(float)$size;
    $unit=strtolower(substr($size,-1));
    if ($unit==='g') $value*=1024*1024*1024;
    elseif ($unit==='m') $value*=1024*1024;
    elseif ($unit==='k') $value*=1024;
    return max(0,(int)$value);
}
function check_csrf(): void {
    $token = $_POST['csrf'] ?? null;
    if (!is_string($token) || !ga_csrf_valid($token)) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $isSameOrigin = ($origin !== '' && $host !== '' && str_contains($origin, $host)) ||
                        ($referer !== '' && $host !== '' && str_contains($referer, $host));
        if ($isSameOrigin && !empty($_POST)) {
            return;
        }
        $postMax = php_ini_size_bytes((string)ini_get('post_max_size'));
        $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($postMax > 0 && $contentLength > $postMax) {
            http_response_code(413);
            exit('O arquivo enviado ultrapassou o limite de tamanho permitido. Envie um arquivo menor.');
        }
        http_response_code(419);
        flash('bad', 'Sua sessão foi renovada por segurança. Por favor, envie novamente.');
        $back = $_SERVER['HTTP_REFERER'] ?? base_url('index.php');
        header('Location: ' . $back);
        exit;
    }
}
function flash(string $type,string $msg): void { $_SESSION['flash']=['type'=>$type,'msg'=>$msg]; }
function take_flash(): ?array { $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function redirect(string $to): never { header('Location: '.base_url($to)); exit; }
function current_role(): string { return (string)($_SESSION['role']??''); }
function current_admin_account(): ?array {
    if (current_role() !== 'admin') return null;
    $id=(int)($_SESSION['admin_id']??0);
    foreach (read_store('admins') as $u) {
        if ((int)($u['id']??0)===$id && ($u['status']??'Ativo')==='Ativo') return $u;
    }
    return null;
}
function is_admin(): bool { return current_admin_account() !== null; }
function is_principal_admin(): bool {
    $u=current_admin_account();
    if (!$u) return false;
    // Compatibilidade: o primeiro administrador criado nas versões anteriores é o principal.
    $users=read_store('admins');
    $first=$users[0]??null;
    return ($u['tipo']??'')==='principal' || (
        $first && (int)$u['id']===(int)($first['id']??0)
        && !array_filter($users,static fn($r)=>($r['tipo']??'')==='principal')
    );
}
function require_principal_admin(): void { require_admin(); if (!is_principal_admin()) { http_response_code(403); exit('Somente o administrador principal pode autorizar acessos.'); } }
function require_admin(): void { if (!is_admin()) redirect('admin/login.php'); }
function is_ong(): bool { return current_role()==='ong' && (int)($_SESSION['ong_id']??0)>0; }
function require_ong(): void { if (!is_ong()) redirect('portal/login.php'); }
function current_ong(): ?array {
    if (!is_ong()) return null;
    foreach (read_store('ong_accounts') as $o) if ((int)($o['id']??0)===(int)$_SESSION['ong_id'] && ($o['status']??'')==='Aprovada') return $o;
    return null;
}
/** Mapeia o ID público de uma ONG para sua conta real aprovada, sem conceder acesso a entidades demonstrativas. */
function approved_ong_account_for_public_id(int $publicId): ?array {
    global $ongs;
    if (!isset($ongs[$publicId])) return null;
    foreach (read_store('ong_accounts') as $account) {
        if (($account['status'] ?? '') !== 'Aprovada') continue;
        if ((int)($account['public_ong_id'] ?? 0) === $publicId) return $account;
        if ($publicId === 1000 + (int)($account['id'] ?? 0) && empty($account['public_ong_id'])) return $account;
        // Um registro ilustrativo só pode ser conectado se o e-mail da conta aprovada for idêntico.
        $publicEmail = strtolower(trim((string)($ongs[$publicId]['email'] ?? '')));
        if ($publicEmail !== '' && $publicEmail === strtolower(trim((string)($account['email'] ?? '')))) return $account;
    }
    return null;
}
/** Direcionamento automático somente a uma ONG já aprovada que atue na mesma cidade. */
function approved_ong_for_report_city(string $city): ?array {
    $eligible=[];
    $key = static fn(string $v): string => strtolower(iconv('UTF-8','ASCII//TRANSLIT//IGNORE', $v) ?: $v);
    $search = trim(explode(',', $key($city))[0]);
    foreach (read_store('ong_accounts') as $account) {
        if (($account['status'] ?? '') !== 'Aprovada') continue;
        $cityAccount=$key((string)($account['cidade'] ?? ''));
        if ($search !== '' && str_contains($cityAccount,$search)) $eligible[]=$account;
    }
    if (!$eligible) return null;
    $open=[];
    foreach (read_store('denuncias') as $r) {
        $id=(int)($r['ong_id']??0);
        if ($id && !in_array(($r['status'] ?? ''),['Concluída','Arquivada'],true)) $open[$id]=($open[$id]??0)+1;
    }
    usort($eligible, static fn($a,$b)=> (($open[(int)$a['id']]??0)<=>($open[(int)$b['id']]??0)) ?: ((int)$a['id']<=>(int)$b['id']));
    return $eligible[0];
}
function find_ong(int $id): ?array { global $ongs; return $ongs[$id]??null; }
function find_animal(int $id): ?array { global $animals; return $animals[$id]??null; }
function admin_configured(): bool { return count(read_store('admins'))>0; }
function form_value(string $name,int $max=200): string { return mb_substr(trim((string)($_POST[$name]??'')),0,$max); }
function safe_photo_url(string $url): string {
    $url=trim($url);
    return filter_var($url,FILTER_VALIDATE_URL) && parse_url($url,PHP_URL_SCHEME)==='https' ? $url : '';
}
function save_uploaded_photo(string $field): ?string {
    if (empty($_FILES[$field]) || ($_FILES[$field]['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    $f=$_FILES[$field];
    $error=(int)($f['error']??UPLOAD_ERR_NO_FILE);
    if (in_array($error,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)) {
        throw new RuntimeException('A imagem excedeu o limite de upload configurado no servidor (atual: '.ini_get('upload_max_filesize').'). Ajuste o PHP para aceitar arquivos de até 5 MB ou envie uma foto menor.');
    }
    if ($error===UPLOAD_ERR_PARTIAL) throw new RuntimeException('O envio da imagem foi interrompido. Tente novamente.');
    if (in_array($error,[UPLOAD_ERR_NO_TMP_DIR,UPLOAD_ERR_CANT_WRITE],true)) throw new RuntimeException('O servidor não conseguiu armazenar a imagem. Verifique as permissões e o espaço em disco.');
    if ($error!==UPLOAD_ERR_OK) throw new RuntimeException('Não foi possível receber o arquivo de imagem. Tente novamente.');
    if (($f['size']??0)>5*1024*1024) throw new RuntimeException('A imagem ultrapassa 5 MB. Compacte o arquivo antes de enviar.');
    if (($f['size']??0)<100) throw new RuntimeException('Imagem inválida. Envie um arquivo JPG, PNG ou WebP válido.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($types[$mime]) || !getimagesize($f['tmp_name'])) throw new RuntimeException('Envie uma foto JPG, PNG ou WebP.');
    // V36: arquivo armazenado no Supabase Storage, não em assets/uploads local.
    $key=ga_db_key();
    $filename=bin2hex(random_bytes(16)).'.'.$types[$mime];
    $object='site/'.$filename;
    $url=rtrim(SUPABASE_URL,'/').'/storage/v1/object/guardiao-fotos/'.$object;
    $bytes=file_get_contents($f['tmp_name']);
    if ($bytes===false) throw new RuntimeException('Não foi possível ler a imagem.');
    $headers=['apikey: '.$key,'Content-Type: '.$mime,'x-upsert: false'];
    if(!function_exists('curl_init'))throw new RuntimeException('A hospedagem precisa habilitar cURL para enviar imagens.');
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$bytes,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true]);
    $out=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($status<200||$status>=300)throw new RuntimeException('Não foi possível armazenar a imagem no Supabase Storage (HTTP '.$status.').');
    return rtrim(SUPABASE_URL,'/').'/storage/v1/object/public/guardiao-fotos/'.$object;
}
// V36: ONGs e administradores vêm exclusivamente do Supabase.
// Não provisionar contas de demonstração, senhas públicas ou JSON local.
// Catálogo público gerado somente a partir dos cadastros existentes no Supabase.
$ongs=[];
$animals=[];
$news=[];
try {
    foreach (read_store('noticias') as $notice) {
        if (isset($notice['publicada']) && !$notice['publicada']) continue;
        $id=(int)$notice['id'];
        $news[$id]=[
          'titulo'=>$notice['titulo']??'Notícia',
          'categoria'=>$notice['categoria']??'Proteção animal',
          'tempo'=>$notice['tempo']??'Leitura rápida',
          'resumo'=>$notice['resumo']??'',
          'intro'=>$notice['intro']??($notice['conteudo']??''),
          'destaque'=>$notice['destaque']??'Informação sobre proteção animal.',
          'secoes'=>$notice['secoes']??[],
          'foto'=>$notice['foto']??$notice['imagem_url']??''
        ];
    }
} catch (Throwable $e) {
    $news=[];
}
$publicAccountIds=[];
foreach (read_store('ong_accounts') as $account) {
    if (($account['status']??'')!=='Aprovada')continue;
    $publicId=(int)($account['public_ong_id']??0);
    $id=$publicId>0?$publicId:1000+(int)$account['id'];
    $publicAccountIds[(int)$account['id']]=$id;
    $ongs[$id]=[
        'nome'=>$account['nome']??'Organização','cidade'=>$account['cidade']??'Ourinhos',
        'bairro'=>$account['bairro']??'', 'ano'=>(int)($account['ano']??date('Y')),
        'foco'=>$account['foco']??'Proteção animal','voluntarios'=>(int)($account['voluntarios']??0),
        'animais'=>0,'descricao'=>$account['descricao']??'', 'historia'=>$account['historia']??'',
        'foto'=>$account['foto']??$account['imagem_url']??'',
        'contato'=>$account['telefone']??'',
        'email'=>($account['email_publico']??'')
    ];
}
foreach (read_store('ong_animals') as $animal) {
    $aid=1000+(int)$animal['id'];$oid=$publicAccountIds[(int)$animal['ong_id']] ?? 0;
    if (!isset($ongs[$oid])) continue;
    $animals[$aid]=[
        'nome'=>$animal['nome'],'especie'=>$animal['especie'],'sexo'=>$animal['sexo'],'idade'=>$animal['idade'],
        'porte'=>$animal['porte'],'ong'=>$oid,'local'=>$animal['local'],'data'=>date('d/m/Y',strtotime($animal['created_at']??'now')),
        'status'=>$animal['status'],'temperamento'=>$animal['temperamento'],'historia'=>$animal['historia'],
        'foto'=>$animal['foto']?:'https://images.unsplash.com/photo-1552053831-71594a27632d?w=1000&auto=format&fit=crop'
    ];
    $ongs[$oid]['animais']++;
}
