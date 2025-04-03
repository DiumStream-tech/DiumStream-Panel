<?php
$versionFile = __DIR__ . '/../update/version.json';

$currentVersion = 'Inconnue';

if (file_exists($versionFile)) {
    $fileContent = file_get_contents($versionFile);
    if ($fileContent !== false) {
        $jsonData = json_decode($fileContent, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['version'])) {
            $currentVersion = trim($jsonData['version']);
        } else {
            error_log("Erreur lors du décodage du fichier version.json ou clé 'version' manquante.");
        }
    } else {
        error_log("Impossible de lire le contenu du fichier version.json");
    }
} else {
    error_log("Le fichier version.json n'existe pas à l'emplacement spécifié");
}
?>

<div class="footer mt-5 bg-gray-800 text-gray-300 py-4 text-center" id="footer">
    Fork créé par DiumStream | Basé sur <a href="https://github.com/Riptiaz/CentralCorp-Panel" class="underline" target="_blank">CentralCorp-Panel</a> fait par Riptiaz et Vexato | <strong>Version <?php echo htmlspecialchars($currentVersion); ?></strong>
    <div class="mt-2">
        <a href="https://discord.dium-corp.fr" class="mx-2" target="_blank" title="Rejoindre notre Discord">
            <i class="bi bi-discord text-2xl"></i>
        </a>
        <a href="https://github.com/DiumStream-tech/DiumStream-Panel" class="mx-2" target="_blank" title="Notre GitHub">
            <i class="bi bi-github text-2xl"></i>
        </a>
    </div>
</div>

</body>
</html>
