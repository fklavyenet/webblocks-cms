<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RoutingConsumerController
{
  public function show(Request $request): string
  {
    return 'host:'.$request->path();
  }

  public function missing(): never
  {
    abort(404);
  }
}
