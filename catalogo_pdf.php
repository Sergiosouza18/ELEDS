<?php
// Catálogo em PDF: página de impressão A4 com todos os produtos cadastrados.
$products = json_decode(@file_get_contents(__DIR__ . '/data/products.json'), true) ?: [];
$groups = json_decode(@file_get_contents(__DIR__ . '/data/groups.json'), true) ?: [];

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function productImage($p) {
    if (!empty($p['image'])) return $p['image'];
    if (!empty($p['images']) && is_array($p['images'])) return $p['images'][0] ?? '';
    return '';
}
function shortName($name, $max = 72) {
    $name = trim((string)$name);
    if (function_exists('mb_strimwidth')) return mb_strimwidth($name, 0, $max, '…', 'UTF-8');
    return strlen($name) > $max ? substr($name, 0, $max - 1) . '…' : $name;
}

// Organiza os produtos por grupo, respeitando a ordem definida em groups.json.
$groupOrder = [];
foreach ($groups as $group) {
    if (!empty($group['name'])) $groupOrder[] = trim((string)$group['name']);
}
$grouped = [];
foreach ($products as $product) {
    $category = trim((string)($product['category'] ?? 'Sem grupo'));
    if ($category === '') $category = 'Sem grupo';
    $grouped[$category][] = $product;
}
$orderedNames = array_values(array_filter($groupOrder, fn($name) => isset($grouped[$name])));
$extraNames = array_values(array_diff(array_keys($grouped), $orderedNames));
 natcasesort($extraNames);
$orderedNames = array_merge($orderedNames, array_values($extraNames));
// Dentro de cada grupo, ordena os produtos pelo código em ordem natural
// (ex.: ALDD-2009, ALDD-2010, ALDD-2011 ... ALDD-2019, ALDD-2020).
foreach ($grouped as &$groupProducts) {
    usort($groupProducts, function ($a, $b) {
        $codeA = trim((string)($a['code'] ?? ''));
        $codeB = trim((string)($b['code'] ?? ''));
        return strnatcasecmp($codeA, $codeB);
    });
}
unset($groupProducts);

$pages = [];
foreach ($orderedNames as $groupName) {
    foreach (array_chunk($grouped[$groupName], 16) as $groupPage) {
        $pages[] = ['group' => $groupName, 'products' => $groupPage];
    }
}
$totalPages = count($pages);
$whatsapps = json_decode(@file_get_contents(__DIR__ . '/data/whatsapp.json'), true) ?: [];
$defaultWa = $whatsapps[0]['display'] ?? '(87) 98117-7326';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Catálogo ELEDS Linha Leve e Pesada 2026 / 2027</title>
<style>
@page { size:A4 portrait; margin:0; }
*{box-sizing:border-box} html,body{margin:0;padding:0;background:#e7e9ed;font-family:Arial,Helvetica,sans-serif;color:#191919}body{-webkit-print-color-adjust:exact;print-color-adjust:exact}
.toolbar{position:fixed;top:12px;left:50%;transform:translateX(-50%);z-index:999;display:flex;gap:9px;padding:9px 12px;background:#111820;border-radius:9px;box-shadow:0 8px 25px #0004}.toolbar button,.toolbar a{border:0;border-radius:6px;padding:10px 14px;background:#e91e32;color:white;font-weight:bold;text-decoration:none;cursor:pointer;font-size:12px}.toolbar a{background:#35404a}
.pdf-page{width:210mm;height:297mm;margin:10mm auto;position:relative;overflow:hidden;page-break-after:always;break-after:page;background:white}.pdf-page:last-child{page-break-after:auto;break-after:auto}.cover img{width:100%;height:100%;object-fit:cover}
.products-page{padding:13mm 11mm 14mm;background:#fff}.catalog-header{height:24mm;display:flex;justify-content:space-between;align-items:center;gap:7mm;border-bottom:.6mm solid #f1f1f1;margin-bottom:5mm}.catalog-header img{width:42mm;height:20mm;object-fit:contain;object-position:left center}.catalog-header .slogan{font-size:6.5pt;color:#555;letter-spacing:.2px}.category-header{display:flex;align-items:center;justify-content:flex-end;gap:3mm;min-width:70mm}.category-header:before{content:'❮❮';color:#ec1c32;font-size:14pt;letter-spacing:-3px}.category-header strong{display:block;min-width:65mm;background:#ec1c32;color:white;font-size:10pt;text-align:center;padding:2.5mm 5mm;text-transform:uppercase;letter-spacing:1px;font-weight:700}
.watermark{position:absolute;top:82mm;left:15mm;right:15mm;text-align:center;font-size:105pt;font-weight:900;font-style:italic;color:rgba(220,22,46,.035);transform:rotate(-16deg);pointer-events:none;z-index:0}
.product-grid{position:relative;z-index:1;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));grid-template-rows:repeat(4,minmax(0,1fr));gap:3mm 3.5mm;height:229mm}.card{position:relative;min-width:0;min-height:0;padding:1.5mm 1mm 5mm;display:flex;flex-direction:column;overflow:hidden}.card:after{content:'';position:absolute;bottom:1.5mm;left:0;right:0;height:2.2mm;background:linear-gradient(120deg,#f3b1ba 0 27%,#ed1c32 27% 100%)}.code{font-size:7pt;font-weight:900;min-height:8mm;line-height:1.15;overflow:hidden}.card-photo{height:26mm;display:flex;align-items:center;justify-content:center;overflow:hidden}.card-photo img{width:100%;height:100%;object-fit:contain}.placeholder{color:#ed1c32;font-weight:bold}.name{font-size:7pt;line-height:1.13;font-weight:800;text-transform:uppercase;margin-top:1.2mm;max-height:10mm;overflow:hidden}.app{font-size:5.8pt;line-height:1.2;color:#3b3b3b;margin-top:1mm;max-height:11mm;overflow:hidden}.brand{display:none}.footer{position:absolute;bottom:5mm;right:9mm;background:#f7d5da;border-radius:50%;width:12mm;height:10mm;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:9pt}
@media print{html,body{background:white}.toolbar{display:none!important}.pdf-page{margin:0}}@media screen{.pdf-page{box-shadow:0 8px 25px #0003}}
</style>
</head>
<body>
<div class="toolbar">
  <button type="button" onclick="window.print()">📄 Salvar / Imprimir PDF</button>
  <a href="index.php">← Voltar ao catálogo</a>
</div>

<section class="pdf-page cover">
  <img src="assets/catalogo/capa_eleds.png" alt="Capa do catálogo ELEDS Linha Leve e Pesada 2026 / 2027">
</section>

<?php foreach ($pages as $pageIndex => $pageData): $pageProducts = $pageData['products']; ?>
<section class="pdf-page products-page">
  <header class="catalog-header">
    <div><img src="assets/eleds-logo.png" alt="ELEDS"><div class="slogan">THE EAGLE'S VISION ON AND OFF ROADS.</div></div>
    <div class="category-header"><strong><?=e($pageData['group'])?></strong></div>
  </header>
  <div class="watermark">ELEDS</div>
  <div class="product-grid">
    <?php foreach ($pageProducts as $product): $img=productImage($product); ?>
    <article class="card">
      <div class="code"><?=e($product['code'] ?? '')?></div>
      <div class="card-photo"><?php if($img): ?><img src="<?=e($img)?>" alt="<?=e($product['name'] ?? '')?>"><?php else: ?><span class="placeholder">ELEDS</span><?php endif; ?></div>
      <div class="name"><?=e(shortName($product['name'] ?? 'Produto', 66))?></div>
      <?php if(!empty($product['application'])): ?><div class="app"><?=e($product['application'])?></div><?php endif; ?>
      <?php if(!empty($product['measures'])): ?><div class="app"><?=e($product['measures'])?></div><?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
  <div class="footer"><?=str_pad((string)($pageIndex+1),2,'0',STR_PAD_LEFT)?></div>
</section>
<?php endforeach; ?>
<script>
window.addEventListener('load', function () {
  if (new URLSearchParams(location.search).get('auto') === '1') {
    setTimeout(function(){ window.print(); }, 700);
  }
});
</script>
</body>
</html>
