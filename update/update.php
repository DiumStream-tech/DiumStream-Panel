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
    return trim(file_get_contents('version.txt'));
}

function getUpdateInfo() {
    $updateJsonPath = 'update.json';
    if (!file_exists($updateJsonPath)) {
        throw new Exception('Fichier update.json introuvable.');
    }
    return json_decode(file_get_contents($updateJsonPath), true);
}

function getLatestVersion() {
    $updateInfo = getUpdateInfo();
    $url = $updateInfo['version_url'];
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "Cache-Control: no-cache, no-store, must-revalidate\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    return trim(file_get_contents($url, false, $context));
}

function isNewVersionAvailable($currentVersion, $latestVersion) {
    return version_compare($currentVersion, $latestVersion, '<');
}

function updateFiles() {
    $updateInfo = getUpdateInfo();
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

        $innerFolder = $extractPath . '/DiumStream-Panel-main';
        if (is_dir($innerFolder)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($innerFolder, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $destination = str_replace($innerFolder, '..', $file);

                if ($file->isDir()) {
                    mkdir($destination);
                } else {
                    rename($file, $destination);
                }
            }
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($innerFolder, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }

            rmdir($innerFolder);
        }
        rmdir($extractPath);

        return true;
    } else {
        return false;
    }
}

function updateDatabase($pdo) {
    $sqlFilePath = '../utils/panel.sql';
    if (!file_exists($sqlFilePath)) {
        return ['success' => false, 'message' => "Fichier panel.sql introuvable."];
    }

    $sqlContent = file_get_contents($sqlFilePath);
    $tableSegments = explode('CREATE TABLE', $sqlContent);
    array_shift($tableSegments);
    $newTables = [];

    $existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tableSegments as $segment) {
        $segment = 'CREATE TABLE ' . $segment;
        preg_match('/`(\w+)`/', $segment, $tableMatch);
        if (isset($tableMatch[1])) {
            $tableName = $tableMatch[1];
            $newTables[] = $tableName;
            if (!in_array($tableName, $existingTables)) {
                if ($pdo->exec($segment) === false) {
                    throw new Exception("Erreur lors de la création de la table '$tableName'.");
                }
            }
        } else {
            throw new Exception("Impossible d'extraire le nom de la table pour le segment suivant : \n$segment\n");
        }
    }

    foreach ($tableSegments as $segment) {
        preg_match('/`(\w+)`/', $segment, $tableMatch);
        $tableName = $tableMatch[1];

        $result = $pdo->query("SHOW COLUMNS FROM `$tableName`");
        $existingColumns = [];
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[$row['Field']] = $row['Type'];
        }

        preg_match_all('/`(\w+)` (\w+\([\d,]+\)|\w+(\(\d+\))?)/', $segment, $matches);
        $newColumns = array_combine($matches[1], $matches[2]);

        foreach ($newColumns as $column => $type) {
            if (!array_key_exists($column, $existingColumns)) {
                $alterQuery = "ALTER TABLE `$tableName` ADD COLUMN `$column` $type";
                if ($pdo->exec($alterQuery) === false) {
                    return ['success' => false, 'message' => "Erreur lors de l'ajout de la colonne '$column' à la table '$tableName'."];
                }
            }
        }
    }    

    return ['success' => true, 'message' => "Base de données mise à jour avec succès."];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_button'])) {
    $currentVersion = getCurrentVersion();
    $latestVersion = getLatestVersion();

    if (isNewVersionAvailable($currentVersion, $latestVersion)) {
        if (updateFiles()) {
            $dbUpdateResult = updateDatabase($pdo);
            if ($dbUpdateResult['success']) {
                file_put_contents('version.txt', $latestVersion);
                ajouter_log($_SESSION['user_email'], "Mise à jour effectuée de la version $currentVersion à $latestVersion");
                echo json_encode(['success' => true, 'message' => "Mise à jour terminée avec succès à la version $latestVersion."]);
            } else {
                echo json_encode($dbUpdateResult);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Échec de la mise à jour des fichiers.']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'Aucune mise à jour disponible.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête non valide.']);
}
?>