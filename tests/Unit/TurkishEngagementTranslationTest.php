<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TurkishEngagementTranslationTest extends TestCase
{
  #[Test]
  public function rating_and_comment_copy_uses_correct_turkish_characters(): void
  {
    $blocks = require dirname(__DIR__, 2).'/resources/lang/tr/blocks.php';
    $public = require dirname(__DIR__, 2).'/resources/lang/tr/public.php';
    $validation = require dirname(__DIR__, 2).'/resources/lang/tr/validation.php';

    $this->assertSame('Henüz onaylanmış yorum yok.', $blocks['comments']['no_approved']);
    $this->assertSame('Yorumlar görünmeden önce incelenir.', $blocks['comments']['helper']);
    $this->assertSame('Bu içeriği puanla', $blocks['rating']['form_label']);
    $this->assertSame('Puanınız için teşekkürler.', $public['engagement']['rating_submitted']);
    $this->assertSame('Bir puan seçin.', $validation['rating']['required']);
    $this->assertSame('Geçerli bir puan seçin.', $validation['rating']['integer']);
  }
}
