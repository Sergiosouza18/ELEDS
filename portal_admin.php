<?php
session_start();
require __DIR__.'/portal_lib.php';
if(!ep_admin()){header('Location: admin.php');exit;}

$clients=ep_read('clientes');
$quotes=ep_read('orcamentos');
$error='';

/* ---------- utilidades de URL / paginação ---------- */
function pa_build($q,$anchor=''){
    return 'portal_admin.php'.($q?'?'.http_build_query($q):'').($anchor!==''?'#'.$anchor:'');
}
function pa_url($set=[],$anchor=''){
    $q=[];
    foreach(['cp','busca','st','op','qb','de','ate'] as $k){
        if(isset($_GET[$k]) && is_string($_GET[$k]) && $_GET[$k]!=='') $q[$k]=$_GET[$k];
    }
    foreach($set as $k=>$v){
        if($v===null || $v==='') unset($q[$k]); else $q[$k]=$v;
    }
    return pa_build($q,$anchor);
}
function pa_back($ok,$anchor=''){
    $b=[];
    parse_str((string)($_POST['back']??''),$b);
    $q=[];
    foreach(['cp','busca','st','op','qb','de','ate'] as $k){
        if(isset($b[$k]) && is_string($b[$k]) && $b[$k]!=='') $q[$k]=$b[$k];
    }
    $q['ok']=$ok;
    return pa_build($q,$anchor);
}
function pa_pager($total,$per,$page,$key,$anchor){
    $pages=max(1,(int)ceil($total/$per));
    if($pages<2) return '';
    $link=function($p,$label,$cls='') use($key,$anchor){
        return '<a class="'.$cls.'" href="'.ep_h(pa_url([$key=>($p>1?$p:null)],$anchor)).'">'.$label.'</a>';
    };
    $h='<nav class="admin-pagination" aria-label="Paginação">';
    if($page>1) $h.=$link($page-1,'‹ Anterior');
    $from=max(1,$page-2); $to=min($pages,$page+2);
    if($from>1){ $h.=$link(1,'1'); if($from>2) $h.='<span class="pa-dots">…</span>'; }
    for($p=$from;$p<=$to;$p++) $h.=$link($p,(string)$p,$p===$page?'active':'');
    if($to<$pages){ if($to<$pages-1) $h.='<span class="pa-dots">…</span>'; $h.=$link($pages,(string)$pages); }
    if($page<$pages) $h.=$link($page+1,'Próxima ›');
    return $h.'</nav>';
}

/* ---------- ações (POST) ---------- */
if($_SERVER['REQUEST_METHOD']==='POST'){
    ep_check();
    $action=$_POST['action']??'';

    if($action==='client'){
        $user=trim((string)($_POST['user']??''));
        $pass=(string)($_POST['password']??'');
        $duplicate=false;
        foreach($clients as $c) if(strcasecmp((string)($c['user']??''),$user)===0) $duplicate=true;
        if(!$user || strlen($pass)<8 || $duplicate){
            $error='Informe usuário único e senha com no mínimo 8 caracteres.';
        }else{
            $clients[]=[
                'id'=>'cust_'.bin2hex(random_bytes(8)),
                'user'=>$user,
                'password'=>password_hash($pass,PASSWORD_DEFAULT),
                'name'=>trim((string)($_POST['name']??'')),
                'document'=>trim((string)($_POST['document']??'')),
                'phone'=>trim((string)($_POST['phone']??'')),
                'email'=>trim((string)($_POST['email']??'')),
                'active'=>true
            ];
            ep_write('clientes',$clients);
            header('Location: '.pa_build(['ok'=>'cliente'],'clientes'));exit;
        }
    }

    if($action==='toggle'){
        foreach($clients as &$c) if(($c['id']??'')===($_POST['id']??'')) $c['active']=empty($c['active']);
        unset($c);
        ep_write('clientes',$clients);
        header('Location: '.pa_back('toggle','clientes'));exit;
    }

    if($action==='release'){
        $id=(string)($_POST['id']??'');
        foreach($quotes as &$q){
            if(($q['id']??'')!==$id || empty($q['client_id']) || !in_array($q['status']??'',['aguardando_analise','proposta_enviada'],true)) continue;
            foreach($q['items'] as $k=>&$item) $item['discount']=max(0,min(100,(float)($_POST['item_discount'][$k]??0)));
            unset($item);
            $q['discount']=max(0,min(100,(float)($_POST['discount']??0)));
            $q['status']='proposta_enviada';
            $q['updated_at']=date('c');
            $q['history'][]=['at'=>date('c'),'user'=>$_SESSION['admin_user'],'event'=>'released'];
            break;
        }
        unset($q);
        ep_write('orcamentos',$quotes);
        header('Location: '.pa_back('release','orc-'.preg_replace('/[^a-zA-Z0-9_-]/','',$id)));exit;
    }

    if($action==='cancel' || $action==='reopen'){
        $id=(string)($_POST['id']??'');
        foreach($quotes as &$q){
            if(($q['id']??'')!==$id || empty($q['client_id'])) continue;
            $cur=$q['status']??'aguardando_analise';
            if($action==='cancel' && in_array($cur,['aguardando_analise','proposta_enviada'],true)){
                $q['status']='cancelado';
                $q['cancel_reason']=mb_substr(trim((string)($_POST['reason']??'')),0,300);
                $q['updated_at']=date('c');
                $q['history'][]=['at'=>date('c'),'user'=>$_SESSION['admin_user'],'event'=>'cancelled'];
            }elseif($action==='reopen' && $cur==='cancelado'){
                $q['status']='aguardando_analise';
                unset($q['cancel_reason']);
                $q['updated_at']=date('c');
                $q['history'][]=['at'=>date('c'),'user'=>$_SESSION['admin_user'],'event'=>'reopened'];
            }
            break;
        }
        unset($q);
        ep_write('orcamentos',$quotes);
        header('Location: '.pa_back($action,'orc-'.preg_replace('/[^a-zA-Z0-9_-]/','',$id)));exit;
    }
}

/* ---------- dados para a tela ---------- */
$okMsgs=['cliente'=>'Cliente cadastrado com sucesso.','toggle'=>'Status do cliente atualizado.','release'=>'Proposta liberada para o cliente.','cancel'=>'Proposta reprovada. O cliente verá o status "Proposta reprovada".','reopen'=>'Orçamento reaberto para análise.'];
$okKey=(string)($_GET['ok']??'');
$okText=$okMsgs[$okKey]??'';

// clientes: busca + paginação
$busca=trim((string)($_GET['busca']??''));
$clientsAll=array_values($clients);
if($busca!==''){
    $clientsAll=array_values(array_filter($clientsAll,function($c) use($busca){
        $hay=($c['name']??'').' '.($c['user']??'').' '.($c['email']??'').' '.($c['phone']??'').' '.($c['document']??'');
        return mb_stripos($hay,$busca)!==false;
    }));
}
$perClients=8;
$totalClients=count($clientsAll);
$pageClients=max(1,min(max(1,(int)ceil($totalClients/$perClients)),(int)($_GET['cp']??1)));
$clientsPage=array_slice($clientsAll,($pageClients-1)*$perClients,$perClients);
$quoteCount=[];
foreach($quotes as $q) if(!empty($q['client_id'])) $quoteCount[$q['client_id']]=($quoteCount[$q['client_id']]??0)+1;

// orçamentos: filtro por status + paginação
$allQuotes=array_values(array_filter(array_reverse($quotes),fn($q)=>!empty($q['client_id'])));
$qb=trim((string)($_GET['qb']??'')); $de=(string)($_GET['de']??''); $ate=(string)($_GET['ate']??'');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$de)) $de='';
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$ate)) $ate='';
if($de!=='' && $ate!=='' && $de>$ate){ $tmp=$de; $de=$ate; $ate=$tmp; }
$filtering=($qb!=='' || $de!=='' || $ate!=='');
if($filtering){
    $allQuotes=array_values(array_filter($allQuotes,function($q) use($qb,$de,$ate){
        if($de!=='' || $ate!==''){
            $d=substr((string)($q['created_at']??''),0,10);
            if($d==='') return false;
            if($de!=='' && $d<$de) return false;
            if($ate!=='' && $d>$ate) return false;
        }
        if($qb!==''){
            $c=$q['customer']??[];
            $hay=($q['number']??'').' '.($c['name']??'').' '.($q['user']??'').' '.($c['document']??'');
            foreach(($q['items']??[]) as $i) $hay.=' '.($i['code']??'').' '.($i['name']??'');
            if(mb_stripos($hay,$qb)===false) return false;
        }
        return true;
    }));
}
$statusLabels=['aguardando_analise'=>['Aguardando análise','wait'],'proposta_enviada'=>['Proposta enviada','ok'],'cancelado'=>['Cancelado','no']];
$counts=['aguardando_analise'=>0,'proposta_enviada'=>0,'cancelado'=>0];
foreach($allQuotes as $q){$s=$q['status']??'aguardando_analise';if(!isset($counts[$s]))$s='aguardando_analise';$counts[$s]++;}
$stFilter=(string)($_GET['st']??'');
if(!isset($counts[$stFilter])) $stFilter='';
$filteredQuotes=$allQuotes;
if($stFilter!==''){
    $filteredQuotes=array_values(array_filter($allQuotes,function($q) use($stFilter){
        $s=$q['status']??'aguardando_analise'; if(!isset($GLOBALS['counts'][$s])) $s='aguardando_analise'; return $s===$stFilter;
    }));
}
$perQuotes=8;
$totalQuotes=count($filteredQuotes);
$pageQuotes=max(1,min(max(1,(int)ceil($totalQuotes/$perQuotes)),(int)($_GET['op']??1)));
$quotesPage=array_slice($filteredQuotes,($pageQuotes-1)*$perQuotes,$perQuotes);
$backQuery=http_build_query(array_filter(['cp'=>$_GET['cp']??'','busca'=>$_GET['busca']??'','st'=>$_GET['st']??'','op'=>$_GET['op']??'','qb'=>$_GET['qb']??'','de'=>$_GET['de']??'','ate'=>$_GET['ate']??''],fn($v)=>is_string($v)&&$v!==''));
$paUser=(string)($_SESSION['admin_user']??'admin');
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Clientes e propostas — ELEDS Linha Leve e Pesada</title><link rel="stylesheet" href="assets/css/style.css?v=20261002-layoutfix1"><style>
body.admin-page{background:#eef3f7}
.admin-actions a.cur{box-shadow:inset 0 -3px #e72435}
.pa-panel{margin-bottom:22px}
.pa-panel h2{font-size:19px;margin:0 0 4px}
.pa-sub{color:#708198;font-size:13px;margin:0 0 18px}
.pa-form{display:grid;grid-template-columns:repeat(3,1fr);gap:0 16px}
.pa-form label small{font-weight:400;color:#718096}
.pa-form-actions{grid-column:1/-1;display:flex;justify-content:flex-end}
.pa-bar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px}
.pa-search{display:flex;gap:6px}
.pa-search input{width:240px;box-sizing:border-box;border:1px solid #ccd7e1;border-radius:7px;padding:10px 12px;font:inherit;font-size:13px}
.pa-search button,.pa-tool{border:0;background:#17364d;color:#fff;border-radius:7px;padding:0 14px;font-weight:800;cursor:pointer;font-size:12px;min-height:38px}
.pa-search a{align-self:center;font-size:12px;color:#0879e8}
.pa-table{width:100%;border-collapse:collapse;font-size:13px}
.pa-table th,.pa-table td{text-align:left;padding:11px 10px;border-bottom:1px solid #edf1f5;vertical-align:middle}
.pa-table th{color:#687b8e;font-size:11px;text-transform:uppercase;letter-spacing:.4px}
.pa-table .r{text-align:right}
.pa-badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:800;white-space:nowrap}
.pa-badge.wait{background:#fff4dc;color:#8a5a00}
.pa-badge.ok{background:#e7f8ee;color:#11733b}
.pa-badge.no{background:#fdecec;color:#9b2626}
.pa-btn{border:0;border-radius:6px;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer;background:#e8f2fb;color:#0868c8}
.pa-btn.danger{background:#fdecec;color:#a62b2b}
.pa-scroll{overflow-x:auto}
.admin-pagination .pa-dots{padding:7px 4px;color:#8fa1b3}
.admin-pagination a.active{background:#e72435;border-color:#e72435;color:#fff}
.pa-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.pa-tabs a{display:inline-flex;gap:8px;align-items:center;padding:8px 14px;border:1px solid #dce5ee;border-radius:20px;background:#fff;color:#33475b;font-size:12px;font-weight:800;text-decoration:none}
.pa-tabs a b{background:#eef3f7;border-radius:10px;padding:1px 8px;font-size:11px}
.pa-tabs a.on{background:#e72435;border-color:#e72435;color:#fff}
.pa-tabs a.on b{background:#ffffff30;color:#fff}
.pa-q{border:1px solid #dfe6ed;border-radius:10px;background:#fff;margin-bottom:10px;overflow:hidden}
.pa-q[open]{box-shadow:0 6px 20px #18344b14;border-color:#c9d6e2}
.pa-q>summary{list-style:none;cursor:pointer;display:flex;align-items:center;gap:14px;padding:14px 16px}
.pa-q>summary::-webkit-details-marker{display:none}
.pa-q>summary:hover{background:#f7fafc}
.pa-arrow{flex:0 0 30px;height:30px;border-radius:50%;background:#fdecee;color:#e72435;display:grid;place-items:center;font-size:14px;transition:transform .2s}
.pa-q[open]>summary .pa-arrow{transform:rotate(90deg);background:#e72435;color:#fff}
.pa-q-main{display:flex;flex-direction:column;min-width:0;flex:1}
.pa-q-main strong{font-size:14px}
.pa-q-main small{color:#708198;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pa-q-meta{color:#708198;font-size:12px;white-space:nowrap}
.pa-q-total{font-weight:900;color:#087a43;white-space:nowrap}
.pa-q-body{padding:4px 18px 18px;border-top:1px solid #edf1f5}
.pa-cust{display:flex;flex-wrap:wrap;gap:6px 22px;color:#516478;font-size:12px;margin:14px 0}
.pa-cust b{color:#23384d}
.pa-q-body input[type=number]{width:90px;box-sizing:border-box;border:1px solid #ccd7e1;border-radius:7px;padding:8px;font:inherit;font-size:13px}
.pa-sum{display:flex;justify-content:flex-end;align-items:center;gap:22px;flex-wrap:wrap;margin-top:14px}
.pa-sum label{font-size:12px;font-weight:800;color:#31465a;display:flex;align-items:center;gap:8px}
.pa-sum .pa-total{font-size:22px;color:#087a43;font-weight:900}
.pa-q-actions{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px}
.pa-q-actions small{color:#708198}
.pa-cancel{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-top:14px;padding-top:14px;border-top:1px dashed #dfe6ed}
.pa-cancel input[type=text]{flex:1;min-width:220px;box-sizing:border-box;border:1px solid #ccd7e1;border-radius:7px;padding:9px 12px;font:inherit;font-size:13px}
.pa-cancel span{flex:1;font-size:13px;color:#9b2626}
.pa-filter{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:10px;padding:14px;background:#f7fafc;border:1px solid #e3eaf1;border-radius:10px}
.pa-filter label{display:flex;flex-direction:column;font-size:11px;font-weight:800;color:#31465a;gap:5px}
.pa-filter input{box-sizing:border-box;border:1px solid #ccd7e1;border-radius:7px;padding:9px 11px;font:inherit;font-size:13px;background:#fff}
.pa-filter .pa-f-name{flex:1;min-width:240px}
.pa-filter .pa-f-name input{width:100%}
.pa-clear{font-size:12px;color:#e72435;font-weight:800;align-self:center;text-decoration:none}
.pa-quick{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px;font-size:12px;color:#708198}
.pa-quick a{padding:5px 11px;border:1px solid #dce5ee;border-radius:16px;background:#fff;color:#33475b;font-weight:700;text-decoration:none}
.pa-quick a:hover{border-color:#e72435;color:#e72435}
.pa-result{margin:0 0 12px;font-size:13px;color:#33475b;font-weight:700}
.pa-empty{text-align:center;color:#708198;padding:30px 10px;font-size:14px}
@media(max-width:900px){.pa-form{grid-template-columns:1fr}.pa-q>summary{flex-wrap:wrap}.pa-q-meta{order:5;width:100%;padding-left:44px}}
</style></head><body class="admin-page">
<header class="admin-header"><div class="container admin-header-inner"><a href="index.php" class="logo logo-image" aria-label="ELEDS Linha Leve e Pesada"><img src="assets/eleds-logo.png" alt="ELEDS Linha Leve e Pesada"></a><div class="admin-actions"><span class="admin-user-chip"><?php if(strcasecmp($paUser,'admin')===0):?><img src="assets/eleds-aguia-avatar.png" alt="Águia ELEDS"><?php elseif(!empty($_SESSION['admin_photo'])):?><img src="<?=ep_h($_SESSION['admin_photo'])?>" alt="Foto"><?php else:?>👤<?php endif;?> <?=ep_h($paUser)?></span><a href="admin.php">Produtos</a><a href="gestao_orcamentos.php">Gestão de orçamentos</a><a href="portal_admin.php" class="cur">Clientes e propostas</a><a href="orcamentos.php">Meus orçamentos</a><a href="index.php">Ver catálogo</a><a href="admin.php?logout=1">Sair</a></div></div></header>

<main class="container admin-main">
<div class="admin-title"><div><span class="section-kicker">ADMINISTRAÇÃO</span><h1>Clientes e propostas</h1><p><?=count($clients)?> cliente(s) cadastrado(s) · <?=$counts['aguardando_analise']?> orçamento(s) aguardando análise</p></div></div>

<?php if($okText):?><div class="admin-alert success"><?=ep_h($okText)?></div><?php endif;?>
<?php if($error):?><div class="admin-alert error"><?=ep_h($error)?></div><?php endif;?>

<section class="admin-panel pa-panel">
<h2>Cadastrar cliente</h2>
<p class="pa-sub">O cliente usa o usuário e a senha abaixo para entrar em portal_cliente.php e montar orçamentos.</p>
<form method="post" class="admin-form pa-form">
<input type="hidden" name="csrf" value="<?=ep_csrf()?>"><input type="hidden" name="action" value="client">
<label>Nome / empresa<input name="name" required></label>
<label>Usuário<input name="user" required autocomplete="off"></label>
<label>Senha inicial <small>(mínimo 8 caracteres)</small><input type="password" name="password" minlength="8" required autocomplete="new-password"></label>
<label>Documento<input name="document"></label>
<label>Telefone<input name="phone"></label>
<label>E-mail<input type="email" name="email"></label>
<div class="pa-form-actions"><button class="admin-primary" type="submit">Cadastrar cliente</button></div>
</form>
</section>

<section class="admin-panel pa-panel" id="clientes">
<div class="pa-bar"><div><h2>Clientes cadastrados</h2><p class="pa-sub" style="margin:0"><?=$totalClients?> cliente(s)<?=$busca!==''?' encontrado(s) para “'.ep_h($busca).'”':''?></p></div>
<form class="pa-search" method="get" action="portal_admin.php#clientes"><?php if($stFilter!==''):?><input type="hidden" name="st" value="<?=ep_h($stFilter)?>"><?php endif;?><?php if($qb!==''):?><input type="hidden" name="qb" value="<?=ep_h($qb)?>"><?php endif;?><?php if($de!==''):?><input type="hidden" name="de" value="<?=ep_h($de)?>"><?php endif;?><?php if($ate!==''):?><input type="hidden" name="ate" value="<?=ep_h($ate)?>"><?php endif;?><input type="search" name="busca" value="<?=ep_h($busca)?>" placeholder="Buscar nome, usuário, e-mail…"><button type="submit">Buscar</button><?php if($busca!==''):?><a href="<?=ep_h(pa_url(['busca'=>null,'cp'=>null],'clientes'))?>">Limpar</a><?php endif;?></form></div>
<?php if(!$clientsPage):?><div class="pa-empty">Nenhum cliente encontrado.</div><?php else:?>
<div class="pa-scroll"><table class="pa-table"><thead><tr><th>Cliente</th><th>Usuário</th><th>Contato</th><th>Orçamentos</th><th>Status</th><th class="r">Ação</th></tr></thead><tbody>
<?php foreach($clientsPage as $c):$active=!empty($c['active']);?>
<tr><td><strong><?=ep_h($c['name']??'')?></strong><?php if(!empty($c['document'])):?><br><small style="color:#708198"><?=ep_h($c['document'])?></small><?php endif;?></td><td><?=ep_h($c['user']??'')?></td><td><?=ep_h($c['phone']??'')?><?php if(!empty($c['email'])):?><br><small style="color:#708198"><?=ep_h($c['email'])?></small><?php endif;?></td><td><?=(int)($quoteCount[$c['id']??'']??0)?></td><td><span class="pa-badge <?=$active?'ok':'no'?>"><?=$active?'Ativo':'Bloqueado'?></span></td>
<td class="r"><form method="post" style="margin:0"><input type="hidden" name="csrf" value="<?=ep_csrf()?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="back" value="<?=ep_h($backQuery)?>"><input type="hidden" name="id" value="<?=ep_h($c['id']??'')?>"><button class="pa-btn <?=$active?'danger':''?>" type="submit"><?=$active?'Bloquear':'Ativar'?></button></form></td></tr>
<?php endforeach;?></tbody></table></div>
<?=pa_pager($totalClients,$perClients,$pageClients,'cp','clientes')?>
<?php endif;?>
</section>

<section class="admin-panel pa-panel" id="orcamentos">
<div class="pa-bar"><div><h2>Orçamentos dos clientes</h2><p class="pa-sub" style="margin:0">Clique na seta para abrir o orçamento, ajustar descontos e liberar a proposta.</p></div>
<div><button type="button" class="pa-tool" id="paOpenAll">Abrir todos</button> <button type="button" class="pa-tool" id="paCloseAll">Fechar todos</button></div></div>
<form class="pa-filter" method="get" action="portal_admin.php#orcamentos">
<?php if($stFilter!==''):?><input type="hidden" name="st" value="<?=ep_h($stFilter)?>"><?php endif;?>
<?php if($busca!==''):?><input type="hidden" name="busca" value="<?=ep_h($busca)?>"><?php endif;?>
<label class="pa-f-name">Buscar orçamento<input type="search" name="qb" value="<?=ep_h($qb)?>" placeholder="Nome do cliente, nº do orçamento ou produto"></label>
<label>De<input type="date" name="de" value="<?=ep_h($de)?>"></label>
<label>Até<input type="date" name="ate" value="<?=ep_h($ate)?>"></label>
<button type="submit" class="pa-tool">Filtrar</button>
<?php if($filtering):?><a class="pa-clear" href="<?=ep_h(pa_url(['qb'=>null,'de'=>null,'ate'=>null,'op'=>null],'orcamentos'))?>">Limpar filtros</a><?php endif;?>
</form>
<div class="pa-quick"><span>Período rápido:</span>
<a href="<?=ep_h(pa_url(['de'=>date('Y-m-d'),'ate'=>date('Y-m-d'),'op'=>null],'orcamentos'))?>">Hoje</a>
<a href="<?=ep_h(pa_url(['de'=>date('Y-m-d',strtotime('-6 days')),'ate'=>date('Y-m-d'),'op'=>null],'orcamentos'))?>">Últimos 7 dias</a>
<a href="<?=ep_h(pa_url(['de'=>date('Y-m-d',strtotime('-29 days')),'ate'=>date('Y-m-d'),'op'=>null],'orcamentos'))?>">Últimos 30 dias</a>
<a href="<?=ep_h(pa_url(['de'=>date('Y-m-01'),'ate'=>date('Y-m-d'),'op'=>null],'orcamentos'))?>">Este mês</a>
</div>
<?php if($filtering):?><p class="pa-result"><?=count($allQuotes)?> orçamento(s) encontrado(s)<?=$qb!==''?' para “'.ep_h($qb).'”':''?><?php if($de!==''||$ate!==''):?> · período: <?=$de!==''?ep_h(date('d/m/Y',strtotime($de))):'início'?> a <?=$ate!==''?ep_h(date('d/m/Y',strtotime($ate))):'hoje'?><?php endif;?></p><?php endif;?>
<div class="pa-tabs">
<a href="<?=ep_h(pa_url(['st'=>null,'op'=>null],'orcamentos'))?>" class="<?=$stFilter===''?'on':''?>">Todos <b><?=count($allQuotes)?></b></a>
<?php foreach($statusLabels as $key=>$lab):?><a href="<?=ep_h(pa_url(['st'=>$key,'op'=>null],'orcamentos'))?>" class="<?=$stFilter===$key?'on':''?>"><?=ep_h($lab[0])?> <b><?=$counts[$key]?></b></a><?php endforeach;?>
</div>
<?php if(!$quotesPage):?><div class="pa-empty">Nenhum orçamento nesta lista.</div><?php endif;?>
<?php foreach($quotesPage as $q):
  $qid=(string)($q['id']??'');$anchor='orc-'.preg_replace('/[^a-zA-Z0-9_-]/','',$qid);
  $st=$q['status']??'aguardando_analise';if(!isset($statusLabels[$st]))$st='aguardando_analise';$lab=$statusLabels[$st];
  $dis=$st==='cancelado'?' disabled':'';
  $items=$q['items']??[];
  $cust=$q['customer']??[];
  $when=!empty($q['created_at'])?date('d/m/Y H:i',strtotime($q['created_at'])):'';
?>
<details class="pa-q" id="<?=ep_h($anchor)?>">
<summary><span class="pa-arrow">▸</span><span class="pa-q-main"><strong><?=ep_h($q['number']??'')?></strong><small><?=ep_h($cust['name']??($q['user']??''))?></small></span><span class="pa-q-meta"><?=ep_h($when)?> · <?=count($items)?> item(ns)</span><span class="pa-q-total"><?=ep_money(ep_total($q))?></span><span class="pa-badge <?=ep_h($lab[1])?>"><?=ep_h($lab[0])?></span></summary>
<div class="pa-q-body">
<div class="pa-cust"><span><b>Cliente:</b> <?=ep_h($cust['name']??'')?></span><?php if(!empty($cust['document'])):?><span><b>Documento:</b> <?=ep_h($cust['document'])?></span><?php endif;?><?php if(!empty($cust['phone'])):?><span><b>Telefone:</b> <?=ep_h($cust['phone'])?></span><?php endif;?><?php if(!empty($cust['email'])):?><span><b>E-mail:</b> <?=ep_h($cust['email'])?></span><?php endif;?></div>
<form method="post">
<input type="hidden" name="csrf" value="<?=ep_csrf()?>"><input type="hidden" name="action" value="release"><input type="hidden" name="back" value="<?=ep_h($backQuery)?>"><input type="hidden" name="id" value="<?=ep_h($qid)?>">
<div class="pa-scroll"><table class="pa-table"><thead><tr><th>Produto</th><th>Qtd.</th><th>Preço unitário</th><th>Desconto individual (%)</th><th class="r">Subtotal</th></tr></thead><tbody>
<?php foreach($items as $k=>$i):$d=max(0,min(100,(float)($i['discount']??0)));$sub=(float)($i['price']??0)*(int)($i['qty']??0)*(1-$d/100);?>
<tr data-price="<?=ep_h((float)($i['price']??0))?>" data-qty="<?=(int)($i['qty']??0)?>"><td><strong><?=ep_h($i['code']??'')?></strong><br><small style="color:#708198"><?=ep_h($i['name']??'')?></small></td><td><?=(int)($i['qty']??0)?></td><td><?=ep_money($i['price']??0)?></td><td><input type="number" min="0" max="100" step="0.01" name="item_discount[<?=$k?>]" value="<?=ep_h($i['discount']??0)?>"<?=$dis?>></td><td class="r pa-sub-cell"><?=ep_money($sub)?></td></tr>
<?php endforeach;?></tbody></table></div>
<div class="pa-sum"><label>Desconto geral adicional (%) <input type="number" min="0" max="100" step="0.01" name="discount" value="<?=ep_h($q['discount']??0)?>"<?=$dis?>></label><span>Total <span class="pa-total"><?=ep_money(ep_total($q))?></span></span></div>
<?php if($st!=='cancelado'):?><div class="pa-q-actions"><small><?=$st==='proposta_enviada'&&!empty($q['updated_at'])?'Proposta liberada em '.ep_h(date('d/m/Y H:i',strtotime($q['updated_at']))).'.':'Aguardando sua análise.'?></small><button class="admin-primary" type="submit" onclick="return confirm('<?=$st==='proposta_enviada'?'Atualizar os descontos e reenviar a proposta ao cliente?':'Liberar proposta final para o cliente?'?>')"><?=$st==='proposta_enviada'?'Atualizar descontos e reenviar':'Salvar descontos e liberar proposta'?></button></div><?php endif;?>
</form>
<?php if($st==='cancelado'):?>
<form method="post" class="pa-cancel"><input type="hidden" name="csrf" value="<?=ep_csrf()?>"><input type="hidden" name="action" value="reopen"><input type="hidden" name="back" value="<?=ep_h($backQuery)?>"><input type="hidden" name="id" value="<?=ep_h($qid)?>"><span><strong>Proposta reprovada.</strong> <?=!empty($q['cancel_reason'])?'Motivo informado ao cliente: '.ep_h($q['cancel_reason']):'Nenhum motivo informado.'?></span><button type="submit" class="pa-btn" onclick="return confirm('Reabrir este orçamento para análise?')">Reabrir para análise</button></form>
<?php else:?>
<form method="post" class="pa-cancel"><input type="hidden" name="csrf" value="<?=ep_csrf()?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="back" value="<?=ep_h($backQuery)?>"><input type="hidden" name="id" value="<?=ep_h($qid)?>"><input type="text" name="reason" maxlength="300" placeholder="Motivo da reprovação (opcional, o cliente verá)"><button type="submit" class="pa-btn danger" onclick="return confirm('Reprovar esta proposta? O cliente verá que ela foi reprovada.')">Reprovar / cancelar proposta</button></form>
<?php endif;?>
</div>
</details>
<?php endforeach;?>
<?=pa_pager($totalQuotes,$perQuotes,$pageQuotes,'op','orcamentos')?>
</section>
</main>
<script>
(function(){
const BRL=n=>n.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
const clamp=v=>{v=parseFloat(String(v).replace(',','.'));return isFinite(v)?Math.min(100,Math.max(0,v)):0;};
document.querySelectorAll('.pa-q form').forEach(f=>{
  const rows=[].slice.call(f.querySelectorAll('tbody tr')),gen=f.querySelector('[name=discount]'),tot=f.querySelector('.pa-total');
  function calc(){let s=0;rows.forEach(r=>{const inp=r.querySelector('input'),t=Number(r.dataset.price)*Number(r.dataset.qty)*(1-clamp(inp.value)/100);r.querySelector('.pa-sub-cell').textContent=BRL(t);s+=t;});tot.textContent=BRL(Math.round(s*(1-clamp(gen.value)/100)*100)/100);}
  f.addEventListener('input',calc);
});
const all=()=>[].slice.call(document.querySelectorAll('details.pa-q'));
document.getElementById('paOpenAll').addEventListener('click',()=>all().forEach(d=>d.open=true));
document.getElementById('paCloseAll').addEventListener('click',()=>all().forEach(d=>d.open=false));
function openHash(){const h=location.hash;if(h.indexOf('#orc-')===0){const d=document.getElementById(h.slice(1));if(d&&d.tagName==='DETAILS'){d.open=true;setTimeout(()=>d.scrollIntoView({block:'start'}),50);}}}
openHash();window.addEventListener('hashchange',openHash);
})();
</script>
</body></html>
