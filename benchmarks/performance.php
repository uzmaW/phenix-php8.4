<?php

declare(strict_types=1);

/**
 * Performance benchmark: Phoenix optimized collections vs plain PHP arrays.
 *
 * Run: php benchmarks/performance.php
 *
 * Plain PHP arrays are already highly optimized C-level hash maps, so the
 * optimized collections are not expected to beat them on raw speed. What they
 * add is an object API with bounds checks, set operations, batch helpers and
 * operation metrics. This benchmark measures the real overhead so regressions
 * are visible instead of assumed.
 */

require __DIR__ . '/../vendor/autoload.php';

use Phoenix\Collections\OptimizedHashMap;
use Phoenix\Collections\OptimizedHashSet;
use Phoenix\Collections\OptimizedVector;

const N = 100_000;

function bench(string $label, callable $plain, callable $optimized): void
{
    $t = microtime(true);
    $plainResult = $plain();
    $plainTime = microtime(true) - $t;

    $t = microtime(true);
    $optResult = $optimized();
    $optTime = microtime(true) - $t;

    $ratio = $optTime > 0 ? $plainTime / $optTime : INF;
    printf(
        "%-28s plain=%8.4fs  optimized=%8.4fs  ratio=%5.2fx  %s\n",
        $label,
        $plainTime,
        $optTime,
        $ratio,
        $plainResult === $optResult ? '[results match]' : '[RESULTS DIFFER]',
    );
}

echo "Phoenix collection benchmark (N = " . number_format(N) . ")\n";
echo str_repeat('-', 90) . "\n";

bench(
    'vector push',
    function (): int {
        $a = [];
        for ($i = 0; $i < N; $i++) {
            $a[] = "item_$i";
        }

        return count($a);
    },
    function (): int {
        $v = new OptimizedVector();
        for ($i = 0; $i < N; $i++) {
            $v->push("item_$i");
        }

        return $v->count();
    },
);

bench(
    'vector batch push',
    function (): int {
        $items = range(0, 999);
        $a = [];
        for ($i = 0; $i < N / 1000; $i++) {
            $a = array_merge($a, $items);
        }

        return count($a);
    },
    function (): int {
        $items = range(0, 999);
        $v = new OptimizedVector();
        for ($i = 0; $i < N / 1000; $i++) {
            $v->push_batch($items);
        }

        return $v->count();
    },
);

bench(
    'vector iterate',
    function (): int {
        $a = [];
        for ($i = 0; $i < N; $i++) {
            $a[] = $i;
        }
        $sum = 0;
        foreach ($a as $value) {
            $sum += $value;
        }

        return $sum;
    },
    function (): int {
        $v = new OptimizedVector();
        for ($i = 0; $i < N; $i++) {
            $v->push($i);
        }
        $sum = 0;
        foreach ($v as $value) {
            $sum += $value;
        }

        return $sum;
    },
);

bench(
    'vector contains (hit)',
    function (): bool {
        $a = range(0, N - 1);

        return in_array(N - 1, $a, true);
    },
    function (): bool {
        $v = new OptimizedVector(range(0, N - 1));

        return $v->contains(N - 1);
    },
);

bench(
    'hashmap set',
    function (): int {
        $m = [];
        for ($i = 0; $i < N; $i++) {
            $m["k$i"] = $i;
        }

        return count($m);
    },
    function (): int {
        $m = new OptimizedHashMap();
        for ($i = 0; $i < N; $i++) {
            $m->set("k$i", $i);
        }

        return $m->count();
    },
);

bench(
    'hashmap get',
    function (): int {
        $m = [];
        for ($i = 0; $i < N; $i++) {
            $m["k$i"] = $i;
        }
        $sum = 0;
        for ($i = 0; $i < N; $i++) {
            $sum += $m["k$i"];
        }

        return $sum;
    },
    function (): int {
        $m = new OptimizedHashMap();
        for ($i = 0; $i < N; $i++) {
            $m->set("k$i", $i);
        }
        $sum = 0;
        for ($i = 0; $i < N; $i++) {
            $sum += $m->get("k$i");
        }

        return $sum;
    },
);

bench(
    'hashmap has',
    function (): int {
        $m = [];
        for ($i = 0; $i < N; $i++) {
            $m["k$i"] = $i;
        }
        $hits = 0;
        for ($i = 0; $i < N; $i++) {
            if (array_key_exists("k$i", $m)) {
                $hits++;
            }
        }

        return $hits;
    },
    function (): int {
        $m = new OptimizedHashMap();
        for ($i = 0; $i < N; $i++) {
            $m->set("k$i", $i);
        }
        $hits = 0;
        for ($i = 0; $i < N; $i++) {
            if ($m->has("k$i")) {
                $hits++;
            }
        }

        return $hits;
    },
);

bench(
    'hashset add',
    function (): int {
        $s = [];
        for ($i = 0; $i < N; $i++) {
            $s["v$i"] = true;
        }

        return count($s);
    },
    function (): int {
        $s = new OptimizedHashSet();
        for ($i = 0; $i < N; $i++) {
            $s->add("v$i");
        }

        return $s->count();
    },
);

bench(
    'hashset contains',
    function (): int {
        $s = [];
        for ($i = 0; $i < N; $i++) {
            $s["v$i"] = true;
        }
        $hits = 0;
        for ($i = 0; $i < N; $i++) {
            if (isset($s["v$i"])) {
                $hits++;
            }
        }

        return $hits;
    },
    function (): int {
        $s = new OptimizedHashSet();
        for ($i = 0; $i < N; $i++) {
            $s->add("v$i");
        }
        $hits = 0;
        for ($i = 0; $i < N; $i++) {
            if ($s->contains("v$i")) {
                $hits++;
            }
        }

        return $hits;
    },
);

echo str_repeat('-', 90) . "\n";

$vector = new OptimizedVector(range(0, 999));
$map = new OptimizedHashMap();
$map->set_many(['a' => 1, 'b' => 2, 'c' => 3]);
$set = new OptimizedHashSet(['x', 'y', 'z', 'x']);

echo "Metrics snapshot:\n";
echo '  vector: ' . json_encode($vector->get_performance_metrics()) . "\n";
echo '  hashmap: ' . json_encode($map->get_performance_metrics()) . "\n";
echo '  hashset: ' . json_encode($set->get_performance_metrics()) . "\n";
echo "\nNote: ratio > 1.0 means the optimized collection was faster than the plain-array baseline.\n";
echo "Object-API collections carry method-dispatch overhead; the realistic target is ratio >= 0.4 (<= 2.5x overhead).\n";
