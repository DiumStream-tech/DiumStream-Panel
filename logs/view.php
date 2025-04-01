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
    if ($user['permissions'] === '*') {
        return true;
    }
    $userPermissions = explode(',', $user['permissions']);
    return in_array($permission, $userPermissions);
}

if (isset($_SESSION['user_token'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE token = :token");
    $stmt->bindParam(':token', $_SESSION['user_token']);
    $stmt->execute();
    $utilisateur = $stmt->fetch();

    if (!$utilisateur) {
        header('Location: ../account/connexion');
        exit();
    }
} else {
    header('Location: ../account/connexion');
    exit();
}

require_once '../ui/header3.php';
?>

<div class="container mx-auto mt-10 p-6 bg-gray-900 text-white border border-gray-700 rounded-lg shadow-lg">
    <?php if (!hasPermission($utilisateur, 'logs_view')): ?>
        <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-gray-800 p-8 rounded-lg text-center">
                <h3 class="text-xl font-bold mb-4">Accès refusé</h3>
                <p class="mb-6">Vous n'avez pas la permission de voir les logs.</p>
                <a href="../settings" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Retour au panel
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 gap-6">
            <div id="logs-view">
                <h2 class="text-3xl font-bold mb-6 text-gray-100 border-b border-gray-600 pb-2">Visualisation des Logs</h2>
                <?php if (hasPermission($utilisateur, 'purge_logs')): ?>
                <form action="purge_logs.php" method="POST">
                    <button type="submit" name="purge_logs" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg shadow-lg transition duration-300 ease-in-out">
                        Purger les Logs
                    </button>
                </form>
                <?php endif; ?>
                
                <div class="overflow-x-auto mt-4">
                    <table class="min-w-full divide-y divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Utilisateur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Timestamp</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php
                            $stmt = $pdo->query("SELECT * FROM logs ORDER BY timestamp DESC");
                            while ($logEntry = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<tr>';
                                echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">' . htmlspecialchars($logEntry['user']) . '</td>';
                                echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">' . htmlspecialchars($logEntry['timestamp']) . '</td>';
                                echo '<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-300">' . htmlspecialchars($logEntry['action']) . '</td>';
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once '../ui/footer.php';
?>