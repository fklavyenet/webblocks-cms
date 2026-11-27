<?php

namespace WebBlocks\Cms\Http\Requests\InternalContentApi;

class ResolveSystemUpdateApiRequest extends RunSystemUpdateApiRequest
{
  public function rules(): array
  {
    return ['confirmed' => ['required', 'accepted'], 'reason' => ['required', 'string', 'min:10', 'max:500']];
  }
}
