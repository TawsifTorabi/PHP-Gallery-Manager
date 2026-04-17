<?php

class Assets
{
    private static array $css = [];
    private static array $js = [];

    private static array $map = [
        'bootstrap' => [
            'css' => ['/vendor/bootstrap-5.3.8-dist/css/bootstrap.min.css'],
            'js'  => ['/vendor/bootstrap-5.3.8-dist/js/bootstrap.min.js']
        ],
        'bootstrap_bundle' => [
            'js'  => ['/vendor/bootstrap-5.3.8-dist/js/bootstrap.bundle.min.js']
        ],
        'cropper' => [
            'css' => ['/vendor/cropper.js_1.5.12/css/cropper.min.css'],
            'js'  => ['/vendor/cropper.js_1.5.12/js/cropper.min.js']
        ],
        'ckeditor' => [
            'js'  => ['/vendor/ckeditor.js_36.0.1/ckeditor.js']
        ],
        'jquery' => [
            'js'  => ['/vendor/jquery_3.6.0_min/jquery-3.6.0.min.js']
        ],
        'popper' => [
            'js'  => ['/vendor/popper.js_2.11.6/popper.min.js']
        ],
        'fontawesome' => [
            'css' => ['/vendor/fontawesome-free-7.2.0-web/css/all.min.css'],
            'js'  => ['/vendor/fontawesome-free-7.2.0-web/js/all.min.js']
        ],
        'glightbox' => [
            'css' => ['/vendor/glightbox-3.3.0/glightbox.min.css'],
            'js'  => ['/vendor/glightbox-3.3.0/glightbox.min.js']
        ],
    ];

    public static function use(string|array $modules, string $type = 'all'): void
    {
        $modules = (array) $modules;

        foreach ($modules as $module) {
            if (!isset(self::$map[$module])) continue;

            // Load CSS if type is 'all' or 'css'
            if ($type === 'all' || $type === 'css') {
                foreach (self::$map[$module]['css'] ?? [] as $css) {
                    self::$css[$css] = true;
                }
            }

            // Load JS if type is 'all' or 'js'
            if ($type === 'all' || $type === 'js') {
                foreach (self::$map[$module]['js'] ?? [] as $js) {
                    self::$js[$js] = true;
                }
            }
        }
    }

    public static function renderCSS(): void
    {
        foreach (self::$css as $file => $_) {
            echo '<link rel="stylesheet" href="' . $file . '">' . PHP_EOL;
        }
    }

    public static function renderJS(): void
    {
        foreach (self::$js as $file => $_) {
            echo '<script src="' . $file . '"></script>' . PHP_EOL;
        }
    }
}
