<?php
if(!function_exists('ce_h')){
    function ce_h($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if(!function_exists('ce_is_https')){
    function ce_is_https(): bool {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        );
    }
}

if(!function_exists('ce_public_base_url')){
    function ce_public_base_url(): string {
        $scheme = ce_is_https() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'comandaria.bacuridigital.com';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

        if($scriptDir === '.' || $scriptDir === '/'){
            $scriptDir = '';
        }

        return $scheme . '://' . $host . $scriptDir;
    }
}

if(!function_exists('ce_asset_url')){
    function ce_asset_url(string $path): string {
        $path = '/' . ltrim($path, '/');
        return ce_public_base_url() . $path;
    }
}

if(!function_exists('ce_current_url')){
    function ce_current_url(): string {
        $scheme = ce_is_https() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'comandaria.bacuridigital.com';
        $uri = $_SERVER['REQUEST_URI'] ?? ($_SERVER['SCRIPT_NAME'] ?? '/');
        return $scheme . '://' . $host . $uri;
    }
}

if(!function_exists('ce_og_tags')){
    function ce_og_tags(
        string $title = 'Comandaria Express',
        string $description = 'Sistema de comandas para restaurantes e bares',
        string $image = 'images/comandaria_logo_og_1200x630.png',
        string $type = 'website'
    ): string {
        $imageUrl = ce_asset_url($image);
        $url = ce_current_url();

        return implode("\n", [
            '<meta name="description" content="' . ce_h($description) . '">',
            '<meta property="og:locale" content="pt_BR">',
            '<meta property="og:site_name" content="Comandaria Express">',
            '<meta property="og:title" content="' . ce_h($title) . '">',
            '<meta property="og:description" content="' . ce_h($description) . '">',
            '<meta property="og:type" content="' . ce_h($type) . '">',
            '<meta property="og:url" content="' . ce_h($url) . '">',
            '<meta property="og:image" content="' . ce_h($imageUrl) . '">',
            '<meta property="og:image:secure_url" content="' . ce_h($imageUrl) . '">',
            '<meta property="og:image:type" content="image/png">',
            '<meta property="og:image:width" content="1200">',
            '<meta property="og:image:height" content="630">',
            '<meta property="og:image:alt" content="Comandaria Express">',
            '<meta name="twitter:card" content="summary_large_image">',
            '<meta name="twitter:title" content="' . ce_h($title) . '">',
            '<meta name="twitter:description" content="' . ce_h($description) . '">',
            '<meta name="twitter:image" content="' . ce_h($imageUrl) . '">'
        ]) . "\n";
    }
}
?>
