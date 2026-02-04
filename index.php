<?php
// Simplified random landing page selector
// Prevents blank pages by ensuring content is always served

// Set proper headers
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

// Simple random choice
$showV2 = (rand(0, 1) === 1);

// Determine which file to include
$targetFile = $showV2 ? 'index-v2.html' : 'index-v1.html';

// Ensure the file exists, fallback to index-v1.html if not
if (!file_exists($targetFile)) {
    $targetFile = 'index-v1.html';
}

// If still no file exists, create a simple fallback
if (!file_exists($targetFile)) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luke Higgins - Musician & Educator</title>
    <style>
        body { margin: 0; padding: 20px; background: #1a1a1a; color: white; font-family: Arial, sans-serif; text-align: center; }
        h1 { color: #d4af37; }
        .error { background: #333; padding: 20px; border-radius: 8px; margin: 20px auto; max-width: 600px; }
    </style>
</head>
<body>
    <h1>Luke Higgins - Musician & Educator</h1>
    <div class="error">
        <p>Website is currently being updated. Please check back soon!</p>
        <p><a href="bio.html" style="color: #d4af37;">View Bio</a> | 
           <a href="lessons.html" style="color: #d4af37;">View Lessons</a> | 
           <a href="contact.html" style="color: #d4af37;">Contact</a></p>
    </div>
</body>
</html>
    <?php
} else {
    // Include the selected file
    include $targetFile;
}
?>
