<?php
session_start();
$configFilePath = '../conn.php';

if (!file_exists($configFilePath)) {
    header('Location: ../setdb');
    exit();
}
require_once '../connexion_bdd.php';

if (isset($_SESSION['user_token'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE token = :token");
    $stmt->bindParam(':token', $_SESSION['user_token']);
    $stmt->execute();
    $utilisateur = $stmt->fetch();

    if ($utilisateur) {
        header('Location: accueil.php');
        exit();
    }
}

$sql = "SELECT COUNT(*) as count FROM users";
$stmt = $pdo->prepare($sql);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row['count'] > 0) {
    header('Location: connexion');
    exit();
}

if (isset($_POST['submit'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    $errors = array();

    if (empty($email) || empty($password) || empty($confirm_password)) {
        $errors[] = "Tous les champs sont obligatoires.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Adresse email invalide.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }

    if (count($errors) === 0) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (email, password, permissions) VALUES (:email, :password, :permissions)";
        $stmt = $pdo->prepare($query);

        $stmt->execute(array(
            'email' => $email,
            'password' => $hashed_password,
            'permissions' => '*'
        ));

        header('Location: connexion?register=success');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Configuration Admin - Panel</title>
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
                        <h1 class="text-3xl font-bold mt-4 gradient-text">Configuration Admin</h1>
                        <p class="text-gray-400 mt-2">Créez le compte administrateur principal</p>
                    </div>

                    <?php if (isset($errors) && count($errors) > 0) : ?>
                    <div class="bg-red-900/50 border border-red-400 text-red-300 px-4 py-3 rounded-xl mb-6">
                        <?php foreach ($errors as $error) : ?>
                        <p class="flex items-center">
                            <i class="bi bi-exclamation-circle-fill mr-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </p>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <form method="post" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Email Administrateur</label>
                            <div class="relative">
                                <input type="email" name="email" required
                                    class="input-text-black w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="admin@exemple.com">
                                <i class="bi bi-envelope-fill absolute right-4 top-3.5 text-gray-500"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Mot de passe</label>
                            <div class="relative">
                                <input type="password" name="password" id="password" required
                                    class="input-text-black w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="••••••••">
                                <i id="togglePassword" class="bi bi-eye-slash-fill absolute right-4 top-3.5 cursor-pointer text-gray-500 hover:text-indigo-400 transition-colors"></i>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Confirmation</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirm_password" required
                                    class="input-text-black w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                    placeholder="••••••••">
                                <i id="toggleConfirmPassword" class="bi bi-eye-slash-fill absolute right-4 top-3.5 cursor-pointer text-gray-500 hover:text-indigo-400 transition-colors"></i>
                            </div>
                        </div>

                        <button type="submit" name="submit" 
                            class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-lg font-semibold transition-all duration-300">
                            <i class="bi bi-shield-lock-fill mr-2"></i>
                            Créer le compte
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php require_once '../ui/footer.php'; ?>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
        const confirmPassword = document.querySelector('#confirm_password');

        togglePassword.addEventListener('click', () => {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            togglePassword.classList.toggle('bi-eye-slash-fill');
            togglePassword.classList.toggle('bi-eye-fill');
        });

        toggleConfirmPassword.addEventListener('click', () => {
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            toggleConfirmPassword.classList.toggle('bi-eye-slash-fill');
            toggleConfirmPassword.classList.toggle('bi-eye-fill');
        });
    </script>
</body>
</html>
