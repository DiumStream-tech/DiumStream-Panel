<?php
session_start();
$configFilePath = 'conn.php';

if (isset($_POST['logout'])) {
    ajouter_log($_SESSION['user_email'], "Déconnexion");
    session_unset();
    session_destroy();
    header('Location: account/connexion');
    exit();
}

if (!file_exists($configFilePath)) {
    header('Location: setdb');
    exit();
}
require_once 'connexion_bdd.php';

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
        header('Location: account/connexion');
        exit();
    }
} else {
    header('Location: account/connexion');
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

$baseDir = realpath(__DIR__ . '/data/files'); 
$currentDir = isset($_GET['dir']) ? $_GET['dir'] : '';

$fullPath = realpath($baseDir . '/' . $currentDir);

if ($fullPath === false || strpos($fullPath, $baseDir) !== 0) {
    die('Accès interdit ou chemin invalide.');
}

if (isset($_POST['create_folder']) && !empty($_POST['new_folder'])) {
    $newFolder = $fullPath . '/' . $_POST['new_folder'];
    if (file_exists($newFolder)) {
        echo "<script>alert('Un dossier avec ce nom existe déjà !');</script>";
    } else {
        mkdir($newFolder, 0777, true);
        ajouter_log($_SESSION['user_email'], "Création du dossier: " . $_POST['new_folder']);
    }
}

if (isset($_POST['upload'])) {
    $uploadFile = $fullPath . '/' . basename($_FILES['upload_file']['name']);
    if (file_exists($uploadFile)) {
        echo "<script>alert('Un fichier avec ce nom existe déjà !');</script>";
    } else {
        if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $uploadFile)) {
            ajouter_log($_SESSION['user_email'], "Upload du fichier: " . basename($_FILES['upload_file']['name']));
        }
    }
}

if (isset($_POST['extract_zip']) && isset($_FILES['upload_zip'])) {
    $zipFile = $fullPath . '/' . basename($_FILES['upload_zip']['name']);
    if (move_uploaded_file($_FILES['upload_zip']['tmp_name'], $zipFile)) {
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($fullPath);
            $zip->close();
            unlink($zipFile);
            ajouter_log($_SESSION['user_email'], "Extraction du zip: " . basename($_FILES['upload_zip']['name']));
        }
    }
}

if (isset($_GET['delete'])) {
    $deletePath = $baseDir . '/' . $_GET['delete'];
    if (is_file($deletePath)) {
        unlink($deletePath);
        ajouter_log($_SESSION['user_email'], "Suppression du fichier: " . $_GET['delete']);
    } elseif (is_dir($deletePath)) {
        deleteDirectory($deletePath);
        ajouter_log($_SESSION['user_email'], "Suppression du dossier: " . $_GET['delete']);
    }
}

if (isset($_POST['delete_selected']) && !empty($_POST['selected_files'])) {
    foreach ($_POST['selected_files'] as $file) {
        $deletePath = $fullPath . '/' . $file;
        if (is_file($deletePath)) {
            unlink($deletePath);
            ajouter_log($_SESSION['user_email'], "Suppression du fichier: " . $file);
        } elseif (is_dir($deletePath)) {
            deleteDirectory($deletePath);
            ajouter_log($_SESSION['user_email'], "Suppression du dossier: " . $file);
        }
    }
}

if (isset($_GET['download'])) {
    $filePath = $baseDir . '/' . $_GET['download'];
    if (is_file($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($filePath));
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        ajouter_log($_SESSION['user_email'], "Téléchargement du fichier: " . $_GET['download']);
        exit;
    }
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}

function createZip($files, $destination) {
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE) !== TRUE) {
        return false;
    }
    
    foreach ($files as $file) {
        if (file_exists($file)) {
            $relativePath = substr($file, strlen(dirname($file)) + 1);
            $zip->addFile($file, $relativePath);
        }
    }
    
    $zip->close();
    return file_exists($destination);
}

if (isset($_POST['create_zip']) && !empty($_POST['selected_files'])) {
    $zipName = 'selection_' . date('YmdHis') . '.zip';
    $zipPath = $fullPath . '/' . $zipName;
    $filesToZip = array_map(function($file) use ($fullPath) {
        return $fullPath . '/' . $file;
    }, $_POST['selected_files']);
    
    if (createZip($filesToZip, $zipPath)) {
        ajouter_log($_SESSION['user_email'], "Création du zip: " . $zipName);
        header("Location: " . $_SERVER['PHP_SELF'] . "?dir=" . urlencode($currentDir));
        exit;
    }
}

require_once 'ui/header4.php';
?>

<div class="min-h-screen flex flex-col bg-gray-900">
    <div class="flex-grow">
        <div class="container mx-auto px-4 py-8">
            <!-- Header amélioré -->
            <div class="flex justify-between items-center mb-8 p-6 bg-gray-800 rounded-xl shadow-lg">
                <div>
                    <h2 class="text-3xl font-bold text-white">📁 Explorateur de Fichiers</h2>
                    <p class="text-sm text-gray-400 mt-2">Gestion des fichiers et dossiers</p>
                </div>
            </div>

            <!-- Outils de gestion -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <!-- Carte de création de dossier -->
                <div class="bg-gray-800 p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow">
                    <h3 class="text-xl font-semibold text-white mb-4">➕ Nouveau dossier</h3>
                    <form method="POST" class="space-y-4">
                        <input type="text" name="new_folder" placeholder="Nom du dossier" class="w-full px-4 py-2 bg-gray-700 text-white rounded-lg focus:ring-2 focus:ring-indigo-500">
                        <button type="submit" name="create_folder" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg">
                            Créer
                        </button>
                    </form>
                </div>

                <!-- Carte d'upload -->
                <div class="bg-gray-800 p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow">
                    <h3 class="text-xl font-semibold text-white mb-4">⬆️ Upload de fichier</h3>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="file" name="upload_file" class="w-full text-white">
                        <button type="submit" name="upload" class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
                            Envoyer
                        </button>
                    </form>
                </div>

                <!-- Carte d'extraction ZIP -->
                <div class="bg-gray-800 p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow">
                    <h3 class="text-xl font-semibold text-white mb-4">📦 Extraire ZIP</h3>
                    <form method="POST" enctype="multipart/formdata" class="space-y-4">
                        <input type="file" name="upload_zip" class="w-full text-white">
                        <button type="submit" name="extract_zip" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg">
                            Extraire
                        </button>
                    </form>
                </div>
            </div>

            <!-- Liste de fichiers améliorée -->
            <form method="POST" id="mainForm">
                <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                    <!-- Barre d'outils -->
                    <div class="px-6 py-4 border-b border-gray-700 flex justify-between items-center">
                        <div class="flex items-center space-x-4">
                            <button type="button" onclick="selectAllFiles()" class="text-indigo-500 hover:text-indigo-600 transition-colors">
                                <i></i> Tout sélectionner
                            </button>

                            <div id="actionButtons" style="display: none;" class="flex items-center space-x-4">
                                <button type="submit" name="delete_selected" class="text-red-500 hover:text-red-600 transition-colors">
                                    <i class="bi bi-trash text-2xl"></i> Supprimer
                                </button>
                                <button type="submit" name="create_zip" class="text-green-500 hover:text-green-600 transition-colors ml-4">
                                <i class="bi bi-file-earmark-zip text-2xl"></i> ZIP
                                </button>
                            </div>
                        </div>
                        
                        <nav class="flex" aria-label="Breadcrumb">
                            <ol class="flex items-center space-x-2">
                                <li>
                                    <a href="?dir=" class="text-gray-400 hover:text-white">Accueil</a>
                                </li>
                                <?php
                                $cumulativePath = '';
                                foreach (explode('/', $currentDir) as $part):
                                    if (!empty($part)):
                                        $cumulativePath .= '/' . $part;
                                ?>
                                <li class="flex items-center">
                                    <span class="text-gray-500 mx-2">/</span>
                                    <a href="?dir=<?= urlencode(trim($cumulativePath, '/')) ?>" class="text-gray-400 hover:text-white">
                                        <?= htmlspecialchars($part) ?>
                                    </a>
                                </li>
                                <?php endif; endforeach; ?>
                            </ol>
                        </nav>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <tbody class="divide-y divide-gray-700">
                                <?php if ($currentDir): ?>
                                <tr class="hover:bg-gray-750 transition-colors">
                                    <td class="px-6 py-4">
                                        <a href="?dir=<?= urlencode(dirname($currentDir)) ?>" class="text-indigo-400 hover:text-indigo-300 flex items-center">
                                            <i class="bi bi-arrow-left-circle text-2xl mr-3"></i>
                                            <span>Dossier parent</span>
                                        </a>
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-400">--</td>
                                    <td class="px-6 py-4 text-right text-gray-400">--</td>
                                </tr>
                                <?php endif; ?>

                                <?php
                                function formatSize($bytes) {
                                    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
                                    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
                                    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
                                    return $bytes . ' B';
                                }

                                $items = scandir($fullPath);
                                foreach ($items as $item):
                                    if ($item !== '.' && $item !== '..' && $item !== 'index.php'):
                                        $itemPath = $fullPath . '/' . $item;
                                        $isDir = is_dir($itemPath);
                                ?>
                                <tr class="hover:bg-gray-750 transition-colors">
                                    <td class="px-6 py-4">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="selected_files[]" value="<?= htmlspecialchars($item) ?>" 
                                                   class="form-checkbox h-5 w-5 text-indigo-600 rounded transition-all duration-200 ease-in-out"
                                                   onchange="updateActionButtons()">
                                        </label>
                                    </td>
                                    
                                    <td class="px-6 py-4">
                                        <div class="flex items-center group">
                                            <i class="bi <?= $isDir ? 'bi-folder-fill text-yellow-400' : 'bi-file-earmark-text text-gray-400' ?> text-2xl mr-3"></i>
                                            <?php if ($isDir): ?>
                                                <a href="?dir=<?= urlencode(trim($currentDir . '/' . $item, '/')) ?>" class="text-white hover:text-indigo-300 font-medium">
                                                    <?= htmlspecialchars($item) ?>
                                                </a>
                                            <?php else: ?>
                                                <div class="relative">
                                                    <span class="text-white font-medium group-hover:text-indigo-300 transition-colors">
                                                        <?= htmlspecialchars($item) ?>
                                                    </span>
                                                    <span class="absolute -bottom-1 left-0 w-full h-0.5 bg-indigo-400 scale-x-0 group-hover:scale-x-100 transition-transform origin-left"></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-right text-gray-400">
                                        <?= $isDir ? 'Dossier' : formatSize(filesize($itemPath)) ?>
                                    </td>
                                    
                                    <td class="px-6 py-4 text-right space-x-4">
                                        <?php if (!$isDir): ?>
                                        <a href="?download=<?= urlencode(trim($currentDir . '/' . $item, '/')) ?>" class="text-green-500 hover:text-green-400" title="Télécharger">
                                            <i class="bi bi-download text-xl"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <a href="?delete=<?= urlencode(trim($currentDir . '/' . $item, '/')) ?>" class="text-red-500 hover:text-red-400" title="Supprimer" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet élément?')">
                                            <i class="bi bi-trash text-xl"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php require_once './ui/footer.php'; ?>
</div>

<?php if (hasPermission($utilisateur, 'file_access')): ?>
<script>
function selectAllFiles() {
    const checkboxes = document.querySelectorAll('input[name="selected_files[]"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    updateActionButtons();
}

function updateActionButtons() {
    var checkboxes = document.querySelectorAll('input[name="selected_files[]"]:checked');
    var actionButtons = document.getElementById('actionButtons');
    
    if (checkboxes.length > 0) {
        actionButtons.style.display = 'flex';
    } else {
        actionButtons.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="selected_files[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', updateActionButtons);
    });
});
</script>
<?php endif; ?>
