<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Integrates the runnable business-case examples into the test suite.
 *
 * Each example must execute cleanly (exit code 0) and finish with its
 * completion marker so the documented scenarios stay in sync with the code.
 */
final class BusinessCasesTest extends TestCase
{
    private const EXAMPLES = [
        'OrderProcessing',
        'SubscriptionPayments',
        'AnalyticsAggregation',
    ];

    public function test_all_business_case_examples_are_present(): void
    {
        foreach (self::EXAMPLES as $name) {
            $path = $this->examplePath("{$name}.php");
            $this->assertFileExists($path, "Business case example {$name}.php is missing");
        }
    }

    public function test_order_processing_example_runs(): void
    {
        $this->runExample('OrderProcessing');
    }

    public function test_subscription_payments_example_runs(): void
    {
        $this->runExample('SubscriptionPayments');
    }

    public function test_analytics_aggregation_example_runs(): void
    {
        $this->runExample('AnalyticsAggregation');
    }

    public function test_subscription_example_reports_provider_fallback_and_saga_rollback(): void
    {
        $output = $this->runExample('SubscriptionPayments');

        $this->assertStringContainsString('fall back to playable demo order', $output);
        $this->assertStringContainsString('blocked by payment guard', $output);
        $this->assertStringContainsString('entitlements rolled back', $output);
    }

    public function test_analytics_example_reports_rollups_and_cohorts(): void
    {
        $output = $this->runExample('AnalyticsAggregation');

        $this->assertStringContainsString('Hits per route', $output);
        $this->assertStringContainsString('Visitors per day', $output);
        $this->assertStringContainsString('Parallel dashboard refresh', $output);
    }

    private function runExample(string $name): string
    {
        $php = PHP_BINARY;
        $path = $this->examplePath("{$name}.php");
        exec((string) escapeshellcmd("{$php} {$path}"), $output, $exitCode);

        $text = implode("\n", $output);

        $this->assertSame(0, $exitCode, "Example {$name} failed:\n{$text}");
        $this->assertStringContainsString('Done.', $text, "Example {$name} did not complete");

        return $text;
    }

    private function examplePath(string $file): string
    {
        return dirname(__DIR__, 2) . '/examples/business-cases/' . $file;
    }
}
