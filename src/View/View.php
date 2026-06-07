<?php

namespace Phoenix\View;

final class View
{
    public function __construct(
        private string $view,
        private array $data = []
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
                mkdir(Factory::cachePath(), 0755, true);
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
            $content
        );
        $content = preg_replace(
            '/@if\s*\((.+?)\)/',
            '<?php if($1): ?>',
            $content
        );
        $content = str_replace('@endif', '<?php endif; ?>', $content);
        $content = str_replace('@foreach', '<?php foreach', $content);
        $content = str_replace('@endforeach', '<?php endforeach; ?>', $content);
        return '<?php /* Cached */ ?>' . $content;
    }
}
