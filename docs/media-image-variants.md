# Media Image Variants

WebBlocks CMS generates a fixed set of product-owned image variants from public JPEG, PNG, and WebP media. Editors continue to manage only the original media item; generated files are derivative cache artifacts and do not appear as separate library records.

## System variants

The package-owned `media_transforms.php` config defines thumbnail, card, responsive content, hero, and social variants. These definitions are CMS product behavior. This release intentionally does not add project-defined variants or an admin screen for changing dimensions and quality.

Transforms are generated on first use and stored on the source media disk below `media/transforms/{media_id}`. The cache fingerprint includes only transformation-relevant source identity, dimensions, MIME data, focal point, and variant definition; editorial metadata changes do not invalidate it. Replacing or deleting media and changing its focal point clear obsolete transforms. The Media edit screen shows existing variants without generating missing files and can explicitly clear and regenerate every system variant.

SVG and unsupported raster formats safely use their original public URL. Original files are never overwritten. Derived transform files are not independent editorial content and can be regenerated after transfer or restore.

## Editorial workflow

Image media exposes a focal-point picker on the Media edit screen. The selected normalized X/Y position guides crop variants such as thumbnail, card, hero, and social. Contain variants preserve the original aspect ratio.

The same screen previews every system variant. Media pickers use the thumbnail variant, while the public Image block emits lazy-loaded responsive `srcset` candidates from the contain variants.

Contain variants preserve aspect ratio and use the original directly when it is already no wider than the requested variant. Crop variants calculate a source crop at the configured aspect ratio around the focal point. Small originals are never enlarged: the configured output ratio is scaled down uniformly to fit the source, then cropped without distortion.

Responsive Image and Gallery markup uses measured result dimensions, sorts candidates by actual width, removes duplicate URLs, and omits `srcset` unless at least two distinct candidates remain. Gallery `sizes` follows its configured column count. Page or site social images use the `social` crop for Open Graph and Twitter metadata. `card` and `hero` remain reserved because the current package has no first-class Media Library field for those block roles.

## Storage and runtime requirements

Transforms use PHP GD and Laravel filesystem disks. JPEG, PNG, and WebP generation depends on the corresponding GD codec. If the source format or runtime codec is unavailable, public rendering falls back to the original media URL instead of failing.

PNG alpha is retained. Decode, resize, encode, and storage failures remove incomplete output and fall back safely. `ext-gd` remains optional under Composer `suggest`.

Operators can regenerate all eligible images in bounded batches, target one id, or safely prune obsolete fingerprint directories without touching originals or current transforms:

```bash
php artisan webblocks:media-variants:regenerate
php artisan webblocks:media-variants:regenerate --media=123
php artisan webblocks:media-variants:regenerate --prune
```

Generation is synchronous and intentionally does not require a queue. Large libraries should be regenerated during a maintenance window.
