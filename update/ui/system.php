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
        .update-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.9); z-index: 9999; display: none; align-items: center; justify-content: center; flex-direction: column; }
        .update-content { background: #1e293b; border-radius: 12px; padding: 2rem; width: 90%; max-width: 500px; text-align: center; }
        .progress-bar { background: #334155; height: 10px; border-radius: 5px; margin: 1rem 0; overflow: hidden; }
        .progress { background: #3b82f6; height: 100%; width: 0%; transition: width 0.3s; }
        .lds-ripple { display: inline-block; position: relative; width: 80px; height: 80px; }
        .lds-ripple div { position: absolute; border: 4px solid #3b82f6; opacity: 1; border-radius: 50%; animation: lds-ripple 1s cubic-bezier(0, 0.2, 0.8, 1) infinite; }
        .lds-ripple div:nth-child(2) { animation-delay: -0.5s; }
        @keyframes lds-ripple { 0% { top: 36px; left: 36px; width: 0; height: 0; opacity: 0; } 4.9% { top: 36px; left: 36px; width: 0; height: 0; opacity: 0; } 5% { top: 36px; left: 36px; width: 0; height: 0; opacity: 1; } 100% { top: 0px; left: 0px; width: 72px; height: 72px; opacity: 0; } }
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
                <?php if ($latestVersion && version_compare($currentVersion, $latestVersion, '<')): ?>
                <button onclick="performUpdate()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg flex items-center">
                    <i class="bi bi-cloud-arrow-down mr-2"></i>
                    Mettre à jour
                </button>
                <?php endif; ?>
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

<div class="update-overlay" id="updateOverlay">
    <div class="update-content">
        <div id="updatingContent">
            <div class="lds-ripple"><div></div><div></div></div>
            <h2 class="text-xl font-bold mb-2">Mise à jour en cours</h2>
            <p class="text-gray-300 mb-4">Veuillez patienter, cela peut prendre quelques instants...</p>
            <div class="progress-bar">
                <div class="progress" id="updateProgress"></div>
            </div>
        </div>
        <div id="updateComplete" style="display: none;">
            <i class="bi bi-check-circle text-green-500 text-6xl mb-4"></i>
            <h2 class="text-xl font-bold mb-2">Mise à jour terminée !</h2>
            <p class="text-gray-300 mb-4">Le système va recharger automatiquement...</p>
            <button onclick="location.reload()" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded-lg">
                Recharger maintenant
            </button>
        </div>
    </div>
</div>

<script>
function performUpdate() {
    const overlay = document.getElementById('updateOverlay');
    const progressBar = document.getElementById('updateProgress');
    const updatingContent = document.getElementById('updatingContent');
    const updateComplete = document.getElementById('updateComplete');

    overlay.style.display = 'flex';
    
    let progress = 0;
    const interval = setInterval(() => {
        progress += Math.random() * 20;
        progressBar.style.width = Math.min(progress, 90) + '%';
    }, 500);

    fetch('/update/update.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'update_button=1'
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(interval);
        progressBar.style.width = '100%';
        
        if (data.success) {
            updatingContent.style.display = 'none';
            updateComplete.style.display = 'block';
            setTimeout(() => {
                location.reload();
            }, 3000);
        } else {
            alert('Erreur lors de la mise à jour: ' + data.message);
            overlay.style.display = 'none';
        }
    })
    .catch(error => {
        clearInterval(interval);
        console.error('Erreur:', error);
        alert('Une erreur est survenue lors de la mise à jour');
        overlay.style.display = 'none';
    });
}
</script>

<?php include __DIR__.'/../../ui/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
