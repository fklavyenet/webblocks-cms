@extends('layouts.public', [
    'title' => $publicMeta['title'] ?? $page->title,
    'publicMeta' => $publicMeta,
])
