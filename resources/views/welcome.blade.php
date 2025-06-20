<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'SportApp') }}</title>

    <!-- Theme Color -->
    <meta name="theme-color" content="#448EF7">
    
    <!-- Favicons (commented out until actual icons are added)
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    -->

        <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <!-- React Refresh Preamble -->
    <script>
        window.__vite_plugin_react_preamble_installed__ = true;
        window.$RefreshReg$ = () => {};
        window.$RefreshSig$ = () => (type) => type;
        
        // Unregister any existing service worker
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations().then(function(registrations) {
                for(let registration of registrations) {
                    console.log('Unregistering SW:', registration.scope);
                    registration.unregister();
                }
            });
        }
    </script>
    
    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
<body class="antialiased">
    <!-- React App Container -->
    <div id="app"></div>


    </body>
</html>
