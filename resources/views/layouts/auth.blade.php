<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name', 'PropMgr') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div class="auth-shell">
            <div class="auth-card-wrap">
                <section class="auth-brand-panel">
                    <div class="auth-brand-top">
                        <div class="logo-mark">P</div>
                        <div>
                            <p class="logo-text is-dark">PropMgr</p>
                            <p class="eyebrow-text is-dark">Security-first property operations</p>
                        </div>
                    </div>

                    <div class="auth-brand-copy">
                        <p class="row-label">Phase 0 baseline</p>
                        <h1>{{ $headline ?? 'Authentication foundation is now part of the project baseline.' }}</h1>
                        <p>{{ $subhead ?? 'Fortify, Sanctum, Livewire, Alpine, and the shared PropMgr visual system are now wired as the starting point for implementation.' }}</p>
                    </div>

                    <div class="auth-brand-points">
                        <span class="badge badge-lime">2FA ready</span>
                        <span class="badge badge-violet">Fortify</span>
                        <span class="badge badge-sky">Sanctum</span>
                    </div>
                </section>

                <main class="auth-form-panel">
                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>