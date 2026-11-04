<?php
declare(strict_types=1);
namespace Phoenix\Validation;

use Phoenix\Core\Result;

/**
 * Fluent rule builder for a single field.
 *
 * Each rule-method appends a rule descriptor; validate() runs them all and
 * returns an array of error strings (empty = valid).
 */
final class RuleBuilder
{
    /** @var list<array<mixed>> */
    private array $rules = [];

    public function __construct(private readonly string $field) {}

    // -------------------------------------------------------------------------
    // Rule registration
    // -------------------------------------------------------------------------

    public function required(): self
    {
        $this->rules[] = ['required'];
        return $this;
    }

    public function email(): self
    {
        $this->rules[] = ['email'];
        return $this;
    }

    /** Minimum string length. */
    public function min(int $length): self
    {
        $this->rules[] = ['min', $length];
        return $this;
    }

    /** Maximum string length. */
    public function max(int $length): self
    {
        $this->rules[] = ['max', $length];
        return $this;
    }

    /** Minimum numeric value. */
    public function minValue(float $v): self
    {
        $this->rules[] = ['min_value', $v];
        return $this;
    }

    /** Maximum numeric value. */
    public function maxValue(float $v): self
    {
        $this->rules[] = ['max_value', $v];
        return $this;
    }

    public function regex(string $pattern): self
    {
        $this->rules[] = ['regex', $pattern];
        return $this;
    }

    /** Value must be in the given list (strict comparison). */
    public function in(array $values): self
    {
        $this->rules[] = ['in', $values];
        return $this;
    }

    /** Value must not be empty (empty() === false). */
    public function notEmpty(): self
    {
        $this->rules[] = ['not_empty'];
        return $this;
    }

    public function numeric(): self
    {
        $this->rules[] = ['numeric'];
        return $this;
    }

    /**
     * Arbitrary custom rule.
     *
     * @param callable(mixed): bool $fn     Return true = valid.
     * @param string               $message Error message on failure.
     */
    public function custom(callable $fn, string $message): self
    {
        $this->rules[] = ['custom', $fn, $message];
        return $this;
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /** Run all registered rules against $value; returns list of error strings. */
    public function validate(mixed $value): array
    {
        $errors = [];
        foreach ($this->rules as $rule) {
            $error = $this->check($rule, $value);
            if ($error !== null) {
                $errors[] = $error;
            }
        }
        return $errors;
    }

    private function check(array $rule, mixed $value): ?string
    {
        return match ($rule[0]) {
            'required'  => ($value === null || $value === '')
                               ? "{$this->field} is required"
                               : null,
            'email'     => (!is_string($value) || !filter_var($value, FILTER_VALIDATE_EMAIL))
                               ? "{$this->field} must be a valid email"
                               : null,
            'min'       => (strlen((string) $value) < $rule[1])
                               ? "{$this->field} must be at least {$rule[1]} characters"
                               : null,
            'max'       => (strlen((string) $value) > $rule[1])
                               ? "{$this->field} must be at most {$rule[1]} characters"
                               : null,
            'min_value' => ((float) $value < $rule[1])
                               ? "{$this->field} must be at least {$rule[1]}"
                               : null,
            'max_value' => ((float) $value > $rule[1])
                               ? "{$this->field} must be at most {$rule[1]}"
                               : null,
            'regex'     => (!preg_match($rule[1], (string) $value))
                               ? "{$this->field} format is invalid"
                               : null,
            'in'        => (!in_array($value, $rule[1], true))
                               ? "{$this->field} must be one of: " . implode(', ', $rule[1])
                               : null,
            'not_empty' => (empty($value))
                               ? "{$this->field} cannot be empty"
                               : null,
            'numeric'   => (!is_numeric($value))
                               ? "{$this->field} must be numeric"
                               : null,
            'custom'    => (!($rule[1])($value))
                               ? $rule[2]
                               : null,
            default     => null,
        };
    }
}

// ---------------------------------------------------------------------------

/**
 * Multi-field Validator.
 *
 * Fluent usage:
 *   $v = new Validator();
 *   $v->field('email')->required()->email();
 *   $v->field('age')->required()->numeric()->minValue(18);
 *   $result = $v->validate($_POST);
 *
 * One-shot usage:
 *   $result = Validator::make($_POST, [
 *       'email' => 'required|email',
 *       'name'  => 'required|min:2|max:100',
 *   ]);
 */
final class Validator
{
    /** @var array<string, RuleBuilder> */
    private array $builders = [];

    /**
     * Return (and register) a RuleBuilder for the named field.
     * Calling twice for the same field replaces the previous builder.
     */
    public function field(string $name): RuleBuilder
    {
        return $this->builders[$name] = new RuleBuilder($name);
    }

    /**
     * Validate $data against all registered field rules.
     *
     * @return Result Ok(value is array<string,mixed> $data) or Err(value is array<array-key,array<int,string>> field errors)
     */
    public function validate(array $data): Result
    {
        $errors = [];

        foreach ($this->builders as $field => $builder) {
            $value       = $data[$field] ?? null;
            $fieldErrors = $builder->validate($value);
            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
            }
        }

        return empty($errors) ? Result::ok($data) : Result::err($errors);
    }

    // -------------------------------------------------------------------------
    // Static one-shot factory
    // -------------------------------------------------------------------------

    /**
     * Validate $data using a Laravel-style rule string map.
     *
     * Supported rule tokens: required, email, numeric, min:N, max:N
     *
     * Example:
     *   Validator::make($data, ['email' => 'required|email', 'age' => 'required|numeric|min:1']);
     */
    public static function make(array $data, array $rules): Result
    {
        $v = new self();

        foreach ($rules as $field => $ruleString) {
            $builder = $v->field((string) $field);

            foreach (explode('|', (string) $ruleString) as $token) {
                $token = trim($token);

                if (str_starts_with($token, 'min:')) {
                    $builder->min((int) substr($token, 4));
                    continue;
                }
                if (str_starts_with($token, 'max:')) {
                    $builder->max((int) substr($token, 4));
                    continue;
                }
                if (str_starts_with($token, 'min_value:')) {
                    $builder->minValue((float) substr($token, 10));
                    continue;
                }
                if (str_starts_with($token, 'max_value:')) {
                    $builder->maxValue((float) substr($token, 10));
                    continue;
                }

                match ($token) {
                    'required' => $builder->required(),
                    'email'    => $builder->email(),
                    'numeric'  => $builder->numeric(),
                    'not_empty' => $builder->notEmpty(),
                    default    => null,
                };
            }
        }

        return $v->validate($data);
    }
}
