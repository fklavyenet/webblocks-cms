@extends('webblocks-cms::layouts.public', [
    'title' => $publicMeta['title'] ?? $page->title,
    'publicMeta' => $publicMeta,
])
