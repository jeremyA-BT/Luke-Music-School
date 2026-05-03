<?php http_response_code(404); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found | Luke Higgins Music</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/modern-touches.css">
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500;600&family=Raleway:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 20px;
            background: var(--theme-bg-primary);
        }
        .error-code {
            font-family: 'Cinzel', serif;
            font-size: clamp(5rem, 15vw, 9rem);
            font-weight: 600;
            color: var(--color-primary);
            line-height: 1;
            margin: 0 0 8px;
        }
        .error-title {
            font-family: 'Raleway', sans-serif;
            font-size: clamp(1.2rem, 3vw, 1.8rem);
            font-weight: 600;
            color: var(--theme-text-primary, #fff);
            margin: 0 0 16px;
        }
        .error-message {
            font-size: 1rem;
            color: var(--theme-text-secondary);
            max-width: 420px;
            margin: 0 0 36px;
            line-height: 1.7;
        }
        .error-nav {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .error-nav a {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: .9rem;
            font-weight: 600;
            text-decoration: none;
            transition: opacity .2s;
        }
        .error-nav a:hover { opacity: .85; }
        .error-nav .btn-home {
            background: var(--color-primary);
            color: #fff;
        }
        .error-nav .btn-secondary {
            background: transparent;
            color: var(--color-primary);
            border: 2px solid var(--color-primary);
        }
        .error-icon {
            font-size: 3rem;
            color: var(--color-primary);
            opacity: .35;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-icon"><i class="fas fa-music"></i></div>
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-message">
            This page has wandered off somewhere. Head back home or choose a page from the list below.
        </p>
        <nav class="error-nav">
            <a href="/" class="btn-home">← Back Home</a>
            <a href="/Bio" class="btn-secondary">Bio</a>
            <a href="/Lessons" class="btn-secondary">Lessons</a>
            <a href="/Contact" class="btn-secondary">Contact</a>
        </nav>
    </div>

    <script src="/assets/js/theme.js"></script>
</body>
</html>
