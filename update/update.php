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
    $versionFile = __DIR__ . '/../update/version.json';
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
    $updateJsonPath = __DIR__ . '/../update/update.json';
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
        mkdir($extractPath);

        $zip->extractTo($extractPath);
        $zip->close();
        unlink($zipFile);

        $innerFolder = glob("$extractPath/*")[0];
        if (is_dir($innerFolder)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($innerFolder, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
                $destination = str_replace("$innerFolder/", '../', $file->getPathname());
                if ($file->isDir()) {
                    mkdir($destination);
                } else {
                    rename($file->getPathname(), $destination);
                }
            }
            rmdir($innerFolder);
        }
        rmdir($extractPath);

        return true;
    } else {
        throw new Exception('Échec de l\'ouverture du fichier ZIP.');
    }
}

function updateDatabase() {
    global $pdo;
    
    $sqlFilePath = __DIR__ . '/../utils/panel.sql';
    
    if (!file_exists($sqlFilePath)) {
        throw new Exception("Fichier panel.sql introuvable.");
    }

    try {
        foreach (explode(';', file_get_contents($sqlFilePath)) as $query) {
            if (trim($query)) {
                $pdo->exec(trim($query));
            }
        }
        
        return ['success' => true, 'message' => 'Base de données mise à jour avec succès.'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => "Erreur lors de la mise à jour de la base de données : {$e->getMessage()}"];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['check_update'])) {
            $currentVersion = getCurrentVersion();
            $latestVersion = getLatestVersion();

            if (isNewVersionAvailable($currentVersion, $latestVersion)) {
                echo json_encode(['success' => true, 'new_version_available' => true, 'current_version' => $currentVersion, 'latest_version' => $latestVersion]);
            } else {
                echo json_encode(['success' => true, 'new_version_available' => false, 'current_version' => $currentVersion]);
            }
        } elseif (isset($_POST['update_button'])) {
            updateFiles();
            updateDatabase();
            
            file_put_contents(__DIR__ . '/../update/version.json', json_encode(['version' => getLatestVersion()], JSON_PRETTY_PRINT));
            
            ajouter_log($_SESSION['user_email'], "Mise à jour effectuée.");
            
            echo json_encode(['success' => true, 'message' => "Mise à jour effectuée avec succès."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Requête non valide.']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => "Erreur : {$e->getMessage()}"]);
        
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête non valide.']);
}
?>
