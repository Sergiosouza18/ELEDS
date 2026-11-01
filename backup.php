<?php
session_start();
require __DIR__ . '/admin_config.php';
if (empty($_SESSION['admin_ok'])) { header('Location: admin.php'); exit; }
// Backup completo é exclusivo de usuários com perfil Administrador.
if ((string)($_SESSION['admin_role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    exit('Acesso negado. Apenas administradores podem realizar o backup completo.');
}
if (!class_exists('ZipArchive')) { http_response_code(500); exit('A extensão ZipArchive não está disponível no servidor.'); }
$root = realpath(__DIR__);
$tmp = tempnam(sys_get_temp_dir(), 'he_backup_');
$zip = new ZipArchive();
if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) { http_response_code(500); exit('Não foi possível criar o backup.'); }
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getRealPath();
    if ($path === false || strpos($path, $root) !== 0) continue;
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if ($relative === '' || preg_match('#(^|/)(\.git|backups?)(/|$)#i', $relative)) continue;
    $zip->addFile($path, $relative);
}
$zip->close();
$name = 'backup_HE_Linha_Pesada_' . date('Y-m-d_H-i-s') . '.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: ' . filesize($tmp));
header('Cache-Control: no-store, no-cache, must-revalidate');
readfile($tmp); @unlink($tmp); exit;
