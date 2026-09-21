<?php
/**
 * Plain-PHP template engine with layouts, partials and sections.
 *
 *   View::render('pages/user/home', ['title' => 'Home'], 'layouts/app');
 *
 * Templates are normal PHP files inside /views. All data keys become variables,
 * plus a set of globals ($settings, $user, $bg, $navPages, $flash...).
 */
final class View
{
    /** @var array<string,string> */
    public static array $sections = [];
    /** @var string[] */
    private static array $open = [];
    public static array $globals = [];
    private static string $viewsDir = '';

    public static function init(string $viewsDir): void
    {
        self::$viewsDir = $viewsDir;
    }

    public static function path(string $template): string
    {
        $template = str_replace(['..', '\\'], ['', '/'], $template);
        $file = self::$viewsDir . '/' . ltrim($template, '/') . '.php';
        return $file;
    }

    public static function exists(string $template): bool
    {
        return is_file(self::path($template));
    }

    /** Render a template to a string (no layout). */
    public static function fetch(string $template, array $data = []): string
    {
        $file = self::path($template);
        if (!is_file($file)) {
            return '<!-- missing view: ' . e($template) . ' -->';
        }
        // Locals are prefixed so they can never shadow a template variable
        // (extract(EXTR_SKIP) would otherwise keep our own $data/$file).
        $__vars = [];
        foreach (array_merge(self::$globals, $data) as $__k => $__v) {
            $__vars[(string) $__k] = self::normalize($__v);
        }
        $__file = $file;
        unset($file, $data, $template, $k, $v);
        extract($__vars, EXTR_SKIP);
        unset($__vars);
        ob_start();
        try {
            include $__file;
        } catch (Throwable $ex) {
            ob_end_clean();
            throw $ex;
        }
        return (string) ob_get_clean();
    }

    /**
     * Templates always read data with `->` (like the original EJS/JS did).
     * Assoc arrays become stdClass, lists stay lists, objects pass through.
     */
    public static function normalize($value)
    {
        if (is_array($value)) {
            if ($value === [] || array_is_list($value)) {
                return array_map([self::class, 'normalize'], $value);
            }
            $obj = new NhObj();
            foreach ($value as $k => $v) {
                $obj->{(string) $k} = self::normalize($v);
            }
            return $obj;
        }
        return $value;
    }

    /** Render a template inside a layout and echo it. */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        self::$sections = [];
        $vars = array_merge(self::$globals, $data);
        $content = self::fetch($template, $vars);
        if ($layout) {
            $vars['content'] = $content;
            echo self::fetch($layout, $vars);
        } else {
            echo $content;
        }
    }

    /** Partial (echoes). */
    public static function show(string $template, array $data = []): void
    {
        echo self::fetch($template, $data);
    }

    /* -------- sections -------- */

    public static function start(string $name): void
    {
        self::$open[] = $name;
        ob_start();
    }

    public static function end(): void
    {
        $name = array_pop(self::$open);
        $body = (string) ob_get_clean();
        self::$sections[$name] = (self::$sections[$name] ?? '') . $body;
    }

    public static function section(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    public static function push(string $name, string $html): void
    {
        self::$sections[$name] = (self::$sections[$name] ?? '') . $html;
    }
}
