<?php
session_start();
$configFilePath = '../conn.php';
if (!file_exists($configFilePath)) {
    header('Location: ../setdb');
    exit();
}
require_once '../connexion_bdd.php';
require_once '../vendor/autoload.php';

use RobThree\Auth\TwoFactorAuth;

function ajouter_log($user, $action) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO logs (user, timestamp, action) VALUES (:user, :timestamp, :action)");
    $stmt->execute([
        ':user' => $user,
        ':timestamp' => date('Y-m-d H:i:s'),
        ':action' => $action
    ]);
}

function generateToken($length = 40) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_!?./$';
    $charactersLength = strlen($characters);
    $token = '';
    for ($i = 0; $i < $length; $i++) {
        $token .= $characters[rand(0, $charactersLength - 1)];
    }
    return $token;
}

$sql = "SELECT COUNT(*) as count FROM users";
$stmt = $pdo->prepare($sql);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);
$comptesExistants = $row['count'] > 0;

if (!$comptesExistants) {
    header('Location: register.php');
    exit();
}

$errors = [];
$show_2fa_form = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        if ($user['two_factor_enabled']) {
            // Protection brute-force 2FA
            $maxAttempts = 5;
            $blockDuration = 300; // 5 minutes
            $attemptKey = '2fa_attempts_' . $user['id'];
            $blockKey = '2fa_block_' . $user['id'];

            if (!isset($_SESSION[$attemptKey])) $_SESSION[$attemptKey] = 0;
            if (!isset($_SESSION[$blockKey])) $_SESSION[$blockKey] = 0;

            if ($_SESSION[$blockKey] > time()) {
                $errors[] = "Trop de tentatives. Réessayez dans " . ($_SESSION[$blockKey] - time()) . " secondes.";
            } else {
                if (!isset($_POST['code'])) {
                    $_SESSION['pending_2fa_user'] = $user['id'];
                    echo '<form method="post">';
                    echo '<input type="hidden" name="email" value="'.htmlspecialchars($email).'">';
                    echo '<input type="hidden" name="password" value="'.htmlspecialchars($password).'">';
                    echo '<label>Code 2FA :</label> <input type="text" name="code" required>';
                    echo '<button type="submit">Valider</button>';
                    echo '</form>';
                    exit;
                } else {
                    $tfa = new TwoFactorAuth('VotrePanel');
                    $code = trim($_POST['code']);
                    if (!$tfa->verifyCode($user['two_factor_secret'], $code)) {
                        $_SESSION[$attemptKey]++;
                        if ($_SESSION[$attemptKey] >= $maxAttempts) {
                            $_SESSION[$blockKey] = time() + $blockDuration;
                            $errors[] = "Trop de tentatives. Réessayez dans $blockDuration secondes.";
                        } else {
                            $errors[] = "Code 2FA invalide. Tentative " . $_SESSION[$attemptKey] . "/$maxAttempts.";
                        }
                    } else {
                        // Connexion réussie
                        $_SESSION[$attemptKey] = 0;
                        $_SESSION[$blockKey] = 0;
                        $_SESSION['user_token'] = $user['token'];
                        $_SESSION['user_email'] = $user['email'];
                        header('Location: ../settings');
                        exit;
                    }
                }
            }
        } else {
            $_SESSION['user_token'] = $user['token'];
            $_SESSION['user_email'] = $user['email'];
            header('Location: ../settings');
            exit;
        }
    } else {
        $errors[] = "Identifiants invalides.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Connexion - Panel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .glass-effect {
            background: rgba(31, 41, 55, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .gradient-text {
            background: linear-gradient(45deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .input-text-black {
            color: #000 !important;
        }
        .input-text-black::placeholder {
            color: #6b7280 !important;
        }
    </style>
</head>

<body class="bg-gray-900 text-white min-h-screen flex flex-col">
    <div class="flex-grow flex items-center">
        <div class="container mx-auto px-4 py-12">
            <div class="max-w-md mx-auto glass-effect rounded-xl overflow-hidden">
                <div class="p-8">
                    <div class="text-center mb-8">
                        <i class="bi bi-shield-lock-fill text-6xl gradient-text"></i>
                        <h1 class="text-3xl font-bold mt-4 gradient-text">Connexion Sécurisée</h1>
                    </div>

                    <?php if (!empty($errors)) : ?>
                    <div class="bg-red-900/50 border border-red-400 text-red-300 px-4 py-3 rounded-xl mb-6">
                        <?php foreach ($errors as $error) : ?>
                        <p class="flex items-center">
                            <i class="bi bi-exclamation-circle-fill mr-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </p>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!$show_2fa_form): ?>
                    <form method="post" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Adresse email</label>
                            <div class="relative">
                                <input type="email" name="email" required
                                    class="input-text-black w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="exemple@domaine.com">
                                <i class="bi bi-envelope-fill absolute right-4 top-3.5 text-gray-500"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Mot de passe</label>
                            <div class="relative">
                                <input id="password" type="password" name="password" required
                                    class="input-text-black w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="••••••••">
                                <i id="togglePassword" class="bi bi-eye-slash-fill absolute right-4 top-3.5 cursor-pointer text-gray-500 hover:text-indigo-400 transition-colors"></i>
                            </div>
                        </div>

                        <button type="submit" 
                            class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-lg font-semibold transition-all duration-300">
                            <i class="bi bi-box-arrow-in-right mr-2"></i>
                            Se connecter
                        </button>
                    </form>
                    <?php else: ?>
                    <form method="post" class="space-y-6">
                        <div class="text-center">
                            <i class="bi bi-shield-check text-4xl gradient-text"></i>
                            <h2 class="text-xl font-semibold mt-4 gradient-text">Vérification en 2 étapes</h2>
                            <p class="text-gray-400 mt-2">Entrez le code de votre application d'authentification</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Code 2FA</label>
                            <div class="relative">
                                <input type="text" name="2fa_code" required
                                    class="input-text-black w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="123456">
                                <i class="bi bi-key-fill absolute right-4 top-3.5 text-gray-500"></i>
                            </div>
                        </div>

                        <button type="submit" 
                            class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-lg font-semibold transition-all duration-300">
                            <i class="bi bi-shield-check mr-2"></i>
                            Vérifier le code
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php require_once '../ui/footer.php'; ?>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('bi-eye-slash-fill');
            this.classList.toggle('bi-eye-fill');
        });
    </script>
</body>
</html>
    <?php require_once '../ui/footer.php'; ?>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('bi-eye-slash-fill');
            this.classList.toggle('bi-eye-fill');
        });
    </script>
</body>
</html>
