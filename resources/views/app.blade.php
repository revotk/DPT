<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- Estilos CSS con colores institucionales -->
    <style>
        :root {
            --color-negro: #000000;
            --color-rosa: #d5007f;
            --color-gris: #b2b2b2;
            --color-gris-claro: #f5f5f5;
            --color-vino: #950054;
        }

        body {
            background-color: var(--color-gris-claro);
            color: var(--color-negro);
            font-family: 'Instrument Sans', Arial, sans-serif;
        }

        a {
            color: var(--color-rosa);
            text-decoration: none;
            transition: color 0.2s;
        }

        a:hover {
            color: var(--color-vino);
        }

        .btn-primary {
            background-color: var(--color-rosa);
            border-color: var(--color-rosa);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--color-vino);
            border-color: var(--color-vino);
        }

        .card {
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            background-color: white;
        }

        .card-header {
            border-bottom: 3px solid var(--color-rosa);
        }

        .highlight {
            color: var(--color-rosa);
            font-weight: 600;
        }

        .border-accent {
            border-color: var(--color-rosa) !important;
        }

        /* Elementos de formulario */
        .form-control:focus {
            border-color: var(--color-rosa);
            box-shadow: 0 0 0 0.2rem rgba(213, 0, 127, 0.25);
        }
    </style>

    @routes
    @vite(['resources/js/app.ts'])
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
