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
    foreach (array_chunk($grouped[$groupName], 9) as $groupPage) {
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
@page { size: A4 portrait; margin: 0; }
* { box-sizing: border-box; }
html, body { margin:0; padding:0; background:#dfe5ea; font-family: Arial, Helvetica, sans-serif; color:#111; }
body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.toolbar { position:fixed; top:14px; left:50%; transform:translateX(-50%); z-index:999; display:flex; gap:8px; padding:9px 12px; background:#061522; border-radius:10px; box-shadow:0 8px 30px #0005; }
.toolbar button, .toolbar a { border:0; border-radius:7px; padding:10px 14px; background:#0879e8; color:#fff; font-weight:800; text-decoration:none; cursor:pointer; font-size:12px; }
.toolbar a { background:#29445a; }
.pdf-page { width:210mm; height:297mm; margin:10mm auto; position:relative; overflow:hidden; page-break-after:always; break-after:page; background:#fff; }
.pdf-page:last-child { page-break-after:auto; break-after:auto; }
.cover { background:#061522; display:flex; align-items:center; justify-content:center; }
.cover img { width:100%; height:100%; object-fit:cover; }
.cover-info { position:absolute; left:16mm; bottom:15mm; color:#fff; font-weight:800; font-size:9pt; letter-spacing:.3px; }
.products-page { background:#101092; padding:8mm 7.5mm 14mm; }
.page-inner { height:100%; background:#fff; border-radius:7mm; padding:4mm; display:flex; flex-direction:column; }
.page-title { display:flex; align-items:center; justify-content:space-between; padding:0 1mm 3mm; min-height:11mm; }
.page-title strong { color:#0d168c; font-size:11pt; text-transform:uppercase; letter-spacing:.5px; }
.page-title span { color:#5b6a78; font-size:7pt; font-weight:700; }
.product-grid { flex:1; display:grid; grid-template-columns:repeat(3, 1fr); grid-template-rows:repeat(3, 1fr); gap:3mm; }
.card { min-width:0; min-height:0; border:0.45mm solid #101092; border-radius:5mm; padding:2.7mm; display:flex; flex-direction:column; background:#fff; overflow:hidden; }
.card-photo { height:52mm; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.card-photo img { width:100%; height:100%; object-fit:contain; }
.placeholder { color:#0d168c; font-size:22pt; font-weight:900; font-style:italic; }
.code { color:#11149a; font-size:7pt; font-weight:900; text-transform:uppercase; margin-top:1.2mm; }
.name { font-family: Impact, Haettenschweiler, 'Arial Narrow Bold', Arial, sans-serif; font-size:10.5pt; line-height:1.05; text-transform:uppercase; margin-top:1.2mm; min-height:17mm; overflow:hidden; }
.brand { color:#11149a; font-size:6.5pt; font-weight:900; margin-top:auto; text-transform:uppercase; }
.app { color:#44515c; font-size:5.8pt; line-height:1.15; margin-top:1mm; max-height:8mm; overflow:hidden; }
.footer { position:absolute; left:0; right:0; bottom:0; height:13mm; color:#fff; display:flex; align-items:center; justify-content:space-between; padding:0 10mm; font-size:7pt; font-weight:800; }
.footer-logo { display:flex; align-items:center; gap:2mm; }
.footer-logo img { width:9mm; height:9mm; object-fit:contain; filter:brightness(0) invert(1); }
.footer-number { font-size:10pt; }
@media print {
  html, body { background:#fff; }
  .toolbar { display:none !important; }
  .pdf-page { margin:0; }
}
@media screen {
  .pdf-page { box-shadow:0 10px 35px #0003; }
}
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
  <div class="page-inner">
    <div class="page-title">
      <strong><?=e($pageData['group'])?></strong>
      <span>2026 / 2027</span>
    </div>
    <div class="product-grid">
      <?php foreach ($pageProducts as $product):
        $img = productImage($product);
      ?>
      <article class="card">
        <div class="card-photo">
          <?php if ($img): ?><img src="<?=e($img)?>" alt="<?=e($product['name'] ?? '')?>"><?php else: ?><div class="placeholder">HE</div><?php endif; ?>
        </div>
        <div class="code">CÓDIGO: <?=e($product['code'] ?? '')?></div>
        <div class="name"><?=e(shortName($product['name'] ?? 'Produto'))?></div>
        <?php if (!empty($product['application'])): ?><div class="app"><?=e($product['application'])?></div><?php endif; ?><?php if (!empty($product['measures'])): ?><div class="app"><b>MEDIDAS:</b> <?=e($product['measures'])?></div><?php endif; ?>
        <div class="brand">MARCA: <?=e($product['brand'] ?? 'HE')?></div>
      </article>
      <?php endforeach; ?>
      <?php for ($i=count($pageProducts); $i<9; $i++): ?><div></div><?php endfor; ?>
    </div>
  </div>
  <div class="footer">
    <div class="footer-logo"><img src="assets/eleds-logo.png" alt="HE"><span>LINHA LEVE E PESADA</span></div>
    <div>Atendimento: <?=e($defaultWa)?></div>
    <div class="footer-number"><?=str_pad((string)($pageIndex + 1), 2, '0', STR_PAD_LEFT)?></div>
  </div>
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
