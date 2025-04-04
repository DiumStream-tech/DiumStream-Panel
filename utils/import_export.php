<?php
session_start();
$configFilePath = '../conn.php';
if (!file_exists($configFilePath)) {
    header('Location: ../setdb');
    exit();
}
require_once '../connexion_bdd.php';

function ajouter_log($user, $action) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO logs (user, timestamp, action) VALUES (:user, :timestamp, :action)");
    $stmt->execute([
        ':user' => $user,
        ':timestamp' => date('Y-m-d H:i:s'),
        ':action' => $action
    ]);
}

function hasPermission($user, $permission) {
    if ($user['permissions'] === '*') {
        return true;
    }
    $userPermissions = explode(',', $user['permissions']);
    return in_array($permission, $userPermissions);
}

if (isset($_POST['logout'])) {
    if (isset($_SESSION['user_email'])) {
        ajouter_log($_SESSION['user_email'], "Déconnexion");
    }
    
    session_unset();
    session_destroy();
    header('Location: ../account/connexion');
    exit();
}

if (!isset($_SESSION['user_token']) || !isset($_SESSION['user_email'])) {
    header('Location: ../account/connexion');
    exit();
}
$email = $_SESSION['user_email'];
$token = $_SESSION['user_token'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND token = :token");
$stmt->bindParam(':email', $email);
$stmt->bindParam(':token', $token);
$stmt->execute();
$utilisateur = $stmt->fetch();

if (!$utilisateur) {
    header('Location: ../account/connexion');
    exit();
}

$hasPermission = hasPermission($utilisateur, 'export_import');

$message = '';

if ($hasPermission) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['json_file'])) {
        if ($_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
            $tempFileName = $_FILES['json_file']['tmp_name'];
            $jsonFile = file_get_contents($tempFileName);
            $importData = json_decode($jsonFile, true);

            foreach ($importData as $table => $rows) {
                if ($table === 'users') {
                    continue;
                }
                $stmt = $pdo->prepare("SHOW TABLES LIKE :table");
                $stmt->bindParam(':table', $table);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    $pdo->exec("TRUNCATE TABLE $table");

                    foreach ($rows as $row) {
                        $existingColumns = [];
                        $columnsStmt = $pdo->prepare("SHOW COLUMNS FROM $table");
                        $columnsStmt->execute();
                        $columnsData = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

                        foreach ($row as $column => $value) {
                            if (in_array($column, $columnsData)) {
                                $existingColumns[$column] = $value;
                            }
                        }

                        if (!empty($existingColumns)) {
                            $columns = implode(',', array_keys($existingColumns));
                            $placeholders = implode(',', array_fill(0, count($existingColumns), '?'));
                            $stmt = $pdo->prepare("INSERT INTO $table ($columns) VALUES ($placeholders)");
                            $stmt->execute(array_values($existingColumns));
                        }
                    }
                }
            }

            if (file_exists($tempFileName)) {
                unlink($tempFileName);
            }
            ajouter_log($email, "Importation de la base de données");
            $message = 'Importation réussie.';
        } else {
            $message = 'Erreur lors du téléchargement du fichier.';
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export') {
        $tables = isset($_GET['tables']) ? $_GET['tables'] : [];
        
        if (empty($tables)) {
            $message = 'Veuillez sélectionner au moins une table à exporter.';
        } else {
            $exportData = [];

            foreach ($tables as $table) {
                $stmt = $pdo->prepare("SELECT * FROM $table");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $exportData[$table] = $rows;
            }

            $jsonData = json_encode($exportData, JSON_PRETTY_PRINT);

            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="database_export.json"');

            ajouter_log($email, "Exportation de la base de données (tables: " . implode(', ', $tables) . ")");
            echo $jsonData;
            exit;
        }
    }
}

include '../ui/header5.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la Base de Données</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <style>
        .glass-panel {
            background: rgba(17, 24, 39, 0.65);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .hover-scale {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .hover-scale:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        .file-upload-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
        }
        .export-card {
            background: linear-gradient(145deg, rgba(30, 41, 59, 0.8), rgba(15, 23, 42, 0.9));
        }
        .permission-denied-card {
            background: linear-gradient(145deg, rgba(55, 65, 81, 0.9), rgba(31, 41, 55, 0.9));
        }
    </style>
</head>
<body class="bg-gray-950 text-gray-200">
    <?php require_once '../ui/header5.php'; ?>

    <div class="container mx-auto py-12 px-4">
        <div class="max-w-7xl mx-auto">
            <h1 class="text-4xl font-bold mb-8 text-center text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-400">
                Gestion des Données
            </h1>

            <?php if ($message): ?>
                <div class="glass-panel p-6 mb-8 rounded-lg border border-<?php echo strpos($message, 'Erreur') !== false ? 'red-500/30' : 'green-500/30'; ?>">
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <i class="fas <?php echo strpos($message, 'Erreur') !== false ? 'fa-exclamation-triangle text-red-400' : 'fa-check-circle text-green-400'; ?> text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-lg font-semibold"><?php echo $message; ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($hasPermission): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Carte d'importation -->
                <div class="file-upload-card hover-scale rounded-2xl shadow-xl p-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-blue-500/10 p-3 rounded-full">
                            <i class="fas fa-file-import text-blue-400 text-2xl"></i>
                        </div>
                        <h2 class="text-2xl font-semibold ml-4">Importer des Données</h2>
                    </div>
                    
                    <form method="post" enctype="multipart/form-data" class="space-y-6">
                        <div class="relative border-2 border-dashed border-gray-700 rounded-lg p-8 text-center transition-all hover:border-blue-400">
                            <input type="file" name="json_file" id="json_file" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            <div class="space-y-2">
                                <i class="fas fa-cloud-upload-alt text-3xl text-blue-400"></i>
                                <p class="text-gray-300">Glissez-déposez ou <span class="text-blue-400">parcourir</span></p>
                                <p class="text-sm text-gray-400">Format JSON uniquement</p>
                            </div>
                        </div>
                        <div id="file-selected" class="hidden text-center text-sm text-gray-300">
                            <i class="fas fa-file-alt mr-2"></i>
                            <span id="file-name"></span>
                        </div>
                        <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-purple-500 text-white font-bold py-3 rounded-lg hover:opacity-90 transition-opacity">
                            <i class="fas fa-upload mr-2"></i>Importer les Données
                        </button>
                    </form>
                </div>

                <div class="export-card hover-scale rounded-2xl shadow-xl p-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-green-500/10 p-3 rounded-full">
                            <i class="fas fa-file-export text-green-400 text-2xl"></i>
                        </div>
                        <h2 class="text-2xl font-semibold ml-4">Exporter des Données</h2>
                    </div>

                    <form method="get" action="" id="exportForm" class="space-y-6">
                        <input type="hidden" name="action" value="export">
                        
                        <div class="bg-gray-800/50 rounded-lg p-4 max-h-96 overflow-y-auto custom-scrollbar">
                            <h3 class="text-lg font-semibold mb-4">Sélection des Tables</h3>
                            <div class="grid grid-cols-1 gap-2">
                                <?php
                                $tables = [
                                    'logs',
                                    'ignored_folders',
                                    'mods',
                                    'options',
                                    'roles',
                                    'users',
                                    'whitelist',
                                    'whitelist_roles'
                                ];
                                foreach ($tables as $table) {
                                    echo '
                                    <label class="flex items-center p-3 rounded-lg hover:bg-gray-700/50 transition-colors">
                                        <input type="checkbox" name="tables[]" value="'.$table.'" 
                                            class="form-checkbox h-5 w-5 text-purple-500 border-2 border-gray-600 rounded-md focus:ring-purple-500">
                                        <span class="ml-3 text-gray-200">'.$table.'</span>
                                    </label>';
                                }
                                ?>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-teal-500 text-white font-bold py-3 rounded-lg hover:opacity-90 transition-opacity">
                            <i class="fas fa-download mr-2"></i>Exporter la Sélection
                        </button>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="permission-denied-card rounded-2xl shadow-xl p-8 text-center max-w-2xl mx-auto">
                <div class="bg-red-500/10 p-6 rounded-full inline-block">
                    <i class="fas fa-ban text-red-400 text-4xl"></i>
                </div>
                <h3 class="text-2xl font-bold mt-6">Accès Restreint</h3>
                <p class="text-gray-300 mt-3">Vous ne disposez pas des autorisations nécessaires pour accéder à cette fonctionnalité.</p>
                <a href="../settings" class="mt-6 inline-block bg-gray-700 hover:bg-gray-600 text-white px-6 py-2 rounded-lg transition-colors">
                    Retour au Panel
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div id="errorOverlay" class="fixed inset-0 bg-black/70 hidden items-center justify-center p-4 z-50">
        <div class="bg-gray-800 rounded-2xl shadow-xl max-w-md w-full p-6 text-center">
            <div class="bg-red-500/10 p-4 rounded-full inline-block">
                <i class="fas fa-exclamation-triangle text-red-400 text-3xl"></i>
            </div>
            <h3 class="text-xl font-bold mt-4">Sélection Requise</h3>
            <p class="text-gray-300 mt-2">Veuillez sélectionner au moins une table à exporter.</p>
            <button id="closeOverlay" class="mt-6 bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg transition-colors">
                Fermer
            </button>
        </div>
    </div>

    <script>
    document.getElementById('json_file').addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name || 'Aucun fichier sélectionné';
        document.getElementById('file-name').textContent = fileName;
        document.getElementById('file-selected').classList.toggle('hidden', !e.target.files.length);
    });

    document.getElementById('exportForm').addEventListener('submit', function(e) {
        const checkboxes = this.querySelectorAll('input[name="tables[]"]:checked');
        if (checkboxes.length === 0) {
            e.preventDefault();
            document.getElementById('errorOverlay').classList.remove('hidden');
        }
    });

    document.getElementById('closeOverlay').addEventListener('click', () => {
        document.getElementById('errorOverlay').classList.add('hidden');
    });
    </script>

    <?php include '../ui/footer.php'; ?>
</body>
</html>