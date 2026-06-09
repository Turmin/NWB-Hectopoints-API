<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/Nwb.php';

header('Content-Type: application/xml; charset=utf-8');

function xmlEscape($value): string
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function writeUrl(string $loc, string $changefreq, string $priority): void
{
    echo "  <url>\n";
    echo '    <loc>' . xmlEscape($loc) . "</loc>\n";
    echo '    <changefreq>' . xmlEscape($changefreq) . "</changefreq>\n";
    echo '    <priority>' . xmlEscape($priority) . "</priority>\n";
    echo "  </url>\n";
}

$baseUrl = rtrim(nwbRequestBaseUrl(), '/');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
writeUrl($baseUrl . '/', 'weekly', '1.0');
echo "</urlset>\n";
