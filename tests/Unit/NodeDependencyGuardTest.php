<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use WebBlocks\Cms\Tests\Support\NodeDependencyGuard;

class NodeDependencyGuardTest extends TestCase
{
  #[DataProvider('forbiddenDependencies')]
  public function test_forbidden_dependencies_and_commands_are_rejected(string $path, string $content): void
  {
    $errors = NodeDependencyGuard::fileErrors($path, $content);
    $this->assertNotEmpty($errors, $path);
    $this->assertStringContainsString(explode('/node_modules', $path)[0], implode("\n", $errors));
  }

  public static function forbiddenDependencies(): array
  {
    return [
      'nested manifest' => ['tests/browser/package.json', '{}'],
      'lockfile' => ['package-lock.json', '{}'],
      'pnpm workspace' => ['pnpm-workspace.yaml', 'packages: []'],
      'node version file' => ['.node-version', '22'],
      'npm configuration' => ['.npmrc', 'audit=false'],
      'asdf version' => ['.tool-versions', "php 8.4\nnodejs 22\n"],
      'installed modules' => ['tools/node_modules/example/index.js', ''],
      'commonjs tool' => ['tests/browser/check.cjs', 'console.log("ok");'],
      'module builtin' => ['tests/browser/check.mjs', "import fs from 'node:fs';"],
      'module bare builtin' => ['tests/browser/check.js', "import {readFileSync} from 'fs';"],
      'npm package import' => ['tests/browser/check.js', "import {chromium} from 'playwright';"],
      'commonjs import' => ['tests/browser/check.js', "const fs = require('fs');"],
      'dynamic import' => ['tests/browser/check.js', 'const module = await import("node:http");'],
      're-export' => ['tests/browser/check.js', "export {test} from 'node:test';"],
      'node global' => ['tests/browser/check.js', 'console.log(process.argv);'],
      'node shebang' => ['tools/check', "#!/usr/bin/env node\nconsole.log('ok');"],
      'direct shell' => ['scripts/check.sh', "#!/bin/sh\nnode tests/check.js"],
      'extensionless shell tool' => ['tests/Browser/check', "#!/bin/sh\nnode tests/check.js"],
      'make recipe' => ['Makefile', "test:\n\tnpm test\n"],
      'powershell command' => ['tools/check.ps1', '& "node.exe" --version'],
      'absolute quoted executable' => ['scripts/check.sh', '"/usr/local/bin/npm" ci'],
      'command sequence' => ['scripts/check.sh', 'php tests/check.php && npm test'],
      'shell substitution' => ['scripts/check.sh', 'echo "$(npm --version)"'],
      'backtick substitution' => ['scripts/check.sh', 'echo `node --version`'],
      'shell interpreter' => ['scripts/check.sh', 'bash -lc "npm ci"'],
      'environment wrapper' => ['scripts/check.sh', 'env NODE_ENV=test npx playwright test'],
      'env unset' => ['scripts/check.sh', 'env -u NODE_OPTIONS node test.js'],
      'env split' => ['scripts/check.sh', "env -S 'node --version'"],
      'literal php subprocess' => ['tests/Support/check.php', '<?php new Process(["node", "--version"]);'],
      'php shell function' => ['tests/Support/check.php', '<?php shell_exec("npm ci");'],
      'php process facade' => ['tests/Support/check.php', '<?php Process::run("npx playwright test");'],
      'php shell commandline' => ['tests/Support/check.php', '<?php Process::fromShellCommandline("node test.js");'],
      'php environment argv' => ['tests/Support/check.php', '<?php new Process(["env", "NODE_ENV=test", "npm", "ci"]);'],
      'php process alias' => ['tests/Support/check.php', '<?php use Symfony\\Component\\Process\\Process as Runner; new Runner(["node"]);'],
      'php backticks' => ['tests/Support/check.php', '<?php $output = `npm ci`;'],
      'workflow setup' => ['.github/workflows/ci.yml', "steps:\n  - uses: actions/setup-node@v4\n"],
      'workflow scalar command' => ['.github/workflows/ci.yml', "steps:\n  - run: npm ci\n"],
      'workflow multiline command' => ['.github/workflows/ci.yml', "steps:\n  - run: |\n      echo start\n      node tests/check.js\n"],
      'composite command' => ['.github/actions/check/action.yml', "runs:\n  using: composite\n  steps:\n    - run: npx playwright test\n"],
      'javascript action runtime' => ['.github/actions/check/action.yml', "runs:\n  using: node24\n"],
      'workflow container' => ['.github/workflows/ci.yml', "jobs:\n  test:\n    container: node:22\n"],
      'docker base' => ['Dockerfile', 'FROM node:22'],
      'docker command' => ['Dockerfile', "FROM php:8.4\nRUN npm ci"],
      'composer scripts' => ['composer.json', '{"scripts":{"test":["php check.php","node test.js"]}}'],
      'documented shell dependency' => ['CONTRIBUTING.md', "```sh\nnpm ci\n```\n"],
      'documented module dependency' => ['CONTRIBUTING.md', "```js\nimport fs from 'node:fs';\n```\n"],
    ];
  }

  #[DataProvider('allowedSource')]
  public function test_browser_code_comments_prose_and_protocol_values_are_allowed(string $path, string $content): void
  {
    $this->assertSame([], NodeDependencyGuard::fileErrors($path, $content));
  }

  public static function allowedSource(): array
  {
    return [
      'prose' => ['README.md', 'Node.js and npm are not required.'],
      'php exclusion list' => ['config/defaults.php', '<?php $forbidden = ["node_modules", "package.json", "yarn.lock"];'],
      'php test literals' => ['tests/Unit/ExampleTest.php', '<?php $fixture = \'<?php shell_exec("npm ci");\';'],
      'php comments' => ['tests/Support/check.php', "<?php // shell_exec('npm ci');\n/* new Process(['node']); */"],
      'php array arguments' => ['tests/Support/check.php', '<?php new Process([PHP_BINARY, "check.php", "node"]);'],
      'php command arguments' => ['tests/Support/check.php', '<?php shell_exec("echo npm install");'],
      'empty composer scripts' => ['composer.json', '{"require":{"php":"^8.3"}}'],
      'php composer script' => ['composer.json', '{"scripts":{"test":"vendor/bin/phpunit","check":"@php tests/check-node-dependencies.php"}}'],
      'dom node' => ['public/cms/js/editor.js', 'var node = document.createElement("span"); var value = {node: node};'],
      'javascript prose and comments' => ['public/cms/js/editor.js', "const text = \"Node's npm tools are not required\"; // import fs from 'node:fs';\n/* require('fs'); */"],
      'quoted javascript example' => ['public/cms/js/editor.js', 'const demo = "import fs from \'node:fs\';";'],
      'relative module' => ['public/cms/js/editor.mjs', "import {editor} from './editor.js'; export {modal} from '../modal.js';"],
      'browser url import' => ['public/cms/js/editor.mjs', "import '/js/editor.js'; import('https://example.test/editor.js');"],
      'shell comments' => ['scripts/check.sh', "# npm ci\nphp tests/check.php # node test.js\n"],
      'quoted shell prose' => ['scripts/check.sh', 'echo "npm install; node test.js"'],
      'quoted literal substitution' => ['scripts/check.sh', "echo '$(npm install)'"],
      'yaml prose and comments' => ['.github/workflows/ci.yml', "# uses: actions/setup-node@v4\nsteps:\n  - run: echo 'node-version: 22'\n"],
      'php workflow' => ['.github/workflows/ci.yml', "steps:\n  - uses: actions/checkout@v4\n  - run: php tests/Support/check-node-dependencies.php\n"],
      'php container' => ['Dockerfile', "FROM php:8.4\nRUN php --version"],
      'php documented commands' => ['CONTRIBUTING.md', "```sh\nphp tests/Support/check-node-dependencies.php\n```\n"],
    ];
  }

  public function test_cli_checks_untracked_files_ignores_installed_dependencies_and_fails_without_leaking_arguments(): void
  {
    $root = sys_get_temp_dir().'/cms-no-node-'.bin2hex(random_bytes(8));
    $filesystem = new Filesystem;
    $filesystem->makeDirectory($root.'/vendor/example', 0755, true);
    try {
      (new Process(['git', 'init', '--quiet', $root]))->mustRun();
      file_put_contents($root.'/.gitignore', "/vendor/\n");
      file_put_contents($root.'/vendor/example/package.json', '{}');
      $script = dirname(__DIR__).'/Support/check-node-dependencies.php';
      $passed = new Process([PHP_BINARY, $script, '--root='.$root]);
      $passed->run();
      $this->assertSame(0, $passed->getExitCode(), $passed->getErrorOutput());
      file_put_contents($root.'/check', "#!/usr/bin/env node\nconsole.log('ok');");
      $extensionless = new Process([PHP_BINARY, $script, '--root='.$root]);
      $extensionless->run();
      $this->assertSame(1, $extensionless->getExitCode());
      $this->assertStringContainsString('check:1:', $extensionless->getErrorOutput());
      unlink($root.'/check');
      file_put_contents($root.'/check.php', '<?php shell_exec("npm config set token synthetic-private-value");');
      $failed = new Process([PHP_BINARY, $script, '--root='.$root]);
      $failed->run();
      $this->assertSame(1, $failed->getExitCode());
      $this->assertStringContainsString('check.php:1:', $failed->getErrorOutput());
      $this->assertStringNotContainsString('synthetic-private-value', $failed->getErrorOutput());
      unlink($root.'/check.php');
      file_put_contents($root.'/package.json', '{}');
      $manifest = new Process([PHP_BINARY, $script, '--root='.$root]);
      $manifest->run();
      $this->assertSame(1, $manifest->getExitCode());
      $this->assertStringContainsString('package.json:', $manifest->getErrorOutput());
      $missing = new Process([PHP_BINARY, $script, '--root='.$root.'/missing']);
      $missing->run();
      $this->assertSame(2, $missing->getExitCode());
    } finally {
      $filesystem->deleteDirectory($root);
    }
  }

  public function test_repository_passes_and_both_gates_run_the_same_check_before_long_tests(): void
  {
    $root = dirname(__DIR__, 2);
    $this->assertSame([], NodeDependencyGuard::errors($root));
    $command = 'php tests/Support/check-node-dependencies.php';
    $hook = file_get_contents($root.'/scripts/pre-push');
    $workflow = file_get_contents($root.'/.github/workflows/ci.yml');
    $this->assertStringContainsString($command, $hook);
    $this->assertStringContainsString('run: '.$command, $workflow);
    $this->assertLessThan(strpos($hook, 'vendor/bin/phpunit'), strpos($hook, $command));
    $this->assertLessThan(strpos($workflow, 'run: composer install'), strpos($workflow, 'run: '.$command));
  }

  public function test_pre_push_stops_before_composer_when_a_node_dependency_is_added(): void
  {
    $root = sys_get_temp_dir().'/cms-no-node-hook-'.bin2hex(random_bytes(8));
    $source = dirname(__DIR__, 2);
    $filesystem = new Filesystem;
    $filesystem->makeDirectory($root.'/tests/Support', 0755, true);
    try {
      (new Process(['git', 'init', '--quiet', $root]))->mustRun();
      foreach (['check-node-dependencies.php', 'NodeDependencyGuard.php'] as $file) {
        copy($source.'/tests/Support/'.$file, $root.'/tests/Support/'.$file);
      }
      file_put_contents($root.'/package.json', '{}');
      $hook = new Process(['/bin/sh', $source.'/scripts/pre-push'], $root);
      $hook->run();
      $this->assertSame(1, $hook->getExitCode());
      $this->assertStringContainsString('package.json:', $hook->getErrorOutput());
      $this->assertStringContainsString('pre-push: check-node-dependencies', $hook->getOutput());
      $this->assertStringNotContainsString('pre-push: composer validate', $hook->getOutput());
    } finally {
      $filesystem->deleteDirectory($root);
    }
  }

  public function test_content_findings_report_the_source_line(): void
  {
    $shell = NodeDependencyGuard::fileErrors('check.sh', "# start\nphp check.php && \\\n npm test\n");
    $this->assertStringContainsString('check.sh:3:', $shell[0]);
    $php = NodeDependencyGuard::fileErrors('check.php', "<?php\n\n\$output = `npm ci`;\n");
    $this->assertStringContainsString('check.php:3:', $php[0]);
    $javascript = NodeDependencyGuard::fileErrors('check.js', "// start\nimport fs from 'node:fs';\n");
    $this->assertStringContainsString('check.js:2:', $javascript[0]);
  }
}
