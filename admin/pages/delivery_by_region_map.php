<?php
/**
 * Delivery rate per region map (Gouvernorats) – fragment for dashboard.
 * Outputs the map HTML with primary color and Gouvernorat name cards on hover.
 */
if (!class_exists('Settings')) {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/StoreContext.php';
    require_once __DIR__ . '/../../config/settings.php';
}
$store_id = StoreContext::getId();
$conn = (new Database())->getConnection();
$settings = new Settings($conn, $store_id);
$primary_color = $settings->getSetting('primary_color', '#6366f1');

$gouvernorats = [
    'Ariana', 'Béja', 'Ben Arous', 'Bizerte', 'Gabès', 'Gafsa', 'Jendouba',
    'Kairouan', 'Kasserine', 'Kebili', 'Le Kef', 'Mahdia', 'Manouba', 'Médenine',
    'Monastir', 'Nabeul', 'Sfax', 'Sidi Bouzid', 'Siliana', 'Sousse',
    'Tataouine', 'Tozeur', 'Tunis', 'Zaghouan'
];

$data_file = __DIR__ . '/delivery_region_map_data.txt';
if (!is_readable($data_file)) {
    return;
}

$html = file_get_contents($data_file);
if ($html === false || trim($html) === '') {
    return;
}

// Replace any blue/purple stroke in the inline style with primary
$html = str_replace('#0c4a6e', $primary_color, $html);

// Replace purple fill and white stroke with primary-based colors
$primary_hex = $primary_color;
$html = str_replace(
    'fill: rgb(230, 217, 251); stroke: rgb(255, 255, 255); opacity: 1; outline: none;',
    'fill: ' . $primary_hex . '; fill-opacity: 0.5; stroke: ' . $primary_hex . '; stroke-width: 0.5; opacity: 1; outline: none;',
    $html
);

// Remove the legend block (< 100%, < 80%, < 60%, < 40%, < 20%)
$html = preg_replace(
    '/<div class="absolute bottom-0 right-4">.*?(?:&lt;|<) 20%.*?<\/span><\/div><\/div><\/div>/s',
    '',
    $html
);

// Remove title block "Delivery rate per region - All Companies" (two divs)
$html = preg_replace(
    '/<div class="flex-col space-y-1\.5 p-6 flex h-16 items-start border-b border-gray-200 px-4"><div class="font-semibold leading-none tracking-tight">Delivery rate per region - All Companies<\/div><\/div>/',
    '',
    $html
);
// Single border from parent card; avoid duplicate on map wrapper
$html = str_replace('class="border bg-card ', 'class="bg-card ', $html);

// Add <title> and data-gouvernorat for each path (match any <path ... class="rsm-geography" ... >)
$index = 0;
$html = preg_replace_callback(
    '/<path\s+[^>]*?class="rsm-geography\s*"[^>]*?d="([^"]+)"[^>]*>/',
    function ($m) use ($gouvernorats, &$index) {
        $name = isset($gouvernorats[$index]) ? $gouvernorats[$index] : ('Region ' . ($index + 1));
        $index++;
        $full = $m[0];
        $insert = ' data-gouvernorat="' . htmlspecialchars($name) . '"><title>' . htmlspecialchars($name) . '</title>';
        return substr($full, 0, -1) . $insert;
    },
    $html
);

// Wrap map + primary-color override style + hover card + script
$primary_esc = htmlspecialchars($primary_color, ENT_QUOTES, 'UTF-8');
echo '<div class="region-map-with-tooltip" id="region-map-wrap">';
echo $html;
?>
<div id="region-map-tooltip" class="region-map-tooltip-card" aria-hidden="true" role="tooltip"></div>
<style>
.region-map-with-tooltip { position: relative; }
/* Default fill and stroke from primary (overrides purple/white in data) */
#region-map-wrap .rsm-geography {
    fill: <?php echo $primary_esc; ?> !important;
    fill-opacity: 0.5 !important;
    stroke: <?php echo $primary_esc; ?> !important;
    stroke-width: 0.5px !important;
    opacity: 1;
    outline: none;
}
/* Hover: brighter stroke */
#region-map-wrap .rsm-geography:hover {
    stroke: <?php echo $primary_esc; ?> !important;
    stroke-width: 1.5px !important;
}
.region-map-tooltip-card {
    position: fixed;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 600;
    color: #fff;
    background: <?php echo $primary_esc; ?> !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    pointer-events: none;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transform: translate(-50%, -100%) translateY(-6px);
    transition: opacity 0.15s ease, visibility 0.15s ease, transform 0.15s ease;
    white-space: nowrap;
}
.region-map-tooltip-card.visible {
    opacity: 1;
    visibility: visible;
}
</style>
<script>
(function() {
    function initRegionMapTooltip() {
        var wrap = document.getElementById('region-map-wrap');
        var tooltip = document.getElementById('region-map-tooltip');
        if (!wrap || !tooltip) return;
        var paths = wrap.querySelectorAll('path.rsm-geography[data-gouvernorat]');
        if (paths.length === 0) return;
        function show(e) {
            var name = e.currentTarget.getAttribute('data-gouvernorat');
            if (name) {
                tooltip.textContent = name;
                tooltip.classList.add('visible');
                tooltip.setAttribute('aria-hidden', 'false');
                tooltip.style.left = e.clientX + 'px';
                tooltip.style.top = (e.clientY - 10) + 'px';
            }
        }
        function move(e) {
            tooltip.style.left = e.clientX + 'px';
            tooltip.style.top = (e.clientY - 10) + 'px';
        }
        function hide() {
            tooltip.classList.remove('visible');
            tooltip.setAttribute('aria-hidden', 'true');
        }
        for (var i = 0; i < paths.length; i++) {
            paths[i].addEventListener('mouseenter', show);
            paths[i].addEventListener('mousemove', move);
            paths[i].addEventListener('mouseleave', hide);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRegionMapTooltip);
    } else {
        initRegionMapTooltip();
    }
})();
</script>
</div>
<?php
