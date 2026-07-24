<?php
$files_to_modify = [
    'index.html',
    'login.html',
    'portal.html',
    'admin_dashboard.php',
    'parent_portal.php',
    'teacher_portal.php',
    'accounts_dashboard.php',
    'forgot_password.php',
    'first_login_setup.php'
];

$directory = __DIR__ . '/..';
$favicon_tag = "\n    <link rel=\"icon\" type=\"image/png\" href=\"logo.png\">";

foreach ($files_to_modify as $filename) {
    $path = $directory . '/' . $filename;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        if (strpos($content, 'rel="icon"') !== false || strpos($content, 'rel="shortcut icon"') !== false) {
            echo "Favicon already exists in $filename\n";
            continue;
        }
        
        // Insert favicon tag inside <head>
        $new_content = preg_replace('/(<head[^>]*>)/i', '$1' . $favicon_tag, $content, 1);
        
        file_put_contents($path, $new_content);
        echo "Added favicon to $filename\n";
    } else {
        echo "File not found: $filename\n";
    }
}
?>
