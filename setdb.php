<?php
$configFilePath = './conn.php';
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Configuration de la base de données</title>
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
                        <i class="bi bi-database-fill text-6xl gradient-text"></i>
                        <h1 class="text-3xl font-bold mt-4 gradient-text">Configuration Initiale</h1>
                    </div>

                    <?php if (!file_exists($configFilePath)) : ?>
                        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST') : ?>
                            <?php
                            $host = $_POST['host'];
                            $dbname = $_POST['dbname'];
                            $username = $_POST['username'];
                            $password = $_POST['password'];
                            $webhookUrl = $_POST['webhook_url'];

                            try {
                                $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

                                $configContent = <<<EOT
<?php

\$databaseConfig = [
    'host' => '$host',
    'dbname' => '$dbname',
    'username' => '$username',
    'password' => '$password',
];

\$webhookConfig = [
    'url' => '$webhookUrl',
];

?>
EOT;

                                file_put_contents($configFilePath, $configContent);

                                // Exécuter le fichier SQL pour initialiser la base de données
                                $sqlFile = 'utils/panel.sql';
                                $sqlCommands = file_get_contents($sqlFile);
                                $pdo->exec($sqlCommands);

                                header('Location: account/register');
                                exit();
                            } catch (PDOException $e) {
                                echo '<div class="bg-red-900/50 border border-red-400 text-red-300 px-4 py-3 rounded-xl mb-6">';
                                echo "Erreur de connexion : " . $e->getMessage();
                                echo '</div>';
                            }
                        endif; ?>

                        <form method="post" class="space-y-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-2">Hôte MySQL</label>
                                <div class="relative">
                                    <input type="text" name="host" required
                                        class="input-text-black w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        placeholder="localhost:3306">
                                    <i class="bi bi-server absolute right-4 top-3.5 text-gray-500"></i>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-2">Nom de la base</label>
                                <div class="relative">
                                    <input type="text" name="dbname" required
                                        class="input-text-black w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        placeholder="Nom de la base de données">
                                    <i class="bi bi-database absolute right-4 top-3.5 text-gray-500"></i>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-2">Utilisateur</label>
                                <div class="relative">
                                    <input type="text" name="username" required
                                        class="input-text-black w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        placeholder="root">
                                    <i class="bi bi-person-circle absolute right-4 top-3.5 text-gray-500"></i>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-2">Mot de passe</label>
                                <div class="relative">
                                    <input type="password" name="password"
                                        class="input-text-black w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        placeholder="••••••••">
                                    <i id="togglePassword" class="bi bi-eye-slash-fill absolute right-4 top-3.5 cursor-pointer text-gray-500 hover:text-indigo-400 transition-colors"></i>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-400 mb-2">Webhook Discord</label>
                                <div class="relative">
                                    <input type="text" name="webhook_url" required
                                        class="input-text-black w-full px-4 py-3 bg-gray-800/50 border border-gray-700 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                        placeholder="https://discord.com/api/webhooks/...">
                                    <i class="bi bi-link absolute right-4 top-3.5 text-gray-500"></i>
                                </div>
                            </div>

                            <button type="submit" 
                                class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-lg font-semibold transition-all duration-300">
                                Configurer la base de données
                                <i class="bi bi-arrow-right-circle ml-2"></i>
                            </button>
                            </form>
                    <?php else : ?>
                        <!-- Message si la configuration est déjà effectuée -->
                        <div class="text-center py-8">
                            <div class="animate-bounce mb-6">
                                <i class="bi bi-check-circle-fill text-6xl text-green-400"></i>
                            </div>
                            <p class="text-xl text-gray-300 mb-4">Configuration déjà effectuée</p>
                            <a href="account/connexion" class="text-indigo-400 hover:text-indigo-300 transition-colors">
                                Aller à la page de connexion
                                <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (file_exists('ui/footer.php')) : ?>
        <?php require_once 'ui/footer.php'; ?>
    <?php else : ?>
        <footer class="bg-gray-800/50 py-4 mt-auto">
            <div class="container mx-auto text-center text-gray-400">
                <p>Système de configuration - <?= date('Y') ?></p>
            </div>
        </footer>
    <?php endif; ?>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('input[name="password"]');

        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            togglePassword.classList.toggle('bi-eye-fill');
            togglePassword.classList.toggle('bi-eye-slash-fill');
        });
    </script>
</body>
</html>
