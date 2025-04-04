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
?>

<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <?php require_once '../ui/header3.php'; ?>
    <style>
        .main-content {
            min-height: calc(100vh - 150px);
            display: flex;
            flex-direction: column;
        }
        .flex-grow {
            flex-grow: 1;
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
                <div class="space-y-8">
                    <div class="flex flex-col md:flex-row justify-between items-start gap-4">
                        <div>
                            <h2 class="text-4xl font-bold bg-gradient-to-r from-blue-400 to-purple-500 bg-clip-text text-transparent">
                                Gestion des Logs
                            </h2>
                            <p class="text-gray-400 mt-2">Journal des activités système</p>
                        </div>
                        
                        <?php if (hasPermission($utilisateur, 'purge_logs')): ?>
                        <form action="purge_logs.php" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir purger tous les logs ?')">
                            <button type="submit" name="purge_logs" class="flex items-center gap-2 bg-red-600/25 hover:bg-red-600/50 border border-red-600 text-red-400 px-6 py-3 rounded-lg transition-all hover:scale-[1.02] group">
                                <i class="fas fa-trash group-hover:animate-pulse"></i>
                                Purger les Logs
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <div class="bg-gray-800/50 rounded-xl overflow-hidden border border-gray-700/50">
                        <div class="px-6 py-4 border-b border-gray-700/50">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold">Historique des activités</h3>
                                <div class="relative">
                                    <input type="text" placeholder="Rechercher..." class="bg-gray-900/50 border border-gray-700/50 rounded-lg pl-10 pr-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent" id="searchInput">
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
                                    $stmt = $pdo->query("SELECT * FROM logs ORDER BY timestamp DESC");
                                    while ($logEntry = $stmt->fetch(PDO::FETCH_ASSOC)):
                                    ?>
                                    <tr class="hover:bg-gray-800/25 transition-colors duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-100"><?= htmlspecialchars($logEntry['user']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($logEntry['timestamp']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300"><?= htmlspecialchars($logEntry['action']) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-4 border-t border-gray-700/50 flex justify-between items-center">
                            <span class="text-sm text-gray-400">Affichage des 50 derniers logs</span>
                            <div class="flex gap-2">
                                <button class="bg-gray-700/50 hover:bg-gray-700/75 px-4 py-2 rounded-lg text-sm">Précédent</button>
                                <button class="bg-gray-700/50 hover:bg-gray-700/75 px-4 py-2 rounded-lg text-sm">Suivant</button>
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
    const table = document.getElementById('logsTable')
    const rows = Array.from(table.rows).slice(1)
    const isAsc = table.getAttribute('data-sort-direction') === 'asc'
    
    rows.sort((a, b) => {
        const aValue = a.cells[columnIndex].textContent.trim()
        const bValue = b.cells[columnIndex].textContent.trim()
        
        if (columnIndex === 1) {
            return isAsc ? new Date(bValue) - new Date(aValue) : new Date(aValue) - new Date(bValue)
        }
        
        return isAsc ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue)
    })
    
    table.tBodies[0].append(...rows)
    table.setAttribute('data-sort-direction', isAsc ? 'desc' : 'asc')
}

document.getElementById('searchInput').addEventListener('input', (e) => {
    const searchValue = e.target.value.toLowerCase()
    const rows = document.querySelectorAll('#logsTable tbody tr')
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase()
        row.style.display = text.includes(searchValue) ? '' : 'none'
    })
})
</script>

<?php require_once '../ui/footer.php'; ?>
