<?php
/**
 * Asset Minification Script
 * Minifies CSS and JavaScript files for production
 */

echo "========================================\n";
echo "Asset Minification\n";
echo "========================================\n\n";

$minified = 0;
$errors = 0;

// CSS Files to minify
$cssFiles = [
    'assets/css/style.css'
];

// JavaScript Files to minify
$jsFiles = [
    'assets/js/chart-components.js'
];

/**
 * Minify CSS
 */
function minifyCSS($css)
{
    // Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    // Remove whitespace
    $css = preg_replace('/\s+/', ' ', $css);
    // Remove spaces around operators
    $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);
    // Remove trailing semicolons
    $css = preg_replace('/;}/', '}', $css);
    return trim($css);
}

/**
 * Minify JavaScript
 */
function minifyJS($js)
{
    // Remove single-line comments
    $js = preg_replace('/\/\/.*$/m', '', $js);
    // Remove multi-line comments
    $js = preg_replace('/\/\*.*?\*\//s', '', $js);
    // Remove whitespace
    $js = preg_replace('/\s+/', ' ', $js);
    // Remove spaces around operators
    $js = preg_replace('/\s*([=+\-*\/{}();,])\s*/', '$1', $js);
    return trim($js);
}

// Minify CSS Files
echo "Minifying CSS files...\n";
foreach ($cssFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $minified_content = minifyCSS($content);

        $minFile = str_replace('.css', '.min.css', $file);
        file_put_contents($minFile, $minified_content);

        $originalSize = strlen($content);
        $minifiedSize = strlen($minified_content);
        $savings = round((($originalSize - $minifiedSize) / $originalSize) * 100, 2);

        echo "  ✅ $file → $minFile (saved $savings%)\n";
        $minified++;
    } else {
        echo "  ⚠️  $file not found\n";
    }
}

// Minify JS Files
echo "\nMinifying JavaScript files...\n";
foreach ($jsFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $minified_content = minifyJS($content);

        $minFile = str_replace('.js', '.min.js', $file);
        file_put_contents($minFile, $minified_content);

        $originalSize = strlen($content);
        $minifiedSize = strlen($minified_content);
        $savings = round((($originalSize - $minifiedSize) / $originalSize) * 100, 2);

        echo "  ✅ $file → $minFile (saved $savings%)\n";
        $minified++;
    } else {
        echo "  ⚠️  $file not found\n";
    }
}

echo "\n========================================\n";
echo "Summary\n";
echo "========================================\n";
echo "Files minified: $minified\n";
echo "Errors: $errors\n";
echo "\n✅ Asset minification complete!\n";
echo "\nTo use minified assets, update your HTML:\n";
echo "  <link rel=\"stylesheet\" href=\"assets/css/style.min.css\">\n";
echo "  <script src=\"assets/js/chart-components.min.js\"></script>\n";
