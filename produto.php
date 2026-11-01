<?php
session_start();
$products = json_decode(file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
$whatsapps = json_decode(file_get_contents(__DIR__ . '/data/whatsapp.json'), true) ?: [];
$defaultWhatsapp = $whatsapps[0] ?? ['number'=>'5587981177326'];
foreach ($whatsapps as $w) { if (!empty($w['default'])) { $defaultWhatsapp = $w; break; } }
$defaultWaNumber = preg_replace('/\D+/', '', $defaultWhatsapp['number'] ?? '');
$code = trim($_GET['codigo'] ?? '');

$product = null;
foreach ($products as $p) {
    if (($p['code'] ?? '') === $code) {
        $product = $p;
        break;
    }
}
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $product ? e($product['name']) : 'Produto não encontrado' ?> — HE</title>
<link rel="stylesheet" href="assets/css/style.css?v=20261002-layoutfix1">
</head>
<body>
<header class="site-header"><div class="container header-inner">
<a href="index.php" class="logo"><span class="logo-he">ELEDS</span><span><strong>LINHA LEVE E PESADA</strong><small>CATÁLOGO DIGITAL</small></span></a>
</div></header>
<main class="container detail-page">
<?php if ($product): ?>
<div class="detail-card">
<?php $detailImages = !empty($product['images']) && is_array($product['images']) ? $product['images'] : (!empty($product['image']) ? [$product['image']] : []); ?>
<div><div class="detail-photo"><?php if (!empty($detailImages)): ?><img id="detailMainImage" src="<?=e($detailImages[0])?>" alt="<?=e($product['name'])?>"><?php else: ?><span>HE</span><?php endif; ?></div><?php if(count($detailImages)>1): ?><div class="detail-gallery"><?php foreach($detailImages as $im): ?><button type="button" onclick="document.getElementById('detailMainImage').src=this.dataset.src" data-src="<?=e($im)?>"><img src="<?=e($im)?>" alt=""></button><?php endforeach; ?></div><?php endif; ?></div>
<div class="detail-content">
<span class="section-kicker"><?= e($product['category']) ?></span>
<h1><?= e($product['name']) ?></h1>
<p><b>Código:</b> <?= e($product['code']) ?></p>
<p><b>Marca:</b> <?= e($product['brand'] ?? 'HE') ?></p>
<?php if (!empty($product['application'])): ?><p><b>Aplicação:</b> <?= e($product['application']) ?></p><?php endif; ?>
<?php if (!empty($product['measures'])): ?><p><b>Medidas:</b> <?= e($product['measures']) ?></p><?php endif; ?>
<a class="details-btn big" href="https://wa.me/<?=e($defaultWaNumber)?>?text=<?= urlencode('Olá! Gostaria de um orçamento do produto '.($product['code']??'').' - '.($product['name']??'')) ?>" target="_blank" rel="noopener">Pedir orçamento pelo WhatsApp</a> <button type="button" class="quote-add quote-add-modal" data-code="<?=e($product['code'])?>" data-name="<?=e($product['name'])?>" onclick="addQuote(this.dataset.code,this.dataset.name,this)">＋ Adicionar ao orçamento</button>
</div>
</div>
<?php else: ?>
<div class="empty"><h2>Produto não encontrado.</h2><a href="index.php">Voltar ao catálogo</a></div>
<?php endif; ?>
</main>
<script>window.DEFAULT_WHATSAPP = <?=json_encode($defaultWaNumber)?>; window.DEFAULT_WHATSAPP_DISPLAY = <?=json_encode($defaultWhatsapp['display'] ?? '')?>; window.QUOTE_LOGGED_IN = <?=isset($_SESSION['admin_ok']) ? 'true' : 'false'?>; window.QUOTE_USER = <?=json_encode($_SESSION['admin_user'] ?? '')?>; window.LOAD_QUOTE_ID = <?=json_encode(trim($_GET['orcamento'] ?? ''))?>;</script><script src="assets/js/orcamento.js"></script>
</body>
</html>
