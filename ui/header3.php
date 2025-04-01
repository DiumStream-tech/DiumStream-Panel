<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
    @media (max-width: 1536px) {
        .header-buttons-container {
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .logout-button {
            order: 3;
            margin-top: 0.5rem;
            width: 100%;
        }
    }
        ::-webkit-scrollbar {
            display: none;
        }
        body {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    
    </style>
</head>
<body class="bg-gray-800 text-white">
    <nav class="bg-gray-900 p-4 static w-full z-10 top-0 shadow">
        <div class="container mx-auto flex flex-wrap items-center justify-between">
            <a class="text-xl font-bold" href="../settings">Panel</a>
            <button class="text-white 2xl:hidden" id="nav-toggle">
                <i class="bi bi-list"></i>
            </button>
            <div class="w-full 2xl:flex 2xl:items-center 2xl:w-auto hidden 2xl:block" id="nav-content">
                <ul class="flex flex-wrap items-center text-sm space-x-4">
                    <li class="nav-item">
                        <a class="block 2xl:inline-block px-4 py-2" href="../settings#">
                        <i class="bi bi-gear mr-2"></i> Général
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="block 2xl:inline-block px-4 py-2" href="../settings#server-info-settings">
                        <i class="bi bi-hdd-network mr-2"></i> Serveur
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="block 2xl:inline-block px-4 py-2" href="../settings#loader-settings">
                        <i class="bi bi-cloud-arrow-down mr-2"></i> Loader
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="block 2xl:inline-block px-4 py-2" href="../settings#mods-settings">
                        <i class="bi bi-puzzle mr-2"></i> Mods optionnels
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="block 2xl:inline-block px-4 py-2" href="../settings#maintenance-settings">
                        <i class="bi bi-tools mr-2"></i> Maintenance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="block 2xl:inline-block px-4 py-2" href="../settings#whitelist-settings">
                        <i class="bi bi-person-check mr-2"></i> Whitelist
                        </a>
                    </li>
                </ul>
                <div class="flex flex-col 2xl:flex-row items-center space-y-4 2xl:space-y-0 2xl:space-x-4 mt-4 2xl:mt-0 header-buttons-container">
                    <div class="relative inline-block text-left w-full 2xl:w-auto">
                    <button type="button" class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-yellow-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-yellow-700" id="menu-button-other" aria-expanded="true" aria-haspopup="true">
                    <i class="bi bi-three-dots mr-2"></i> Autres
                            <svg class="-mr-1 h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div id="other-dropdown" class="hidden absolute right-0 z-10 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="menu-button-other" tabindex="-1">
                            <div class="py-1" role="none">
                                <a class="block px-4 py-2 text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1" id="menu-item-1" href="../settings#video-settings">
                                <i class="bi bi-camera-video mr-2"></i> Vidéo communautaire
                                </a>
                                <a class="block px-4 py-2 text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1" id="menu-item-1" href="../settings#alert-settings">
                                <i class="bi bi-bell mr-2"></i> Alerte
                                </a>
                                <a class="block px-4 py-2 text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1" id="menu-item-1" href="../settings#ignored-folders-settings">
                                <i class="bi bi-folder-x mr-2"></i> Dossiers ignorés
                                </a>
                                <a class="block px-4 py-2 text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1" id="menu-item-2" href="../settings#roles-settings">
                                <i class="bi bi-images mr-2"></i> Fond d'écran par rôle
                                </a>
                                <a class="block px-4 py-2 text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1" id="menu-item-5" href="../settings#rpc-settings">
                                <i class="bi bi-discord mr-2"></i> Discord RPC
                                </a>
                                <a class="block px-4 py-2 text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1" id="menu-item-6" href="../settings#splash-settings">
                                <i class="bi bi-water mr-2"></i> Splash
                                </a>
                            </div>
                        </div>
                    </div>          <div class="relative inline-block text-left w-full 2xl:w-auto">
                    <button type="button" class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700" id="menu-button-settings" aria-expanded="true" aria-haspopup="true">
                            <i class="bi bi-sliders mr-2"></i> Paramètres Panel
                            <svg class="-mr-1 h-5 w-5 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <div id="settings-panel-dropdown" class="hidden absolute right-0 z-10 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="menu-button-settings" tabindex="-1">
                            <div class="py-1" role="none">
                                <a href="../utils/import_export.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1" id="menu-item-3">
                                    <i class="bi bi-arrow-down-up mr-2"></i> Importer/Exporter
                                </a>
                                <a href="../account/new/register" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1" id="menu-item-4">
                                    <i class="bi bi-person-plus mr-2"></i> Ajouter un utilisateur
                                </a>
                                <a href="../file" class="block px-4 py-2 text-gray-700 hover:bg-gray-100" role="menuitem" tabindex="-1" id="menu-item-5">
                                    <i class="bi bi-folder mr-2"></i> Fichiers Panel
                                </a>
                            </div>
                        </div>
                    </div>
                    <form class="w-full 2xl:w-auto logout-button" method="post" action="">
                        <a href="account/mon_compte" class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700">
                            <i class="bi bi-person-circle mr-2"></i> Mon Compte
                        </a>
                        </form> 
                        <form class="w-full 2xl:w-auto logout-button" method="post" action="">
                            <button class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-700" type="submit" name="logout">
                            <i class="bi bi-box-arrow-left mr-2"></i> Déconnexion
                        </button>
                    </form>  
                </div>
            </div>
        </div>
    </nav>

    <script>
        document.getElementById('nav-toggle').addEventListener('click', function() {
            var navContent = document.getElementById('nav-content');
            navContent.classList.toggle('hidden');
        });

        document.getElementById('menu-button-other').addEventListener('click', function() {
            var dropdown = document.getElementById('other-dropdown');
            dropdown.classList.toggle('hidden');
        });

        document.getElementById('menu-button-settings').addEventListener('click', function() {
            var dropdown = document.getElementById('settings-panel-dropdown');
            dropdown.classList.toggle('hidden');
        });

        window.addEventListener('click', function(e) {
            var otherButton = document.getElementById('menu-button-other');
            var otherDropdown = document.getElementById('other-dropdown');
            if (!otherButton.contains(e.target) && !otherDropdown.contains(e.target)) {
                otherDropdown.classList.add('hidden');
            }

            var settingsButton = document.getElementById('menu-button-settings');
            var settingsDropdown = document.getElementById('settings-panel-dropdown');
            if (!settingsButton.contains(e.target) && !settingsDropdown.contains(e.target)) {
                settingsDropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>