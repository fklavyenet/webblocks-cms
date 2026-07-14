<?php

namespace WebBlocks\Cms\Support\InternalContentApi;

class InternalContentPlanResult
{
  public function __construct(
    public readonly bool $ok,
    public readonly array $normalizedPlan,
    public readonly array $warnings = [],
    public readonly array $errors = [],
    public readonly array $writes = [],
    public readonly mixed $data = null,
    public readonly array $renderability = [],
  ) {}

  public function toArray(): array
  {
    $payload = ['ok' => $this->ok];
    $code = $this->errorCode();

    if ($code !== null) {
      $payload['code'] = $code;
    }

    return $payload + [
      'writes' => $this->writes,
      'data' => $this->data,
      'normalized_plan' => $this->normalizedPlan,
      'renderability' => $this->renderability,
      'warnings' => $this->warnings,
      'errors' => $this->errors,
    ];
  }

  /**
   * Promotes a stable error code carried by a normalizer error (such as the
   * human-only block type policy) to the top level of the error envelope.
   */
  private function errorCode(): ?string
  {
    foreach ($this->errors as $error) {
      if (is_array($error) && is_string($error['code'] ?? null) && $error['code'] !== '') {
        return $error['code'];
      }
    }

    return null;
  }
}
