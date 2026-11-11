<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Public\ThemeAssetController;

Route::middleware(['install.required'])
  ->get('/cms/themes/{version}/{checksum}/{path}', ThemeAssetController::class)
  ->whereNumber('version')->where('checksum', '[a-f0-9]{64}')->where('path', '[a-z0-9_./-]+')
  ->name('public-theme-assets.show');
