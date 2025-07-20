<?php
header('Content-Type: application/json');

function checkLoaderCompatibility($loader, $mcVersion) {
    $loaderRanges = [
        'forge' => ['min' => '1.2', 'max' => '1.21.8'],
        'fabric' => ['min' => '1.14', 'max' => '1.21.8'],
        'neoforge' => ['min' => '1.20.2', 'max' => '1.21.8'],
        'quilt' => ['min' => '1.17', 'max' => '1.21.8']
    ];

    if (isset($loaderRanges[$loader])) {
        $min = $loaderRanges[$loader]['min'];
        $max = $loaderRanges[$loader]['max'];
        
        if (version_compare($mcVersion, $min, '<') || version_compare($mcVersion, $max, '>')) {
            return [
                'status' => 'out_of_range',
                'message' => 'Version Minecraft incompatible',
                'suggested_version' => $max
            ];
        }
    }
    return ['status' => 'ok'];
}

function getForgeBuilds($mcVersion) {
    $url = "https://files.minecraftforge.net/net/minecraftforge/forge/index_$mcVersion.html";
    $builds = [];

    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $html = @file_get_contents($url, false, $context);

    if ($html !== false) {
        $dom = new DOMDocument;
        $prevUseErrors = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prevUseErrors);
        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//a[contains(@href, "maven.minecraftforge.net/net/minecraftforge/forge/")]');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (preg_match('/forge\/([\d\.\-]+)\/forge-\1-/', $href, $matches)) {
                $version = $matches[1];
                if (!in_array($version, $builds)) {
                    $builds[] = $version;
                }
            }
        }
    }

    return $builds;
}

function getFabricBuilds($mcVersion) {
    $url = "https://meta.fabricmc.net/v2/versions/loader/";
    $builds = [];
    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $json = @file_get_contents($url, false, $context);

    $minFabricVersion = "1.14";
    $maxFabricVersion = "1.21.8";

    if (version_compare($mcVersion, $minFabricVersion, ">=") && version_compare($mcVersion, $maxFabricVersion, "<=")) {
        if ($json !== false) {
            $data = json_decode($json, true);
            foreach ($data as $item) {
                if (isset($item['version'])) {
                    $builds[] = $item['version'];
                }
            }
        }
    }

    return $builds;
}

function getNeoForgeBuilds($mcVersion) {
    $url = "https://maven.neoforged.net/releases/net/neoforged/neoforge/maven-metadata.xml";
    $builds = [];

    $minNeoForgeVersion = "1.20.2";
    $maxNeoForgeVersion = "1.21.8";

    if (version_compare($mcVersion, $minNeoForgeVersion, ">=") && version_compare($mcVersion, $maxNeoForgeVersion, "<=")) {
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $xml = @file_get_contents($url, false, $context);

        if ($xml !== false) {
            try {
                $dom = new DOMDocument();
                $prevUseErrors = libxml_use_internal_errors(true);
                $dom->loadXML($xml);
                libxml_clear_errors();
                libxml_use_internal_errors($prevUseErrors);
                $xpath = new DOMXPath($dom);

                $versions = $xpath->query('//versions/version');

                foreach ($versions as $versionNode) {
                    $builds[] = $versionNode->nodeValue;
                }
            } catch (Exception $e) {
                error_log("Error parsing NeoForge XML: " . $e->getMessage());
                return [];
            }
        }
    }

    return $builds;
}

function getQuiltBuilds($mcVersion) {
    $url = "https://meta.quiltmc.org/v3/versions/loader/$mcVersion";
    $builds = [];
    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $json = @file_get_contents($url, false, $context);

    $minQuiltVersion = "1.17";
    $maxQuiltVersion = "1.21.8";

    if (version_compare($mcVersion, $minQuiltVersion, ">=") && version_compare($mcVersion, $maxQuiltVersion, "<=")) {
        if ($json !== false) {
            $data = json_decode($json, true);
            foreach ($data as $item) {
                if (isset($item['loader']['version'])) {
                    $builds[] = $item['loader']['version'];
                }
            }
        }
    }
    return $builds;
}

if (isset($_GET['loader']) && isset($_GET['mc_version'])) {
    try {
        $allowedLoaders = ['forge', 'fabric', 'neoforge', 'quilt'];
        $loader = strtolower(trim($_GET['loader']));
        $mcVersion = trim($_GET['mc_version']);

        if (!in_array($loader, $allowedLoaders)) {
            throw new Exception("Loader inconnu : " . htmlspecialchars($loader));
        }
        if (!preg_match('/^\d+(\.\d+){1,2}$/', $mcVersion)) {
            throw new Exception("Format de version Minecraft invalide");
        }

        $compatCheck = checkLoaderCompatibility($loader, $mcVersion);
        if ($compatCheck['status'] === 'out_of_range') {
            echo json_encode($compatCheck);
            exit;
        }

        $builds = [];

        switch ($loader) {
            case 'forge':
                $builds = getForgeBuilds($mcVersion);
                break;
            case 'fabric':
                $builds = getFabricBuilds($mcVersion);
                break;
            case 'neoforge':
                $builds = getNeoForgeBuilds($mcVersion);
                break;
            case 'quilt':
                $builds = getQuiltBuilds($mcVersion);
                break;
        }

        if (empty($builds)) {
            echo json_encode(['status' => 'warning', 'message' => 'Aucun build trouvé pour cette version.', 'builds' => []]);
        } else {
            echo json_encode(['status' => 'success', 'builds' => $builds]);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
