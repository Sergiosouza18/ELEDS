<?php
session_start();
require __DIR__ . '/admin_config.php';
require_once __DIR__ . '/admin_lib.php';

$dataFile = __DIR__ . '/data/products.json';
$whatsappFile = __DIR__ . '/data/whatsapp.json';
$groupsFile = __DIR__ . '/data/groups.json';
$usersFile = __DIR__ . '/data/users.json';
$imageDir = __DIR__ . '/assets/products';
$userImageDir = __DIR__ . '/assets/users';
if (!is_dir($imageDir)) mkdir($imageDir, 0775, true);
if (!is_dir($userImageDir)) mkdir($userImageDir, 0775, true);

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function loadProducts($file) { return json_decode(file_get_contents($file), true) ?: []; }
function loadWhatsapps($file) { return json_decode(file_get_contents($file), true) ?: []; }
function loadUsers($file) {
    if (!file_exists($file)) {
        $users = [[
            'id' => uniqid('usr_', true),
            'user' => ADMIN_USER,
            'password' => password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => date('c')
        ]];
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $users;
    }
    $users = json_decode(file_get_contents($file), true);
    return is_array($users) ? $users : [];
}
function saveUsers($file, $users) {
    $json = json_encode(array_values($users), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return file_put_contents($file, $json, LOCK_EX) !== false;
}
function loadGroups($file, $products) {
    if (!file_exists($file)) {
        $names=[]; foreach($products as $p){$n=trim($p['category']??'Outros'); if($n!==''&&!in_array($n,$names,true))$names[]=$n;}
        $items=[]; foreach($names as $i=>$n)$items[]=['id'=>'grp_'.($i+1),'name'=>$n];
        file_put_contents($file,json_encode($items,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
        return $items;
    }
    $items=json_decode(file_get_contents($file),true); return is_array($items)?$items:[];
}
function saveGroups($file, $items) {
    $json=json_encode(array_values($items),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return file_put_contents($file,$json,LOCK_EX)!==false;
}
function saveWhatsapps($file, $items) {
    $json = json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return file_put_contents($file, $json, LOCK_EX) !== false;
}
function saveProducts($file, $products) {
    he_before_save($file, $products); // backup automático + histórico
    $json = json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return file_put_contents($file, $json, LOCK_EX) !== false;
}
function productImages($p) {
    $imgs = [];
    if (!empty($p['images']) && is_array($p['images'])) $imgs = $p['images'];
    if (!$imgs && !empty($p['image'])) $imgs = [$p['image']];
    return array_values(array_unique(array_filter($imgs)));
}
function uploadedImages($files) {
    $out=[]; if (!isset($files['tmp_name']) || !is_array($files['tmp_name'])) return $out;
    foreach($files['tmp_name'] as $i=>$tmp){ if(!is_uploaded_file($tmp)) continue; $ext=strtolower(pathinfo($files['name'][$i]??'',PATHINFO_EXTENSION)); if(in_array($ext,['jpg','jpeg','png','webp'],true)) $out[]=['tmp'=>$tmp,'ext'=>$ext]; }
    return $out;
}

function redirectAdmin($msg='') {
    header('Location: admin.php' . ($msg ? '?msg=' . urlencode($msg) : ''));
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    redirectAdmin('Você saiu do painel.');
}

$users = loadUsers($usersFile);

if (!isset($_SESSION['admin_ok'])) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = trim($_POST['user'] ?? '');
        $pass = $_POST['password'] ?? '';
        $authenticated = false;
        foreach ($users as $account) {
            if (strcasecmp((string)($account['user'] ?? ''), $user) === 0 && password_verify($pass, (string)($account['password'] ?? ''))) {
                $authenticated = true;
                $_SESSION['admin_ok'] = true;
                $_SESSION['admin_user'] = (string)$account['user'];
                $_SESSION['admin_photo'] = (string)($account['photo'] ?? '');
                $_SESSION['admin_role'] = (string)($account['role'] ?? (strcasecmp((string)($account['user'] ?? ''), ADMIN_USER) === 0 ? 'admin' : 'user'));
                break;
            }
        }
        // Compatibilidade: se o users.json ainda não tiver sido criado corretamente, aceita o acesso antigo.
        if (!$authenticated && hash_equals(ADMIN_USER, $user) && hash_equals(ADMIN_PASSWORD, $pass)) {
            $_SESSION['admin_ok'] = true;
            $_SESSION['admin_user'] = ADMIN_USER;
            $_SESSION['admin_role'] = 'admin';
            $foundLegacy = false;
            foreach ($users as &$account) {
                if (strcasecmp((string)($account['user'] ?? ''), ADMIN_USER) === 0) {
                    $account['password'] = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
                    $account['role'] = 'admin';
                    $foundLegacy = true;
                    break;
                }
            }
            unset($account);
            if (!$foundLegacy) {
                $users[] = ['id'=>uniqid('usr_', true), 'user'=>ADMIN_USER, 'password'=>password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT), 'role'=>'admin', 'created_at'=>date('c')];
            }
            saveUsers($usersFile, $users);
        }
        if ($authenticated || isset($_SESSION['admin_ok'])) redirectAdmin();
        $error = 'Usuário ou senha incorretos.';
    }
    ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin — ELEDS Linha Leve e Pesada</title><link rel="stylesheet" href="assets/css/style.css?v=20261002-layoutfix1"></head><body class="admin-body"><div class="admin-login"><div class="admin-logo"><img src="assets/eleds-logo.png" alt="ELEDS Linha Leve e Pesada"><span>Painel administrativo</span></div><h1>Painel administrativo</h1><p>Entre para cadastrar e editar produtos.</p><?php if($error): ?><div class="admin-alert error"><?= e($error) ?></div><?php endif; ?><form method="post"><label>Usuário<input name="user" autocomplete="username" required></label><label>Senha<input type="password" name="password" autocomplete="current-password" required></label><button class="admin-primary" type="submit">Entrar no painel</button></form><a class="back-link" href="index.php">← Voltar ao catálogo</a></div></body></html><?php exit;
}

$currentRole = (string)($_SESSION['admin_role'] ?? 'user');
$isAdministrator = ($currentRole === 'admin');

$products = loadProducts($dataFile);
$whatsapps = loadWhatsapps($whatsappFile);
$groups = loadGroups($groupsFile, $products);
$msg = $_GET['msg'] ?? '';
$editCode = trim($_GET['editar'] ?? '');
$editing = null;
foreach ($products as $p) if (($p['code'] ?? '') === $editCode) { $editing = $p; break; }
$dupMode = false;
if ($editing === null && ($dup = trim($_GET['duplicar'] ?? '')) !== '') foreach ($products as $p) if (($p['code'] ?? '') === $dup) { $editing = $p; $editing['code'] = $dup . '-COPIA'; $editing['images'] = []; $editing['image'] = ''; $dupMode = true; break; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $originalCode = trim($_POST['original_code'] ?? '');
        $code = trim($_POST['code'] ?? ''); $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'Outros') ?: 'Outros'; $brand = trim($_POST['brand'] ?? 'HE') ?: 'HE'; $application = trim($_POST['application'] ?? ''); $measures = trim($_POST['measures'] ?? '');
        $priceInput = trim((string)($_POST['price'] ?? ''));
        $priceNormalized = str_replace(',', '.', str_replace('.', '', preg_replace('/[^0-9,.]/', '', $priceInput)));
        if ($priceInput !== '' && (!preg_match('/^\d+(?:[.,]\d{1,2})?$/', $priceInput) || !is_numeric($priceNormalized))) redirectAdmin('Preço inválido. Use 125,90 ou deixe em branco.');
        $price = $priceInput === '' ? null : round((float)$priceNormalized, 2);
        if ($code === '' || $name === '') redirectAdmin('Código e nome são obrigatórios.');
        if (!empty($_POST['is_new'])) foreach ($products as $p) if (($p['code'] ?? '') === $code) redirectAdmin('Já existe um produto com este código.');
        $uploads = uploadedImages($_FILES['images'] ?? []); $found=false;
        foreach ($products as &$p) {
            if (($p['code'] ?? '') === $originalCode || ($originalCode === '' && ($p['code'] ?? '') === $code)) {
                $p['code']=$code; $p['name']=$name; $p['category']=$category; $p['brand']=$brand; $p['application']=$application; $p['measures']=$measures; $p['price']=$price;
                $imgs=productImages($p);
                foreach($uploads as $u){$base=preg_replace('/[^A-Za-z0-9_-]+/','_',$code);$fn=$base.'_'.substr(uniqid('',true),-8).'.'.$u['ext'];if(move_uploaded_file($u['tmp'],$imageDir.'/'.$fn))$imgs[]='assets/products/'.$fn;}
                $p['images']=array_values(array_unique($imgs)); $p['image']=$p['images'][0]??''; $found=true; break;
            }
        }
        unset($p);
        if(!$found){ foreach($products as $p) if(($p['code']??'')===$code) redirectAdmin('Já existe um produto com este código.'); $new=['code'=>$code,'name'=>$name,'category'=>$category,'brand'=>$brand,'application'=>$application,'measures'=>$measures,'price'=>$price,'image'=>'','images'=>[]]; foreach($uploads as $u){$base=preg_replace('/[^A-Za-z0-9_-]+/','_',$code);$fn=$base.'_'.substr(uniqid('',true),-8).'.'.$u['ext'];if(move_uploaded_file($u['tmp'],$imageDir.'/'.$fn))$new['images'][]='assets/products/'.$fn;} $new['image']=$new['images'][0]??''; $products[]=$new; }
        if (!saveProducts($dataFile, $products)) {
            redirectAdmin('Não foi possível salvar o produto. Verifique a permissão de escrita da pasta data.');
        }
        redirectAdmin('Produto salvo com sucesso.');
    }

    if ($action === 'delete_image') {
        $code = trim($_POST['code'] ?? '');
        $image = trim($_POST['image'] ?? '');

        if ($code === '' || $image === '') redirectAdmin('Imagem inválida.');

        $foundProduct = false;
        foreach ($products as &$p) {
            if (($p['code'] ?? '') !== $code) continue;
            $foundProduct = true;

            $imgs = productImages($p);
            $newImgs = [];

            foreach ($imgs as $img) {
                if ($img === $image) {
                    // Remove o arquivo físico somente se estiver dentro da pasta
                    // de imagens dos produtos.
                    $relative = ltrim(str_replace(['\\', '../'], ['/', ''], $img), '/');
                    $fullPath = __DIR__ . '/' . $relative;
                    $baseDir = realpath($imageDir);
                    $realFile = is_file($fullPath) ? realpath($fullPath) : false;

                    if ($realFile && $baseDir && strpos($realFile, $baseDir . DIRECTORY_SEPARATOR) === 0) {
                        @unlink($realFile);
                    }
                    continue;
                }
                $newImgs[] = $img;
            }

            $p['images'] = array_values(array_unique($newImgs));
            $p['image'] = $p['images'][0] ?? '';
            break;
        }
        unset($p);

        if (!$foundProduct) redirectAdmin('Produto não encontrado.');

        saveProducts($dataFile, $products);
        redirectAdmin('Imagem apagada com sucesso. Edite o produto para continuar.');
    }

    if ($action === 'delete') {
        $code = trim($_POST['code'] ?? '');
        $products = array_values(array_filter($products, fn($p) => ($p['code'] ?? '') !== $code));
        saveProducts($dataFile, $products);
        redirectAdmin('Produto excluído.');
    }

    if ($action === 'save_group') {
        $id=trim($_POST['group_id']??'');
        $name=trim($_POST['group_name']??'');
        if($name==='') redirectAdmin('Informe o nome do grupo.');
        $found=false; $oldName='';
        foreach($groups as &$g){
            if((string)($g['id']??'')===$id && $id!==''){
                $oldName=trim($g['name']??''); $g['name']=$name; $found=true; break;
            }
        }
        unset($g);
        if(!$found){
            foreach($groups as $g) if(strcasecmp(trim($g['name']??''),$name)===0) redirectAdmin('Já existe um grupo com esse nome.');
            $groups[]=['id'=>uniqid('grp_',true),'name'=>$name];
        } elseif($oldName!==$name){
            foreach($products as &$p) if(($p['category']??'')===$oldName) $p['category']=$name;
            unset($p); saveProducts($dataFile,$products);
        }
        saveGroups($groupsFile,$groups); redirectAdmin('Grupo salvo com sucesso.');
    }

    if ($action === 'delete_group') {
        $id=trim($_POST['group_id']??''); $target=null;
        foreach($groups as $g) if((string)($g['id']??'')===$id) {$target=$g; break;}
        if(!$target) redirectAdmin('Grupo não encontrado.');
        $used=false; foreach($products as $p) if(($p['category']??'')===($target['name']??'')) {$used=true; break;}
        if($used) redirectAdmin('Não é possível excluir este grupo porque existem produtos cadastrados nele.');
        $groups=array_values(array_filter($groups,fn($g)=>(string)($g['id']??'')!==$id));
        saveGroups($groupsFile,$groups); redirectAdmin('Grupo excluído.');
    }

    if ($action === 'save_whatsapp') {
        $id = trim($_POST['id'] ?? '');
        $name = trim($_POST['wa_name'] ?? '');
        $display = trim($_POST['wa_display'] ?? '');
        $number = preg_replace('/\D+/', '', $_POST['wa_number'] ?? '');
        if ($name === '' || $number === '') redirectAdmin('Nome e número do WhatsApp são obrigatórios.');
        if (strlen($number) < 10) redirectAdmin('Informe um número de WhatsApp válido com DDD.');
        $found = false;
        foreach ($whatsapps as &$w) {
            if ((string)($w['id'] ?? '') === $id && $id !== '') { $w['name']=$name; $w['display']=$display ?: $number; $w['number']=$number; $found=true; break; }
        }
        unset($w);
        if (!$found) { $whatsapps[] = ['id'=>uniqid('wa_', true),'name'=>$name,'display'=>$display ?: $number,'number'=>$number,'default'=>empty($whatsapps)]; }
        saveWhatsapps($whatsappFile, $whatsapps);
        redirectAdmin('WhatsApp salvo com sucesso.');
    }

    if ($action === 'set_default_whatsapp') {
        $id = trim($_POST['id'] ?? '');
        foreach ($whatsapps as &$w) $w['default'] = ((string)($w['id'] ?? '') === $id);
        unset($w);
        saveWhatsapps($whatsappFile, $whatsapps);
        redirectAdmin('WhatsApp principal atualizado.');
    }

    if ($action === 'delete_whatsapp') {
        $id = trim($_POST['id'] ?? '');
        $whatsapps = array_values(array_filter($whatsapps, fn($w) => (string)($w['id'] ?? '') !== $id));
        if (!empty($whatsapps) && !array_filter($whatsapps, fn($w) => !empty($w['default']))) $whatsapps[0]['default'] = true;
        saveWhatsapps($whatsappFile, $whatsapps);
        redirectAdmin('WhatsApp excluído.');
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $currentUser = (string)($_SESSION['admin_user'] ?? '');
        if ($new === '' || strlen($new) < 6) redirectAdmin('A nova senha deve ter pelo menos 6 caracteres.');
        if ($new !== $confirm) redirectAdmin('A confirmação da nova senha não confere.');
        $changed = false;
        foreach ($users as &$account) {
            if (strcasecmp((string)($account['user'] ?? ''), $currentUser) === 0) {
                if (!password_verify($current, (string)($account['password'] ?? ''))) redirectAdmin('A senha atual está incorreta.');
                $account['password'] = password_hash($new, PASSWORD_DEFAULT);
                $account['updated_at'] = date('c');
                $changed = true;
                break;
            }
        }
        unset($account);
        if (!$changed || !saveUsers($usersFile, $users)) redirectAdmin('Não foi possível alterar a senha.');
        redirectAdmin('Senha alterada com sucesso.');
    }

    if ($action === 'save_profile_photo') {
        $currentUser = (string)($_SESSION['admin_user'] ?? '');
        if (empty($_FILES['profile_photo']['tmp_name']) || !is_uploaded_file($_FILES['profile_photo']['tmp_name'])) redirectAdmin('Selecione uma foto.');
        $tmp = $_FILES['profile_photo']['tmp_name'];
        $info = @getimagesize($tmp);
        $mime = $info['mime'] ?? '';
        $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($extMap[$mime])) redirectAdmin('Use uma imagem JPG, PNG ou WEBP.');
        if (($_FILES['profile_photo']['size'] ?? 0) > 5 * 1024 * 1024) redirectAdmin('A foto deve ter no máximo 5 MB.');
        $changed = false;
        foreach ($users as &$account) {
            if (strcasecmp((string)($account['user'] ?? ''), $currentUser) !== 0) continue;
            if (!empty($account['photo'])) { $old = __DIR__ . '/' . ltrim((string)$account['photo'], '/'); if (is_file($old)) @unlink($old); }
            $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $currentUser);
            $fn = $safe . '_' . time() . '.' . $extMap[$mime];
            if (!move_uploaded_file($tmp, $userImageDir . '/' . $fn)) redirectAdmin('Não foi possível salvar a foto.');
            $account['photo'] = 'assets/users/' . $fn;
            $_SESSION['admin_photo'] = $account['photo'];
            $changed = true; break;
        }
        unset($account);
        if (!$changed || !saveUsers($usersFile, $users)) redirectAdmin('Não foi possível atualizar o perfil.');
        redirectAdmin('Foto do perfil atualizada com sucesso.');
    }

    if ($action === 'create_user') {
        if (!$isAdministrator) redirectAdmin('Acesso negado. Apenas administradores podem criar usuários.');
        $newUser = trim($_POST['new_user'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $newRole = (($_POST['new_role'] ?? 'user') === 'admin') ? 'admin' : 'user';
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $newUser)) redirectAdmin('O usuário deve ter de 3 a 40 caracteres e usar apenas letras, números, ponto, hífen ou underline.');
        if (strlen($newPassword) < 6) redirectAdmin('A senha deve ter pelo menos 6 caracteres.');
        if ($newPassword !== $confirmPassword) redirectAdmin('A confirmação da senha não confere.');
        foreach ($users as $account) {
            if (strcasecmp((string)($account['user'] ?? ''), $newUser) === 0) redirectAdmin('Esse usuário já existe.');
        }
        $users[] = [
            'id' => uniqid('usr_', true),
            'user' => $newUser,
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'role' => $newRole,
            'created_at' => date('c')
        ];
        if (!saveUsers($usersFile, $users)) redirectAdmin('Não foi possível criar o usuário. Verifique a permissão da pasta data.');
        redirectAdmin('Usuário criado com sucesso.');
    }

    if ($action === 'delete_user') {
        if (!$isAdministrator) redirectAdmin('Acesso negado. Apenas administradores podem excluir usuários.');
        $id = trim($_POST['id'] ?? '');
        $currentUser = (string)($_SESSION['admin_user'] ?? '');
        $target = null;
        foreach ($users as $account) if ((string)($account['id'] ?? '') === $id) { $target = $account; break; }
        if (!$target) redirectAdmin('Usuário não encontrado.');
        if (strcasecmp((string)($target['user'] ?? ''), ADMIN_USER) === 0) redirectAdmin('O administrador principal não pode ser excluído.');
        if (strcasecmp((string)($target['user'] ?? ''), $currentUser) === 0) redirectAdmin('Você não pode excluir o usuário que está conectado.');
        if (count($users) <= 1) redirectAdmin('O painel precisa ter pelo menos um usuário.');
        $users = array_values(array_filter($users, fn($account) => (string)($account['id'] ?? '') !== $id));
        if (!saveUsers($usersFile, $users)) redirectAdmin('Não foi possível excluir o usuário.');
        redirectAdmin('Usuário excluído.');
    }
}

usort($products, fn($a,$b) => strnatcasecmp($a['name'] ?? '', $b['name'] ?? ''));
$categories = [];
foreach ($groups as $g) { $n=trim($g['name']??''); if($n!=='') $categories[]=$n; }
foreach ($products as $p) { $n=trim($p['category']??'Outros'); if($n!==''&&!in_array($n,$categories,true)) $categories[]=$n; }
natcasesort($categories); $categories=array_values($categories);
$groupEditId=trim($_GET['editar_grupo']??''); $groupEditing=null; foreach($groups as $g) if((string)($g['id']??'')===$groupEditId){$groupEditing=$g;break;}
$waEditId = trim($_GET['editar_whatsapp'] ?? '');
$waEditing = null;
foreach ($whatsapps as $w) if ((string)($w['id'] ?? '') === $waEditId) { $waEditing = $w; break; }
$search = trim($_GET['q'] ?? '');
if ($search !== '') $products = array_values(array_filter($products, function($p) use($search) { return stripos(($p['code']??'').' '.($p['name']??'').' '.($p['category']??''), $search) !== false; }));
$perPage=(int)($_GET['por_pagina']??30); if(!in_array($perPage,[10,30,50,100],true))$perPage=30; $totalItems=count($products); $totalPages=max(1,(int)ceil($totalItems/$perPage)); $page=max(1,(int)($_GET['pagina']??1)); if($page>$totalPages)$page=$totalPages; $pageProducts=array_slice($products,($page-1)*$perPage,$perPage);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Painel — ELEDS Linha Leve e Pesada</title><link rel="stylesheet" href="assets/css/style.css?v=20261002-layoutfix1"></head><body class="admin-page">
<header class="admin-header"><div class="container admin-header-inner"><a href="index.php" class="logo logo-image" aria-label="ELEDS Linha Leve e Pesada"><img src="assets/eleds-logo.png" alt="ELEDS Linha Leve e Pesada"></a><div class="admin-actions"><span class="admin-user-chip"><?php if(strcasecmp((string)($_SESSION['admin_user'] ?? ''), 'admin') === 0): ?><img src="assets/eleds-aguia-avatar.png" alt="Águia ELEDS"><?php elseif(!empty($_SESSION['admin_photo'])): ?><img src="<?=e($_SESSION['admin_photo'])?>" alt="Foto"><?php else: ?>👤<?php endif; ?> <?=e($_SESSION['admin_user'] ?? ADMIN_USER)?></span><?php if ($isAdministrator): ?><a href="gestao_orcamentos.php">Gestão de orçamentos</a><a href="portal_admin.php">Clientes e propostas</a><?php endif; ?><a href="orcamentos.php">Meus orçamentos</a><a href="index.php">Ver catálogo</a><a href="admin.php?logout=1">Sair</a></div></div></header>
<main class="container admin-main">
<div class="admin-title"><div><span class="section-kicker">ADMINISTRAÇÃO</span><h1>Produtos</h1><p><?= count($products) ?> produto(s) exibido(s)</p></div><div class="admin-title-actions"><a class="admin-primary admin-pdf" href="catalogo_pdf.php?auto=1" target="_blank">📄 Catálogo em PDF</a><?php if ($isAdministrator): ?><a class="admin-primary" href="gestao_orcamentos.php">📊 Gestão de Orçamentos</a><a class="admin-primary" href="backup.php" onclick="return confirm('Baixar backup completo do projeto em ZIP?')">💾 Backup completo</a><?php endif; ?><a class="admin-primary admin-new" href="admin.php#form-produto">+ Novo produto</a></div></div>
<?php if($msg): ?><div class="admin-alert success"><?= e($msg) ?></div><?php endif; ?>
<section class="admin-grid">
<div class="admin-panel" id="form-produto"><h2><?= $dupMode ? 'Duplicar produto' : ($editing ? 'Editar produto' : 'Cadastrar produto') ?></h2><form method="post" enctype="multipart/form-data" class="admin-form"><input type="hidden" name="action" value="save"><input type="hidden" name="original_code" value="<?= $dupMode ? '' : e($editing['code'] ?? '') ?>"><?php if ($dupMode): ?><input type="hidden" name="is_new" value="1"><?php endif; ?><label>Código *<input name="code" required value="<?= e($editing['code'] ?? '') ?>" placeholder="Ex.: ALDP-41001"></label><label>Nome / descrição *<textarea name="name" required rows="3" placeholder="Descrição do produto"><?= e($editing['name'] ?? '') ?></textarea></label><label>Grupo / categoria *<select name="category" required><option value="">Selecione um grupo</option><?php foreach($categories as $cat): ?><option value="<?= e($cat) ?>" <?= (($editing['category'] ?? '')===$cat)?'selected':'' ?>><?= e($cat) ?></option><?php endforeach; ?></select></label><label>Marca<input name="brand" value="<?= e($editing['brand'] ?? 'HE') ?>"></label><label>Aplicação / compatibilidade<textarea name="application" rows="3" placeholder="Ex.: Volvo FH, MB Atego..."><?= e($editing['application'] ?? '') ?></textarea></label><label>Medidas <span class="admin-help">(opcional)</span><input name="measures" value="<?= e($editing['measures'] ?? '') ?>" placeholder="Ex.: A: 80MM L: 380MM P: 80MM"></label><label>Preço (R$) <span class="admin-help">(controle interno; não aparece no catálogo)</span><input name="price" inputmode="decimal" placeholder="Ex.: 125,90" value="<?= isset($editing['price']) && $editing['price'] !== null ? e(number_format((float)$editing['price'], 2, ',', '')) : '' ?>"></label><label>Imagens do produto <span class="admin-help">(selecione uma ou várias)</span><input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple><?php $editImgs=$editing?productImages($editing):[]; if($editImgs): ?><div class="admin-gallery admin-gallery-edit"><?php foreach($editImgs as $im): ?><div class="admin-image-item"><img class="admin-preview" src="<?=e($im)?>" alt=""><button type="button" class="admin-delete-image" data-delete-image-code="<?=e($editing['code'])?>" data-delete-image-path="<?=e($im)?>">Apagar imagem</button></div><?php endforeach; ?></div><small>As imagens atuais podem ser apagadas individualmente. Novas imagens serão adicionadas às atuais.</small><?php endif; ?></label><button class="admin-primary" type="submit">Salvar produto</button><?php if($editing): ?><a class="admin-cancel" href="admin.php">Cancelar edição</a><?php endif; ?></form></div>
<div class="admin-panel"><div class="admin-list-head"><h2>Produtos cadastrados</h2><form method="get" class="admin-search"><input name="q" value="<?= e($search) ?>" placeholder="Pesquisar produto..."><input type="hidden" name="por_pagina" value="<?=e($perPage)?>"><button>Buscar</button></form></div><div class="admin-page-controls"><form method="get"><input type="hidden" name="q" value="<?=e($search)?>"><label>Produtos por página <select name="por_pagina" onchange="this.form.submit()"><option value="10" <?=$perPage===10?'selected':''?>>10</option><option value="30" <?=$perPage===30?'selected':''?>>30</option><option value="50" <?=$perPage===50?'selected':''?>>50</option><option value="100" <?=$perPage===100?'selected':''?>>100</option></select></label></form><span>Página <?=$page?> de <?=$totalPages?></span></div><div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Foto</th><th>Código</th><th>Produto</th><th>Grupo</th><th>Preço (interno)</th><th>Ações</th></tr></thead><tbody><?php foreach($pageProducts as $p): $imgs=productImages($p); ?><tr><td><?php if($imgs): ?><div class="admin-mini-gallery"><?php foreach(array_slice($imgs,0,3) as $im): ?><img src="<?=e($im)?>" alt="" class="admin-thumb"><?php endforeach; ?></div><?php else:?><span class="admin-noimg">HE</span><?php endif;?></td><td><strong><?=e($p['code'])?></strong></td><td><?=e($p['name'])?><?php if(count($imgs)>1): ?><small class="image-count">📷 <?=count($imgs)?> imagens</small><?php endif;?></td><td><?=e($p['category']??'Outros')?></td><td><?= isset($p['price']) && $p['price'] !== null ? 'R$ '.e(number_format((float)$p['price'],2,',','.')) : '—' ?></td><td class="admin-row-actions"><a href="admin.php?editar=<?=urlencode($p['code'])?>#form-produto">Editar</a><a href="admin.php?duplicar=<?=urlencode($p['code'])?>#form-produto">Duplicar</a><form method="post" onsubmit="return confirm('Excluir este produto?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="code" value="<?=e($p['code'])?>"><button type="submit">Excluir</button></form></td></tr><?php endforeach;?></tbody></table></div><?php if($totalPages>1): ?><nav class="admin-pagination"><?php for($i=1;$i<=$totalPages;$i++): ?><a class="<?=$i===$page?'active':''?>" href="?q=<?=urlencode($search)?>&por_pagina=<?=$perPage?>&pagina=<?=$i?>"><?=$i?></a><?php endfor; ?></nav><?php endif; ?></div>
</section>
<section class="admin-panel" id="grupos">
<h2>📁 Cadastro de Grupos de Produtos</h2>
<p class="admin-help">Crie, edite e organize os grupos que aparecem no catálogo. Ao renomear um grupo, os produtos desse grupo são atualizados automaticamente.</p>
<form method="post" class="admin-form whatsapp-form">
<input type="hidden" name="action" value="save_group"><input type="hidden" name="group_id" value="<?=e($groupEditing['id'] ?? '')?>">
<label>Nome do grupo *<input name="group_name" required value="<?=e($groupEditing['name'] ?? '')?>" placeholder="Ex.: Lâmpadas"></label>
<button class="admin-primary" type="submit"><?= $groupEditing ? 'Salvar alteração' : 'Adicionar grupo' ?></button>
<?php if($groupEditing): ?><a class="admin-cancel" href="admin.php#grupos">Cancelar</a><?php endif; ?>
</form>
<div class="whatsapp-list">
<?php foreach($groups as $g): $groupCount=0; foreach($products as $p) if(($p['category']??'')===($g['name']??'')) $groupCount++; ?>
<div class="whatsapp-item"><div><strong>📁 <?=e($g['name']??'')?></strong><span><?= $groupCount ?> produto(s)</span></div><div class="admin-row-actions"><a href="admin.php?editar_grupo=<?=urlencode($g['id']??'')?>#grupos">Editar</a><?php if($groupCount===0): ?><form method="post" onsubmit="return confirm('Excluir este grupo?');"><input type="hidden" name="action" value="delete_group"><input type="hidden" name="group_id" value="<?=e($g['id']??'')?>"><button type="submit">Excluir</button></form><?php else: ?><small class="admin-help">Grupo em uso</small><?php endif; ?></div></div>
<?php endforeach; ?>
</div></section>
<section class="admin-panel whatsapp-admin" id="whatsapp">
<h2>📱 Números de WhatsApp</h2>
<p class="admin-help">Cadastre vários números para atendimento. O número marcado como <strong>principal</strong> será usado nos botões de orçamento dos produtos.</p>
<form method="post" class="admin-form whatsapp-form">
<input type="hidden" name="action" value="save_whatsapp"><input type="hidden" name="id" value="<?=e($waEditing['id'] ?? '')?>">
<label>Nome / setor *<input name="wa_name" required value="<?=e($waEditing['name'] ?? '')?>" placeholder="Ex.: Vendas, Loja, Atendimento"></label>
<label>Número do WhatsApp *<input name="wa_number" required value="<?=e($waEditing['number'] ?? '')?>" placeholder="Ex.: 5587981177326"></label>
<label>Como aparecerá no site<input name="wa_display" value="<?=e($waEditing['display'] ?? '')?>" placeholder="Ex.: (87) 98117-7326"></label>
<button class="admin-primary" type="submit"><?= $waEditing ? 'Salvar alteração' : 'Adicionar WhatsApp' ?></button>
<?php if($waEditing): ?><a class="admin-cancel" href="admin.php#whatsapp">Cancelar</a><?php endif; ?>
</form>
<div class="whatsapp-list">
<?php foreach($whatsapps as $w): ?>
<div class="whatsapp-item"><div><strong><?=e($w['name'] ?? '')?></strong><span><?=e($w['display'] ?? $w['number'] ?? '')?></span><?php if(!empty($w['default'])):?><em>Principal</em><?php endif;?></div><div class="admin-row-actions"><a href="admin.php?editar_whatsapp=<?=urlencode($w['id'] ?? '')?>#whatsapp">Editar</a><?php if(empty($w['default'])):?><form method="post"><input type="hidden" name="action" value="set_default_whatsapp"><input type="hidden" name="id" value="<?=e($w['id'] ?? '')?>"><button type="submit">Tornar principal</button></form><?php endif; ?><form method="post" onsubmit="return confirm('Excluir este número de WhatsApp?');"><input type="hidden" name="action" value="delete_whatsapp"><input type="hidden" name="id" value="<?=e($w['id'] ?? '')?>"><button type="submit">Excluir</button></form></div></div>
<?php endforeach; ?>
</div></section>
<section class="admin-panel" id="usuarios">
<h2>🔐 Usuários e senha</h2>
<p class="admin-help">Cada usuário possui seu próprio login, senha e perfil. Administradores podem gerenciar usuários; usuários/consultores não podem excluir ou criar outras contas.</p>
<div class="admin-grid" style="margin-top:18px">
<div class="admin-panel" style="margin:0">
<h3>Alterar minha senha</h3>
<form method="post" class="admin-form">
<input type="hidden" name="action" value="change_password">
<label>Senha atual *<input type="password" name="current_password" autocomplete="current-password" required></label>
<label>Nova senha *<input type="password" name="new_password" minlength="6" autocomplete="new-password" required><small>Mínimo de 6 caracteres.</small></label>
<label>Confirmar nova senha *<input type="password" name="confirm_password" minlength="6" autocomplete="new-password" required></label>
<button class="admin-primary" type="submit">Alterar senha</button>
</form>
</div>
<?php if ($isAdministrator): ?>
<div class="admin-panel" style="margin:0">
<h3>Criar novo usuário</h3>
<form method="post" class="admin-form">
<input type="hidden" name="action" value="create_user">
<label>Usuário *<input name="new_user" minlength="3" maxlength="40" pattern="[A-Za-z0-9._-]+" required placeholder="Ex.: joao.silva"></label>
<label>Perfil de acesso *<select name="new_role" required><option value="user">Usuário / Consultor</option><option value="admin">Administrador</option></select><small>Administradores podem criar e excluir usuários e realizar backup completo.</small></label>
<label>Senha *<input type="password" name="new_password" minlength="6" autocomplete="new-password" required></label>
<label>Confirmar senha *<input type="password" name="confirm_password" minlength="6" autocomplete="new-password" required></label>
<button class="admin-primary" type="submit">Criar usuário</button>
</form>
</div>
<?php endif; ?>
</div>
<div class="admin-panel" style="margin-top:18px"><h3>📷 Minha foto de perfil</h3><p class="admin-help">A foto aparecerá ao lado do seu usuário no painel e na página inicial do catálogo enquanto estiver logado.</p><form method="post" enctype="multipart/form-data" class="admin-form profile-photo-form"><input type="hidden" name="action" value="save_profile_photo"><label>Foto <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" required></label><button class="admin-primary" type="submit">Salvar minha foto</button></form></div>
<div class="whatsapp-list" style="margin-top:18px">
<h3>Usuários cadastrados</h3>
<?php foreach($users as $account): ?>
<?php $accountRole = (string)($account['role'] ?? (strcasecmp((string)($account['user'] ?? ''), ADMIN_USER)===0 ? 'admin' : 'user')); ?>
<div class="whatsapp-item"><div><strong>👤 <?=e($account['user'] ?? '')?></strong><span><?= !empty($account['created_at']) ? 'Criado em '.date('d/m/Y H:i', strtotime($account['created_at'])) : '' ?></span><span><b>Perfil:</b> <?= $accountRole==='admin' ? 'Administrador' : 'Usuário / Consultor' ?></span><?php if(strcasecmp((string)($account['user'] ?? ''), (string)($_SESSION['admin_user'] ?? ''))===0): ?><em>Você</em><?php endif; ?></div><div class="admin-row-actions"><?php if($isAdministrator && strcasecmp((string)($account['user'] ?? ''), (string)($_SESSION['admin_user'] ?? ''))!==0 && strcasecmp((string)($account['user'] ?? ''), ADMIN_USER)!==0): ?><form method="post" onsubmit="return confirm('Excluir este usuário?');"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?=e($account['id'] ?? '')?>"><button type="submit">Excluir</button></form><?php endif; ?></div></div>
<?php endforeach; ?>
</div>
</section>
<script>
document.querySelectorAll('[data-delete-image-code]').forEach(function(button) {
    button.addEventListener('click', function() {
        if (!confirm('Apagar esta imagem do produto? Esta ação não pode ser desfeita.')) return;

        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'admin.php';

        const fields = {
            action: 'delete_image',
            code: button.dataset.deleteImageCode,
            image: button.dataset.deleteImagePath
        };

        Object.keys(fields).forEach(function(name) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = fields[name];
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    });
});
</script>
</main></body></html>
