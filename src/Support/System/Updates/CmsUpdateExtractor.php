<?php

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdatePackageExtractor;
use ZipArchive;

class CmsUpdateExtractor extends UpdatePackageExtractor
{
  public function extract(string $archivePath, string $destinationDirectory): string
  {
    app(CmsUpdateProgress::class)->stage('extract');

    $root = parent::extract($archivePath, $destinationDirectory);
    $archive = new ZipArchive;
    if ($archive->open($archivePath, ZipArchive::RDONLY) !== true) {
      throw new UpdateException(__('webblocks-cms::admin.update_cli.failed'), 'The verified CMS archive could not be opened.');
    }
    $comment = $archive->getArchiveComment();
    $archive->close();
    if ($comment !== false && $comment !== '') {
      $artifact = app(CmsPublisherArtifact::class);
      $artifact->transactional = true;
      $source = json_decode($comment, true);
      if (($source['product'] ?? null) !== 'webblocks-cms'
        || ! is_string($source['source_commit'] ?? null)
        || preg_match('/\A[a-f0-9]{40}\z/', $source['source_commit']) !== 1) {
        throw new UpdateException(__('webblocks-cms::admin.update_cli.failed'), 'The CMS package does not declare a valid source commit.');
      }
      $artifact->archive = $archivePath;
      $artifact->commit = $source['source_commit'];
      $artifact->checksum = hash_file('sha256', $archivePath) ?: null;
    }

    return $root;
  }
}
