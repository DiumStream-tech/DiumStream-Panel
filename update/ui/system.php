<?php
session_start();
require_once __DIR__.'/../../connexion_bdd.php';

if (!isset($_SESSION['user_token']) || !isset($_SESSION['user_email'])) {
    header('Location: ../../login.php');
    exit();
}

function getUpdateInfo() {
    $updateJsonPath = __DIR__ . '/../json/update.json';
    if (!file_exists($updateJsonPath)) {
        throw new Exception('Fichier update.json introuvable.');
    }
    return json_decode(file_get_contents($updateJsonPath), true);
}

function getCurrentVersion() {
    $versionFile = __DIR__ . '/../json/version.json';
    if (!file_exists($versionFile)) {
        throw new Exception('Fichier version.json introuvable.');
    }
    $jsonData = json_decode(file_get_contents($versionFile), true);
    return $jsonData['version'] ?? 'Inconnue';
}

function getChangelogs() {
    $changelogFile = __DIR__ . '/../json/changelogs.json';
    if (!file_exists($changelogFile)) {
        return ['versions' => []];
    }
    $data = json_decode(file_get_contents($changelogFile), true);
    return is_array($data) ? $data : ['versions' => []];
}

try {
    $currentVersion = getCurrentVersion();
    $updateInfo = getUpdateInfo();
    $changelogs = getChangelogs();
    
    $latestVersion = null;
    $patchNotes = [];
    
    if (isset($updateInfo['version_url'])) {
        $latestVersionData = json_decode(file_get_contents($updateInfo['version_url']), true);
        $latestVersion = $latestVersionData['version'] ?? null;
    }

    if (isset($updateInfo['patch_notes_url'])) {
        $remoteNotes = json_decode(file_get_contents($updateInfo['patch_notes_url']), true) ?? [];
        $patchNotes = array_merge($changelogs['versions'], $remoteNotes);
    } else {
        $patchNotes = $changelogs['versions'];
    }

    if (!empty($patchNotes)) {
        usort($patchNotes, function($a, $b) {
            $dateA = $a['date'] ?? '1970-01-01';
            $dateB = $b['date'] ?? '1970-01-01';
            return strtotime($dateB) <=> strtotime($dateA);
        });
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Informations système - Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        ::-webkit-scrollbar { display: none; }
        body { -ms-overflow-style: none; scrollbar-width: none; min-height: 100vh; display: flex; flex-direction: column; }
        .main-content { flex: 1; }
        .version-card { background: linear-gradient(145deg, #1e293b, #0f172a); border: 1px solid #334155; border-radius: 12px; transition: transform 0.2s; }
        .version-card:hover { transform: translateY(-2px); }
        .changelog-entry { border-left: 4px solid; transition: transform 0.2s; }
        .changelog-entry:hover { transform: translateX(5px); }
        .type-Added { border-color: #10B981; }
        .type-Fixed { border-color: #EF4444; }
        .type-Improved { border-color: #3B82F6; }
        .type-UI-UX { border-color: #F59E0B; background: linear-gradient(90deg, rgba(245, 158, 11, 0.05) 0%, transparent 100%); }
        .type-Security { border-color: #EF4444; }
        .type-Infrastructure { border-color: #8B5CF6; }
        .type-Performance { border-color: #10B981; }
        .type-Accessibility { border-color: #3B82F6; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">

<div class="main-content">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold flex items-center">
                <i class="bi bi-patch-check text-blue-500 mr-3"></i>
                Informations système
            </h1>
            
            <div class="flex space-x-4">
                <a href="javascript:history.back()" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg flex items-center">
                    <i class="bi bi-arrow-left mr-2"></i>
                    Retour
                </a>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-800/50 border border-red-500 text-red-100 px-6 py-4 rounded-lg mb-8">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="version-card p-6">
                <h3 class="text-xl font-semibold mb-4 flex items-center">
                    <i class="bi bi-hdd-stack text-green-400 mr-2"></i>
                    Version actuelle
                </h3>
                <div class="text-2xl font-mono font-bold text-green-400">
                    <?= htmlspecialchars($currentVersion) ?>
                </div>
            </div>

            <div class="version-card p-6">
                <h3 class="text-xl font-semibold mb-4 flex items-center">
                    <i class="bi bi-cloud-arrow-down text-blue-400 mr-2"></i>
                    Dernière version disponible
                </h3>
                <div class="text-2xl font-mono font-bold text-blue-400">
                    <?= htmlspecialchars($latestVersion ?? 'Non disponible') ?>
                </div>
            </div>
        </div>

        <div class="bg-gray-800/50 rounded-xl p-6">
            <h2 class="text-2xl font-bold mb-6 flex items-center">
                <i class="bi bi-journal-text text-purple-400 mr-3"></i>
                Historique des versions
            </h2>

            <?php if (!empty($patchNotes)): ?>
                <div class="space-y-4">
                    <?php foreach ($patchNotes as $version): ?>
                        <div class="bg-gray-700/50 p-5 rounded-lg">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-semibold">
                                    Version <?= htmlspecialchars($version['version']) ?>
                                </h3>
                                <span class="text-sm text-gray-400">
                                    <?= htmlspecialchars($version['date']) ?>
                                </span>
                            </div>
                            
                            <div class="space-y-3">
                                <?php foreach ($version['changes'] as $change): ?>
                                    <div class="changelog-entry p-4 rounded-lg bg-gray-800/50 type-<?= htmlspecialchars($change['type']) ?>">
                                        <div class="flex items-center mb-2">
                                            <span class="font-semibold text-sm uppercase tracking-wider">
                                                <?= htmlspecialchars($change['type']) ?>
                                            </span>
                                            <span class="mx-2">•</span>
                                            <span class="text-sm text-gray-300">
                                                <?= htmlspecialchars($change['title']) ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-300 text-sm">
                                            <?= htmlspecialchars($change['description']) ?>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <i class="bi bi-journal-x text-4xl text-gray-500 mb-4"></i>
                    <p class="text-gray-400">Aucune donnée d'historique disponible</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__.'/../../ui/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
