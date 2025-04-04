<?php
session_start();
require_once '../connexion_bdd.php';
require_once '../vendor/autoload.php';

use RobThree\Auth\TwoFactorAuth;

if (!isset($_SESSION['user_email'])) {
    header('Location: connexion.php');
    exit();
}

$user_email = $_SESSION['user_email'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute(['email' => $user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$success_message = $error_message = '';

$tfa = new TwoFactorAuth('Panel Launcher');

if (isset($_POST['confirm_2fa'])) {
    $entered_code = $_POST['2fa_code'];
    $secret = $_SESSION['temp_2fa_secret'];

    if ($tfa->verifyCode($secret, $entered_code)) {
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
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion du compte - Panel Launcher</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/css/custom.css" rel="stylesheet">
</head>
<body class="bg-gray-900 text-gray-100 flex flex-col min-h-screen">
    <nav class="bg-gray-800 p-4">
        <div class="container mx-auto flex justify-between items-center">
            <a href="/" class="text-xl font-bold text-indigo-400 hover:text-indigo-300 transition duration-300">
                <i class="bi bi-shield-lock-fill mr-2"></i>Panel Launcher
            </a>
            <a href="../settings" class="text-gray-300 hover:text-white transition duration-300">
                <i class="bi bi-arrow-left-circle mr-2"></i>Retour au tableau de bord
            </a>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8 flex-1">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold mb-8 text-center border-b border-gray-700 pb-4">
                <i class="bi bi-person-circle mr-2"></i>Gestion du compte
            </h1>

            <div id="messageContainer" class="fixed top-20 right-5 transition-all duration-500 z-50"></div>

            <div class="space-y-8">
                <section class="bg-gray-800 rounded-xl shadow-2xl p-6 hover:shadow-indigo-500/10 transition-shadow">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="bi bi-envelope-fill mr-2 text-indigo-400"></i>
                        Adresse email
                    </h2>
                    <form method="post" class="space-y-4">
                        <div class="form-group">
                            <label class="block text-sm font-medium mb-2">Nouvelle adresse email</label>
                            <div class="relative">
                                <input type="email" name="new_email" required
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg py-2 px-4 pr-10 text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                    placeholder="Entrez votre nouvelle adresse email">
                                <i class="bi bi-at absolute right-3 top-2.5 text-gray-400"></i>
                            </div>
                        </div>
                        <button type="submit" name="change_email" 
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center justify-center transition-all">
                            <i class="bi bi-save-fill mr-2"></i>Mettre à jour l'email
                        </button>
                    </form>
                </section>

                <section class="bg-gray-800 rounded-xl shadow-2xl p-6 hover:shadow-indigo-500/10 transition-shadow">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="bi bi-shield-lock-fill mr-2 text-indigo-400"></i>
                        Sécurité du compte
                    </h2>
                    <form method="post" class="space-y-4">
                        <div class="form-group">
                            <label class="block text-sm font-medium mb-2">Mot de passe actuel</label>
                            <div class="relative">
                                <input type="password" name="current_password" required
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg py-2 px-4 pr-10 text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                                <i class="bi bi-lock-fill absolute right-3 top-2.5 text-gray-400"></i>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-group">
                                <label class="block text-sm font-medium mb-2">Nouveau mot de passe</label>
                                <div class="relative">
                                    <input type="password" name="new_password" required
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg py-2 px-4 pr-10 text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                                    <i class="bi bi-key-fill absolute right-3 top-2.5 text-gray-400"></i>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="block text-sm font-medium mb-2">Confirmation</label>
                                <div class="relative">
                                    <input type="password" name="confirm_password" required
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg py-2 px-4 pr-10 text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
                                    <i class="bi bi-check-circle-fill absolute right-3 top-2.5 text-gray-400"></i>
                                </div>
                            </div>
                        </div>

                        <button type="submit" name="change_password" 
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center justify-center transition-all">
                            <i class="bi bi-arrow-repeat mr-2"></i>Changer le mot de passe
                        </button>
                    </form>
                </section>

                <section class="bg-gray-800 rounded-xl shadow-2xl p-6 hover:shadow-indigo-500/10 transition-shadow">
                    <h2 class="text-xl font-semibold mb-4 flex items-center">
                        <i class="bi bi-shield-check-fill mr-2 text-indigo-400"></i>
                        Authentification à deux facteurs (2FA)
                    </h2>

                    <?php if ($user['two_factor_secret']): ?>
                        <div class="alert bg-green-900/50 border border-green-800 p-4 rounded-lg mb-6">
                            <p class="text-green-400 flex items-center">
                                <i class="bi bi-check-circle-fill mr-2"></i>
                                La 2FA est activée pour votre compte
                            </p>
                        </div>

                        <form method="post">
                            <button type="submit" name="disable_2fa" 
                                class="w-full bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg flex items-center justify-center transition-all">
                                <i class="bi bi-shield-slash-fill mr-2"></i>Désactiver la 2FA
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="space-y-6">
                            <button id="generateQRCode" onclick="generateQRCode()"
                                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-4 rounded-lg flex items-center justify-center transition-all">
                                <i class="bi bi-qr-code-scan mr-2"></i>Activer la 2FA
                            </button>

                            <div id="qrCodeContainer" class="hidden space-y-4 p-4 bg-gray-700/50 rounded-lg">
                                <p class="text-gray-300 text-sm">Scannez ce QR code avec Google Authenticator :</p>
                                <img id="qrCodeImage" src="" alt="QR Code" class="mx-auto w-48 h-48">
                                <p id="manualCode" class="text-center text-gray-300 text-sm font-mono"></p>
                            </div>

                            <form method="post" id="confirmForm" class="hidden space-y-4">
                                <div class="form-group">
                                    <label class="block text-sm font-medium mb-2">Code de vérification</label>
                                    <div class="relative">
                                        <input type="text" name="2fa_code" required
                                            class="w-full bg-gray-700 border border-gray-600 rounded-lg py-2 px-4 pr-10 text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                            placeholder="Entrez le code à 6 chiffres">
                                        <i class="bi bi-shield-fill-check absolute right-3 top-2.5 text-gray-400"></i>
                                    </div>
                                </div>

                                <button type="submit" name="confirm_2fa" 
                                    class="w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-lg flex items-center justify-center transition-all">
                                    <i class="bi bi-check-circle-fill mr-2"></i>Confirmer l'activation
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <?php include '../ui/footer.php'; ?>

    <script>
        function generateQRCode() {
            <?php
            $secret = $tfa->createSecret();
            $_SESSION['temp_2fa_secret'] = $secret;
            $qrCodeUrl = $tfa->getQRCodeImageAsDataUri($user_email, $secret);
            ?>
            
            document.getElementById('qrCodeImage').src = "<?php echo $qrCodeUrl; ?>";
            document.getElementById('manualCode').textContent = "Code manuel : <?php echo chunk_split($secret, 4, ' '); ?>";
            document.getElementById('qrCodeContainer').classList.remove('hidden');
            document.getElementById('confirmForm').classList.remove('hidden');
            document.getElementById('generateQRCode').classList.add('hidden');
        }

        function showMessage(message, isError = false) {
            const messageContainer = document.getElementById('messageContainer');
            messageContainer.innerHTML = `
                <div class="p-4 rounded-lg shadow-lg flex items-center space-x-3 animate-fade-in-up ${isError ? 'bg-red-800/90' : 'bg-green-800/90'}">
                    <i class="bi ${isError ? 'bi-exclamation-triangle-fill text-red-400' : 'bi-check-circle-fill text-green-400'} text-xl"></i>
                    <span>${message}</span>
                </div>
            `;
            
            setTimeout(() => {
                messageContainer.classList.add('animate-fade-out');
                setTimeout(() => messageContainer.innerHTML = '', 500);
            }, 5000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($success_message)): ?>
                showMessage("<?php echo addslashes($success_message); ?>");
            <?php elseif (!empty($error_message)): ?>
                showMessage("<?php echo addslashes($error_message); ?>", true);
            <?php endif; ?>
        });
    </script>
</body>
</html>

