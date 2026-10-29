<?php
declare(strict_types=1);

namespace Phoenix\Macros;

use Phoenix\Collections\Vector;

/**
 * Macro interface for metaprogramming
 */
interface Macro
{
    public function expand(array $arguments): array;
}

/**
 * Base macro implementation
 */
abstract class BaseMacro implements Macro
{
    protected string $name;
    protected array $arguments = [];

    public function __construct(string $name, array $arguments = [])
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public function get_name(): string { return $this->name; }
    public function get_arguments(): array { return $this->arguments; }
}

/**
 * Macro expansion result
 */
class MacroExpansion
{
    public function __construct(
        public readonly array $code,
        public readonly array $warnings = [],
        public readonly array $errors = []
    ) {}

    public function has_errors(): bool { return !empty($this->errors); }
    public function has_warnings(): bool { return !empty($this->warnings); }
}

/**
 * Macro system manager with performance optimizations
 */
class MacroSystem
{
    private static ?MacroSystem $instance = null;
    private array $macros = [];
    private array $expansion_cache = [];
    private bool $cache_enabled = true;

    /** Performance metrics for macro operations */
    private static array $metrics = [
        'macros_registered' => 0,
        'expansions_performed' => 0,
        'cache_hits' => 0,
        'generation_time' => 0.0,
    ];

    public static function get_metrics(): array { return self::$metrics; }
    public static function reset_metrics(): void { self::$metrics = ['macros_registered'=>0,'expansions_performed'=>0,'cache_hits'=>0,'generation_time'=>0.0]; }

    public static function get_instance(): MacroSystem
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function register_macro(string $name, Macro $macro): void
    {
        self::$metrics['macros_registered']++;
        $this->macros[$name] = $macro;
        if (isset($this->expansion_cache[$name])) {
            unset($this->expansion_cache[$name]);
        }
    }

    public function expand_macro(string $name, array $arguments): MacroExpansion
    {
        self::$metrics['expansions_performed']++;

        $cache_key = $name . '_' . md5(serialize($arguments));
        if ($this->cache_enabled && isset($this->expansion_cache[$cache_key])) {
            self::$metrics['cache_hits']++;
            return $this->expansion_cache[$cache_key];
        }

        if (!isset($this->macros[$name])) {
            return new MacroExpansion([], [], ["Macro '$name' not found"]);
        }

        $start_time = microtime(true);
        try {
            $macro = $this->macros[$name];
            $code = $macro->expand($arguments);
            $generation_time = microtime(true) - $start_time;

            $expansion = new MacroExpansion($code, [], []);
            self::$metrics['generation_time'] += $generation_time;

            if ($this->cache_enabled) {
                $this->expansion_cache[$cache_key] = $expansion;
            }

            return $expansion;
        } catch (\Exception $e) {
            self::$metrics['generation_time'] += (microtime(true) - $start_time);
            return new MacroExpansion([], [], [$e->getMessage()]);
        }
    }

    public function expand(string $macro_name, array $arguments): MacroExpansion
    {
        $system = self::get_instance();
        return $system->expand_macro($macro_name, $arguments);
    }
}

/**
 * Macro utilities
 */
class MacroUtils
{
    public static function register_builtin_macros(): void
    {
        $system = MacroSystem::get_instance();
        $system->register_macro('getter_setter', new GetterSetterMacro('getter_setter'));
        $system->register_macro('builder', new BuilderMacro('builder'));
        $system->register_macro('factory', new FactoryMacro('factory'));
        $system->register_macro('repository', new RepositoryMacro('repository'));
        $system->register_macro('validation', new ValidationMacro('validation'));
    }

    public static function generate(string $macro_name, array $arguments): MacroExpansion
    {
        $system = MacroSystem::get_instance();
        $code = $system->expand_macro($macro_name, $arguments);
        return $code;
    }

    public static function write_to_file(MacroExpansion $expansion, string $filename): void
    {
        if ($expansion->has_errors()) {
            throw new \InvalidArgumentException("Cannot write file due to errors: " . implode(', ', $expansion->errors));
        }
        $content = implode("\n\n", $expansion->code);
        file_put_contents($filename, "<?php\n\n" . $content);
    }

    /** Quick vector creation */
    public static function vec(array $items): Vector
    {
        $vector = new \Phoenix\Collections\Vector($items);
        return $vector;
    }

    /** Quick hashmap creation */
    public static function map(array $data): \Phoenix\Collections\OptimizedHashMap
    {
        $map = new \Phoenix\Collections\OptimizedHashMap();
        foreach ($data as $key => $value) {
            $map->set($key, $value);
        }
        return $map;
    }

    /** Quick hashset creation */
    public static function set(\Phoenix\Collections\OptimizedHashSet $set)