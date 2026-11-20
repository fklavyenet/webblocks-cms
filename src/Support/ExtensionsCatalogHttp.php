<?php

namespace WebBlocks\Cms\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ExtensionsCatalogHttp
{
  public function json(string $path, array $query, int $limit): array
  {
    $response = $this->request()->acceptJson()->withOptions([
      'progress' => static function ($total, $downloaded) use ($limit): void {
        if ($total > $limit || $downloaded > $limit) {
          throw new RuntimeException('Extensions catalog response exceeded its size limit.');
        }
      },
    ])->get($this->url($path), $query);
    $result = $response->successful() && strlen($response->body()) <= $limit ? $response->json() : null;
    if (! is_array($result)) {
      throw new RuntimeException('Extensions catalog did not return a JSON object.');
    }

    return $result;
  }

  public function download(string $path, mixed $file, int $size, string $checksum): void
  {
    $deadline = hrtime(true) + 15000000000;
    $response = $this->request()->withOptions(['stream' => true, 'read_timeout' => 5])->get($this->url($path));
    $body = $response->toPsrResponse()->getBody();
    try {
      if (! $response->successful()) {
        throw new RuntimeException('Extensions download did not return a successful response.');
      }

      $bytes = 0;
      $hash = hash_init('sha256');
      while (! $body->eof()) {
        $chunk = $body->read(min(65536, $size - $bytes + 1));
        $bytes += strlen($chunk);
        if ($chunk === '' || $bytes > $size || hrtime(true) > $deadline || fwrite($file, $chunk) !== strlen($chunk)) {
          throw new RuntimeException('Extensions download could not be read within its limits.');
        }
        hash_update($hash, $chunk);
      }

      if ($bytes !== $size || ! hash_equals($checksum, hash_final($hash)) || ! fflush($file)) {
        throw new RuntimeException('Extensions download failed integrity verification.');
      }
    } finally {
      $body->close();
    }
  }

  private function request(): PendingRequest
  {
    return Http::connectTimeout(3)->timeout(15)->withoutRedirecting()->withHeaders(['Accept-Encoding' => 'identity']);
  }

  private function url(string $path): string
  {
    $base = ExtensionsCatalog::secureBaseUrl();
    if ($base === null) {
      throw new RuntimeException('Extensions catalog URL is unavailable.');
    }

    return $base.$path;
  }
}
