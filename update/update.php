<?php
session_start();
$configFilePath = '../conn.php';

if (!file_exists($configFilePath)) {
    echo json_encode(['success' => false, 'message' => 'Fichier de configuration introuvable.']);
    exit();
}

require_once '../connexion_bdd.php';

if (!isset($_SESSION['user_token']) || !isset($_SESSION['user_email'])) {
    echo json_encode(['success' => false, 'message' => 'Accès refusé. Veuillez vous connecter.']);
    exit();
}

if (isset($_SESSION['user_token'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE token = :token");
    $stmt->execute([':token' => $_SESSION['user_token']]);
    $user = $stmt->fetch();

    if (!$user) {
        die(json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']));
    }

    function hasPermission($user, $permission) {
        if ($user['permissions'] === '*') return true;
        $userPermissions = explode(',', $user['permissions']);
        return in_array($permission, $userPermissions);
    }

    if (!hasPermission($user, 'update')) {
        die(json_encode(['success' => false, 'message' => 'Permission refusée']));
    }
}

function ajouter_log($user, $action) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO logs (user, timestamp, action) VALUES (:user, :timestamp, :action)");
    $stmt->execute([
        ':user' => $user,
        ':timestamp' => date('Y-m-d H:i:s'),
        ':action' => $action
    ]);
}

function getCurrentVersion() {
    $versionFile = __DIR__ . '/json/version.json';
    if (!file_exists($versionFile)) {
        throw new Exception('Fichier version.json introuvable.');
    }
    $fileContent = file_get_contents($versionFile);
    $jsonData = json_decode($fileContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($jsonData['version'])) {
        throw new Exception('Erreur lors de la lecture ou du décodage du fichier version.json.');
    }
    return trim($jsonData['version']);
}

function getUpdateInfo() {
    $updateJsonPath = __DIR__ . '/json/update.json';
    if (!file_exists($updateJsonPath)) {
        throw new Exception('Fichier update.json introuvable.');
    }
    $fileContent = file_get_contents($updateJsonPath);
    $jsonData = json_decode($fileContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Erreur lors de la lecture ou du décodage du fichier update.json.');
    }
    return $jsonData;
}

function getLatestVersion() {
    $updateInfo = getUpdateInfo();
    if (!isset($updateInfo['version_url'])) {
        throw new Exception('Clé "version_url" manquante dans update.json.');
    }
    $url = $updateInfo['version_url'];
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "Cache-Control: no-cache, no-store, must-revalidate\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    $latestVersionContent = file_get_contents($url, false, $context);
    if ($latestVersionContent === false) {
        throw new Exception("Impossible de récupérer la dernière version depuis l'URL.");
    }
    $jsonData = json_decode($latestVersionContent, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($jsonData['version'])) {
        throw new Exception('Erreur lors de la lecture ou du décodage de la version distante.');
    }
    return trim($jsonData['version']);
}

function isNewVersionAvailable($currentVersion, $latestVersion) {
    return version_compare($currentVersion, $latestVersion, '<');
}

function deleteFolderRecursive($folderPath) {
    foreach (glob("$folderPath/*") as $item) {
        if (is_dir($item)) {
            deleteFolderRecursive($item);
            rmdir($item);
        } else {
            unlink($item);
        }
    }
}

function updateFiles() {
    $updateInfo = getUpdateInfo();
    if (!isset($updateInfo['zip_url'])) {
        throw new Exception('Clé "zip_url" manquante dans update.json.');
    }

    $zipFile = 'update.zip';
    $url = $updateInfo['zip_url'];

    file_put_contents($zipFile, fopen($url, 'r'));

    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $extractPath = './temp-update';
        if (!is_dir($extractPath)) {
            mkdir($extractPath, 0755, true);
        }

        $zip->extractTo($extractPath);
        $zip->close();
        unlink($zipFile);

        $innerFolder = glob("$extractPath/*")[0];
        if (is_dir($innerFolder)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($innerFolder, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                $destination = str_replace("$innerFolder/", '../', $file->getPathname());
                $destinationDir = dirname($destination);
                
                if (!is_dir($destinationDir)) {
                    mkdir($destinationDir, 0755, true);
                }

                if (is_dir($file)) {
                    if (!is_dir($destination)) {
                        mkdir($destination, 0755);
                    }
                } else {
                    if (file_exists($destination)) {
                        unlink($destination);
                    }
                    rename($file, $destination);
                }
            }
            
            // Suppression améliorée du dossier temporaire
            deleteFolderRecursive($extractPath);
            rmdir($extractPath);
        }
    } else {
        throw new Exception('Impossible d\'ouvrir le fichier ZIP.');
    }
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['check_update'])) {
            $currentVersion = getCurrentVersion();
            $latestVersion = getLatestVersion();
            $newVersionAvailable = isNewVersionAvailable($currentVersion, $latestVersion);

            echo json_encode([
                'success' => true,
                'new_version_available' => $newVersionAvailable,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion
            ]);
            exit();
        }

        if (isset($_POST['update_button'])) {
            updateFiles();
            
            $currentVersion = getCurrentVersion();
            $latestVersion = getLatestVersion();
            
            if (!isNewVersionAvailable($currentVersion, $latestVersion)) {
                ajouter_log($_SESSION['user_email'], "Mise à jour réussie vers la version $latestVersion");
                echo json_encode([
                    'success' => true,
                    'message' => 'Mise à jour terminée avec succès.',
                    'new_version' => $latestVersion
                ]);
            } else {
                throw new Exception('La mise à jour semble avoir échoué. La version actuelle n\'a pas été mise à jour.');
            }
            exit();
        }
    }
} catch (Exception $e) {
    ajouter_log($_SESSION['user_email'], "Échec de la mise à jour : " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit();
}

echo json_encode([
    'success' => false,
    'message' => 'Action non reconnue.'
]);
exit();
