<?php
declare(strict_types=1);

echo '<h2>CR3 Preview Extraction Test</h2>';

echo '<h3>ExifTool</h3>';

$path = trim(
    (string)shell_exec('which exiftool 2>&1')
);

if ($path !== '') {

    echo '<p style="color:green;">';
    echo '✅ ExifTool found: ';
    echo htmlspecialchars(
        $path,
        ENT_QUOTES,
        'UTF-8'
    );
    echo '</p>';

    echo '<pre>';

    echo htmlspecialchars(
        (string)shell_exec(
            'exiftool -ver 2>&1'
        ),
        ENT_QUOTES,
        'UTF-8'
    );

    echo '</pre>';

} else {

    echo '<p style="color:red;">';
    echo '❌ ExifTool is NOT installed or not available.';
    echo '</p>';
}