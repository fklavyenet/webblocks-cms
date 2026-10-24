<?php

namespace WebBlocks\Cms\Models\Concerns;

use WebBlocks\Cms\Models\Media;

/** Both public names refer to the same belongsTo/media_id relationship. */
trait ReusesMediaRelationAliases
{
  public function getRelationValue($key)
  {
    if (in_array($key, ['media', 'asset'], true) && ! $this->relationLoaded($key)) {
      $alias = $key === 'media' ? 'asset' : 'media';
      if ($this->relationLoaded($alias)) {
        $media = $this->getRelation($alias);
        // An unsaved foreign-key replacement must not reuse the old image.
        if (($media instanceof Media && (string) $media->getKey() === (string) $this->media_id)
          || ($media === null && ! $this->isDirty('media_id'))) {
          $this->setRelation($key, $media);

          return $media;
        }
      }
    }

    return parent::getRelationValue($key);
  }
}
