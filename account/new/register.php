<?php
session_start();
$configFilePath = '../../conn.php';
if (!file_exists($configFilePath)) {
    header('Location: ../../setdb');
    exit();
}
require_once '../../connexion_bdd.php';

function ajouter_log($user, $action) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO logs (user, timestamp, action) VALUES (:user, :timestamp, :action)");
    $stmt->execute([
        ':user' => $user,
        ':timestamp' => date('Y-m-d H:i:s'),
        ':action' => $action
    ]);
}

function hasPermission($user, $permission) {
    if ($user['permissions'] === '*') {
        return true;
    }
    $userPermissions = explode(',', $user['permissions']);
    return in_array($permission, $userPermissions);
}

if (isset($_POST['logout'])) {
    ajouter_log($_SESSION['user_email'], "Déconnexion");
    session_unset();
    session_destroy();
    header('Location: ../connexion');
    exit();
}

if (isset($_SESSION['user_token'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE token = :token");
    $stmt->bindParam(':token', $_SESSION['user_token']);
    $stmt->execute();
    $utilisateur = $stmt->fetch();

    if (!$utilisateur) {
        header('Location: ../connexion');
        exit();
    }
} else {
    header('Location: ../connexion');
    exit();
}

$message = '';

if (isset($_POST['submit'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $permissions = isset($_POST['permissions']) ? implode(',', $_POST['permissions']) : '';

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

    $query = "SELECT id FROM users WHERE email = :email";
    $stmt = $pdo->prepare($query);
    $stmt->execute(array('email' => $email));

    if ($stmt->rowCount() > 0) {
        $errors[] = "Adresse email déjà utilisée.";
    }

    if (count($errors) === 0) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (email, password, permissions) VALUES (:email, :password, :permissions)";
        $stmt = $pdo->prepare($query);

        $stmt->execute(array(
            'email' => $email,
            'password' => $hashed_password,
            'permissions' => $permissions
        ));

        $message = "Utilisateur ajouté avec succès.";
        ajouter_log($_SESSION['user_email'], "Ajout de l'utilisateur: $email");
    }
}

if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    $user_email = $_POST['user_email'];
    
    if ($user_id != 1) {
        $query = "DELETE FROM users WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(array('id' => $user_id));
        
        $message = "Utilisateur supprimé avec succès.";
        ajouter_log($_SESSION['user_email'], "Suppression de l'utilisateur: $user_email");
    } else {
        $message = "Impossible de supprimer l'utilisateur principal.";
    }
}

if (isset($_POST['change_password'])) {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if ($new_password === $confirm_new_password) {
        if ($user_id != 1) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = :password WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute(array('password' => $hashed_password, 'id' => $user_id));
            
            $message = "Mot de passe changé avec succès.";
            ajouter_log($_SESSION['user_email'], "Changement de mot de passe pour l'utilisateur ID: $user_id");
        } else {
            $message = "Impossible de changer le mot de passe de l'utilisateur principal ici.";
        }
    } else {
        $message = "Les nouveaux mots de passe ne correspondent pas.";
    }
}

if (isset($_POST['change_permissions'])) {
    $user_id = $_POST['user_id'];
    $permissions = isset($_POST['permissions']) ? implode(',', $_POST['permissions']) : '';
    
    if ($user_id != 1) {
        $query = "UPDATE users SET permissions = :permissions WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute(array('permissions' => $permissions, 'id' => $user_id));
        
        $message = "Permissions mises à jour avec succès.";
        ajouter_log($_SESSION['user_email'], "Modification des permissions pour l'utilisateur ID: $user_id");
    } else {
        $message = "Impossible de modifier les permissions de l'utilisateur principal.";
    }
}

$stmt = $pdo->prepare("SELECT id, email, permissions FROM users");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Gestion des utilisateurs</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>

<?php require_once '../../ui/header2.php'; ?>

<body class="bg-gray-900 text-white">
    <?php if (!hasPermission($utilisateur, 'register_users')): ?>
        <div id="accessDeniedOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="bg-gray-800 p-8 rounded-lg text-center">
                <h3 class="text-xl font-bold mb-4">Accès refusé</h3>
                <p class="mb-6">Vous n'avez pas la permission d'ajouter ou de gérer les utilisateurs.</p>
                <a href="../../settings" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Retour au panel
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="container mx-auto mt-20 p-6 bg-gray-900 text-white border border-gray-700 rounded-lg shadow-lg">
            <div class="flex justify-center">
                <div class="w-full max-w-md">
                    <h2 class="text-3xl font-bold mb-6 text-center">Ajouter un utilisateur</h2>
                    <?php if (!empty($message)) : ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($errors) && count($errors) > 0) : ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                            <ul>
                                <?php foreach ($errors as $error) : ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="">
                        <div class="mb-4">
                            <label for="email" class="block text-gray-400 text-sm font-medium mb-2">E-mail :</label>
                            <input type="email" name="email" id="email" class="form-input mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div class="mb-4">
                            <label for="password" class="block text-gray-400 text-sm font-medium mb-2">Mot de passe :</label>
                            <input type="password" name="password" id="password" class="form-input mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div class="mb-4">
                            <label for="confirm_password" class="block text-gray-400 text-sm font-medium mb-2">Confirmez le mot de passe :</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-input mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div class="mb-4">
                            <label class="block text-gray-400 text-sm font-medium mb-2">Permissions :</label>
                            <div>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="permissions[]" value="logs_view" class="form-checkbox">
                                    <span class="ml-2">Voir les logs</span>
                                </label>
                            </div>
                            <div>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="permissions[]" value="file_access" class="form-checkbox">
                                    <span class="ml-2">Accès aux fichiers</span>
                                </label>
                            </div>
                            <div>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="permissions[]" value="register_users" class="form-checkbox">
                                    <span class="ml-2">Enregistrer de nouveaux utilisateurs</span>
                                </label>
                            </div>
                            <div>
                                <label class="inline-flex items-center">
                                    <input type="checkbox" name="permissions[]" value="export_import" class="form-checkbox">
                                    <span class="ml-2">Exporter/Importer</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex items-center justify-center">
                            <button type="submit" name="submit" class="bg-indigo-500 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-opacity-50">
                                Ajouter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="container mx-auto mt-10 p-6 bg-gray-800 text-white border border-gray-700 rounded-lg shadow-lg">
            <h2 class="text-3xl font-bold mb-6 text-center">Liste des utilisateurs</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($users as $user) : ?>
                    <div class="p-4 bg-gray-900 rounded-lg shadow-md">
                        <h3 class="text-lg font-medium mb-2"><?php echo htmlspecialchars($user['email']); ?></h3>
                        <p class="text-sm text-gray-400 mb-2">Permissions : <?php echo htmlspecialchars($user['permissions']); ?></p>
                        <?php if ($user['id'] != 1 && $utilisateur['permissions'] === '*') : ?>
                            <div class="flex justify-between items-center">
                                <form method="post" action="" class="inline-block" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="user_email" value="<?php echo $user['email']; ?>">
                                    <button type="submit" name="delete_user" class="bg-red-500 text-white py-1 px-2 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50">
                                        Supprimer
                                    </button>
                                </form>
                                <button onclick="showChangePasswordOverlay(<?php echo $user['id']; ?>)" class="bg-yellow-500 text-white py-1 px-2 rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-opacity-50">
                                    Changer le password
                                </button>
                                <button onclick="showChangePermissionsOverlay(<?php echo $user['id']; ?>, '<?php echo $user['permissions']; ?>')" class="bg-blue-500 text-white py-1 px-2 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                                    Modifier les permissions
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="changePasswordOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
            <div class="bg-gray-800 p-8 rounded-lg">
                <h3 class="text-xl font-bold mb-4">Changer le password</h3>
                <form method="post" action="" id="changePasswordForm">
                    <input type="hidden" name="user_id" id="changePasswordUserId">
                    <input type="password" name="new_password" placeholder="Nouveau mot de passe" class="form-input mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                    <input type="password" name="confirm_new_password" placeholder="Confirmer le mot de passe" class="form-input mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500 mt-2" required>
                    <div class="flex justify-between mt-4">
                        <button type="submit" name="change_password" class="bg-green-500 text-white py-1 px-2 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                            Confirmer
                        </button>
                        <button type="button" onclick="hideChangePasswordOverlay()" class="bg-gray-500 text-white py-1 px-2 rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="changePermissionsOverlay" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center">
            <div class="bg-gray-800 p-8 rounded-lg">
                <h3 class="text-xl font-bold mb-4">Modifier les permissions</h3>
                <form method="post" action="" id="changePermissionsForm">
                    <input type="hidden" name="user_id" id="changePermissionsUserId">
                    <div>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="permissions[]" value="logs_view" id="perm_logs_view" class="form-checkbox">
                            <span class="ml-2">Voir les logs</span>
                        </label>
                    </div>
                    <div>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="permissions[]" value="file_access" id="perm_file_access" class="form-checkbox">
                            <span class="ml-2">Accès aux fichiers</span>
                        </label>
                    </div>
                    <div>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="permissions[]" value="register_users" id="perm_register_users" class="form-checkbox">
                            <span class="ml-2">Enregistrer de nouveaux utilisateurs</span>
                        </label>
                    </div>
                    <div>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="permissions[]" value="export_import" id="perm_export_import" class="form-checkbox">
                            <span class="ml-2">Exporter/Importer</span>
                        </label>
                    </div>
                    <div class="flex justify-between mt-4">
                        <button type="submit" name="change_permissions" class="bg-green-500 text-white py-1 px-2 rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                            Confirmer
                        </button>
                        <button type="button" onclick="hideChangePermissionsOverlay()" class="bg-gray-500 text-white py-1 px-2 rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-50">
                            Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function showChangePasswordOverlay(userId) {
                document.getElementById('changePasswordUserId').value = userId;
                document.getElementById('changePasswordOverlay').classList.remove('hidden');
                document.getElementById('changePasswordOverlay').classList.add('flex');
            }

            function hideChangePasswordOverlay() {
                document.getElementById('changePasswordOverlay').classList.remove('flex');
                document.getElementById('changePasswordOverlay').classList.add('hidden');
            }

            function showChangePermissionsOverlay(userId, permissions) {
                document.getElementById('changePermissionsUserId').value = userId;
                const permArray = permissions.split(',');
                document.getElementById('perm_logs_view').checked = permArray.includes('logs_view');
                document.getElementById('perm_file_access').checked = permArray.includes('file_access');
                document.getElementById('perm_register_users').checked = permArray.includes('register_users');
                document.getElementById('perm_export_import').checked = permArray.includes('export_import');
                document.getElementById('changePermissionsOverlay').classList.remove('hidden');
                document.getElementById('changePermissionsOverlay').classList.add('flex');
            }

            function hideChangePermissionsOverlay() {
                document.getElementById('changePermissionsOverlay').classList.remove('flex');
                document.getElementById('changePermissionsOverlay').classList.add('hidden');
            }
        </script>
    <?php endif; ?>

    <?php require_once '../../ui/footer.php'; ?>
</body>
</html>