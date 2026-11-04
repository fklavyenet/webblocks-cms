<?php

return [
  'variants' => [
    'thumbnail' => ['width' => 320, 'height' => 240, 'fit' => 'crop', 'quality' => 82],
    'card' => ['width' => 800, 'height' => 600, 'fit' => 'crop', 'quality' => 84],
    'content-small' => ['width' => 640, 'height' => null, 'fit' => 'contain', 'quality' => 84],
    'content' => ['width' => 1280, 'height' => null, 'fit' => 'contain', 'quality' => 86],
    'content-large' => ['width' => 1920, 'height' => null, 'fit' => 'contain', 'quality' => 86],
    'logo' => ['width' => 1024, 'height' => 128, 'fit' => 'contain', 'quality' => 86],
    'logo-webp' => ['width' => 1024, 'height' => 128, 'fit' => 'contain', 'quality' => 86, 'format' => 'webp'],
    'content-small-webp' => ['width' => 640, 'height' => null, 'fit' => 'contain', 'quality' => 84, 'format' => 'webp'],
    'content-webp' => ['width' => 1280, 'height' => null, 'fit' => 'contain', 'quality' => 86, 'format' => 'webp'],
    'content-large-webp' => ['width' => 1920, 'height' => null, 'fit' => 'contain', 'quality' => 86, 'format' => 'webp'],
    'hero' => ['width' => 1920, 'height' => 900, 'fit' => 'crop', 'quality' => 86],
    'social' => ['width' => 1200, 'height' => 630, 'fit' => 'crop', 'quality' => 86],
  ],
];
