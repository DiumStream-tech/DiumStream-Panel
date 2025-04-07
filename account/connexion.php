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
    if (isset($_POST['email']) && isset($_POST['password'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Adresse email invalide.";
        }

        if (empty($password)) {
            $errors[] = "Veuillez saisir votre mot de passe.";
        }

        if (empty($errors)) {
            try {
                $sth = $pdo->prepare("SELECT * FROM users WHERE email = :email");
                $sth->execute(['email' => $email]);

                if ($sth->rowCount() === 0) {
                    $errors[] = "Adresse email ou mot de passe incorrect.";
                } else {
                    $user = $sth->fetch();

                    if (!password_verify($password, $user['password'])) {
                        $errors[] = "Adresse email ou mot de passe incorrect.";
                    } else {
                        if ($user['two_factor_secret']) {
                            $_SESSION['temp_user_id'] = $user['id'];
                            $show_2fa_form = true;
                        } else {
                            $token = generateToken();
                            $_SESSION['user_email'] = $email;
                            $_SESSION['user_token'] = $token;

                            $stmt = $pdo->prepare("UPDATE users SET token = :token WHERE email = :email");
                            $stmt->bindParam(':token', $token);
                            $stmt->bindParam(':email', $email);
                            $stmt->execute();

                            ajouter_log($email, "Connexion réussie");

                            header('Location: ../settings');
                            exit();
                        }
                    }
                }
            } catch (PDOException $e) {
                echo "Erreur de connexion à la base de données: " . $e->getMessage();
                exit();
            }
        }
    } elseif (isset($_POST['2fa_code'])) {
        $code = $_POST['2fa_code'];
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['temp_user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $tfa = new TwoFactorAuth('Panel Launcher');
        if ($tfa->verifyCode($user['two_factor_secret'], $code)) {
            $token = generateToken();
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_token'] = $token;

            $stmt = $pdo->prepare("UPDATE users SET token = :token WHERE id = :id");
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':id', $user['id']);
            $stmt->execute();

            ajouter_log($user['email'], "Connexion réussie avec 2FA");

            unset($_SESSION['temp_user_id']);
            header('Location: ../settings');
            exit();
        } else {
            $errors[] = "Code 2FA incorrect.";
            $show_2fa_form = true;
        }
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
        /* Style personnalisé pour le texte saisi */
        .input-text-black {
            color: #000 !important;
        }
        .input-text-black::placeholder {
            color: #6b7280 !important; /* Gris Tailwind-500 */
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
