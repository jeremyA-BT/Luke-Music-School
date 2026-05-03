<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$pdo  = getDb();
$root = getAdminRoot();
$id   = inputInt('id', 'get');

$stmt = $pdo->prepare("SELECT * FROM lms_presets WHERE id = ?");
$stmt->execute([$id]);
$preset = $stmt->fetch();

if (!$preset) {
    flashMessage('error', 'Preset not found.');
    redirect($root . '/presets/index.php');
}

$pdo->prepare("DELETE FROM lms_presets WHERE id = ?")->execute([$id]);
flashMessage('success', 'Preset "' . $preset['description'] . '" deleted.');
redirect($root . '/presets/index.php');
