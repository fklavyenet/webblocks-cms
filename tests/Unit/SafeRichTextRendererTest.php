<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Formatting\SafeRichTextRenderer;

/**
 * The renderer is the second half of a sanitizing pair: the admin editor cleans
 * the same content in JavaScript before it is stored, and this runs again on the
 * way to the page. A tag the editor can produce but this drops disappears
 * between saving and publishing, which is the failure this file guards.
 */
class SafeRichTextRendererTest extends TestCase
{
  private function render(string $content): string
  {
    return (new SafeRichTextRenderer)->sanitize($content);
  }

  #[Test]
  public function it_keeps_the_editor_inline_vocabulary(): void
  {
    $this->assertSame(
      '<p>a <strong>b</strong> <em>c</em> <code>d</code> <s>e</s><br>f</p>',
      $this->render('<p>a <strong>b</strong> <em>c</em> <code>d</code> <s>e</s><br>f</p>'),
    );
  }

  #[Test]
  #[DataProvider('legacyInlineSpellings')]
  public function it_folds_legacy_inline_spellings_into_the_canonical_tag(string $content, string $expected): void
  {
    $this->assertSame($expected, $this->render($content));
  }

  public static function legacyInlineSpellings(): array
  {
    return [
      'b' => ['<p><b>x</b></p>', '<p><strong>x</strong></p>'],
      'i' => ['<p><i>x</i></p>', '<p><em>x</em></p>'],
      'strike' => ['<p><strike>x</strike></p>', '<p><s>x</s></p>'],
      'del' => ['<p><del>x</del></p>', '<p><s>x</s></p>'],
      'bare b at root' => ['<b>x</b>', '<p><strong>x</strong></p>'],
    ];
  }

  #[Test]
  public function it_keeps_a_blockquote_and_its_paragraphs(): void
  {
    $this->assertSame(
      '<blockquote><p>one</p><p>two</p></blockquote>',
      $this->render('<blockquote><p>one</p><p>two</p></blockquote>'),
    );
  }

  #[Test]
  public function it_wraps_loose_quote_text_in_a_paragraph(): void
  {
    $this->assertSame('<blockquote><p>loose</p></blockquote>', $this->render('<blockquote>loose</blockquote>'));
  }

  #[Test]
  public function it_flattens_a_quote_inside_a_quote_to_one_level(): void
  {
    $this->assertSame(
      '<blockquote><p>outer</p><p>inner</p></blockquote>',
      $this->render('<blockquote><p>outer</p><blockquote><p>inner</p></blockquote></blockquote>'),
    );
  }

  #[Test]
  public function it_drops_an_empty_quote(): void
  {
    $this->assertSame('', $this->render('<blockquote><p>  </p></blockquote>'));
  }

  #[Test]
  public function it_keeps_a_nested_list_inside_its_item(): void
  {
    $this->assertSame(
      '<ul><li>one<ul><li>one a</li></ul></li><li>two</li></ul>',
      $this->render('<ul><li>one<ul><li>one a</li></ul></li><li>two</li></ul>'),
    );
  }

  #[Test]
  public function it_keeps_an_ordered_list_nested_in_an_unordered_one(): void
  {
    $this->assertSame(
      '<ul><li>one<ol><li>a</li></ol></li></ul>',
      $this->render('<ul><li>one<ol><li>a</li></ol></li></ul>'),
    );
  }

  #[Test]
  public function it_adopts_a_list_nested_directly_under_its_parent_list(): void
  {
    // Browsers accept ul > ul; the spec does not. The level should survive
    // rather than collapse into a sibling item.
    $this->assertSame(
      '<ul><li>one<ul><li>deeper</li></ul></li></ul>',
      $this->render('<ul><li>one</li><ul><li>deeper</li></ul></ul>'),
    );
  }

  #[Test]
  public function it_keeps_an_item_that_only_holds_a_nested_list(): void
  {
    $this->assertSame(
      '<ul><li><ul><li>deep</li></ul></li></ul>',
      $this->render('<ul><li><ul><li>deep</li></ul></li></ul>'),
    );
  }

  #[Test]
  public function it_treats_a_wrapping_div_as_a_paragraph(): void
  {
    $this->assertSame('<p>copy</p>', $this->render('<div>copy</div>'));
  }

  #[Test]
  #[DataProvider('rejectedMarkup')]
  public function it_still_rejects_what_it_always_rejected(string $content, string $expected): void
  {
    $this->assertSame($expected, $this->render($content));
  }

  public static function rejectedMarkup(): array
  {
    return [
      'script' => ['<p>a</p><script>alert(1)</script>', '<p>a</p>'],
      'iframe' => ['<iframe src="https://example.com"></iframe><p>a</p>', '<p>a</p>'],
      'image' => ['<p>a</p><img src="x.png">', '<p>a</p>'],
      'table' => ['<table><tr><td>a</td></tr></table><p>b</p>', '<p>b</p>'],
      'heading' => ['<h2>Title</h2><p>a</p>', '<p>a</p>'],
      'heading inside a quote' => ['<blockquote><h2>Title</h2><p>a</p></blockquote>', '<blockquote><p>a</p></blockquote>'],
      'button' => ['<button>go</button><p>a</p>', '<p>a</p>'],
      'style' => ['<style>p{color:red}</style><p>a</p>', '<p>a</p>'],
    ];
  }

  #[Test]
  public function it_strips_a_javascript_href_but_keeps_the_text(): void
  {
    $this->assertSame('<p>click</p>', $this->render('<p><a href="javascript:alert(1)">click</a></p>'));
  }

  #[Test]
  public function it_keeps_a_safe_link_and_adds_rel(): void
  {
    $this->assertSame(
      '<p><a href="https://example.com" rel="noopener noreferrer">site</a></p>',
      $this->render('<p><a href="https://example.com">site</a></p>'),
    );
  }

  #[Test]
  public function it_drops_attributes_the_vocabulary_does_not_carry(): void
  {
    $this->assertSame(
      '<p><strong>x</strong></p>',
      $this->render('<p><strong class="danger" onclick="alert(1)" style="color:red">x</strong></p>'),
    );
  }

  #[Test]
  public function it_renders_nothing_for_empty_content(): void
  {
    $this->assertSame('', $this->render(''));
    $this->assertSame('', $this->render('   '));
    $this->assertSame('', $this->render('<p></p>'));
  }
}
