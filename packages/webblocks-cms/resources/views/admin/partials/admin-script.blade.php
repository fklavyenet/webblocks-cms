@php
  $scriptPath = public_path($path);
@endphp

@if (is_file($scriptPath))
  <script src="{{ asset($path) }}?v={{ filemtime($scriptPath) }}" defer></script>
@endif
