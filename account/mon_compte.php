<?php
session_start();
require_once '../connexion_bdd.php';
require_once '../vendor/autoload.php';

use Sonata\GoogleAuthenticator\GoogleAuthenticator;
use Sonata\GoogleAuthenticator\GoogleQrUrl;

if (!isset($_SESSION['user_email'])) {
    header('Location: connexion.php');
    exit();
}

$user_email = $_SESSION['user_email'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute(['email' => $user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$success_message = $error_message = '';

$g = new GoogleAuthenticator();

if (isset($_POST['confirm_2fa'])) {
    $entered_code = $_POST['2fa_code'];
    $secret = $_SESSION['temp_2fa_secret'];

    if ($g->checkCode($secret, $entered_code)) {
        $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = :secret WHERE email = :email");
        $stmt->execute(['secret' => $secret, 'email' => $user_email]);
        $user['two_factor_secret'] = $secret;
        $success_message = "2FA activée avec succès.";
        unset($_SESSION['temp_2fa_secret']);
    } else {
        $error_message = "Code 2FA incorrect. Veuillez réessayer.";
    }
}

if (isset($_POST['disable_2fa'])) {
    $stmt = $pdo->prepare("UPDATE users SET two_factor_secret = NULL WHERE email = :email");
    $stmt->execute(['email' => $user_email]);
    $user['two_factor_secret'] = null;
    $success_message = "2FA désactivée avec succès.";
}

if (isset($_POST['change_email'])) {
    $new_email = filter_input(INPUT_POST, 'new_email', FILTER_VALIDATE_EMAIL);
    if ($new_email) {
        $stmt = $pdo->prepare("UPDATE users SET email = :new_email WHERE email = :current_email");
        $stmt->execute(['new_email' => $new_email, 'current_email' => $user_email]);
        $_SESSION['user_email'] = $new_email;
        $user_email = $new_email;
        $success_message = "Adresse e-mail mise à jour avec succès.";
    } else {
        $error_message = "Adresse e-mail invalide.";
    }
}

if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
            $stmt->execute(['password' => $hashed_password, 'email' => $user_email]);
            $success_message = "Mot de passe mis à jour avec succès.";
        } else {
            $error_message = "Les nouveaux mots de passe ne correspondent pas.";
        }
    } else {
        $error_message = "Mot de passe actuel incorrect.";
    }
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Compte</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-white">
    <div class="container mx-auto mt-8 p-8 bg-gray-800 rounded-lg shadow-lg">
        <h2 class="text-3xl font-bold mb-6 text-center text-white">Mon Compte</h2>

        <div id="messageContainer" class="mb-4" style="display: none;"></div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-gray-700 p-6 rounded-lg shadow">
                <h3 class="text-xl font-semibold mb-4 text-white">Changer l'adresse e-mail</h3>
                <form method="post" action="">
                    <div class="mb-4">
                        <label for="new_email" class="block text-sm font-medium text-gray-300">Nouvelle adresse e-mail</label>
                        <input type="email" name="new_email" id="new_email" required class="mt-1 block w-full rounded-md bg-gray-600 border-gray-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-white">
                    </div>
                    <button type="submit" name="change_email" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        <i class="bi bi-envelope-fill mr-2"></i>Changer l'e-mail
                    </button>
                </form>
            </div>

            <div class="bg-gray-700 p-6 rounded-lg shadow">
                <h3 class="text-xl font-semibold mb-4 text-white">Changer le mot de passe</h3>
                <form method="post" action="">
                    <div class="mb-4">
                        <label for="current_password" class="block text-sm font-medium text-gray-300">Mot de passe actuel</label>
                        <input type="password" name="current_password" id="current_password" required class="mt-1 block w-full rounded-md bg-gray-600 border-gray-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-white">
                    </div>
                    <div class="mb-4">
                        <label for="new_password" class="block text-sm font-medium text-gray-300">Nouveau mot de passe</label>
                        <input type="password" name="new_password" id="new_password" required class="mt-1 block w-full rounded-md bg-gray-600 border-gray-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-white">
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-300">Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_password" id="confirm_password" required class="mt-1 block w-full rounded-md bg-gray-600 border-gray-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-white">
                    </div>
                    <button type="submit" name="change_password" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        <i class="bi bi-lock-fill mr-2"></i>Changer le mot de passe
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-8 bg-gray-700 p-6 rounded-lg shadow">
            <h3 class="text-xl font-semibold mb-4 text-white">Authentification à deux facteurs (2FA)</h3>
            <?php if ($user['two_factor_secret']): ?>
                <p class="mb-4 text-gray-300">La 2FA est actuellement activée pour votre compte.</p>
                <form method="post" action="">
                    <button type="submit" name="disable_2fa" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        <i class="bi bi-shield-slash-fill mr-2"></i>Désactiver la 2FA
                    </button>
                </form>
            <?php else: ?>
                <p class="mb-4 text-gray-300">La 2FA n'est pas activée pour votre compte.</p>
                <button id="generateQRCode" onclick="generateQRCode()" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline mb-4">
                    <i class="bi bi-shield-check mr-2"></i>Activer la 2FA
                </button>
                <div id="qrCodeContainer" class="mb-4 text-center" style="display: none;">
                    <p class="mb-4 text-gray-300">Scannez ce QR code avec l'application Google Authenticator :</p>
                    <img id="qrCodeImage" src="" alt="QR Code" class="mx-auto mb-2">
                    <p id="manualCode" class="text-sm text-gray-300"></p>
                </div>
                <form method="post" action="" id="confirmForm" style="display: none;">
                    <div class="mb-4">
                        <label for="2fa_code" class="block text-sm font-medium text-gray-300">Code 2FA :</label>
                        <input type="text" name="2fa_code" id="2fa_code" required class="mt-1 block w-full rounded-md bg-gray-600 border-gray-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 text-white">
                    </div>
                    <button type="submit" name="confirm_2fa" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Confirmer l'activation de la 2FA
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="../settings" class="text-blue-500 hover:text-blue-700">
            <i class="bi bi-arrow-left-circle mr-2"></i>Retour au tableau de bord
        </a>
    </div>

    <script>
        function generateQRCode() {
            <?php
            $secret = $g->generateSecret();
            $_SESSION['temp_2fa_secret'] = $secret;
            $qrCodeUrl = GoogleQrUrl::generate($user_email, $secret, 'Panel Launcher');
            ?>
            
            document.getElementById('qrCodeImage').src = "<?php echo $qrCodeUrl; ?>";
            document.getElementById('manualCode').textContent = "Code manuel : <?php echo $secret; ?>";
            document.getElementById('qrCodeContainer').style.display = 'block';
            document.getElementById('confirmForm').style.display = 'block';
            document.getElementById('generateQRCode').style.display = 'none';
        }

        function showMessage(message, isError = false) {
            const messageContainer = document.getElementById('messageContainer');
            if (message && message.trim() !== '') {
                messageContainer.innerHTML = message;
                messageContainer.className = isError 
                    ? 'bg-red-800 border border-red-700 text-red-100 px-4 py-3 rounded relative'
                    : 'bg-green-800 border border-green-700 text-green-100 px-4 py-3 rounded relative';
                messageContainer.style.display = 'block';
            } else {
                messageContainer.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($success_message) && !empty($success_message)): ?>
                showMessage("<?php echo addslashes($success_message); ?>");
            <?php elseif (isset($error_message) && !empty($error_message)): ?>
                showMessage("<?php echo addslashes($error_message); ?>", true);
            <?php else: ?>
                document.getElementById('messageContainer').style.display = 'none';
            <?php endif; ?>
        });
    </script>
</body>
</html>