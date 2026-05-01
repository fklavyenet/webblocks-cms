<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\HtmlStructureAssertions;

abstract class TestCase extends BaseTestCase
{
    use HtmlStructureAssertions;
}
