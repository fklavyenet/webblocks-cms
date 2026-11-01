<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Seo\PageSeoHtmlCheck;

class PageSeoHtmlCheckTest extends TestCase
{
  private function html(string $body, string $head = '', string $lang = 'tr'): string
  {
    return '<!doctype html><html lang="'.$lang.'"><head><title>Sayfa</title><link rel="canonical" href="https://example.test/tr/sayfa">'.$head.'</head><body>'.$body.'</body></html>';
  }

  #[Test]
  public function technical_defects_and_editorial_judgment_have_different_groups(): void
  {
    $report = (new PageSeoHtmlCheck)->inspect($this->html('<main><h2>Başlık</h2><h4 id="duplicate">Alt başlık</h4><p id="duplicate">İçerik</p><img src="image.jpg"></main>'), 'tr');
    $this->assertSame(['duplicate_ids', 'image_alt_missing'], array_column($report['technical'], 'code'));
    $this->assertSame(['description_missing', 'h1_missing', 'heading_skipped', 'image_dimensions_missing'], array_column($report['editorial'], 'code'));
    $this->assertSame([], $report['not_checked']);
  }

  #[Test]
  public function empty_decorative_alt_and_main_header_h1_are_valid(): void
  {
    $report = (new PageSeoHtmlCheck)->inspect($this->html('<nav><h1>Site</h1></nav><main><header><h1>Sayfa</h1></header><img src="a" alt="" width="20" height="30"><img src="b" alt width="20" height="30"></main>', '<meta name="description" content="Açıklama">'), 'tr');
    $this->assertSame([], $report['technical']);
    $this->assertSame([], $report['editorial']);
  }

  #[Test]
  public function duplicate_head_tags_and_language_mismatch_are_technical_but_noindex_is_a_review(): void
  {
    $report = (new PageSeoHtmlCheck)->inspect($this->html('<main><h1>A</h1><h1>B</h1></main>', '<title>Extra</title><link rel="CANONICAL alternate" href="javascript:invalid"><meta name="description" content="a"><meta name="description" content="b"><meta name="ROBOTS" content="noindex,nofollow">', 'de'), 'tr');
    $this->assertSame(['title_multiple', 'canonical_multiple', 'canonical_invalid', 'language_mismatch', 'description_multiple'], array_column($report['technical'], 'code'));
    $this->assertSame(['noindex_present', 'h1_multiple'], array_column($report['editorial'], 'code'));
  }

  #[Test]
  public function absent_main_and_oversize_html_are_explicitly_unchecked(): void
  {
    $check = new PageSeoHtmlCheck;
    $this->assertSame(['main_content_missing'], $check->inspect($this->html('<nav>Menu</nav>'), 'tr')['not_checked']);
    $this->assertSame(['html_unavailable'], $check->inspect(str_repeat('x', 2 * 1024 * 1024 + 1), 'en')['not_checked']);
  }

  #[Test]
  public function hidden_ui_and_script_copy_do_not_count_as_main_content(): void
  {
    $report = (new PageSeoHtmlCheck)->inspect($this->html('<main><script>text</script><div hidden><h1>Hidden</h1></div></main>'), 'tr');
    $this->assertContains('main_content_empty', array_column($report['editorial'], 'code'));
    $this->assertContains('h1_missing', array_column($report['editorial'], 'code'));
  }
}
