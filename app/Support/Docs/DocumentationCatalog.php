<?php

namespace App\Support\Docs;

use DOMDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class DocumentationCatalog
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        return collect([
            $this->makeDocument(
                slug: 'readme',
                title: 'README',
                description: 'Product overview, installation, admin navigation, privacy-aware visitor reports, and core project usage.',
                relativePath: 'README.md',
            ),
            $this->makeDocument(
                slug: 'changelog',
                title: 'Changelog',
                description: 'Release history plus the current unreleased work tracked in source control.',
                relativePath: 'CHANGELOG.md',
            ),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        return $this->all()->firstWhere('slug', $slug);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public function withRenderedContent(array $document): array
    {
        $markdown = $this->source($document);

        return [
            ...$document,
            'markdown' => $markdown,
            'html' => $this->renderMarkdown($markdown),
        ];
    }

    public function renderMarkdown(string $markdown): HtmlString
    {
        $html = (string) Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return new HtmlString($this->rewriteInternalMarkdownLinks($html));
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function source(array $document): string
    {
        $path = (string) ($document['path'] ?? '');

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function makeDocument(string $slug, string $title, string $description, string $relativePath): array
    {
        $path = base_path($relativePath);

        return [
            'slug' => $slug,
            'title' => $title,
            'description' => $description,
            'relative_path' => $relativePath,
            'path' => $path,
            'updated_at' => is_file($path) ? now()->setTimestamp((int) filemtime($path)) : null,
        ];
    }

    private function rewriteInternalMarkdownLinks(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');

        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $container = $document->getElementsByTagName('div')->item(0);

        if (! $container) {
            return $html;
        }

        foreach ($container->getElementsByTagName('a') as $anchor) {
            $href = trim((string) $anchor->getAttribute('href'));

            if (! $this->isInternalMarkdownLink($href)) {
                continue;
            }

            $normalized = ltrim($href, './');
            $documentSlug = $this->pathMap()[$normalized] ?? null;

            $anchor->setAttribute('href', $documentSlug
                ? route('admin.docs.show', $documentSlug)
                : route('admin.docs.index')
            );
        }

        $output = '';

        foreach ($container->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    private function isInternalMarkdownLink(string $href): bool
    {
        if ($href === '' || Str::startsWith($href, ['#', 'http://', 'https://', 'mailto:', 'tel:'])) {
            return false;
        }

        return Str::endsWith(Str::before($href, '#'), '.md');
    }

    /**
     * @return array<string, string>
     */
    private function pathMap(): array
    {
        return $this->all()
            ->mapWithKeys(fn (array $document) => [(string) $document['relative_path'] => (string) $document['slug']])
            ->all();
    }
}
