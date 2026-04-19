<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- SEO básico -->
    <title>Superlistia — Tu lista de la compra, adaptada a tu dieta</title>
    <meta name="description" content="Listas de la compra con IA que respetan tus alergias e intolerancias. Sin anuncios. Sin venta de datos. Hecha en España.">

    <!-- Open Graph (WhatsApp, Facebook, LinkedIn) -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://superlistia.com">
    <meta property="og:title" content="Superlistia — Tu lista de la compra, adaptada a tu dieta">
    <meta property="og:description" content="Listas de la compra con IA que respetan tus alergias e intolerancias. Sin anuncios. Sin venta de datos.">
    <meta property="og:image" content="https://superlistia.com/og-image.png">
    <meta property="og:locale" content="es_ES">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Superlistia — Tu lista de la compra, adaptada a tu dieta">
    <meta name="twitter:description" content="Listas de la compra con IA que respetan tus alergias e intolerancias.">
    <meta name="twitter:image" content="https://superlistia.com/og-image.png">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">

    <!-- Canonical -->
    <link rel="canonical" href="https://superlistia.com/">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    <div id="root"></div>
</body>
</html>
