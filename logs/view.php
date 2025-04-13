<?php
session_start();
$configFilePath = '../conn.php';

if (isset($_POST['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ../account/connexion');
    exit();
}

if (!file_exists($configFilePath)) {
    header('Location: ../setdb');
    exit();
}
require_once '../connexion_bdd.php';

function hasPermission($user, $permission) {
    if ($user['permissions'] === '*') return true;
    $userPermissions = explode(',', $user['permissions']);
    return in_array($permission, $userPermissions);
}

if (isset($_SESSION['user_token'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE token = :token");
    $stmt->execute([':token' => $_SESSION['user_token']]);
    $utilisateur = $stmt->fetch();

    if (!$utilisateur) {
        header('Location: ../account/connexion');
        exit();
    }
} else {
    header('Location: ../account/connexion');
    exit();
}

$stmtCheck = $pdo->query("SELECT COUNT(*) as count FROM logs");
$logCount = $stmtCheck->fetch(PDO::FETCH_ASSOC)['count'];

if (isset($_POST['export_file'])) {
    if (!hasPermission($utilisateur, 'logs_export')) {
        error_log('Tentative d\'export CSV non autorisée par '.$utilisateur['username']);
        exit('Permissions insuffisantes');
    }

    if ($logCount == 0) {
        $_SESSION['export_error'] = 'Aucun log à exporter';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="logs_export_'.date('Y-m-d_H-i').'.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Utilisateur', 'Date/Heure', 'Action'], ';');
    
    $stmt = $pdo->query("SELECT * FROM logs ORDER BY timestamp DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['user'],
            date('d/m/Y H:i', strtotime($row['timestamp'])),
            $row['action']
        ], ';');
    }
    
    fclose($output);
    exit();
}

if (isset($_POST['export_discord'])) {
    if (!hasPermission($utilisateur, 'logs_export')) {
        error_log('Tentative d\'export Discord non autorisée par '.$utilisateur['username']);
        exit('Permissions insuffisantes');
    }

    if ($logCount == 0) {
        $_SESSION['export_error'] = 'Aucun log à exporter';
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }

    require_once '../conn.php';
    
    if (!isset($webhookConfig['url']) || empty($webhookConfig['url'])) {
        echo "<script>alert('Webhook Discord non configuré')</script>";
        exit();
    }

    $webhookUrl = $webhookConfig['url'];

    $stmt = $pdo->query("SELECT * FROM logs ORDER BY timestamp DESC");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $embeds = [];
    $currentMessage = "**📜 Export des logs - ".date('d/m/Y H:i')."**\n";
    
    foreach ($logs as $log) {
        $line = sprintf(
            "🔹 **%s** [%s]\n`%s`\n\n",
            htmlspecialchars($log['user']),
            date('d/m H:i', strtotime($log['timestamp'])),
            htmlspecialchars($log['action'])
        );
        
        if (strlen($currentMessage) + strlen($line) > 1900) {
            $embeds[] = [
                'title' => '📜 Logs système',
                'color' => 0x5865F2,
                'description' => $currentMessage,
                'footer' => [
                    'text' => 'Export généré via le panel administrateur'
                ]
            ];
            $currentMessage = "";
        }
        
        $currentMessage .= $line;
    }
    
    if (!empty($currentMessage)) {
        $embeds[] = [
            'title' => '📜 Logs système',
            'color' => 0x5865F2,
            'description' => $currentMessage,
            'footer' => [
                'text' => 'Export généré via le panel administrateur'
            ]
        ];
    }

    $chunks = array_chunk($embeds, 10);
    
    foreach ($chunks as $chunk) {
        $data = [
            'username' => 'Logs Exporter',
            'avatar_url' => 'https://example.com/logo.png',
            'embeds' => $chunk
        ];

        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 204) {
            echo "<script>alert('Erreur lors de l\'envoi à Discord (Code $httpCode)')</script>";
            exit();
        }
        
        usleep(500000);
    }

    echo "<script>alert('Export Discord envoyé avec succès')</script>";
}

?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <?php require_once '../ui/header3.php'; ?>
    <style>
        html, body {
            height: 100%;
        }
        
        .main-content {
            min-height: calc(100vh - 150px);
            display: flex;
            flex-direction: column;
        }
        
        .flex-grow {
            flex-grow: 1;
        }

        .log-entry {
            background: linear-gradient(145deg, rgba(39, 39, 42, 0.4), rgba(63, 63, 70, 0.2));
            margin: 2px 0;
            border-radius: 8px;
        }

        .log-entry:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.15);
        }

        .log-row {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .search-input {
            background: rgba(17, 24, 39, 0.5) !important;
            backdrop-filter: blur(10px);
        }

        .pagination-btn {
            background: linear-gradient(135deg, rgba(55, 65, 81, 0.5), rgba(31, 41, 55, 0.5));
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .pagination-btn:hover {
            background: linear-gradient(135deg, rgba(55, 65, 81, 0.7), rgba(31, 41, 55, 0.7));
        }
    </style>
</head>
<body class="bg-gray-900">
<div class="main-content">
    <div class="flex-grow">
        <div class="container mx-auto mt-10 p-6 bg-gray-900 text-white rounded-lg shadow-2xl border-2 border-gray-800">
            <?php if (!hasPermission($utilisateur, 'logs_view')): ?>
                <div class="fixed inset-0 bg-black/75 z-50 flex items-center justify-center">
                    <div class="bg-gray-800 p-8 rounded-xl text-center animate-fade-in">
                        <h3 class="text-2xl font-bold mb-4 text-red-400">Accès refusé</h3>
                        <p class="mb-6 text-gray-300">Permissions insuffisantes pour accéder aux logs</p>
                        <a href="../settings" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-all hover:scale-105">
                            <i class="fas fa-arrow-left"></i>
                            Retour au panel
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php if (isset($_SESSION['export_error'])): ?>
                <div class="fixed inset-0 bg-black/75 z-50 flex items-center justify-center">
                    <div class="bg-gray-800 p-8 rounded-xl text-center animate-fade-in">
                        <h3 class="text-2xl font-bold mb-4 text-red-400">Erreur d'export</h3>
                        <p class="mb-6 text-gray-300"><?= htmlspecialchars($_SESSION['export_error']) ?></p>
                        <button onclick="closeOverlay()" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-all hover:scale-105">
                            Fermer
                        </button>
                    </div>
                </div>
                <?php unset($_SESSION['export_error']); endif; ?>

                <div class="space-y-8">
                    <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                        <div>
                            <h2 class="text-4xl font-bold bg-gradient-to-r from-blue-400 to-purple-500 bg-clip-text text-transparent">
                                Gestion des Logs
                            </h2>
                            <p class="text-gray-400 mt-2">Journal des activités système</p>
                        </div>
                        
                        <div class="flex flex-col md:flex-row gap-4">
                            <?php if (hasPermission($utilisateur, 'purge_logs')): ?>
                            <form action="purge_logs.php" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir purger tous les logs ?')">
                                <button type="submit" name="purge_logs" class="flex items-center gap-2 bg-red-600/25 hover:bg-red-600/50 border border-red-600 text-red-400 px-6 py-3 rounded-lg transition-all hover:scale-[1.02] group">
                                    <i class="fas fa-trash group-hover:animate-pulse"></i>
                                    Purger les Logs
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <?php if (hasPermission($utilisateur, 'logs_export') && $logCount > 0): ?>
                            <form method="POST" class="flex gap-4">
                                <button type="submit" name="export_file" class="flex items-center gap-2 bg-green-600/25 hover:bg-green-600/50 border border-green-600 text-green-400 px-6 py-3 rounded-lg transition-all hover:scale-[1.02] group">
                                    <i class="fas fa-file-export group-hover:animate-bounce"></i>
                                    Exporter en CSV
                                </button>
                                
                                <button type="submit" name="export_discord" class="flex items-center gap-2 bg-indigo-600/25 hover:bg-indigo-600/50 border border-indigo-600 text-indigo-400 px-6 py-3 rounded-lg transition-all hover:scale-[1.02] group">
                                    <i class="fab fa-discord group-hover:animate-spin"></i>
                                    Exporter vers Discord
                                </button>
                            </form>
                            <?php elseif(hasPermission($utilisateur, 'logs_export') && $logCount === 0): ?>
                            <div class="flex items-center gap-2 px-6 py-3 text-gray-400">
                                <i class="fas fa-exclamation-circle"></i>
                                Aucun log à exporter
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-gray-800/50 rounded-xl overflow-hidden border border-gray-700/50">
                        <div class="px-6 py-4 border-b border-gray-700/50">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold">Historique des activités</h3>
                                <div class="relative">
                                    <input type="text" placeholder="Rechercher..." class="search-input bg-gray-900/50 border border-gray-700/50 rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="searchInput">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-700/50" id="logsTable">
                                <thead class="bg-gray-800/75">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider cursor-pointer hover:text-blue-400 transition-colors duration-200" onclick="sortTable(0)">Utilisateur</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider cursor-pointer hover:text-blue-400 transition-colors duration-200" onclick="sortTable(1)">Date/Heure</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-gray-300 uppercase tracking-wider cursor-pointer hover:text-blue-400 transition-colors duration-200" onclick="sortTable(2)">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700/50">
                                    <?php
                                    $stmt = $pdo->query("SELECT * FROM logs ORDER BY timestamp DESC LIMIT 50");
                                    while ($logEntry = $stmt->fetch(PDO::FETCH_ASSOC)):
                                    ?>
                                    <tr class="log-entry log-row hover:bg-gray-800/25 transition-all duration-300">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-100 relative">
                                            <span class="text-blue-400">•</span>
                                            <span class="ml-3"><?= htmlspecialchars($logEntry['user']) ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                            <?= date('d/m/Y H:i', strtotime(htmlspecialchars($logEntry['timestamp']))) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">
                                            <div class="inline-flex items-center gap-2 bg-gray-700/25 px-3 py-1 rounded-full">
                                                <i class="fas fa-terminal text-xs text-purple-400"></i>
                                                <span><?= htmlspecialchars($logEntry['action']) ?></span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-4 border-t border-gray-700/50 flex justify-between items-center">
                            <span class="text-sm text-gray-400">Affichage des 50 derniers logs</span>
                            <div class="flex gap-2">
                                <button class="pagination-btn hover:bg-gray-700/75 px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                                    <i class="fas fa-chevron-left"></i>
                                    Précédent
                                </button>
                                <button class="pagination-btn hover:bg-gray-700/75 px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                                    Suivant
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function sortTable(columnIndex) {
    const table = document.getElementById('logsTable');
    const rows = Array.from(table.rows).slice(1);
    const isAsc = table.getAttribute('data-sort-direction') === 'asc';

    const th = table.querySelectorAll('th')[columnIndex];
    table.querySelectorAll('th').forEach(header => {
        header.querySelector('.sort-indicator')?.remove();
    });
    
    const indicator = document.createElement('span');
    indicator.className = 'sort-indicator ml-2';
    indicator.innerHTML = isAsc ? '↑' : '↓';
    th.appendChild(indicator);

    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim();
        const bValue = b.cells[columnIndex].textContent.trim();
        
        if (columnIndex === 1) {
            const dateA = new Date(aValue.split(' ').reverse().join('-'));
            const dateB = new Date(bValue.split(' ').reverse().join('-'));
            return isAsc ? dateB - dateA : dateA - dateB;
        }
        
        return isAsc ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
    });
    
    table.tBodies[0].animate(
        [{ opacity: 1 }, { opacity: 0 }, { opacity: 1 }],
        { duration: 300 }
    );
    
    setTimeout(() => {
        table.tBodies[0].append(...rows);
    }, 150);
    
    table.setAttribute('data-sort-direction', isAsc ? 'desc' : 'asc');
}

document.getElementById('searchInput').addEventListener('input', (e) => {
    const searchValue = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#logsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});

function closeOverlay() {
    document.querySelector('.fixed.inset-0').remove();
}
</script>

<?php require_once '../ui/footer.php'; ?>
