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

$query = "SELECT id, email, permissions FROM users";
if ($utilisateur['permissions'] !== '*') {
    $query .= " WHERE permissions != '*'";
}
$stmt = $pdo->prepare($query);
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
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-dark@4/dark.css" rel="stylesheet">
    <style>
        body {
            background-color: #1a1d23;
        }
        .overlay-content {
            background: #2d333b;
            border: 1px solid #3b424b;
            box-shadow: 0 0 30px rgba(0,0,0,0.4);
        }
        .permission-tag {
            background: #3b424b;
            border-color: #4a525d;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }
        
        .input-text-black {
            color: #000 !important;
        }
        .input-text-black::placeholder {
            color: #6b7280 !important;
        }
    </style>
</head>

<?php require_once '../../ui/header2.php'; ?>

<body class="text-gray-200">
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
        <div class="container mx-auto px-4 py-8">
            <?php if (!empty($message)) : ?>
                <div class="bg-green-500/20 border-l-4 border-green-500 p-4 mb-6">
                    <div class="flex items-center">
                        <i class="bi bi-check-circle-fill text-green-500 mr-2"></i>
                        <span class="text-green-200"><?php echo $message; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-[#2d333b] rounded-xl shadow-2xl p-6 mb-8">
                <h2 class="text-2xl font-bold mb-6 text-indigo-400">
                    <i class="bi bi-person-plus mr-2"></i>Ajouter un utilisateur
                </h2>
                
                <?php if (isset($errors) && count($errors) > 0) : ?>
                    <div class="bg-red-500/20 border-l-4 border-red-500 p-4 mb-6">
                        <?php foreach ($errors as $error) : ?>
                            <div class="flex items-center">
                                <i class="bi bi-exclamation-circle-fill text-red-500 mr-2"></i>
                                <span class="text-red-200"><?php echo $error; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="space-y-6">
                    <div class="form-group">
                        <label class="block text-sm font-medium text-gray-300 mb-2">E-mail</label>
                        <input type="email" name="email" required
                            class="input-text-black w-full bg-[#3b424b] border border-[#4a525d] rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Mot de passe</label>
                            <div class="relative">
                                <input type="password" name="password" required
                                    class="input-text-black w-full bg-[#3b424b] border border-[#4a525d] rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 pr-12">
                                <button type="button" onclick="togglePasswordVisibility(this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-indigo-500 transition-colors">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Confirmation</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" required
                                    class="input-text-black w-full bg-[#3b424b] border border-[#4a525d] rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 pr-12">
                                <button type="button" onclick="togglePasswordVisibility(this)"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-indigo-500 transition-colors">
                                    <i class="bi bi-eye-fill"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#3b424b] p-4 rounded-lg">
                        <label class="block text-sm font-medium text-gray-300 mb-4">Permissions</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <label class="flex items-center space-x-3 hover:bg-[#4a525d]/50 p-2 rounded-lg transition-all">
                                <input type="checkbox" name="permissions[]" value="logs_view"
                                    class="form-checkbox h-5 w-5 text-indigo-600 border-2 border-[#4a525d] rounded focus:ring-indigo-500">
                                <span class="text-gray-300">Voir les logs</span>
                            </label>

                            <label class="flex items-center space-x-3 hover:bg-[#4a525d]/50 p-2 rounded-lg transition-all">
                                <input type="checkbox" name="permissions[]" value="file_access"
                                    class="form-checkbox h-5 w-5 text-indigo-600 border-2 border-[#4a525d] rounded focus:ring-indigo-500">
                                <span class="text-gray-300">Accès aux fichiers</span>
                            </label>

                            <label class="flex items-center space-x-3 hover:bg-[#4a525d]/50 p-2 rounded-lg transition-all">
                                <input type="checkbox" name="permissions[]" value="register_users"
                                    class="form-checkbox h-5 w-5 text-indigo-600 border-2 border-[#4a525d] rounded focus:ring-indigo-500">
                                <span class="text-gray-300">Créer des utilisateurs</span>
                            </label>

                            <label class="flex items-center space-x-3 hover:bg-[#4a525d]/50 p-2 rounded-lg transition-all">
                                <input type="checkbox" name="permissions[]" value="export_import"
                                    class="form-checkbox h-5 w-5 text-indigo-600 border-2 border-[#4a525d] rounded focus:ring-indigo-500">
                                <span class="text-gray-300">Exporter/Importer</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-500 text-white py-3 px-6 rounded-lg transition-all duration-200">
                        <i class="bi bi-save-fill mr-2"></i>Créer l'utilisateur
                    </button>
                </form>
            </div>

            <div class="bg-[#2d333b] rounded-xl shadow-2xl p-6">
                <h2 class="text-2xl font-bold mb-6 text-indigo-400">
                    <i class="bi bi-people-fill mr-2"></i>Utilisateurs existants
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($users as $user) : ?>
                        <?php if ($user['id'] != 1) : ?>
                        <div class="bg-[#3b424b]/50 hover:bg-[#3b424b]/70 rounded-xl p-4 transition-all duration-200">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-semibold text-gray-100">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </h3>
                                <?php if ($user['permissions'] === '*') : ?>
                                    <span class="bg-yellow-500/20 text-yellow-400 text-sm px-3 py-1 rounded-full">Admin</span>
                                <?php endif; ?>
                            </div>

                            <div class="text-sm text-gray-300 mb-4">
                                <div class="flex items-center space-x-2 mb-3">
                                    <i class="bi bi-shield-lock"></i>
                                    <span>Permissions :</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <?php 
                                    $permissions = $user['permissions'] === '*' ? ['*'] : explode(',', $user['permissions']);
                                    foreach ($permissions as $perm) :
                                        $colorClass = $perm === '*' ? 'bg-purple-500/20 text-purple-400' : 'bg-[#4a525d] text-gray-200';
                                    ?>
                                        <span class="text-xs px-3 py-1 rounded-full <?php echo $colorClass; ?>">
                                            <?php echo htmlspecialchars($perm); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <?php if ($user['id'] != 1 && $utilisateur['permissions'] === '*') : ?>
                                <div class="flex space-x-3 border-t border-[#4a525d] pt-4">
                                    <button onclick="showChangePasswordOverlay(<?php echo $user['id']; ?>)"
                                        class="flex-1 bg-[#4a525d] hover:bg-[#5a6470] text-white text-sm px-4 py-2 rounded-lg transition-all">
                                        <i class="bi bi-key-fill mr-2"></i>Password
                                    </button>

                                    <button onclick="showChangePermissionsOverlay(<?php echo $user['id']; ?>, '<?php echo $user['permissions']; ?>')"
                                        class="flex-1 bg-[#4a525d] hover:bg-[#5a6470] text-white text-sm px-4 py-2 rounded-lg transition-all">
                                        <i class="bi bi-shield-lock-fill mr-2"></i>Permissions
                                    </button>

                                    <form method="post" class="flex-1" onsubmit="return confirmDelete()">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="user_email" value="<?php echo $user['email']; ?>">
                                        <button type="submit" name="delete_user"
                                            class="w-full bg-red-500/20 hover:bg-red-500/30 text-red-400 text-sm px-4 py-2 rounded-lg transition-all">
                                            <i class="bi bi-trash-fill mr-2"></i>Supprimer
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Overlay changement mot de passe -->
            <div id="changePasswordOverlay" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4">
                <div class="overlay-content rounded-xl w-full max-w-md p-6 animate-fade-in">
                    <h3 class="text-xl font-bold mb-6 text-indigo-400">
                        <i class="bi bi-key-fill mr-2"></i>Changer le mot de passe
                    </h3>

                    <form method="post" action="">
                        <input type="hidden" name="user_id" id="changePasswordUserId">

                        <div class="space-y-4">
                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-300 mb-2">Nouveau mot de passe</label>
                                <div class="relative">
                                    <input type="password" name="new_password" required
                                        class="input-text-black w-full bg-[#3b424b] border border-[#4a525d] rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 pr-12">
                                    <button type="button" onclick="togglePasswordVisibility(this)"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-indigo-500 transition-colors">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-300 mb-2">Confirmation</label>
                                <div class="relative">
                                    <input type="password" name="confirm_new_password" required
                                        class="input-text-black w-full bg-[#3b424b] border border-[#4a525d] rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 pr-12">
                                    <button type="button" onclick="togglePasswordVisibility(this)"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-indigo-500 transition-colors">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" onclick="hideChangePasswordOverlay()"
                                class="bg-[#4a525d] hover:bg-[#5a6470] text-white px-6 py-2 rounded-lg transition-all">
                                Annuler
                            </button>
                            <button type="submit" name="change_password"
                                class="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-2 rounded-lg transition-all">
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Overlay permissions -->
            <div id="changePermissionsOverlay" class="fixed inset-0 bg-black/80 z-50 hidden items-center justify-center p-4">
                <div class="overlay-content rounded-xl w-full max-w-md p-6 animate-fade-in">
                    <h3 class="text-xl font-bold mb-6 text-indigo-400">
                        <i class="bi bi-shield-lock-fill mr-2"></i>Modifier les permissions
                    </h3>

                    <form method="post" action="">
                        <input type="hidden" name="user_id" id="changePermissionsUserId">

                        <div class="grid grid-cols-1 gap-4">
                            <label class="flex items-center space-x-3 hover:bg-[#3b424b]/50 p-2 rounded-lg transition-all">
                                <input type="checkbox" name="permissions[]" value="logs_view" id="perm_logs_view"
                                    class="form-checkbox h-5 w-5 text-indigo-600 border-2 border-[#4a525d] rounded focus:ring-indigo-500">
                                <span class="text-gray-300">Voir les logs</span>
                            </label>

                            <label class="flex items-center space-x-3 hover:bg-[#3b424b]/50 p-2 rounded-lg transition-all">
                                <input type="checkbox" name="permissions[]" value="file_access" id="perm_file_access"
                                    class="form-checkbox h-5 w-5 text-indigo-600 border-2 border-[#4a525d] rounded focus:ring-indigo-500">
                                <span class="text-gray-300">Accès aux fichiers</span>
                            </label>

                            <label class="flex items-center space-x-3 hover:bg-[#3b424b]/50 p-2 rounded-lg transition-all">
                                <input type="checkbox" name="permissions[]" value="register_users" id="perm_register_users"
                                class="form-checkbox h-5 w-5 text-indigo-600 border-2 border-[#4a525d] rounded focus:ring-indigo-500">
                                <span class="text-gray-300">Créer des utilisateurs</span>
                            </label>

                            <label class="flex items-center space-x-3 hover:bg-[#3b424b]/50 p-2 rounded-lg transition-all">
                                <input type="checkbox" name="permissions[]" value="export_import" id="perm_export_import"
                                    class="form-checkbox h-5 w-5 text-indigo-600 border-2 border-[#4a525d] rounded focus:ring-indigo-500">
                                <span class="text-gray-300">Exporter/Importer</span>
                            </label>
                        </div>

                        <div class="flex justify-end space-x-3 mt-6">
                            <button type="button" onclick="hideChangePermissionsOverlay()"
                                class="bg-[#4a525d] hover:bg-[#5a6470] text-white px-6 py-2 rounded-lg transition-all">
                                Annuler
                            </button>
                            <button type="submit" name="change_permissions"
                                class="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-2 rounded-lg transition-all">
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                // Fonctions de gestion des overlays
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

                function togglePasswordVisibility(button) {
                    const input = button.previousElementSibling;
                    const icon = button.querySelector('i');
                    
                    if (input.type === "password") {
                        input.type = "text";
                        icon.classList.remove('bi-eye-fill');
                        icon.classList.add('bi-eye-slash-fill');
                    } else {
                        input.type = "password";
                        icon.classList.remove('bi-eye-slash-fill');
                        icon.classList.add('bi-eye-fill');
                    }
                }

                function confirmDelete() {
                    return Swal.fire({
                        title: 'Êtes-vous sûr ?',
                        text: "Cette action est irréversible !",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Oui, supprimer !',
                        cancelButtonText: 'Annuler'
                    }).then((result) => {
                        return result.isConfirmed;
                    });
                }
            </script>

            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </div>
    <?php endif; ?>

    <?php require_once '../../ui/footer.php'; ?>
</body>
</html>
