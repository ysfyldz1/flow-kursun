<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /**
     * "Dashboard::index" → modules/Dashboard/views/index.php
     * "layout"           → views/layout.php
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): void
    {
        $content = self::capture(self::path($view), $data);
        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::capture(self::path($layout), $data + ['content' => $content]);
    }

    public static function partial(string $view, array $data = []): string
    {
        return self::capture(self::path($view), $data);
    }

    private static function path(string $view): string
    {
        if (str_contains($view, '::')) {
            [$module, $name] = explode('::', $view, 2);
            return BASE_PATH . "/modules/{$module}/views/{$name}.php";
        }
        return BASE_PATH . "/views/{$view}.php";
    }

    private static function capture(string $__file, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        require $__file;
        return (string) ob_get_clean();
    }
}
