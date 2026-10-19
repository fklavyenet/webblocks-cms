<?php

namespace WebBlocks\Cms\Tests\Support;

use RuntimeException;

final class NodeDependencyGuard
{
  private const FILES = ['package.json', 'package-lock.json', 'npm-shrinkwrap.json', 'pnpm-lock.yaml', 'pnpm-workspace.yaml', 'yarn.lock', 'bun.lock', 'bun.lockb', '.npmrc', '.yarnrc', '.yarnrc.yml', '.nvmrc', '.node-version'];

  private const COMMANDS = ['node', 'nodejs', 'npm', 'npx', 'pnpm', 'yarn', 'corepack', 'playwright', 'puppeteer', 'vite', 'webpack', 'rollup', 'eslint', 'tsc', 'tsx', 'ts-node', 'jest', 'vitest', 'mocha'];

  /** @return list<string> */
  public static function errors(string $root): array
  {
    $process = proc_open(['git', '-C', $root, 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (! is_resource($process)) {
      throw new RuntimeException('Cannot list repository files.');
    }
    $output = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || $output === false) {
      throw new RuntimeException('Cannot list repository files. Run this check in a Git checkout.');
    }
    $errors = [];
    foreach (array_unique(explode("\0", $output)) as $path) {
      if ($path === '' || ! is_file($root.'/'.$path)) {
        continue;
      }
      $content = self::readsContent($path) ? file_get_contents($root.'/'.$path) : '';
      if ($content === false) {
        throw new RuntimeException('Cannot read repository file: '.$path);
      }
      $errors = [...$errors, ...self::fileErrors($path, $content)];
    }

    return array_values(array_unique($errors));
  }

  private static function readsContent(string $path): bool
  {
    return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['', 'php', 'js', 'mjs', 'cjs', 'sh', 'bash', 'ps1', 'cmd', 'bat', 'mk', 'yml', 'yaml', 'json', 'md'], true)
      || str_starts_with($path, 'scripts/') || basename($path) === '.tool-versions';
  }

  /** @return list<string> */
  public static function fileErrors(string $path, string $content): array
  {
    $segments = explode('/', $path);
    if (in_array('node_modules', $segments, true)) {
      $directory = implode('/', array_slice($segments, 0, array_search('node_modules', $segments, true) + 1));

      return [$directory.': Node dependency directory is forbidden.'];
    }
    if (in_array(basename($path), self::FILES, true) || str_ends_with($path, '.cjs')) {
      return [$path.': Node/npm manifest, configuration or CommonJS tool is forbidden.'];
    }
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $errors = [];
    $report = static function (int $line, string $reason) use ($path, &$errors): void {
      $errors[] = $path.':'.$line.': '.$reason;
    };
    if ($extension === 'php') {
      self::phpCommands($content, $report);
    } elseif (in_array($extension, ['js', 'mjs'], true)) {
      self::javascript($content, $report);
    } elseif ($path === 'composer.json') {
      $composer = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
      $scripts = $composer['scripts'] ?? [];
      array_walk_recursive($scripts, static function ($command) use ($report): void {
        if (is_string($command)) {
          self::shell($command, $report);
        }
      });
    } elseif (in_array($extension, ['yml', 'yaml'], true)) {
      $blockIndent = null;
      foreach (explode("\n", $content) as $index => $line) {
        if (preg_match('/^\s*(?:#|$)/', $line)) {
          continue;
        }
        $indent = strlen($line) - strlen(ltrim($line));
        if ($blockIndent !== null && $indent > $blockIndent) {
          self::shell(preg_replace('/^\s*-\s*/', '', $line), $report, $index + 1);
        } else {
          $blockIndent = null;
        }
        if (preg_match('/^\s*(?:-\s*)?(?:uses:\s*[\x22\x27]?[^\s#]*setup-node@|node-version(?:-file)?:|using:\s*[\x22\x27]?node\d+)/i', $line)) {
          $report($index + 1, 'Declared Node runtime/setup is forbidden.');
        }
        if (preg_match('/^\s*(?:-\s*)?(?:run|script|command|entrypoint):\s*(.*)$/', $line, $match)) {
          if (preg_match('/^[|>]/', $match[1]) || $match[1] === '') {
            $blockIndent = $indent;
          } else {
            self::shell(trim($match[1], "\x22\x27"), $report, $index + 1);
          }
        }
        if (preg_match('/^\s*(?:-\s*)?(?:image|container):\s*[\x22\x27]?(?:[^\s\x22\x27]+\/)?node(?:js)?(?=[:@\s\x22\x27]|$)/i', $line)) {
          $report($index + 1, 'Node container image is forbidden.');
        }
      }
    } elseif ($extension === 'md') {
      preg_match_all('/^```(sh|bash|shell|js|javascript|mjs)\s*\n(.*?)^```/ms', $content, $blocks, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
      foreach ($blocks as $block) {
        $line = substr_count(substr($content, 0, $block[2][1]), "\n") + 1;
        if (in_array($block[1][0], ['js', 'javascript', 'mjs'], true)) {
          self::javascript($block[2][0], static fn ($offset, $reason) => $report($line + $offset - 1, $reason));
        } else {
          self::shell($block[2][0], $report, $line);
        }
      }
    } elseif (basename($path) === '.tool-versions') {
      foreach (explode("\n", $content) as $index => $line) {
        if (preg_match('/^\s*node(?:js)?\s+/i', $line)) {
          $report($index + 1, 'Declared Node version is forbidden.');
        }
      }
    } elseif (in_array($extension, ['sh', 'bash', 'ps1', 'cmd', 'bat', 'mk'], true) || str_starts_with($path, 'scripts/')
      || in_array(basename($path), ['Makefile', 'GNUmakefile'], true) || preg_match('/^#![^\n]*\b(?:sh|bash|zsh|dash)\b/', $content)) {
      self::shell($content, $report);
    }
    if (basename($path) === 'Dockerfile') {
      foreach (explode("\n", $content) as $index => $line) {
        if (preg_match('/^\s*FROM\s+(?:--\S+\s+)*(?:[^\s]+\/)?node(?:js)?(?=[:@\s]|$)/i', $line)) {
          $report($index + 1, 'Node container image is forbidden.');
        }
        if (preg_match('/^\s*RUN\s+(.*)$/i', $line, $match)) {
          self::shell($match[1], $report, $index + 1);
        }
      }
    }
    if (preg_match('/^#![^\n]*\b(node|nodejs|npm|npx|tsx)\b/', $content)) {
      $report(1, 'Node/npm executable shebang is forbidden.');
    }

    return array_values(array_unique($errors));
  }

  private static function shell(string $command, callable $report, int $line = 1): void
  {
    preg_match_all('~\x22(?:\\\\.|[^\x22\\\\])*\x22|\x27[^\x27]*\x27|\x60[^\x60]*\x60|\#[^\n]*|\$\(|&&|\|\||[;&|\n()]|[^\s;&|()]+~', str_replace("\\\n", '  ', $command), $matches, PREG_OFFSET_CAPTURE);
    $start = true;
    $skipNext = false;
    $nestedNext = false;
    $interpreter = false;
    foreach ($matches[0] as [$raw, $offset]) {
      if (str_starts_with($raw, '#')) {
        continue;
      }
      if (str_starts_with($raw, '"')) {
        preg_match_all('/(?<!\\\\)\$\(([^()]*)\)/', $raw, $substitutions);
        foreach ($substitutions[1] as $substitution) {
          self::shell($substitution, $report, $line + substr_count(substr($command, 0, $offset), "\n"));
        }
      }
      if (str_starts_with($raw, '`')) {
        self::shell(substr($raw, 1, -1), $report, $line + substr_count(substr($command, 0, $offset), "\n"));
      }
      if (in_array($raw, ['&&', '||', ';', '&', '|', "\n", '$(', '(', ')'], true)) {
        $start = true;
        $interpreter = false;
        $skipNext = false;
        $nestedNext = false;

        continue;
      }
      $word = trim($raw, "\x22\x27");
      if ($nestedNext) {
        self::shell($word, $report, $line + substr_count(substr($command, 0, $offset), "\n"));
        $nestedNext = false;

        continue;
      }
      if ($interpreter && in_array(strtolower($word), ['-c', '-lc', '/c', '-command'], true)) {
        $nestedNext = true;

        continue;
      }
      if (! $start) {
        continue;
      }
      if ($skipNext) {
        $skipNext = false;

        continue;
      }
      if (in_array($word, ['-u', '--unset', '-C', '--chdir'], true)) {
        $skipNext = true;
      }
      if ($word === '-S') {
        $nestedNext = true;
      }
      if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $word) || str_starts_with($word, '-')) {
        continue;
      }
      $binary = strtolower(preg_replace('/\.(exe|cmd|ps1)$/i', '', basename(str_replace('\\', '/', $word))));
      if (in_array($binary, self::COMMANDS, true)) {
        $report($line + substr_count(substr($command, 0, $offset), "\n"), 'Node/npm command is forbidden ('.$binary.').');
      }
      $interpreter = in_array($binary, ['sh', 'bash', 'zsh', 'dash', 'cmd', 'powershell', 'pwsh'], true);
      $start = in_array($binary, ['env', 'command', 'exec', 'time', 'nohup', 'if', 'then', 'else', 'elif', 'do', '!'], true);
    }
  }

  private static function phpCommands(string $content, callable $report): void
  {
    $tokens = array_values(array_filter(token_get_all($content), static fn ($token) => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
    $text = static fn ($token) => is_array($token) ? $token[1] : $token;
    $aliases = [];
    foreach ($tokens as $index => $token) {
      if (is_array($token) && $token[0] === T_USE) {
        $cursor = $index + 1;
        if (strtolower($text($tokens[$cursor] ?? '')) === 'function') {
          $cursor++;
        }
        $name = strtolower(basename(str_replace('\\', '/', $text($tokens[$cursor] ?? ''))));
        if (strtolower($text($tokens[$cursor + 1] ?? '')) === 'as') {
          $aliases[strtolower($text($tokens[$cursor + 2] ?? ''))] = $name;
        }
      }
    }
    $backtick = null;
    $backtickLine = 1;
    foreach ($tokens as $index => $token) {
      if ($token === '`') {
        if ($backtick === null) {
          $backtick = '';
        } else {
          self::shell($backtick, $report, $backtickLine);
          $backtick = null;
        }
      } elseif ($backtick !== null) {
        if ($backtick === '' && is_array($token)) {
          $backtickLine = $token[2];
        }
        $backtick .= $text($token);
      }
      if (! is_array($token)) {
        continue;
      }
      $name = strtolower(basename(str_replace('\\', '/', $token[1])));
      $name = $aliases[$name] ?? $name;
      $previous = $text($tokens[$index - 1] ?? '');
      $class = strtolower(basename(str_replace('\\', '/', $text($tokens[$index - 2] ?? ''))));
      $class = $aliases[$class] ?? $class;
      $call = in_array($name, ['exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open'], true)
        || ($name === 'process' && strtolower($previous) === 'new')
        || ($class === 'process' && $previous === '::' && in_array($name, ['run', 'start', 'command', 'fromshellcommandline'], true));
      if (! $call || $text($tokens[$index + 1] ?? '') !== '(') {
        continue;
      }
      $argument = $index + 2;
      $array = false;
      if ($text($tokens[$argument] ?? '') === '[') {
        $argument++;
        $array = true;
      } elseif (strtolower($text($tokens[$argument] ?? '')) === 'array' && $text($tokens[$argument + 1] ?? '') === '(') {
        $argument += 2;
        $array = true;
      }
      $parts = [];
      $line = $token[2];
      while (is_array($literal = $tokens[$argument] ?? null) && $literal[0] === T_CONSTANT_ENCAPSED_STRING) {
        $value = substr($literal[1], 1, -1);
        $value = $literal[1][0] === "'" ? str_replace(['\\\\', "\\'"], ['\\', "'"], $value) : stripcslashes($value);
        if ($parts === []) {
          $line = $literal[2];
        }
        $parts[] = $array ? escapeshellarg($value) : $value;
        if (! $array || $text($tokens[$argument + 1] ?? '') !== ',') {
          break;
        }
        $argument += 2;
      }
      if ($parts !== []) {
        self::shell(implode(' ', $parts), $report, $line);
      }
    }
  }

  private static function javascript(string $content, callable $report): void
  {
    $pattern = <<<'REGEX'
~//[^\n]*|/\*.*?\*/|"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|`(?:\\.|[^`\\])*`|[A-Za-z_$][A-Za-z0-9_$]*|[^\s]~s
REGEX;
    preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE);
    $tokens = array_values(array_filter($matches[0], static fn ($token) => ! str_starts_with($token[0], '//') && ! str_starts_with($token[0], '/*')));
    foreach ($tokens as $index => [$word, $offset]) {
      $specifier = null;
      if ($word === 'require' && ($tokens[$index + 1][0] ?? '') === '(') {
        $specifier = $tokens[$index + 2][0] ?? '';
      } elseif (in_array($word, ['import', 'export'], true) && ($tokens[$index + 1][0] ?? '') !== '.') {
        $next = $tokens[$index + 1][0] ?? '';
        if ($next === '(') {
          $specifier = $tokens[$index + 2][0] ?? '';
        } elseif ($next !== '' && in_array($next[0], ['"', "'", '`'], true)) {
          $specifier = $next;
        } else {
          for ($cursor = $index + 1; $cursor < count($tokens) && $tokens[$cursor][0] !== ';'; $cursor++) {
            if ($tokens[$cursor][0] === 'from') {
              $specifier = $tokens[$cursor + 1][0] ?? '';
              break;
            }
          }
        }
      }
      if ($specifier !== null && $specifier !== '' && in_array($specifier[0], ['"', "'", '`'], true)) {
        $module = substr($specifier, 1, -1);
        if (! preg_match('~^(?:\.{1,2}/|/|https?:|data:|blob:)~i', $module)) {
          $report(substr_count(substr($content, 0, $offset), "\n") + 1, 'Node/npm or bare package module import is forbidden.');
        }
      }
      if (in_array($word, ['__dirname', '__filename'], true) || ($word === 'process' && ($tokens[$index + 1][0] ?? '') === '.' && in_array($tokens[$index + 2][0] ?? '', ['argv', 'env', 'execPath', 'version'], true))) {
        $report(substr_count(substr($content, 0, $offset), "\n") + 1, 'Node-only runtime global is forbidden.');
      }
    }
  }
}
