<?php
session_start();require __DIR__.'/portal_lib.php';
$clients=ep_read('clientes');$error='';
if(isset($_GET['sair'])){unset($_SESSION['ep_client']);header('Location: portal_cliente.php');exit;}
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='login'){ep_check();foreach($clients as $c){if(!empty($c['active']) && hash_equals(mb_strtolower((string)$c['user']),mb_strtolower(trim((string)($_POST['user']??'')))) && password_verify((string)($_POST['password']??''),(string)($c['password']??''))){session_regenerate_id(true);$_SESSION['ep_client']=$c['id'];header('Location: index.php');exit;}}$error='Usuário ou senha inválidos.';}
$client=null;foreach($clients as $c)if(($c['id']??'')===($_SESSION['ep_client']??'') && !empty($c['active'])){$client=$c;break;}
if(!$client)unset($_SESSION['ep_client']);
$products=ep_read('products');$quotes=ep_read('orcamentos');
if($client && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='send'){
 ep_check();$items=[];$qtys=$_POST['qty']??[];if(!is_array($qtys))$qtys=[];
 foreach($products as $p){$code=(string)($p['code']??'');$qty=max(0,min(999,(int)($qtys[$code]??0)));if($qty<1||!is_numeric($p['price']??null)||$p['price']<0)continue;$items[]=['code'=>$code,'name'=>(string)($p['name']??''),'qty'=>$qty,'price'=>round((float)$p['price'],2),'discount'=>0];if(count($items)>=100)break;}
 if(!$items)$error='Selecione pelo menos um produto que tenha preço cadastrado.';
 else{$now=date('c');$id='cli_'.bin2hex(random_bytes(10));$quotes[]=['id'=>$id,'number'=>'CLI-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(4)),0,8)),'user'=>$client['user'],'client_id'=>$client['id'],'customer'=>['name'=>$client['name'],'document'=>$client['document']??'','phone'=>$client['phone']??'','email'=>$client['email']??''],'items'=>$items,'discount'=>0,'status'=>'aguardando_analise','created_at'=>$now,'updated_at'=>$now,'history'=>[['at'=>$now,'user'=>$client['user'],'event'=>'submitted']]];if(ep_write('orcamentos',$quotes)){header('Location: portal_cliente.php?enviado=1');exit;}$error='Falha ao salvar o orçamento.';}
}
if(!$client){?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Portal de Clientes — ELEDS Linha Leve e Pesada</title><link rel="stylesheet" href="assets/css/style.css?v=20261002-layoutfix1"></head><body class="admin-body"><div class="admin-login"><div class="admin-logo"><img src="assets/eleds-logo.png" alt="ELEDS Linha Leve e Pesada"><span>Portal de clientes</span></div><h1>Portal de clientes</h1><p>Entre para montar e enviar seu orçamento.</p><?php if($error):?><div class="admin-alert error"><?=ep_h($error)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=ep_csrf()?>"><input type="hidden" name="action" value="login"><label>Usuário<input name="user" autocomplete="username" required></label><label>Senha<input type="password" name="password" autocomplete="current-password" required></label><button class="admin-primary" type="submit">Entrar no portal</button></form><a class="back-link" href="index.php">← Voltar ao catálogo</a></div></body></html><?php exit;}
$ocProducts=[];foreach($products as $p){if(!is_numeric($p['price']??null)||$p['price']<0)continue;$ocProducts[(string)($p['code']??'')]=['name'=>(string)($p['name']??''),'price'=>round((float)$p['price'],2)];}
$ocStatus=['proposta_enviada'=>['Proposta liberada','ok'],'cancelado'=>['Proposta reprovada','no'],'reprovada'=>['Proposta reprovada','no'],'reprovado'=>['Proposta reprovada','no']];
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Meu orçamento — ELEDS Linha Leve e Pesada</title><link rel="stylesheet" href="assets/css/style.css?v=eleds-premium-clean-banner-3"><link rel="stylesheet" href="assets/css/logo-eagle.css?v=1"><style>
.main-nav a.eleds-client-access{display:inline-flex;align-items:center;justify-content:center;border-radius:9px;padding:12px 16px;color:#fff!important;font-weight:800;text-decoration:none;white-space:nowrap;transition:filter .2s ease,transform .2s ease;background:#e91b39!important}
.main-nav a.eleds-client-access:hover{filter:brightness(1.12);transform:translateY(-1px)}
@media(max-width:900px){.main-nav a.eleds-client-access{margin:4px 0;text-align:center}}
body{background:var(--light,#f4f7fa);color:var(--text,#182433)}
.oc-wrap{padding:30px 0 10px}
.oc-head{margin-bottom:20px}
.oc-head h1{font-size:30px;margin:4px 0}
.oc-head p{margin:0;color:var(--muted);font-size:13px}
.oc-card{background:#fff;border:1px solid var(--line);border-radius:10px;padding:22px;margin-bottom:22px;box-shadow:0 4px 18px rgba(15,23,42,.04)}
.oc-card h2{margin:0 0 4px;font-size:20px}
.oc-card .oc-sub{color:var(--muted);font-size:13px;margin:0 0 16px}
.oc-scroll{overflow-x:auto}
.oc-table{width:100%;border-collapse:collapse;min-width:620px}
.oc-table th{font-size:11px;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);text-align:left;padding:10px;border-bottom:2px solid var(--line)}
.oc-table td{padding:12px 10px;border-bottom:1px solid var(--line);vertical-align:middle}
.oc-table .r{text-align:right}
.oc-code{font-weight:900}
.oc-name{display:block;color:var(--muted);font-size:13px;margin-top:2px}
.oc-qty{width:84px;padding:9px;border:1px solid #cfd8e3;border-radius:7px;font-size:14px}
.oc-del{border:1.5px solid #e72435;background:#fff;color:#d91f32;border-radius:7px;padding:8px 12px;font-weight:900;cursor:pointer;font-size:12px}
.oc-del:hover{background:#fff1f3}
.oc-total{display:flex;justify-content:flex-end;align-items:baseline;gap:12px;padding:16px 10px 2px;font-size:15px}
.oc-total strong{font-size:24px;color:#087a43}
.oc-note{color:var(--muted);font-size:12px;text-align:right;margin:2px 10px 0}
.oc-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:16px}
.oc-btn-sec{display:inline-flex;align-items:center;justify-content:center;border:1.5px solid #cfd8e3;background:#fff;color:#33475b;border-radius:7px;padding:11px 16px;font-weight:900;cursor:pointer;text-decoration:none;font-size:14px}
.oc-btn-sec:hover{background:#f4f7fa}
.oc-actions .admin-primary{font-size:14px}
.oc-actions .admin-primary[disabled]{opacity:.45;cursor:not-allowed}
.oc-search{width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #cfd8e3;border-radius:8px;font-size:14px}
.oc-results{margin-top:12px;max-height:360px;overflow:auto;border:1px solid var(--line);border-radius:8px}
.oc-result{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid var(--line)}
.oc-result:last-child{border-bottom:0}
.oc-result .oc-price{color:#087a43;font-weight:800;white-space:nowrap;margin-left:auto}
.oc-add{border:1.5px solid var(--blue,#0879e8);background:#fff;color:#d91f32;border-color:#e72435;border-radius:7px;padding:8px 14px;font-weight:900;cursor:pointer;font-size:12px;white-space:nowrap}
.oc-add:hover{background:#fff1f3}
.oc-hint{padding:16px;color:var(--muted);font-size:13px;text-align:center}
.oc-badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:800;background:#fff4dc;color:#8a5a00}
.oc-badge.ok{background:#e7f8ee;color:#11733b}
.oc-badge.no{background:#fdecec;color:#9b2626}
.oc-link{color:#d91f32;font-weight:800;text-decoration:none}
.oc-link:hover{text-decoration:underline}
.admin-alert{margin-bottom:18px}
.copyright{text-align:center;font-size:11px;color:#8fa1b3;padding:0 0 18px}
</style></head><body>
<header class="site-header"><div class="container header-inner"><a href="index.php" class="logo logo-image logo-eagle" aria-label="ELEDS Linha Leve e Pesada"><span class="eagle-logo"><img src="assets/eleds-logo-site.png" alt="ELEDS Linha Leve e Pesada"><i class="eagle-glint" aria-hidden="true"><b class="g-streak"></b><b class="g-diag"></b><b class="g-glow"></b><b class="g-rays"></b></i></span></a><button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mainNav">☰ <span>Menu</span></button><nav id="mainNav" class="main-nav"><a href="index.php"><span>Início</span></a><a href="index.php#produtos"><span>Produtos</span></a><a href="portal_cliente.php?historico=1" class="eleds-client-access active">🛒 Meu orçamento <span id="ocCount"></span></a><a href="portal_cliente.php?sair=1"><span>Sair (<?=ep_h($client['name'])?>)</span></a></nav></div></header>
<main class="container oc-wrap">
<div class="oc-head"><span class="section-kicker">ÁREA DO CLIENTE</span><h1>Meu orçamento</h1><p>Olá, <?=ep_h($client['name'])?>. Revise os itens, ajuste as quantidades ou exclua o que não precisa e envie para análise.</p></div>
<?php if(isset($_GET['enviado'])):?><div class="admin-alert success">Orçamento enviado para análise! Aguarde a liberação da administração.</div><?php endif;?>
<?php if($error):?><div class="admin-alert error"><?=ep_h($error)?></div><?php endif;?>

<section class="oc-card">
<h2>Itens do orçamento</h2>
<p class="oc-sub">O preço final poderá ser ajustado e liberado pela administração. <strong id="ocSummary"></strong></p>
<form method="post" id="ocForm">
<input type="hidden" name="csrf" value="<?=ep_csrf()?>"><input type="hidden" name="action" value="send">
<div id="ocEmpty" class="empty" hidden style="padding:40px 20px"><div>🛒</div><h3>Seu orçamento está vazio</h3><p>Adicione produtos pela busca abaixo ou escolha no catálogo.</p><a href="index.php#produtos">Ver catálogo</a></div>
<div id="ocFilled" hidden>
<div class="oc-scroll"><table class="oc-table"><thead><tr><th>Produto</th><th>Preço unitário</th><th>Quantidade</th><th class="r">Subtotal</th><th class="r">Ação</th></tr></thead><tbody id="ocBody"></tbody></table></div>
<div class="oc-total"><span>Total estimado</span><strong id="ocTotal">R$ 0,00</strong></div>
<p class="oc-note">Valor sujeito a ajuste e liberação pela administração.</p>
<div class="oc-actions"><a class="oc-btn-sec" href="index.php#produtos">+ Continuar escolhendo produtos</a><button type="button" class="oc-btn-sec" id="ocClear">Limpar orçamento</button><button type="submit" class="admin-primary" id="ocSend">Enviar para análise</button></div>
</div>
</form>
</section>


<section class="oc-card">
<h2>Meus orçamentos enviados</h2>
<p class="oc-sub">Acompanhe o andamento e abra a proposta quando for liberada.</p>
<?php $ocMine=array_values(array_filter(array_reverse($quotes),fn($q)=>($q['client_id']??null)===$client['id']));?>
<?php if(!$ocMine):?><div class="oc-hint">Você ainda não enviou nenhum orçamento.</div><?php else:?>
<div class="oc-scroll"><table class="oc-table"><thead><tr><th>Número</th><th>Data</th><th>Status</th><th>Valor</th><th>Detalhes</th></tr></thead><tbody>
<?php foreach($ocMine as $q):$st=$q['status']??'';$released=$st==='proposta_enviada';$lab=$ocStatus[$st]??['Aguardando análise',''];?>
<tr><td class="oc-code"><?=ep_h($q['number']??'')?></td><td><?=ep_h(substr($q['created_at']??'',0,10))?></td><td><span class="oc-badge <?=ep_h($lab[1])?>"><?=ep_h($lab[0])?></span></td><td><?=$released?ep_money(ep_total($q)):(($lab[1]==='no')?'—':'Em análise')?></td><td><?php if($released):?><a class="oc-link" href="proposta_cliente.php?id=<?=urlencode($q['id'])?>" target="_blank">Ver proposta / imprimir PDF</a><?php elseif(!empty($q['cancel_reason']) && $lab[1]==='no'):?>Motivo: <?=ep_h($q['cancel_reason'])?><?php else:?>—<?php endif;?></td></tr>
<?php endforeach;?></tbody></table></div><?php endif;?>
</section>
</main>
<footer id="contato"><div class="container footer-inner"><div class="footer-brand"><span>ELEDS</span><strong>LINHA LEVE E PESADA</strong></div><p>Iluminação e acessórios para carros, utilitários, caminhões e implementos.</p><div class="footer-brands">VOLVO &nbsp; MERCEDES-BENZ &nbsp; SCANIA &nbsp; FORD &nbsp; VW &nbsp; DAF</div></div><div class="copyright">© 2026 — Catálogo Linha Leve e Pesada. Todos os direitos reservados.</div></footer>
<script>
(function(){
const PRODUCTS=<?=json_encode($ocProducts,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_FORCE_OBJECT)?>;
const KEY='eleds_cart_<?=ep_h(preg_replace('/[^a-zA-Z0-9_-]/','',(string)$client['id']))?>';
<?php if(isset($_GET['enviado'])): ?>try{localStorage.removeItem(KEY);}catch(e){}
<?php endif; ?>
const $=id=>document.getElementById(id);
const BRL=n=>n.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
function load(){try{const v=JSON.parse(localStorage.getItem(KEY)||'{}');const out={};if(v&&typeof v==='object'&&!Array.isArray(v))for(const c in v){const q=Math.min(999,Math.floor(Number(v[c])||0));if(q>0&&Object.prototype.hasOwnProperty.call(PRODUCTS,c))out[c]=q;}return out;}catch(e){return {};}}
let cart=load();
function save(){try{localStorage.setItem(KEY,JSON.stringify(cart));}catch(e){}}
function el(tag,cls,text){const n=document.createElement(tag);if(cls)n.className=cls;if(text!=null)n.textContent=text;return n;}
function totals(){let t=0,c=0;for(const k in cart){t+=PRODUCTS[k].price*cart[k];c+=cart[k];}$('ocTotal').textContent=BRL(t);const n=Object.keys(cart).length;$('ocSummary').textContent=n?'· '+n+(n===1?' produto':' produtos')+', '+c+(c===1?' unidade':' unidades'):'';$('ocCount').textContent=c>0?'('+c+')':'(0)';document.querySelectorAll('#ocBody tr').forEach(tr=>{const k=tr.dataset.code;const s=tr.querySelector('.oc-sub-total');if(s&&cart[k])s.textContent=BRL(PRODUCTS[k].price*cart[k]);});}
function render(){
  const codes=Object.keys(cart);const body=$('ocBody');body.innerHTML='';
  $('ocEmpty').hidden=codes.length>0;$('ocFilled').hidden=codes.length===0;
  codes.forEach(code=>{
    const p=PRODUCTS[code];const tr=el('tr');tr.dataset.code=code;
    const tdP=el('td');tdP.appendChild(el('span','oc-code',code));tdP.appendChild(el('span','oc-name',p.name));tr.appendChild(tdP);
    tr.appendChild(el('td',null,BRL(p.price)));
    const tdQ=el('td');const inp=el('input','oc-qty');inp.type='number';inp.min='1';inp.max='999';inp.value=cart[code];inp.setAttribute('aria-label','Quantidade de '+code);
    inp.addEventListener('input',()=>{let q=Math.floor(Number(inp.value)||0);if(q<1)return;if(q>999){q=999;inp.value=q;}cart[code]=q;save();totals();});
    inp.addEventListener('blur',()=>{if(!(Number(inp.value)>=1)){inp.value=cart[code];}});
    tdQ.appendChild(inp);tr.appendChild(tdQ);
    const tdS=el('td','r oc-sub-total',BRL(p.price*cart[code]));tr.appendChild(tdS);
    const tdA=el('td','r');const del=el('button','oc-del','Excluir');del.type='button';del.setAttribute('aria-label','Excluir '+code+' do orçamento');
    del.addEventListener('click',()=>{delete cart[code];save();render();});
    tdA.appendChild(del);tr.appendChild(tdA);body.appendChild(tr);
  });
  totals();
}
$('ocClear').addEventListener('click',()=>{if(confirm('Remover todos os itens do orçamento?')){cart={};save();render();}});
$('ocForm').addEventListener('submit',e=>{
  const codes=Object.keys(cart);
  if(!codes.length){e.preventDefault();alert('Adicione pelo menos um produto ao orçamento.');return;}
  if(!confirm('Enviar este orçamento para análise?')){e.preventDefault();return;}
  e.target.querySelectorAll('input[data-gen]').forEach(n=>n.remove());
  codes.forEach(c=>{const h=document.createElement('input');h.type='hidden';h.name='qty['+c+']';h.value=cart[c];h.setAttribute('data-gen','1');e.target.appendChild(h);});
});
const menu=document.querySelector('.menu-toggle'),nav=$('mainNav');
menu&&menu.addEventListener('click',()=>{const o=nav.classList.toggle('open');menu.setAttribute('aria-expanded',o?'true':'false');});
render();
})();
</script>
</body></html>
