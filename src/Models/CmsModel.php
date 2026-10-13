<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use WebBlocks\Cms\Support\Database\CmsTable;
use WebBlocks\Cms\Support\Relational\DocumentFields;
use WebBlocks\Cms\Support\Relational\RelationalDocuments;
use WebBlocks\Cms\Support\Relational\SettingDefinitions;
use WebBlocks\Cms\Support\Relational\StructuredSettings;
use WebBlocks\Cms\Support\Relational\TokenRelations;

abstract class CmsModel extends Model
{
  private array $pendingRelationalValues = [];

  private array $pendingStructuredValues = [];

  private array $relationalValueCache = [];

  private bool $structuredSettingsPrimed = false;

  public function setAttribute($key, $value)
  {
    parent::setAttribute($key, $value);
    unset($this->relationalValueCache[$key]);
    if (in_array($key, SettingDefinitions::attributes($this), true) && app(StructuredSettings::class)->available()) {
      $this->structuredSettingsPrimed = false;
      $this->pendingStructuredValues[$key] = $this instanceof Block ? $value : parent::getAttribute($key);
    }
    if (is_string($key) && DocumentFields::for($this, $key) !== null) {
      $definition = DocumentFields::for($this, $key);
      if (app(RelationalDocuments::class)->available($definition)) {
        $this->pendingRelationalValues[$key] = $this instanceof Block
          ? app(RelationalDocuments::class)->decoded($value) : $value;
      }
    } elseif ($this instanceof CmsApiToken && isset(TokenRelations::LISTS[$key]) && app(TokenRelations::class)->available($key)) {
      $this->pendingRelationalValues[$key] = $value;
    }

    return $this;
  }

  public function getAttribute($key)
  {
    if (array_key_exists($key, $this->pendingStructuredValues)) {
      return $this->pendingStructuredValues[$key];
    }
    if (array_key_exists($key, $this->pendingRelationalValues)) {
      return $this->relationalCompatibleValue($key, $this->pendingRelationalValues[$key]);
    }
    if (array_key_exists($key, $this->relationalValueCache)) {
      return $this->relationalValueCache[$key];
    }
    $value = parent::getAttribute($key);
    if (! $this->structuredSettingsPrimed && in_array($key, SettingDefinitions::attributes($this), true)) {
      [$found, $canonical] = app(StructuredSettings::class)->read($this, $key);
      if ($found) {
        return $this->relationalValueCache[$key] = $canonical;
      }
    }
    $definition = is_string($key) ? DocumentFields::for($this, $key) : null;
    if ($definition !== null) {
      $legacy = $this instanceof Block ? app(RelationalDocuments::class)->decoded($value) : $value;
      $value = app(RelationalDocuments::class)->read($this, $definition, $legacy);

      return $this->relationalValueCache[$key] = $this->relationalCompatibleValue($key, $value);
    }
    if ($this instanceof CmsApiToken && isset(TokenRelations::LISTS[$key])) {
      return $this->relationalValueCache[$key] = app(TokenRelations::class)->read($this, $key, $value);
    }

    return $value;
  }

  private function relationalCompatibleValue(string $key, mixed $value): mixed
  {
    return $this instanceof Block && $key === 'settings' && is_array($value) && ! is_array($this->attributes[$key] ?? null)
      ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : $value;
  }

  public function save(array $options = [])
  {
    if (! ($this instanceof Page || $this instanceof Block || $this instanceof Site || $this instanceof CmsApiToken || $this instanceof EmbeddedApplication)) {
      return parent::save($options);
    }

    return $this->getConnection()->transaction(function () use ($options) {
      $locked = $this->exists
        ? $this->newQueryWithoutScopes()->whereKey($this->getKey())->lockForUpdate()->first() : null;
      // Refresh rollback copies from the authoritative values before every write.
      foreach (array_unique(['settings', 'notification_settings', ...array_keys(TokenRelations::LISTS), ...SettingDefinitions::attributes($this)]) as $key) {
        if (array_key_exists($key, $this->getAttributes()) && (DocumentFields::for($this, $key) !== null || ($this instanceof CmsApiToken && isset(TokenRelations::LISTS[$key])) || in_array($key, SettingDefinitions::attributes($this), true))) {
          $value = $locked !== null && ! array_key_exists($key, $this->pendingRelationalValues) && ! array_key_exists($key, $this->pendingStructuredValues) && ! $this->isDirty($key)
            ? $locked->getAttribute($key) : $this->getAttribute($key);
          if ($this instanceof Block && is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
          }
          $this->setAttribute($key, $value);
        }
      }

      return parent::save($options);
    });
  }

  protected function finishSave(array $options)
  {
    foreach ($this->pendingRelationalValues as $key => $value) {
      $definition = DocumentFields::for($this, $key);
      if ($definition !== null) {
        app(RelationalDocuments::class)->write($this, $definition, $value);
      } elseif ($this instanceof CmsApiToken) {
        app(TokenRelations::class)->write($this, $key, $value);
      }
    }
    foreach ($this->pendingStructuredValues as $key => $value) {
      app(StructuredSettings::class)->write($this, $key, $value);
    }
    if ($this->wasRecentlyCreated && SettingDefinitions::attributes($this) !== []) {
      app(StructuredSettings::class)->captureDefaults($this);
    }
    $this->structuredSettingsPrimed = false;
    $this->pendingStructuredValues = [];
    $this->pendingRelationalValues = [];
    $this->relationalValueCache = [];
    parent::finishSave($options);
  }

  public function setRawAttributes(array $attributes, $sync = false)
  {
    $this->structuredSettingsPrimed = false;
    $this->relationalValueCache = [];
    $this->pendingRelationalValues = [];
    $this->pendingStructuredValues = [];

    return parent::setRawAttributes($attributes, $sync);
  }

  public function serializedSettings(): ?string
  {
    $value = $this->getAttribute('settings');

    return is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : $value;
  }

  public function attributesToArray()
  {
    $attributes = parent::attributesToArray();
    foreach (array_unique(['settings', 'notification_settings', ...array_keys(TokenRelations::LISTS), ...SettingDefinitions::attributes($this)]) as $key) {
      if (array_key_exists($key, $attributes) && (DocumentFields::for($this, $key) !== null || ($this instanceof CmsApiToken && isset(TokenRelations::LISTS[$key])) || in_array($key, SettingDefinitions::attributes($this), true))) {
        $attributes[$key] = $this->getAttribute($key);
      }
    }

    return $attributes;
  }

  public function portableAttributes(): array
  {
    $attributes = $this->getAttributes();
    foreach (array_unique(['settings', 'notification_settings', ...SettingDefinitions::attributes($this)]) as $key) {
      if ((DocumentFields::for($this, $key) !== null || in_array($key, SettingDefinitions::attributes($this), true)) && array_key_exists($key, $attributes)) {
        $value = $this->getAttribute($key);
        $attributes[$key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : $value;
      }
    }

    return $attributes;
  }

  public function scopeWhereRuntimeSetting($query, string $path, mixed $value)
  {
    return app(RelationalDocuments::class)->where($query, DocumentFields::for($this, 'settings'), $path, $value);
  }

  public function scopeWhereRuntimeSettingNull($query, string $path)
  {
    return app(RelationalDocuments::class)->where($query, DocumentFields::for($this, 'settings'), $path, null, true);
  }

  public function structuredSettingsPrimed(): bool
  {
    return $this->structuredSettingsPrimed;
  }

  public function markStructuredSettingsPrimed(): void
  {
    $this->structuredSettingsPrimed = true;
  }

  public function seedRelationalValue(string $attribute, mixed $value): void
  {
    $this->relationalValueCache[$attribute] = $value;
  }

  public function newCollection(array $models = []): Collection
  {
    $collection = parent::newCollection($models);
    if (SettingDefinitions::attributes($this) !== []) {
      app(StructuredSettings::class)->prime($collection);
    }

    return $collection;
  }

  public function getTable()
  {
    return CmsTable::name(parent::getTable());
  }
}
