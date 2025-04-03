<script>
document.addEventListener('DOMContentLoaded', function() {
    const loaderTypeSelect = document.getElementById('loader-type');
    const mcVersionInput = document.getElementById('minecraft_version');
    const loaderBuildVersionSelect = document.getElementById('loader-build-version');
    const loaderBuildVersionInput = document.getElementById('loader-build-version-input');
    const loaderForgeVersion = "<?php echo htmlspecialchars($row['loader_forge_version'], ENT_QUOTES); ?>";

    function fetchLoaderBuildVersions(loaderType, mcVersion) {
        const apiUrl = `function/loader_api.php?loader=${loaderType}&mc_version=${mcVersion}`;
        
        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    updateLoaderBuildVersions(data.builds, loaderType);
                } else {
                    console.error('Erreur API:', data.message);
                }
            })
            .catch(error => {
                console.error('Erreur lors de la récupération des versions de build:', error);
            });
    }

    function updateLoaderBuildVersions(builds, loaderType) {
        loaderBuildVersionSelect.innerHTML = '';

        builds.forEach(build => {
            const option = document.createElement('option');
            option.value = build;
            option.textContent = build;

            if (loaderType === 'forge' && build === loaderForgeVersion) {
                option.selected = true;
            }

            loaderBuildVersionSelect.appendChild(option);
        });

        if (['forge', 'fabric', 'neoforge'].includes(loaderType)) {
            loaderBuildVersionSelect.style.display = 'block';
            loaderBuildVersionInput.style.display = 'none';
        } else {
            loaderBuildVersionSelect.style.display = 'none';
            loaderBuildVersionInput.style.display = 'block';
        }
    }

    loaderTypeSelect.addEventListener('change', function() {
        const loaderType = loaderTypeSelect.value;
        const mcVersion = mcVersionInput.value;

        if (mcVersion) {
            fetchLoaderBuildVersions(loaderType, mcVersion);
        }
        
        if (['forge', 'fabric', 'neoforge'].includes(loaderType)) {
            loaderBuildVersionSelect.style.display = 'block';
            loaderBuildVersionInput.style.display = 'none';
        } else {
            loaderBuildVersionSelect.style.display = 'none';
            loaderBuildVersionInput.style.display = 'block';
        }
    });

    mcVersionInput.addEventListener('change', function() {
        const loaderType = loaderTypeSelect.value;

        if (loaderType && mcVersionInput.value) {
            fetchLoaderBuildVersions(loaderType, mcVersionInput.value);
        }
    });

    // Initialisation au chargement de la page
    const initialLoaderType = loaderTypeSelect.value;
    if (initialLoaderType && mcVersionInput.value) {
        fetchLoaderBuildVersions(initialLoaderType, mcVersionInput.value);
        
        if (['forge', 'fabric', 'neoforge'].includes(initialLoaderType)) {
            loaderBuildVersionSelect.style.display = 'block';
            loaderBuildVersionInput.style.display = 'none';
        } else {
            loaderBuildVersionSelect.style.display = 'none';
            loaderBuildVersionInput.style.display = 'block';
        }
    }
});
</script>

<div class="grid grid-cols-1 gap-6">
    <div id="loader-settings">
        <div class="container mx-auto mt-10 p-6 bg-gray-900 text-white border border-gray-700 rounded-lg shadow-lg">
            <h2 class="text-3xl font-bold mb-6 text-gray-100 border-b border-gray-600 pb-2">Paramètres du Loader et de Minecraft</h2>
            
            <form method="post" action="settings#loader-settings">
                <div class="mb-6">
                    <label for="minecraft_version" class="block text-sm font-medium text-gray-400 mb-2">Version de Minecraft :</label>
                    <input type="text" class="form-input mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500" id="minecraft_version" name="minecraft_version" value="<?php echo htmlspecialchars($row['minecraft_version'], ENT_QUOTES); ?>">
                </div>

                <div class="flex items-center mb-6">
                    <input type="checkbox" class="form-checkbox h-5 w-5 text-indigo-600 rounded focus:ring-indigo-500" id="loader-activation" name="loader_activation" <?php if ($row['loader_activation'] == 1) echo 'checked'; ?>>
                    <label for="loader-activation" class="ml-2 block text-sm text-gray-400">Activer le loader</label>
                </div>

                <div class="mb-6">
                    <label for="loader-type" class="block text-sm font-medium text-gray-400 mb-2">Type de Loader :</label>
                    <select class="form-select mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500" id="loader-type" name="loader_type">
                        <option value="forge" <?php if ($row['loader_type'] == 'forge') echo 'selected'; ?>>Forge</option>
                        <option value="fabric" <?php if ($row['loader_type'] == 'fabric') echo 'selected'; ?>>Fabric</option>
                        <option value="neoforge" <?php if ($row['loader_type'] == 'neoforge') echo 'selected'; ?>>NeoForge</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label for="loader-build-version" class="block text-sm font-medium text-gray-400 mb-2">Version de Build du Loader :</label>
                    <select class="form-select mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500" id="loader-build-version" name="loader_forge_version" style="display:none;"></select>
                    <input type="text" class="form-input mt-1 block w-full rounded-lg border-gray-600 bg-gray-700 text-gray-200 p-2 focus:ring-indigo-500 focus:border-indigo-500" id="loader-build-version-input" name="loader_build_version" style="display:none;" value="<?php echo htmlspecialchars($row['loader_build_version'], ENT_QUOTES); ?>">
                </div>

                <button type="submit" name="submit_loader_settings" class="mt-6 px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg shadow-md hover:bg-indigo-700 focus:outline-none focus:ring-indigo-500">
                    Enregistrer
                </button>
            </form>
        </div>
    </div>
</div>
