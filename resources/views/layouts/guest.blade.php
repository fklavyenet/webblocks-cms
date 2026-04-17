<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.head-meta', [
            'title' => $title ?? config('app.name'),
            'metaDescription' => $metaDescription ?? config('app.slogan'),
        ])

        <link rel="stylesheet" href="https://webblocksui.com/packages/webblocks/dist/webblocks-ui.css">
        <link rel="stylesheet" href="https://webblocksui.com/packages/webblocks/dist/webblocks-icons.css">
        <style>
            .wb-auth-shell.wb-auth-split {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
            }

            .wb-auth-shell.wb-auth-split .wb-auth-panel {
                max-width: 50vw;
            }

            .wb-auth-shell .wb-auth-panel:is([style*="background"], .wb-bg-primary, .wb-bg-success, .wb-bg-danger, .wb-bg-warning, .wb-bg-info) {
                color: #fff;
            }

            .wb-auth-shell .wb-auth-panel:is([style*="background"], .wb-bg-primary, .wb-bg-success, .wb-bg-danger, .wb-bg-warning, .wb-bg-info) .wb-auth-panel-title,
            .wb-auth-shell .wb-auth-panel:is([style*="background"], .wb-bg-primary, .wb-bg-success, .wb-bg-danger, .wb-bg-warning, .wb-bg-info) .wb-auth-panel-text {
                color: inherit;
            }

            @media (max-width: 900px) {
                .wb-auth-shell.wb-auth-split {
                    grid-template-columns: 1fr;
                }

                .wb-auth-shell.wb-auth-split .wb-auth-panel {
                    max-width: none;
                }
            }
        </style>
    </head>
    <body>
        <main>
            {{ $slot }}
        </main>

        <script src="https://webblocksui.com/packages/webblocks/dist/webblocks-ui.js"></script>
    </body>
</html>
