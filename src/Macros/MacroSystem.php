<?php
declare(strict_types=1);

namespace Phoenix\Macros;

use Phoenix\Collections\OptimizedHashMap;
use Phoenix\Collections\OptimizedHashSet;
use Phoenix\Collections\OptimizedVector;

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
    public function __construct(
        protected string $name,
        protected array $arguments = [],
    ) {}

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_arguments(): array
    {
        return $this->arguments;
    }
}

/**
 * Macro expansion result
 */
class MacroExpansion
{
    public function __construct(
        public readonly array $code,
        public readonly array $warnings = [],
        public readonly array $errors = [],
    ) {}

    public function has_errors(): bool
    {
        return $this->errors !== [];
    }

    public function has_warnings(): bool
    {
        return $this->warnings !== [];
    }

    /** Generated code joined for inspection or writing */
    public function to_code(): string
    {
        return implode("\n\n", $this->code);
    }
}

/**
 * Macro system manager with expansion caching
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

    public static function get_metrics(): array
    {
        return self::$metrics;
    }

    public static function reset_metrics(): void
    {
        self::$metrics = [
            'macros_registered' => 0,
            'expansions_performed' => 0,
            'cache_hits' => 0,
            'generation_time' => 0.0,
        ];
    }

    public static function get_instance(): MacroSystem
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /** Reset the singleton (test support) */
    public static function reset_instance(): void
    {
        self::$instance = null;
    }

    public function register_macro(string $name, Macro $macro): void
    {
        self::$metrics['macros_registered']++;
        $this->macros[$name] = $macro;
        $this->expansion_cache = [];
    }

    public function unregister_macro(string $name): bool
    {
        if (!isset($this->macros[$name])) {
            return false;
        }

        unset($this->macros[$name]);
        $this->expansion_cache = [];

        return true;
    }

    public function has_macro(string $name): bool
    {
        return isset($this->macros[$name]);
    }

    /** @return string[] */
    public function get_registered_macros(): array
    {
        return array_keys($this->macros);
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
            $code = $this->macros[$name]->expand($arguments);
            $expansion = new MacroExpansion($code);
            if ($this->cache_enabled) {
                $this->expansion_cache[$cache_key] = $expansion;
            }

            return $expansion;
        } catch (\Throwable $e) {
            return new MacroExpansion([], [], [$e->getMessage()]);
        } finally {
            self::$metrics['generation_time'] += microtime(true) - $start_time;
        }
    }

    public function expand(string $macro_name, array $arguments): MacroExpansion
    {
        return self::get_instance()->expand_macro($macro_name, $arguments);
    }

    /** Enable or disable expansion caching */
    public function set_cache_enabled(bool $enabled): void
    {
        $this->cache_enabled = $enabled;
        if (!$enabled) {
            $this->expansion_cache = [];
        }
    }

    public function is_cache_enabled(): bool
    {
        return $this->cache_enabled;
    }

    /** Clear expansion cache */
    public function clear_cache(): void
    {
        $this->expansion_cache = [];
    }
}

/**
 * Generate getter/setter methods
 */
class GetterSetterMacro extends BaseMacro
{
    public function expand(array $arguments): array
    {
        [$properties, $class_name] = $arguments + [[], ''];

        $code = [];

        foreach ($properties as $property) {
            $code[] = sprintf(
                "public function get_%s(): mixed {\n    return \$this->%s;\n}",
                $property,
                $property,
            );
            $code[] = sprintf(
                "public function set_%s(\$value): self {\n    \$this->%s = \$value;\n    return \$this;\n}",
                $property,
                $property,
            );
        }

        if ($class_name !== '') {
            array_unshift($code, "// getters/setters for {$class_name}");
        }

        return $code;
    }
}

/**
 * Generate builder pattern
 */
class BuilderMacro extends BaseMacro
{
    public function expand(array $arguments): array
    {
        [$class_name, $properties] = $arguments + ['', []];

        $code = [];
        $code[] = sprintf('class %sBuilder {', $class_name);
        $code[] = '    private array $properties = [];';
        $code[] = '';
        $code[] = '    public function __construct() {}';
        $code[] = '';

        foreach ($properties as $property) {
            $code[] = sprintf(
                "    public function %s(\$value): self {\n        \$this->properties['%s'] = \$value;\n        return \$this;\n    }",
                $property,
                $property,
            );
        }

        $code[] = '';
        $code[] = "    public function build(): {$class_name} {";
        // Named argument unpacking: order independent (PHP 8.1+)
        $code[] = '        return new ' . $class_name . '(...$this->properties);';
        $code[] = '    }';
        $code[] = '}';

        return $code;
    }
}

/**
 * Generate named factory methods
 */
class FactoryMacro extends BaseMacro
{
    public function expand(array $arguments): array
    {
        [$class_name, $factories] = $arguments + ['', []];

        $code = [];

        foreach ($factories as $factory_name => $factory_config) {
            $params = $factory_config['params'] ?? [];
            $param_list = implode(', ', array_map(
                static fn ($param): string => '$' . $param,
                $params,
            ));
            $arg_list = implode(', ', array_map(
                static fn ($param): string => '$' . $param,
                $params,
            ));

            $code[] = sprintf(
                "public static function %s(%s): %s {\n    return new %s(%s);\n}",
                $factory_name,
                $param_list,
                $class_name,
                $class_name,
                $arg_list,
            );
        }

        return $code;
    }
}

/**
 * Generate CRUD repository
 */
class RepositoryMacro extends BaseMacro
{
    public function expand(array $arguments): array
    {
        [$entity_class, $repository_interface] = $arguments + ['', ''];

        $interface_clause = $repository_interface !== '' ? " implements {$repository_interface}" : '';

        $code = [];
        $code[] = "class {$entity_class}Repository{$interface_clause} {";
        $code[] = '    private array $storage = [];';
        $code[] = '    private int $next_id = 1;';
        $code[] = '';
        $code[] = '    public function find($id): ?object {';
        $code[] = '        return $this->storage[$id] ?? null;';
        $code[] = '    }';
        $code[] = '';
        $code[] = '    public function find_all(): array {';
        $code[] = '        return array_values($this->storage);';
        $code[] = '    }';
        $code[] = '';
        $code[] = '    public function save($entity): object {';
        $code[] = '        if (!$entity->get_id()) {';
        $code[] = '            $entity->set_id($this->next_id++);';
        $code[] = '        }';
        $code[] = '        $this->storage[$entity->get_id()] = $entity;';
        $code[] = '        return $entity;';
        $code[] = '    }';
        $code[] = '';
        $code[] = '    public function delete($id): bool {';
        $code[] = '        if (isset($this->storage[$id])) {';
        $code[] = '            unset($this->storage[$id]);';
        $code[] = '            return true;';
        $code[] = '        }';
        $code[] = '        return false;';
        $code[] = '    }';
        $code[] = '}';

        return $code;
    }
}

/**
 * Generate validation rules
 */
class ValidationMacro extends BaseMacro
{
    public function expand(array $arguments): array
    {
        [$class_name, $rules] = $arguments + ['', []];

        $code = [];
        $code[] = "class {$class_name}Validator {";
        $code[] = '    public static function validate($data): bool {';

        foreach ($rules as $field => $field_rules) {
            $code[] = sprintf("        if (!isset(\$data['%s'])) {", $field);
            $code[] = sprintf("            throw new \\InvalidArgumentException('%s is required');", $field);
            $code[] = '        }';

            // Accept both ['required', 'email'] and ['min_length' => 8]
            $normalized = is_array($field_rules)
                ? (array_is_list($field_rules)
                    ? array_fill_keys($field_rules, null)
                    : $field_rules)
                : [$field_rules => null];

            foreach ($normalized as $rule => $params) {
                $arg_list = '';
                if (is_string($params)) {
                    $arg_list = ", '" . $params . "'";
                } elseif (is_array($params)) {
                    $arg_list = ", '" . implode("', '", $params) . "'";
                } elseif (is_int($params)) {
                    $arg_list = ', ' . $params;
                }

                $code[] = sprintf(
                    "        if (!self::%s(\$data['%s']%s)) {",
                    $rule,
                    $field,
                    $arg_list,
                );
                $code[] = sprintf(
                    "            throw new \\InvalidArgumentException('%s validation failed: %s');",
                    $field,
                    $rule,
                );
                $code[] = '        }';
            }
        }

        $code[] = '        return true;';
        $code[] = '    }';
        $code[] = '';
        $code[] = '    private static function required($value): bool {';
        $code[] = '        return $value !== null && $value !== "";';
        $code[] = '    }';
        $code[] = '';
        $code[] = '    private static function email($value): bool {';
        $code[] = '        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;';
        $code[] = '    }';
        $code[] = '';
        $code[] = '    private static function min_length($value, $length): bool {';
        $code[] = '        return strlen((string) $value) >= $length;';
        $code[] = '    }';
        $code[] = '';
        $code[] = '    private static function max_length($value, $length): bool {';
        $code[] = '        return strlen((string) $value) <= $length;';
        $code[] = '    }';
        $code[] = '}';

        return $code;
    }
}

/**
 * Macro utilities
 */
class MacroUtils
{
    /** Register built-in macros */
    public static function register_builtin_macros(): void
    {
        $system = MacroSystem::get_instance();
        $system->register_macro('getter_setter', new GetterSetterMacro('getter_setter'));
        $system->register_macro('builder', new BuilderMacro('builder'));
        $system->register_macro('factory', new FactoryMacro('factory'));
        $system->register_macro('repository', new RepositoryMacro('repository'));
        $system->register_macro('validation', new ValidationMacro('validation'));
    }

    /** Generate code from macro */
    public static function generate(string $macro_name, array $arguments): MacroExpansion
    {
        return MacroSystem::get_instance()->expand_macro($macro_name, $arguments);
    }

    /** Write generated code to file */
    public static function write_to_file(MacroExpansion $expansion, string $filename): void
    {
        if ($expansion->has_errors()) {
            throw new \InvalidArgumentException('Cannot write file due to errors: ' . implode(', ', $expansion->errors));
        }

        file_put_contents($filename, "<?php\n\n" . $expansion->to_code() . "\n");
    }
}

/**
 * Convenient helper functions
 */
class Helpers
{
    /** Quick vector creation */
    public static function vec(array $items): OptimizedVector
    {
        return new OptimizedVector($items);
    }

    /** Quick hashmap creation */
    public static function map(array $data): OptimizedHashMap
    {
        return (new OptimizedHashMap())->set_many($data);
    }

    /** Quick hashset creation */
    public static function set(array $items): OptimizedHashSet
    {
        return new OptimizedHashSet($items);
    }

    /** Generate unique IDs */
    public static function generate_id(): string
    {
        return uniqid('id_', true);
    }

    /** Type-safe casting */
    public static function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool' => (bool) $value,
            'array' => (array) $value,
            default => $value,
        };
    }
}

// Initialize built-in macros when the file is first loaded
MacroUtils::register_builtin_macros();
