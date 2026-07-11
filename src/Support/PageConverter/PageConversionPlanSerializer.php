<?php

namespace WebBlocks\Cms\Support\PageConverter;

use InvalidArgumentException;

class PageConversionPlanSerializer
{
  public const VERSION = 1;

  public function serialize(PageConverterPlan $plan): string
  {
    $json = json_encode($this->toPayload($plan), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

    return base64_encode($json);
  }

  /**
   * @return array<string, mixed>
   */
  public function deserialize(string $payload): array
  {
    $json = base64_decode($payload, true);

    if (! is_string($json)) {
      throw new InvalidArgumentException('The submitted conversion plan payload is not valid.');
    }

    $decoded = json_decode($json, true);

    if (! is_array($decoded)) {
      throw new InvalidArgumentException('The submitted conversion plan payload is not valid JSON.');
    }

    return $decoded;
  }

  /**
   * @return array<string, mixed>
   */
  private function toPayload(PageConverterPlan $plan): array
  {
    return [
      'version' => self::VERSION,
      'target' => [
        'site_id' => $plan->input->siteId,
        'locale_id' => $plan->input->localeId,
        'page_layout' => $plan->input->pageLayout,
        'page_title' => $plan->input->pageTitle,
        'page_path' => $plan->input->pagePath,
        'conversion_profile' => $plan->input->conversionProfile,
      ],
      'source' => [
        'type' => $plan->input->sourceType,
        'name' => $plan->input->sourceName,
        'bytes' => $plan->sourceBytes,
        'content_root_summary' => $plan->contentRootSummary,
      ],
      'summary' => [
        'suggestion_count' => $plan->suggestionCount(),
        'fallback_count' => $plan->fallbackCount(),
        'warning_count' => $plan->warningCount(),
      ],
      'blocks' => array_map(
        fn (PageBlockSuggestion $suggestion, int $index): array => $this->blockPayload($suggestion, $index),
        $plan->suggestions,
        array_keys($plan->suggestions),
      ),
    ];
  }

  /**
   * @return array<string, mixed>
   */
  private function blockPayload(PageBlockSuggestion $suggestion, int $index): array
  {
    return [
      'key' => 'block_'.($index + 1),
      'order' => $index + 1,
      'parent_key' => $suggestion->parentKey,
      'block_type' => $suggestion->blockSlug,
      'block_slug' => $suggestion->blockSlug,
      'label' => $suggestion->label,
      'translated_fields' => $suggestion->translatedFields,
      'shared_fields' => $suggestion->sharedFields,
      'confidence' => $suggestion->confidence,
      'warnings' => $suggestion->warnings,
      'fallback_flags' => $suggestion->fallbackFlags,
      'source_fragment' => [
        'summary' => $suggestion->sourceSummary,
        'preview_text' => $suggestion->previewText,
        'html' => $suggestion->sourceHtml,
      ],
    ];
  }
}
