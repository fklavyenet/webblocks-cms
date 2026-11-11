<?php

namespace WebBlocks\Cms\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\NotePolicy;
use WebBlocks\Cms\Queries\NoteQuery;

final class NoteRequest extends FormRequest
{
  public const MAX_BODY = 10000;

  protected $errorBag = 'note';

  protected function prepareForValidation(): void
  {
    if ($this->routeIs('internal-content-api.notes.*')) {
      $this->headers->set('Accept', 'application/json');

      return;
    }

    if ($this->routeIs('admin.notes.store', 'admin.notes.update')) {
      $target = $this->getInputSource()->all()['audience_target'] ?? null;
      if ($target === 'keep' && $this->routeIs('admin.notes.update')) {
        $this->getInputSource()->remove('recipient_id');
        $this->getInputSource()->remove('visibility');

        return;
      }
      $this->merge(match ($target) {
        'everyone' => ['recipient_id' => null, 'visibility' => 'general'],
        'author' => ['recipient_id' => null, 'visibility' => 'private'],
        default => ['recipient_id' => $target, 'visibility' => 'private'],
      });
    }
  }

  protected function getRedirectUrl(): string
  {
    return match (true) {
      $this->routeIs('admin.notes.store') => route('admin.notes.index', $this->query()),
      $this->routeIs('admin.notes.update') => route('admin.notes.edit', ['note' => $this->route('note')] + $this->query()),
      $this->routeIs('admin.notes.read-state') => route('admin.notes.show', ['note' => $this->route('note')] + $this->query()),
      default => route('admin.notes.index'),
    };
  }

  public function authorize(NotePolicy $policy, NoteQuery $notes): bool
  {
    $actor = $this->routeIs('internal-content-api.notes.*') ? $this->attributes->get('cms_api_user') : $this->user();
    if (! $actor instanceof User || ! $policy->access($actor)) {
      return false;
    }

    if (! $this->isMethodSafe() && $this->route('note') !== null) {
      $notes->find($actor, $this->route('note'), $this->routeIs('*.read-state') ? 'view' : 'update');
    }

    return true;
  }

  public function validationData(): array
  {
    return $this->isMethodSafe() ? $this->query() : $this->getInputSource()->all();
  }

  public function rules(): array
  {
    return match (true) {
      $this->routeIs('*.read-state') => self::readRules(),
      $this->isMethodSafe() => self::filterRules(),
      default => self::writeRules($this->isMethod('POST')) + ($this->routeIs('admin.notes.*')
        ? ['audience_target' => ['required', 'string']]
        : []),
    };
  }

  public static function filterRules(): array
  {
    return [
      'search' => ['nullable', 'string', 'max:180'],
      'audience' => ['nullable', Rule::in(['general', 'private', 'administration'])],
      'status' => ['nullable', Rule::in(['read', 'unread'])],
      'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
      'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
    ];
  }

  public static function writeRules(bool $create): array
  {
    $presence = $create ? ['required'] : ['sometimes', 'required'];

    return [
      'title' => [...$presence, 'string', 'max:180', 'not_regex:/[\x00-\x1f\x7f]/'],
      'body' => [...$presence, 'string', 'max:'.self::MAX_BODY, 'not_regex:/[\x00]/'],
      'recipient_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
      'visibility' => ['sometimes', 'required', Rule::in(['general', 'private'])],
    ];
  }

  public static function readRules(): array
  {
    return ['read' => ['required', 'boolean']];
  }
}
