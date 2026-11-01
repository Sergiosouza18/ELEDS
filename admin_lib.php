<?php
// Funções compartilhadas pelo painel: backup automático, histórico e planilhas (.xlsx/.csv).

function he_backup_dir() { $d = __DIR__ . '/data/backups'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }
function he_imgs($p) { $i = $p['images'] ?? []; if (!$i && !empty($p['image'])) $i = [$p['image']]; return array_values(array_filter((array)$i)); }

function he_diff($old, $new) {
    $fields = ['name', 'category', 'brand', 'application', 'measures'];
    $o = []; foreach ($old as $p) $o[$p['code'] ?? ''] = $p;
    $n = []; foreach ($new as $p) $n[$p['code'] ?? ''] = $p;
    $out = [];
    foreach ($n as $code => $p) {
        if (!isset($o[$code])) { $out[] = ['action' => 'criado', 'code' => $code, 'name' => $p['name'] ?? '', 'changes' => []]; continue; }
        $ch = [];
        foreach ($fields as $f) { $a = (string)($o[$code][$f] ?? ''); $b = (string)($p[$f] ?? ''); if ($a !== $b) $ch[$f] = [$a, $b]; }
        $ia = count(he_imgs($o[$code])); $ib = count(he_imgs($p));
        if ($ia !== $ib) $ch['imagens'] = [$ia, $ib];
        if ($ch) $out[] = ['action' => 'alterado', 'code' => $code, 'changes' => $ch];
    }
    foreach ($o as $code => $p) if (!isset($n[$code])) $out[] = ['action' => 'excluído', 'code' => $code, 'name' => $p['name'] ?? '', 'changes' => []];
    return $out;
}

// Chamada antes de gravar products.json: faz backup do arquivo atual e registra o que mudou.
function he_before_save($file, $new) {
    if (!is_file($file)) return;
    $raw = (string)file_get_contents($file);
    $old = json_decode($raw, true);
    $changes = he_diff(is_array($old) ? $old : [], array_values($new));
    if (!$changes) return;
    $dir = he_backup_dir();
    @file_put_contents($dir . '/products_' . date('Ymd_His') . '_' . sprintf('%03d', (int)(fmod(microtime(true), 1) * 1000)) . '.json', $raw, LOCK_EX);
    $files = glob($dir . '/products_*.json') ?: [];
    sort($files);
    foreach (array_slice($files, 0, max(0, count($files) - 60)) as $f) @unlink($f); // mantém os 60 mais recentes
    $lines = '';
    foreach ($changes as $c) {
        $c = ['t' => date('c'), 'user' => $_SESSION['admin_user'] ?? '?', 'note' => $GLOBALS['he_history_note'] ?? ''] + $c;
        $lines .= json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
    @file_put_contents(__DIR__ . '/data/history.jsonl', $lines, FILE_APPEND | LOCK_EX);
}

function he_save_products($file, $products) {
    he_before_save($file, $products);
    return file_put_contents($file, json_encode(array_values($products), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}

// ---------- Planilhas ----------
function he_col($i) { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; }
function he_xml($s) { return htmlspecialchars((string)preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string)$s), ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function he_key($s) {
    $s = mb_strtolower((string)$s, 'UTF-8');
    $s = strtr($s, ['á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']);
    return preg_replace('/[^a-z0-9]/', '', $s);
}

// Gera um .xlsx (primeira linha = cabeçalho em negrito). Retorna o caminho do arquivo temporário.
function he_xlsx_build(array $rows, array $widths = []) {
    $H = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?' . '>';
    $NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $zip = new ZipArchive(); $tmp = tempnam(sys_get_temp_dir(), 'xl');
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', $H.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
    $zip->addFromString('_rels/.rels', $H.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', $H.'<workbook xmlns="'.$NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Produtos" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', $H.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
    $zip->addFromString('xl/styles.xml', $H.'<styleSheet xmlns="'.$NS.'"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
    $cols = ''; foreach ($widths as $i => $w) $cols .= '<col min="'.($i+1).'" max="'.($i+1).'" width="'.$w.'" customWidth="1"/>';
    $data = '';
    foreach ($rows as $r => $row) {
        $data .= '<row r="'.($r+1).'">';
        foreach (array_values($row) as $c => $v) {
            if ((string)$v === '') continue;
            $data .= '<c r="'.he_col($c).($r+1).'" t="inlineStr"'.($r === 0 ? ' s="1"' : '').'><is><t xml:space="preserve">'.he_xml($v).'</t></is></c>';
        }
        $data .= '</row>';
    }
    $zip->addFromString('xl/worksheets/sheet1.xml', $H.'<worksheet xmlns="'.$NS.'"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'.($cols ? '<cols>'.$cols.'</cols>' : '').'<sheetData>'.$data.'</sheetData></worksheet>');
    $zip->close();
    return $tmp;
}

// Lê a primeira planilha de um .xlsx. Retorna lista de linhas (arrays indexados pela coluna) ou null.
function he_xlsx_read($path) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) return null;
    $ss = [];
    if (($x = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $d = new DOMDocument(); @$d->loadXML($x); $xp = new DOMXPath($d);
        foreach ($xp->query("//*[local-name()='si']") as $si) { $t = ''; foreach ($xp->query(".//*[local-name()='t']", $si) as $n) $t .= $n->textContent; $ss[] = $t; }
    }
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheet === false) for ($i = 0; $i < $zip->numFiles; $i++) { $n = $zip->getNameIndex($i); if (preg_match('#^xl/worksheets/[^/]+\.xml$#', $n)) { $sheet = $zip->getFromName($n); break; } }
    $zip->close();
    if (!$sheet) return null;
    $d = new DOMDocument(); @$d->loadXML($sheet); $xp = new DOMXPath($d);
    $rows = [];
    foreach ($xp->query("//*[local-name()='sheetData']/*[local-name()='row']") as $r) {
        $row = [];
        foreach ($xp->query("*[local-name()='c']", $r) as $c) {
            $col = 0;
            if (preg_match('/^([A-Z]+)/', $c->getAttribute('r'), $m)) foreach (str_split($m[1]) as $ch) $col = $col * 26 + ord($ch) - 64;
            $col--;
            $t = $c->getAttribute('t'); $v = '';
            $vn = $xp->query("*[local-name()='v']", $c)->item(0);
            if ($t === 'inlineStr') { foreach ($xp->query(".//*[local-name()='t']", $c) as $n) $v .= $n->textContent; }
            elseif ($t === 's') $v = $ss[(int)($vn ? $vn->textContent : -1)] ?? '';
            elseif ($vn) { $v = $vn->textContent; if ($t === '' && is_numeric($v) && (float)$v == floor((float)$v) && abs((float)$v) < 1e15) $v = (string)(int)(float)$v; }
            $row[$col >= 0 ? $col : count($row)] = trim($v);
        }
        $rows[] = $row;
    }
    return $rows;
}

function he_csv_read($path) {
    $raw = (string)file_get_contents($path);
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3);
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    $first = (string)strtok($raw, "\n");
    $delim = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
    $fh = fopen('php://temp', 'r+'); fwrite($fh, $raw); rewind($fh);
    $rows = []; while (($r = fgetcsv($fh, 0, $delim)) !== false) $rows[] = array_map('trim', $r);
    fclose($fh);
    return $rows;
}

// Aplica as linhas da planilha sobre a lista de produtos. Retorna [produtos, grupos novos, resumo].
function he_import_rows(array $rows, array $products, array $groupNames) {
    $map = ['codigo'=>'code','cod'=>'code','referencia'=>'code','nome'=>'name','descricao'=>'name','produto'=>'name','nomedescricao'=>'name','grupo'=>'category','categoria'=>'category','grupocategoria'=>'category','marca'=>'brand','aplicacao'=>'application','compatibilidade'=>'application','aplicacaocompatibilidade'=>'application','medidas'=>'measures'];
    $stat = ['criados' => 0, 'atualizados' => 0, 'sem_mudanca' => 0, 'erros' => []];
    $head = array_shift($rows); $cols = [];
    foreach ((array)$head as $i => $h) { $k = he_key($h); if (isset($map[$k]) && !in_array($map[$k], $cols, true)) $cols[$i] = $map[$k]; }
    if (!in_array('code', $cols, true)) { $stat['erros'][] = 'Coluna "Código" não encontrada na primeira linha.'; return [$products, [], $stat]; }
    $idx = []; foreach ($products as $i => $p) $idx[$p['code'] ?? ''] = $i;
    $newGroups = [];
    foreach (array_slice($rows, 0, 5000) as $n => $row) {
        $d = []; foreach ($cols as $i => $f) $d[$f] = trim((string)($row[$i] ?? ''));
        $code = $d['code'] ?? ''; if ($code === '') continue;
        $line = $n + 2;
        if (isset($idx[$code])) {
            $p = &$products[$idx[$code]]; $changed = false;
            foreach ($d as $f => $v) if ($f !== 'code' && $v !== '' && (string)($p[$f] ?? '') !== $v) { $p[$f] = $v; $changed = true; }
            unset($p);
            $changed ? $stat['atualizados']++ : $stat['sem_mudanca']++;
        } else {
            if (($d['name'] ?? '') === '') { $stat['erros'][] = "Linha $line ($code): produto novo precisa de nome."; continue; }
            $products[] = ['code' => $code, 'name' => $d['name'], 'category' => ($d['category'] ?? '') ?: 'Outros', 'image' => '', 'images' => [], 'brand' => ($d['brand'] ?? '') ?: 'HE', 'application' => $d['application'] ?? '', 'measures' => $d['measures'] ?? ''];
            $idx[$code] = count($products) - 1; $stat['criados']++;
        }
        $cat = ($d['category'] ?? '');
        if ($cat !== '' && !in_array($cat, $groupNames, true) && !in_array($cat, $newGroups, true)) $newGroups[] = $cat;
    }
    return [$products, $newGroups, $stat];
}
