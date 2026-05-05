<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReleasePackageBoundaryTest extends TestCase
{
    #[Test]
    public function git_attributes_excludes_project_layer_from_release_exports(): void
    {
        $attributes = (string) file_get_contents(base_path('.gitattributes'));

        $this->assertStringContainsString('/project export-ignore', $attributes);
        $this->assertStringContainsString('/.ddev export-ignore', $attributes);
    }

    #[Test]
    public function publish_release_workflow_builds_archives_from_git_archive_with_worktree_attributes(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/publish-release.yml'));

        $this->assertStringContainsString('git archive --format=zip --worktree-attributes --output "$archive_path" HEAD', $workflow);
        $this->assertStringNotContainsString('git ls-files --cached --others --exclude-standard', $workflow);
    }
}
