# Media Image Variants

WebBlocks CMS generates a fixed set of product-owned image variants from public JPEG, PNG, and WebP media. Editors continue to manage only the original media item; generated files are derivative cache artifacts and do not appear as separate library records.

## System variants

The package-owned `media_transforms.php` config defines thumbnail, card, responsive content, hero, and social variants. These definitions are CMS product behavior. This release intentionally does not add project-defined variants or an admin screen for changing dimensions and quality.

Transforms are generated on first use and stored on the source media disk below `media/transforms/{media_id}`. The cache fingerprint includes the source identity, media update time, focal point, and variant definition. Deleting media removes its generated transform directory. The Media edit screen can explicitly clear and regenerate every system variant.

SVG and unsupported raster formats safely use their original public URL. Original files are never overwritten. Derived transform files are not independent editorial content and can be regenerated after transfer or restore.

## Editorial workflow

Image media exposes a focal-point picker on the Media edit screen. The selected normalized X/Y position guides crop variants such as thumbnail, card, hero, and social. Contain variants preserve the original aspect ratio.

The same screen previews every system variant. Media pickers use the thumbnail variant, while the public Image block emits lazy-loaded responsive `srcset` candidates from the contain variants.

## Storage and runtime requirements

Transforms use PHP GD and Laravel filesystem disks. JPEG, PNG, and WebP generation depends on the corresponding GD codec. If the source format or runtime codec is unavailable, public rendering falls back to the original media URL instead of failing.
