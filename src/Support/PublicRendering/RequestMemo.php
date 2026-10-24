<?php

namespace WebBlocks\Cms\Support\PublicRendering;

use Illuminate\Http\Request;

/** Shared by helpers, discarded with the request/job and after database writes. */
class RequestMemo
{
  private ?Request $request = null;

  private array $values = [];

  public function remember(string $group, array $identity, callable $load): mixed
  {
    $this->syncRequest();
    $key = $group.':'.serialize([config('database.default'), $identity]);
    if (! array_key_exists($key, $this->values)) {
      $this->values[$key] = $load();
    }

    return $this->values[$key];
  }

  public function clear(): void
  {
    $this->values = [];
  }

  public function has(string $group, array $identity): bool
  {
    $this->syncRequest();

    return array_key_exists($group.':'.serialize([config('database.default'), $identity]), $this->values);
  }

  public function put(string $group, array $identity, mixed $value): void
  {
    $this->syncRequest();
    $this->values[$group.':'.serialize([config('database.default'), $identity])] = $value;
  }

  private function syncRequest(): void
  {
    $request = app()->bound('request') ? request() : null;
    if ($request !== $this->request) {
      $this->clear();
      $this->request = $request;
    }
  }
}
