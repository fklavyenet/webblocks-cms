<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    @php
        $guestCssPath = public_path('site/css/guest.css');
    @endphp

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('partials.head-meta', [
            'title' => $title ?? config('app.name'),
            'metaDescription' => $metaDescription ?? config('app.slogan'),
        ])

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-icons.css">
        @if (is_file($guestCssPath))
            <link rel="stylesheet" href="{{ asset('site/css/guest.css') }}?v={{ filemtime($guestCssPath) }}">
        @endif
    </head>
    <body>
        <main>
            {{ $slot }}
        </main>

        <script src="https://cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui@master/packages/webblocks/dist/webblocks-ui.js"></script>
        <script>
            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-password-toggle]');

                if (! button) {
                    return;
                }

                var wrapper = button.closest('[data-password-field]');
                var input = wrapper ? wrapper.querySelector('[data-password-input]') : null;

                if (! input) {
                    return;
                }

                var isHidden = input.type === 'password';
                var label = button.querySelector('[data-password-toggle-label]');
                var icon = button.querySelector('[data-password-toggle-icon]');

                input.type = isHidden ? 'text' : 'password';
                button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
                button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');

                if (label) {
                    label.textContent = isHidden ? 'Hide password' : 'Show password';
                }

                if (icon) {
                    icon.classList.remove('wb-icon-eye', 'wb-icon-eye-off');
                    icon.classList.add(isHidden ? 'wb-icon-eye-off' : 'wb-icon-eye');
                }
            });
        </script>
    </body>
</html>
