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
</head>
<body class="bg-gray-950 text-gray-200">
    <?php require_once '../ui/header5.php'; ?>

    <div class="container mx-auto mt-8 px-4">
        <h1 class="text-4xl font-bold mb-8 text-center text-gray-100">Importer/Exporter</h1>

        <?php if ($message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md shadow-md" role="alert">
                <p class="font-bold">Message</p>
                <p><?php echo $message; ?></p>
            </div>
        <?php endif; ?>

        <?php if ($hasPermission): ?>
        <div class="bg-gray-900 rounded-lg shadow-xl p-8 max-w-4xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <div class="bg-gray-800 p-6 rounded-lg shadow-md">
                    <h2 class="text-2xl font-semibold mb-6 flex items-center text-gray-100">
                        <i class="fas fa-file-import mr-3 text-blue-400"></i>Importer
                    </h2>
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-6">
                            <label for="json_file" class="block text-sm font-medium text-gray-300 mb-2">Sélectionner un fichier JSON</label>
                            <div class="flex items-center">
                                <input type="file" name="json_file" id="json_file" accept=".json" class="hidden">
                                <label for="json_file" class="cursor-pointer bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-l transition duration-300 ease-in-out flex-grow text-center">
                                    <i class="fas fa-file-upload mr-2"></i>Choisir un fichier
                                </label>
                                <span id="file-name" class="bg-gray-700 text-gray-300 py-3 px-4 rounded-r w-1/2 truncate">Aucun fichier choisi</span>
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded transition duration-300 ease-in-out flex items-center justify-center">
                            <i class="fas fa-upload mr-2"></i>Importer
                        </button>
                    </form>
                </div>

                <div class="bg-gray-800 p-6 rounded-lg shadow-md">
                    <h2 class="text-2xl font-semibold mb-6 flex items-center text-gray-100">
                        <i class="fas fa-file-export mr-3 text-green-400"></i>Exporter
                    </h2>
                    <form method="get" action="" id="exportForm">
                        <input type="hidden" name="action" value="export">
                        <div class="space-y-3 mb-6 max-h-96 overflow-y-auto pr-2 custom-scrollbar">
                            <h3 class="text-lg font-semibold mb-2 text-gray-200">Tables à exporter</h3>
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
                                echo "<label class='flex items-center text-gray-300 hover:bg-gray-700 p-2 rounded transition duration-200 ease-in-out'>
                                        <input type='checkbox' name='tables[]' value='$table' class='form-checkbox h-5 w-5 text-blue-600 rounded'>
                                        <span class='ml-2'>$table</span>
                                      </label>";
                            }
                            ?>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded transition duration-300 ease-in-out flex items-center justify-center">
                            <i class="fas fa-download mr-2"></i>Exporter la sélection
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div id="accessDeniedOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-gray-800 p-8 rounded-lg text-center">
                <h3 class="text-xl font-bold mb-4 text-gray-100">Accès refusé</h3>
                <p class="mb-6 text-gray-300">Vous n'avez pas la permission d'accéder à Importer/Exporter.</p>
                <a href="../settings" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Retour au panel
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div id="errorOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
        <div class="bg-gray-800 p-8 rounded-lg text-center">
            <h3 class="text-xl font-bold mb-4 text-gray-100">Erreur</h3>
            <p class="mb-6 text-gray-300">Veuillez sélectionner au moins une table à exporter.</p>
            <button id="closeOverlay" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                Fermer
            </button>
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #1F2937;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #4B5563;
            border-radius: 20px;
            border: 2px solid #1F2937;
        }
    </style>

    <script>
    document.getElementById('json_file').addEventListener('change', function(e) {
        var fileName = e.target.files[0] ? e.target.files[0].name : 'Aucun fichier choisi';
        document.getElementById('file-name').textContent = fileName;
    });

    document.getElementById('exportForm').addEventListener('submit', function(e) {
        var checkboxes = this.querySelectorAll('input[name="tables[]"]:checked');
        if (checkboxes.length === 0) {
            e.preventDefault();
            document.getElementById('errorOverlay').style.display = 'flex';
        }
    });

    document.getElementById('closeOverlay').addEventListener('click', function() {
        document.getElementById('errorOverlay').style.display = 'none';
    });
    </script>

    <?php include '../ui/footer.php'; ?>
</body>
</html>