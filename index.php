<?php
session_start();
$products = json_decode(file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
// O preço só é disponibilizado ao cliente autenticado e ativo.
$eledsClient = null;
if (!empty($_SESSION['ep_client'])) {
    $eledsClients = json_decode(@file_get_contents(__DIR__.'/data/clientes.json'), true) ?: [];
    foreach ($eledsClients as $entry) {
        if (($entry['id'] ?? '') === $_SESSION['ep_client'] && !empty($entry['active'])) { $eledsClient = $entry; break; }
    }
    if (!$eledsClient) unset($_SESSION['ep_client']);
}
$eledsPrices = [];
if ($eledsClient) foreach ($products as $entry) {
    if (isset($entry['price']) && is_numeric($entry['price']) && (float)$entry['price'] >= 0) $eledsPrices[(string)($entry['code']??'')] = round((float)$entry['price'], 2);
}

$whatsapps = json_decode(file_get_contents(__DIR__ . '/data/whatsapp.json'), true) ?: [];
$groups = json_decode(@file_get_contents(__DIR__ . '/data/groups.json'), true) ?: [];
$loggedUserPhoto = '';
if (isset($_SESSION['admin_ok'])) {
    $siteUsers = json_decode(@file_get_contents(__DIR__ . '/data/users.json'), true) ?: [];
    foreach ($siteUsers as $siteUser) if (strcasecmp((string)($siteUser['user'] ?? ''), (string)($_SESSION['admin_user'] ?? '')) === 0) { $loggedUserPhoto = (string)($siteUser['photo'] ?? ''); break; }
}
$defaultWhatsapp = $whatsapps[0] ?? ['number'=>'5587981177326','display'=>'(87) 98117-7326'];
foreach ($whatsapps as $w) { if (!empty($w['default'])) { $defaultWhatsapp = $w; break; } }
$defaultWaNumber = preg_replace('/\D+/', '', $defaultWhatsapp['number'] ?? '');
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function normalizeSearch($value) {
    $value = (string)$value;
    if (function_exists('iconv')) { $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value); if ($converted !== false) $value = $converted; }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/i', ' ', $value);
    return trim(preg_replace('/\s+/', ' ', $value));
}
function searchTokens($query) { $n = normalizeSearch($query); return $n === '' ? [] : preg_split('/\s+/', $n); }
$q = trim($_GET['q'] ?? ''); $category = trim($_GET['categoria'] ?? ''); $order = trim($_GET['ordem'] ?? '');
$categories = [];
foreach ($groups as $g) { $cat=trim($g['name']??''); if($cat!=='') $categories[$cat]=0; }
foreach ($products as $product) { $cat = $product['category'] ?? 'Outros'; $categories[$cat] = ($categories[$cat] ?? 0) + 1; }
ksort($categories);
$tokens = searchTokens($q);
$filtered = array_values(array_filter($products, function ($product) use ($tokens, $category) {
    $searchText = normalizeSearch(($product['code']??'').' '.($product['name']??'').' '.($product['category']??'').' '.($product['brand']??'').' '.($product['application']??'').' '.($product['measures']??''));
    foreach ($tokens as $token) if (strpos($searchText, $token) === false) return false;
    return $category === '' || ($product['category'] ?? '') === $category;
}));
if ($order === 'az') usort($filtered, fn($a,$b)=>strnatcasecmp($a['name']??'',$b['name']??''));
elseif ($order === 'codigo') usort($filtered, fn($a,$b)=>strnatcasecmp($a['code']??'',$b['code']??''));
$perPage = 9;
$totalProducts = count($filtered);
$totalPages = max(1, (int)ceil($totalProducts / $perPage));
$currentPage = max(1, min($totalPages, (int)($_GET['pagina'] ?? 1)));
$displayProducts = array_slice($filtered, ($currentPage - 1) * $perPage, $perPage);
$paginationParams = ['q' => $q, 'categoria' => $category, 'ordem' => $order];
$paginationParams = array_filter($paginationParams, fn($value) => $value !== '');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="Catálogo digital ELEDS: iluminação e acessórios para linha leve e pesada."><title>ELEDS Linha Leve e Pesada — Catálogo Digital</title><link rel="stylesheet" href="assets/css/style.css?v=eleds-premium-clean-banner-3"><link rel="stylesheet" href="assets/css/logo-eagle.css?v=1"><style>
.main-nav a.eleds-client-access,.main-nav a.eleds-admin-access{display:inline-flex;align-items:center;justify-content:center;border-radius:9px;padding:12px 16px;color:#fff!important;font-weight:800;text-decoration:none;white-space:nowrap;transition:filter .2s ease,transform .2s ease}
.main-nav a.eleds-client-access{background:#e91b39!important}
.main-nav a.eleds-admin-access{background:#182331!important}
.main-nav a.eleds-client-access:hover,.main-nav a.eleds-admin-access:hover{filter:brightness(1.12);transform:translateY(-1px)}
@media(max-width:900px){.main-nav a.eleds-client-access,.main-nav a.eleds-admin-access{margin:4px 0;text-align:center}}
<?php if($eledsClient): ?>
.product-actions{grid-template-columns:1fr!important}
.product-actions .details-btn{grid-column:1/-1;width:100%!important}
.product-actions .quote-add{grid-column:1/-1;width:100%;background:#e91b39!important;color:#fff!important;border-color:#e91b39!important;font-size:13px!important;min-height:44px}
.product-actions .quote-add:hover{background:#c80e2c!important;color:#fff!important}
.quote-fab,.quote-overlay,.quote-drawer{display:none!important}
<?php endif; ?>
</style></head><body>
<header class="site-header"><div class="container header-inner"><a href="index.php" class="logo logo-image logo-eagle" aria-label="ELEDS Linha Leve e Pesada - Catálogo Digital"><span class="eagle-logo"><img src="assets/eleds-logo-site.png" alt="ELEDS Linha Leve e Pesada"><i class="eagle-glint" aria-hidden="true"><b class="g-streak"></b><b class="g-diag"></b><b class="g-glow"></b><b class="g-rays"></b></i></span></a><button class="menu-toggle" type="button" aria-expanded="false" aria-controls="mainNav">☰ <span>Menu</span></button><nav id="mainNav" class="main-nav"><a class="active" href="index.php"><span>Início</span></a><a href="#produtos"><span>Produtos</span></a><?php if(!$eledsClient): ?><a href="#categorias"><span>Grupos</span></a><?php endif; ?><?php if(!$eledsClient): ?><a href="#sobre"><span>Sobre</span></a><?php endif; ?><?php if(!$eledsClient): ?><a href="catalogo_pdf.php?auto=1" target="_blank"><span>Catálogo PDF</span></a><?php endif; ?><?php if(isset($_SESSION['admin_ok'])): ?><a href="orcamentos.php" class="nav-quotes"><span>Meus orçamentos</span></a><a href="portal_cliente.php" class="eleds-client-access"><span>👤 Área do Cliente</span></a><a href="dashboard.php" class="catalog-user" aria-label="Meu perfil"><?php if(strcasecmp((string)($_SESSION['admin_user'] ?? ''), 'admin') === 0): ?><img src="assets/eleds-aguia-avatar.png" alt="Águia ELEDS"><?php elseif($loggedUserPhoto): ?><img src="<?=e($loggedUserPhoto)?>" alt="Foto de <?=e($_SESSION['admin_user'] ?? 'usuário')?>"><?php else: ?>👤<?php endif; ?><span><?=e($_SESSION['admin_user'] ?? '')?></span></a><?php else: ?><a href="portal_cliente.php" class="eleds-client-access"><span>👤 Área do Cliente</span></a><?php if(!$eledsClient): ?><a href="admin.php" class="eleds-admin-access"><span>🔒 Administração</span></a><?php endif; ?><?php endif; ?><?php if($eledsClient): ?><a href="portal_cliente.php?historico=1" class="eleds-client-access">🛒 Meu orçamento <span id="eledsCartCount"></span></a><a href="portal_cliente.php?sair=1">Sair (<?=e($eledsClient['name'])?>)</a><?php endif; ?></nav><form class="mini-search" action="index.php" method="get"><span>⌕</span><input name="q" value="<?=e($q)?>" placeholder="Pesquisar..."></form></div></header>
<?php if(!$eledsClient): ?><section class="eleds-banner" aria-label="ELEDS: iluminação para linha leve e pesada"><div class="eleds-banner-wrap"><img src="assets/banner-eleds-veiculos.png" alt="ELEDS: Mais tecnologia para todos os caminhos. Carro, utilitário e caminhão com iluminação LED." fetchpriority="high"><a class="eleds-banner-hotspot eleds-banner-products" href="#produtos" aria-label="Explorar produtos"></a><a class="eleds-banner-hotspot eleds-banner-catalog" href="catalogo_pdf.php?auto=1" target="_blank" rel="noopener" aria-label="Ver catálogo PDF"></a></div><div class="eleds-banner-mobile-links"><a href="#produtos">Explorar produtos →</a><a href="catalogo_pdf.php?auto=1" target="_blank" rel="noopener">Ver catálogo PDF</a></div></section><?php endif; ?>
<main class="container"><span id="categorias" class="eleds-anchor"></span><section class="big-search"><form action="index.php" method="get"><span class="search-icon">⌕</span><input id="mainSearch" name="q" value="<?=e($q)?>" placeholder="Pesquise código, produto, modelo, tensão, marca ou aplicação..."><?php if($category):?><input type="hidden" name="categoria" value="<?=e($category)?>"><?php endif;?><button type="submit">Buscar</button></form></section>

<section class="catalog-layout" id="produtos"><aside class="sidebar"><h3>Grupos de Produtos</h3><a class="side-cat <?=!$category?'active':''?>" href="index.php#imagens-produtos"><span>Todos</span><b><?=count($products)?></b></a><?php foreach($categories as $cat=>$count):?><a class="side-cat <?=$category===$cat?'active':''?>" href="?categoria=<?=urlencode($cat)?>#imagens-produtos"><span><?=e($cat)?></span><b><?=$count?></b></a><?php endforeach;?><div class="side-whatsapp"><div class="wa-symbol">◉</div><strong>Solicite seu<br>orçamento pelo<br>WhatsApp</strong><a href="https://wa.me/<?=e($defaultWaNumber)?>" target="_blank" rel="noopener"><?=e($defaultWhatsapp['display'] ?? '')?></a><?php if(count($whatsapps)>1): ?><div class="wa-extra"><?php foreach($whatsapps as $w): ?><a href="https://wa.me/<?=e(preg_replace('/\D+/', '', $w['number'] ?? ''))?>" target="_blank" rel="noopener"><?=e($w['name'] ?? $w['display'] ?? '')?> — <?=e($w['display'] ?? '')?></a><?php endforeach; ?></div><?php endif; ?></div><div class="side-benefits"><p><b>✓</b> Qualidade e<br>confiabilidade</p><p><b>◇</b> As melhores marcas<br>do mercado</p><p><b>♙</b> Atendimento<br>especializado</p></div></aside>
<section class="products-area"><div class="products-heading"><div><span class="section-kicker">CATÁLOGO</span><h2><?= $category?e($category):'Produtos' ?></h2><p><?=$totalProducts?> produto(s) <?= $q?'encontrado(s) para “'.e($q).'”':'no catálogo' ?> · Página <?=$currentPage?> de <?=$totalPages?></p></div><form method="get" class="sort-form"><?php if($q):?><input type="hidden" name="q" value="<?=e($q)?>"><?php endif;?><?php if($category):?><input type="hidden" name="categoria" value="<?=e($category)?>"><?php endif;?><label>Ordenar por:<select name="ordem" onchange="this.form.submit()"><option value="">Mais recentes</option><option value="az" <?=$order==='az'?'selected':''?>>A-Z</option><option value="codigo" <?=$order==='codigo'?'selected':''?>>Código</option></select></label></form></div>
<?php if(!$displayProducts):?><div class="empty"><div>⌕</div><h3>Nenhum produto encontrado</h3><p>Tente pesquisar por outro código, nome ou grupo.</p><a href="index.php">Ver todos os produtos</a></div><?php else:?><div class="product-grid" id="imagens-produtos"><?php foreach($displayProducts as $product): $waText='Olá! Gostaria de um orçamento do produto '.$product['code'].' - '.$product['name'].'.';?><article class="product-card"><div class="product-photo"><?php if(!empty($product['image'])):?><img src="<?=e($product['image'])?>" alt="<?=e($product['name'])?>" loading="lazy"><?php else:?><div class="photo-placeholder"><span>ELEDS</span></div><?php endif;?></div><div class="product-info"><small class="product-code">CÓDIGO: <?=e($product['code'])?></small><h3><?=e($product['name'])?></h3><p class="brand">MARCA: <?=e($product['brand']??'ELEDS')?></p><?php if(!empty($product['application'])):?><p class="application"><?=e($product['application'])?></p><?php endif;?><?php if(!empty($product['measures'])):?><p class="application"><b>MEDIDAS:</b> <?=e($product['measures'])?></p><?php endif;?><?php if($eledsClient): ?><p class="eleds-client-price"><b>Preço: <?=isset($eledsPrices[(string)$product['code']]) ? 'R$ '.number_format($eledsPrices[(string)$product['code']],2,',','.') : 'Não cadastrado'?></b></p><?php endif; ?><div class="product-actions"><button class="details-btn" type="button" onclick='openProduct(<?=json_encode($eledsClient ? $product : array_diff_key($product, ["price"=>true]),JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP)?>)'>⌕ Ver detalhes</button><?php if(!$eledsClient): ?><a class="product-whatsapp" href="https://wa.me/<?=e($defaultWaNumber)?>?text=<?=urlencode($waText)?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?><button class="quote-add" type="button" data-code="<?=e($product['code'])?>" data-name="<?=e($product['name'])?>" onclick="<?= $eledsClient ? 'eledsAddClient(this.dataset.code)' : 'addQuote(this.dataset.code,this.dataset.name,this)' ?>">＋ Adicionar ao orçamento</button></div></div></article><?php endforeach;?></div><?php if($totalPages > 1): ?><nav class="admin-pagination catalog-pagination" aria-label="Paginação de produtos"><?php if($currentPage > 1): ?> <a href="?<?=e(http_build_query($paginationParams + ['pagina' => $currentPage - 1]))?>#imagens-produtos">‹ Anterior</a><?php endif; ?><?php for($p=1; $p<=$totalPages; $p++): ?><a class="<?=$p===$currentPage?'active':''?>" href="?<?=e(http_build_query($paginationParams + ['pagina' => $p]))?>#imagens-produtos" <?=$p===$currentPage?'aria-current="page"':''?>><?=$p?></a><?php endfor; ?><?php if($currentPage < $totalPages): ?><a href="?<?=e(http_build_query($paginationParams + ['pagina' => $currentPage + 1]))?>#imagens-produtos">Próxima ›</a><?php endif; ?></nav><?php endif; ?><?php endif;?></section></section>
<section id="sobre" class="info-strip"><div><strong>Catálogo 2026/2027</strong><span>Produtos para linha leve e pesada</span></div><div><strong>Pesquisa rápida</strong><span>Código, descrição, marca e aplicação</span></div><div><strong>Atendimento</strong><span>Solicite seu orçamento pelo WhatsApp</span></div></section></main>
<footer id="contato"><div class="container footer-inner"><div class="footer-brand"><span>ELEDS</span><strong>LINHA LEVE E PESADA</strong></div><p>Iluminação e acessórios para carros, utilitários, caminhões e implementos.</p><div class="footer-brands">VOLVO &nbsp; MERCEDES-BENZ &nbsp; SCANIA &nbsp; FORD &nbsp; VW &nbsp; DAF</div></div><div class="copyright">© 2026 — Catálogo Linha Leve e Pesada. Todos os direitos reservados.</div></footer>
<a class="floating-whatsapp" href="https://wa.me/<?=e($defaultWaNumber)?>" target="_blank" rel="noopener" aria-label="Pedir orçamento pelo WhatsApp">◉ <span>Pedir orçamento</span></a>
<div id="productModal" class="modal" aria-hidden="true"><div class="modal-box"><button class="modal-close" onclick="closeProduct()">×</button><div><div class="modal-photo"><img id="modalImage" src="" alt=""><span id="modalImageFallback">ELEDS</span><small class="zoom-hint">Clique na imagem para ampliar</small></div><div id="modalGallery" class="modal-gallery"></div></div><div><span id="modalCategory" class="section-kicker"></span><h2 id="modalName"></h2><p><b>Código:</b> <span id="modalCode"></span></p><p><b>Marca:</b> <span id="modalBrand">ELEDS</span></p><p id="modalApplicationWrap"><b>Aplicação:</b> <span id="modalApplication"></span></p><p id="modalMeasuresWrap" style="display:none"><b>Medidas:</b> <span id="modalMeasures"></span></p><?php if($eledsClient): ?><p id="eledsModalPrice" class="eleds-client-price"></p><?php endif; ?><button type="button" class="quote-add quote-add-modal" id="modalQuoteBtn">＋ Adicionar ao orçamento</button> <?php if(!$eledsClient): ?><a class="modal-whatsapp" id="modalWhatsapp" target="_blank" rel="noopener">Pedir orçamento pelo WhatsApp</a><?php endif; ?></div></div></div>
<div id="imageZoom" class="image-zoom" aria-hidden="true">
<div class="zoom-toolbar"><button type="button" onclick="zoomOut()" aria-label="Diminuir zoom">−</button><span id="zoomPercent">100%</span><button type="button" onclick="zoomIn()" aria-label="Aumentar zoom">+</button><button type="button" onclick="resetZoom()" aria-label="Restaurar zoom">↺</button><button type="button" class="zoom-close" onclick="closeImageZoom()" aria-label="Fechar zoom">×</button></div>
<div class="zoom-stage"><img id="zoomImage" src="" alt=""></div>
<p class="zoom-help">Clique na imagem para ampliar • Use + e − ou a roda do mouse</p>
</div>
<script>window.DEFAULT_WHATSAPP = <?=json_encode($defaultWaNumber)?>; window.DEFAULT_WHATSAPP_DISPLAY = <?=json_encode($defaultWhatsapp['display'] ?? '')?>; window.QUOTE_LOGGED_IN = <?=isset($_SESSION['admin_ok']) ? 'true' : 'false'?>; window.QUOTE_USER = <?=json_encode($_SESSION['admin_user'] ?? '')?>; window.LOAD_QUOTE_ID = <?=json_encode(trim($_GET['orcamento'] ?? ''))?>;</script><script src="assets/js/app.js"></script><script src="assets/js/orcamento.js"></script>
<?php if($eledsClient): ?>
<style>.eleds-client-price{font-size:19px;color:#087a43;font-weight:800;margin:12px 0}.eleds-cart-float{position:fixed;bottom:22px;left:22px;z-index:1000;background:#e91b39;color:white;padding:15px 22px;border-radius:12px;box-shadow:0 5px 22px #0003;text-decoration:none;font-weight:800}</style>
<a href="portal_cliente.php?historico=1" class="eleds-cart-float">🛒 Meu orçamento <span id="eledsCartFloatCount"></span></a>
<script>
const eledsClientPrices = <?=json_encode($eledsPrices, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const eledsCartKey = 'eleds_cart_<?=e(preg_replace('/[^a-zA-Z0-9_-]/','',(string)$eledsClient['id']))?>';
function eledsGetCart(){try {const v=JSON.parse(localStorage.getItem(eledsCartKey)||'{}');return v&&typeof v==='object'&&!Array.isArray(v)?v:{};}catch(e){return {};}}
function eledsUpdateCount(){const count=Object.values(eledsGetCart()).reduce((a,v)=>a+Number(v||0),0);for(const id of ['eledsCartCount','eledsCartFloatCount']){const el=document.getElementById(id);if(el)el.textContent='('+count+')';}}
function eledsAddClient(code){if(!Object.prototype.hasOwnProperty.call(eledsClientPrices,code)){alert('Produto sem preço cadastrado.');return;}const cart=eledsGetCart();cart[code]=Math.min(999,(Number(cart[code])||0)+1);localStorage.setItem(eledsCartKey,JSON.stringify(cart));eledsUpdateCount();alert('Produto adicionado ao orçamento!');}
const eledsOriginalOpen=window.openProduct;
if(typeof eledsOriginalOpen==='function')window.openProduct=function(product){eledsOriginalOpen(product);const el=document.getElementById('eledsModalPrice');if(el){const price=eledsClientPrices[product.code];el.textContent=typeof price==='number'?'Preço: '+price.toLocaleString('pt-BR',{style:'currency',currency:'BRL'}):'Preço não cadastrado';}const btn=document.getElementById('modalQuoteBtn');if(btn)btn.onclick=function(event){event.preventDefault();event.stopImmediatePropagation();eledsAddClient(product.code);};};
eledsUpdateCount();
</script>
<?php endif; ?>
</body></html>
