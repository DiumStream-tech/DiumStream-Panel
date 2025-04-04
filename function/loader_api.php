<?php
header('Content-Type: application/json');

function checkLoaderCompatibility($loader, $mcVersion) {
    $loaderRanges = [
        'forge' => ['min' => '1.1', 'max' => '1.21.5'],
        'fabric' => ['min' => '1.14', 'max' => '1.21.5'],
        'neoforge' => ['min' => '1.20.2', 'max' => '1.21.5']
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
    $html = @file_get_contents($url);

    if ($html !== false) {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
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
    $json = @file_get_contents($url);

    $minFabricVersion = "1.14";
    $maxFabricVersion = "1.21.5";

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
    $maxNeoForgeVersion = "1.21.5";

    if (version_compare($mcVersion, $minNeoForgeVersion, ">=") && version_compare($mcVersion, $maxNeoForgeVersion, "<=")) {
        $xml = @file_get_contents($url);

        if ($xml !== false) {
            try {
                $dom = new DOMDocument();
                $dom->loadXML($xml);
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

if (isset($_GET['loader']) && isset($_GET['mc_version'])) {
    try {
        $loader = $_GET['loader'];
        $mcVersion = $_GET['mc_version'];
        
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
            default:
                throw new Exception("Loader inconnu : " . htmlspecialchars($loader));
        }

        echo json_encode(['status' => 'success', 'builds' => $builds]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>
