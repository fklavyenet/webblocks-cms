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
    return [
      'ok' => $this->ok,
      'writes' => $this->writes,
      'data' => $this->data,
      'normalized_plan' => $this->normalizedPlan,
      'renderability' => $this->renderability,
      'warnings' => $this->warnings,
      'errors' => $this->errors,
    ];
  }
}
