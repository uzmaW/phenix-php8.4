<?php

namespace Phoenix\View;

final class View
{
    public function __construct(
        private string $view,
        private array $data = [],
    ) {}

    public function render(): string
    {
        $cacheKey = 'view_' . md5($this->view . serialize($this->data));
        $cachedPath = Factory::cachePath() . '/' . $cacheKey . '.php';
        $sourcePath = Factory::path() . '/' . $this->view . '.php';

        if (!file_exists($sourcePath)) {
            return "<!-- View not found: {$this->view} -->";
        }

        if (!file_exists($cachedPath) || !empty(getenv('APP_DEBUG'))) {
            $content = $this->compile(file_get_contents($sourcePath));
            if (!is_dir(Factory::cachePath())) {
                mkdir(Factory::cachePath(), 0o755, true);
            }
            file_put_contents($cachedPath, $content);
        }

        extract($this->data);
        ob_start();
        include $cachedPath;

        return ob_get_clean();
    }

    public function __toString(): string
    {
        return $this->render();
    }

    private function compile(string $content): string
    {
        $content = preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/',
            '<?php echo htmlspecialchars((string)($1 ?? ""), ENT_QUOTES); ?>',
            $content,
        );
        $content = $this->compileIfDirectives($content);
        $content = str_replace('@endif', '<?php endif; ?>', $content);
        $content = str_replace('@else', '<?php else: ?>', $content);
        $content = str_replace('@foreach', '<?php foreach', $content);
        $content = str_replace('@endforeach', '<?php endforeach; ?>', $content);

        return '<?php /* Cached */ ?>' . $content;
    }

    private function compileIfDirectives(string $content): string
    {
        $result = '';
        $i = 0;
        $len = strlen($content);

        while ($i < $len) {
            if (preg_match('/@if\s*\(/', $content, $matches, PREG_OFFSET_MATCH, $i)) {
                $matchStart = $matches[0][1];
                $result .= substr($content, $i, $matchStart - $i);
                $parenStart = $matchStart + strlen($matches[0][0]) - 1;
                $depth = 1;
                $j = $parenStart + 1;
                while ($j < $len && $depth > 0) {
                    if ($content[$j] === '(') {
                        $depth++;
                    } elseif ($content[$j] === ')') {
                        $depth--;
                    }
                    $j++;
                }
                $condition = substr($content, $parenStart + 1, $j - $parenStart - 2);
                $result .= '<?php if(' . $condition . '): ?>';
                $i = $j;
            } else {
                $result .= substr($content, $i);
                break;
            }
        }

        return $result;
    }
}
