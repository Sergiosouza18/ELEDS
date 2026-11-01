<?php
session_start();
if (empty($_SESSION['admin_ok'])) { header('Location: admin.php'); exit; }
require_once __DIR__ . '/admin_lib.php';
$dataFile = __DIR__ . '/data/products.json'; $groupsFile = __DIR__ . '/data/groups.json';
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_ok() { return hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? '')); }
function no_photo($p) { foreach (he_imgs($p) as $i) if (is_file(__DIR__ . '/' . $i)) return false; return true; }
function no_app($p) { return trim((string)($p['application'] ?? '')) === ''; }

$products = json_decode((string)@file_get_contents($dataFile), true) ?: [];
$msg = ''; $errors = [];

// ---- Exportar Excel ----
if (isset($_GET['exportar'])) {
    $f = $_GET['filtro'] ?? 'todos';
    $list = array_values(array_filter($products, fn($p) => $f === 'sem_foto' ? no_photo($p) : ($f === 'sem_aplicacao' ? no_app($p) : true)));
    $rows = [['Código', 'Nome', 'Grupo', 'Marca', 'Aplicação', 'Medidas', 'Fotos']];
    foreach ($list as $p) $rows[] = [$p['code'] ?? '', $p['name'] ?? '', $p['category'] ?? '', $p['brand'] ?? '', $p['application'] ?? '', $p['measures'] ?? '', count(he_imgs($p))];
    $name = 'catalogo_eleds_' . ($f === 'todos' ? '' : $f . '_') . date('Ymd');
    if (class_exists('ZipArchive')) {
        $tmp = he_xlsx_build($rows, [16, 60, 24, 12, 40, 24, 8]);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $name . '.xlsx"');
        readfile($tmp); @unlink($tmp); exit;
    }
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="' . $name . '.csv"');
    echo "\xEF\xBB\xBF"; $out = fopen('php://output', 'w'); foreach ($rows as $r) fputcsv($out, $r, ';'); exit;
}

// ---- Baixar backup ----
if (isset($_GET['baixar']) && preg_match('/^products_\d{8}_\d{6}_\d{3}\.json$/', $_GET['baixar']) && is_file(he_backup_dir() . '/' . $_GET['baixar'])) {
    header('Content-Type: application/json'); header('Content-Disposition: attachment; filename="' . $_GET['baixar'] . '"');
    readfile(he_backup_dir() . '/' . $_GET['baixar']); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $errors[] = 'Sessão expirada. Recarregue a página e tente de novo.'; }
    elseif (($_POST['action'] ?? '') === 'importar') {
        $up = $_FILES['planilha'] ?? null; $ext = strtolower(pathinfo($up['name'] ?? '', PATHINFO_EXTENSION));
        if (!$up || !is_uploaded_file($up['tmp_name'] ?? '') || !in_array($ext, ['xlsx', 'csv'], true)) $errors[] = 'Envie um arquivo .xlsx ou .csv.';
        elseif ($ext === 'xlsx' && !class_exists('ZipArchive')) $errors[] = 'O PHP deste servidor não tem a extensão zip. Salve a planilha como CSV e envie de novo.';
        else {
            $rows = $ext === 'xlsx' ? he_xlsx_read($up['tmp_name']) : he_csv_read($up['tmp_name']);
            if (!$rows) $errors[] = 'Não foi possível ler a planilha.';
            else {
                $groups = json_decode((string)@file_get_contents($groupsFile), true) ?: [];
                [$new, $newGroups, $st] = he_import_rows($rows, $products, array_column($groups, 'name'));
                $errors = $st['erros'];
                if ($st['criados'] || $st['atualizados']) {
                    $GLOBALS['he_history_note'] = 'importação de ' . basename($up['name']);
                    if (he_save_products($dataFile, $new)) {
                        foreach ($newGroups as $g) $groups[] = ['id' => uniqid('grp_', true), 'name' => $g];
                        if ($newGroups) file_put_contents($groupsFile, json_encode(array_values($groups), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
                        $products = $new;
                    } else $errors[] = 'Não foi possível gravar. Verifique a permissão da pasta data.';
                }
                $msg = "Importação concluída: {$st['criados']} criado(s), {$st['atualizados']} atualizado(s), {$st['sem_mudanca']} sem mudança.";
            }
        }
    }
    elseif (($_POST['action'] ?? '') === 'restaurar' && preg_match('/^products_\d{8}_\d{6}_\d{3}\.json$/', $_POST['arquivo'] ?? '')) {
        $bk = he_backup_dir() . '/' . $_POST['arquivo']; $raw = is_file($bk) ? file_get_contents($bk) : '';
        $arr = json_decode($raw, true);
        if (!is_array($arr)) $errors[] = 'Backup inválido.';
        else {
            $GLOBALS['he_history_note'] = 'restauração do backup ' . $_POST['arquivo'];
            he_before_save($dataFile, $arr); // guarda o estado atual antes de restaurar
            file_put_contents($dataFile, $raw, LOCK_EX);
            $products = $arr; $msg = 'Backup restaurado. O estado anterior também foi salvo como backup.';
        }
    }
}

$semFoto = array_values(array_filter($products, 'no_photo')); $semApp = array_values(array_filter($products, 'no_app'));
$backups = glob(he_backup_dir() . '/products_*.json') ?: []; rsort($backups);
$hist = []; $hf = __DIR__ . '/data/history.jsonl';
if (is_file($hf)) foreach (array_reverse(array_slice(file($hf, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -150)) as $l) if ($c = json_decode($l, true)) $hist[] = $c;
function pend_table($list, $limit = 100) {
    if (!$list) return '<p>Nenhum produto pendente. ✅</p>';
    $h = '<div class="admin-table-wrap" style="max-height:360px;overflow:auto"><table class="admin-table"><thead><tr><th>Código</th><th>Produto</th><th>Grupo</th><th></th></tr></thead><tbody>';
    foreach (array_slice($list, 0, $limit) as $p) $h .= '<tr><td><strong>' . e($p['code']) . '</strong></td><td>' . e($p['name']) . '</td><td>' . e($p['category'] ?? '') . '</td><td><a href="admin.php?editar=' . urlencode($p['code']) . '#form-produto">Editar</a></td></tr>';
    return $h . '</tbody></table></div>' . (count($list) > $limit ? '<small>Mostrando ' . $limit . ' de ' . count($list) . '. Use o Excel para trabalhar com a lista completa.</small>' : '');
}
?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Ferramentas — ELEDS Linha Leve e Pesada</title><link rel="stylesheet" href="assets/css/style.css?v=20261002-layoutfix1"></head><body class="admin-page">
<header class="admin-header"><div class="container admin-header-inner"><a href="index.php" class="logo logo-image"><img src="assets/eleds-logo.png" alt="ELEDS Linha Leve e Pesada"></a><div class="admin-actions"><span>👤 <?= e($_SESSION['admin_user'] ?? '') ?></span><a href="admin.php">Produtos</a><a href="admin.php?logout=1">Sair</a></div></div></header>
<main class="container admin-main">
<div class="admin-title"><div><span class="section-kicker">ADMINISTRAÇÃO</span><h1>Ferramentas</h1><p><?= count($products) ?> produtos · <?= count($semFoto) ?> sem foto · <?= count($semApp) ?> sem aplicação</p></div></div>
<?php if ($msg): ?><div class="admin-alert success"><?= e($msg) ?></div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="admin-alert" style="background:#fdecea;color:#8a1c1c"><?= e($er) ?></div><?php endforeach; ?>

<section class="admin-panel" id="excel" style="margin-bottom:18px"><h2>Excel: exportar e importar</h2>
<p><a class="admin-primary" href="?exportar=1">⬇ Exportar todos os produtos</a> &nbsp; <a href="?exportar=1&filtro=sem_aplicacao">só sem aplicação</a> · <a href="?exportar=1&filtro=sem_foto">só sem foto</a></p>
<form method="post" enctype="multipart/form-data" class="admin-form"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="importar">
<label>Planilha (.xlsx ou .csv)<input type="file" name="planilha" accept=".xlsx,.csv" required></label>
<button class="admin-primary" type="submit" onclick="return confirm('Importar esta planilha? Um backup é criado automaticamente antes.')">Importar</button></form>
<small>Colunas reconhecidas na 1ª linha: <b>Código</b> (obrigatória), Nome, Grupo, Marca, Aplicação, Medidas. Código existente: atualiza só as células preenchidas. Código novo: cria o produto (precisa de nome), sem foto. Fotos continuam sendo enviadas pelo painel de produtos. Dica: exporte "só sem aplicação", preencha no Excel e importe de volta.</small></section>

<section class="admin-panel" id="sem-foto" style="margin-bottom:18px"><h2>Produtos sem foto (<?= count($semFoto) ?>)</h2><?= pend_table($semFoto) ?></section>
<section class="admin-panel" id="sem-aplicacao" style="margin-bottom:18px"><h2>Produtos sem aplicação (<?= count($semApp) ?>)</h2><?= pend_table($semApp) ?></section>

<section class="admin-panel" id="historico" style="margin-bottom:18px"><h2>Histórico de alterações</h2>
<?php if (!$hist): ?><p>Ainda não há alterações registradas. Elas aparecem aqui a partir de agora.</p><?php else: ?>
<div class="admin-table-wrap" style="max-height:420px;overflow:auto"><table class="admin-table"><thead><tr><th>Quando</th><th>Quem</th><th>O quê</th><th>Código</th><th>Detalhe</th></tr></thead><tbody>
<?php foreach ($hist as $c): ?><tr><td><?= e(date('d/m/Y H:i', strtotime($c['t'] ?? 'now'))) ?></td><td><?= e($c['user'] ?? '') ?></td><td><?= e($c['action'] ?? '') ?><?= !empty($c['note']) ? '<br><small>' . e($c['note']) . '</small>' : '' ?></td><td><strong><?= e($c['code'] ?? '') ?></strong></td><td><?php
if (empty($c['changes'])) echo e($c['name'] ?? ''); else foreach ($c['changes'] as $f => $v) echo e($f) . ': <s>' . e(mb_strimwidth((string)$v[0], 0, 40, '…')) . '</s> → <b>' . e(mb_strimwidth((string)$v[1], 0, 40, '…')) . '</b><br>'; ?></td></tr><?php endforeach; ?>
</tbody></table></div><small>Últimas 150 alterações.</small><?php endif; ?></section>

<section class="admin-panel" id="backups" style="margin-bottom:18px"><h2>Backups automáticos</h2>
<p>Um backup do <code>products.json</code> é criado antes de cada alteração (ficam os 60 mais recentes). Fotos não entram no backup: copie a pasta <code>assets/products</code> de vez em quando.</p>
<?php if (!$backups): ?><p>Nenhum backup ainda.</p><?php else: ?><div class="admin-table-wrap" style="max-height:300px;overflow:auto"><table class="admin-table"><thead><tr><th>Data</th><th>Tamanho</th><th></th></tr></thead><tbody>
<?php foreach (array_slice($backups, 0, 30) as $b): $n = basename($b); preg_match('/_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})_/', $n, $m); ?><tr><td><?= e("$m[3]/$m[2]/$m[1] $m[4]:$m[5]:$m[6]") ?></td><td><?= round(filesize($b) / 1024) ?> KB</td><td class="admin-row-actions"><a href="?baixar=<?= e($n) ?>">Baixar</a>
<form method="post" onsubmit="return confirm('Restaurar este backup? O estado atual será salvo como backup antes.');"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="restaurar"><input type="hidden" name="arquivo" value="<?= e($n) ?>"><button type="submit">Restaurar</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
</main></body></html>
