<?php

declare(strict_types=1);

/**
 * Business scenario: admin analytics aggregation pipeline.
 *
 * Behind the admin analytics dashboard (DashboardController::analytics)
 * sits this ingestion/aggregation pipeline. It shows the optimized
 * collections and async utilities working on a realistic metrics feed:
 *
 *  - OptimizedVector     raw event feed (push_batch, filter, map)
 *  - OptimizedHashMap    route roll-ups (hits per controller/route)
 *  - OptimizedHashSet    unique visitors and cohort set algebra
 *  - AsyncResult         parallel refresh of chart datasets (defer + all)
 *  - RateLimiter         dashboard API throttling per admin session
 *
 * Run: php examples/business-cases/AnalyticsAggregation.php
 */

require __DIR__ . '/../../vendor/autoload.php';

use Phoenix\Collections\OptimizedHashMap;
use Phoenix\Collections\OptimizedHashSet;
use Phoenix\Collections\OptimizedVector;
use Phoenix\Concurrency\AsyncResult;
use Phoenix\RateLimit\RateLimiter;

// ---------------------------------------------------------------------------
// 1. Ingest a raw admin-event feed
// ---------------------------------------------------------------------------

$rawEvents = [
    ['route' => '/admin/users', 'admin' => 'uzma', 'day' => 'mon', 'ms' => 12],
    ['route' => '/admin/payments', 'admin' => 'sam', 'day' => 'mon', 'ms' => 48],
    ['route' => '/admin/users', 'admin' => 'uzma', 'day' => 'tue', 'ms' => 9],
    ['route' => '/admin/analytics', 'admin' => 'kai', 'day' => 'tue', 'ms' => 96],
    ['route' => '/admin/payments', 'admin' => 'uzma', 'day' => 'tue', 'ms' => 41],
    ['route' => '/admin/users', 'admin' => 'kai', 'day' => 'wed', 'ms' => 11],
    ['route' => '/admin/settings', 'admin' => 'sam', 'day' => 'wed', 'ms' => 33],
    ['route' => '/admin/payments', 'admin' => 'kai', 'day' => 'wed', 'ms' => 52],
];

$feed = new OptimizedVector();
$feed->push_batch($rawEvents);

echo "Event feed ingested: {$feed->count()} events\n";

// ---------------------------------------------------------------------------
// 2. Roll-up: hits and latency per route with OptimizedHashMap
// ---------------------------------------------------------------------------

$rollup = new OptimizedHashMap();
foreach ($feed as $event) {
    $route = $event['route'];
    if ($rollup->has($route)) {
        $bucket = $rollup->get($route);
        $bucket['hits'] += 1;
        $bucket['ms_total'] += $event['ms'];
        $rollup->set($route, $bucket);
    } else {
        $rollup->set($route, ['hits' => 1, 'ms_total' => $event['ms']]);
    }
}

echo "\nHits per route:\n";
foreach ($rollup->keys() as $route) {
    $b = $rollup->get($route);
    printf("  %-20s %d hits, avg %.1f ms\n", $route, $b['hits'], $b['ms_total'] / $b['hits']);
}

$slow = $feed->filter(fn (array $e): bool => $e['ms'] > 50);
printf(
    "  slow requests (>50 ms): %s (%d of %d)\n",
    implode(', ', $slow->map(fn (array $e): string => $e['route'])->to_array()),
    $slow->count(),
    $feed->count(),
);

// ---------------------------------------------------------------------------
// 3. Unique visitors and cohort algebra with OptimizedHashSet
// ---------------------------------------------------------------------------

$visitorsPerDay = new OptimizedHashMap();
foreach ($feed as $event) {
    $set = $visitorsPerDay->has($event['day']) ? $visitorsPerDay->get($event['day']) : new OptimizedHashSet();
    $set->add($event['admin']);
    $visitorsPerDay->set($event['day'], $set);
}

echo "\nVisitors per day:\n";
foreach ($visitorsPerDay->keys() as $day) {
    /** @var OptimizedHashSet $set */
    $set = $visitorsPerDay->get($day);
    printf("  %s: %d unique (%s)\n", $day, $set->count(), implode(', ', $set->to_array()));
}

/** @var OptimizedHashSet $mon */
$mon = $visitorsPerDay->get('mon');
/** @var OptimizedHashSet $tue */
$tue = $visitorsPerDay->get('tue');
/** @var OptimizedHashSet $wed */
$wed = $visitorsPerDay->get('wed');

$allWeek = $mon->union($tue)->union($wed);
$loyal = $tue->intersection($wed);
$mondayOnly = $mon->difference($tue->union($wed));

printf(
    "  whole week: %d unique admins | loyal (tue ∩ wed): %s | monday-only: %s\n",
    $allWeek->count(),
    implode(', ', $loyal->to_array()) ?: '(none)',
    implode(', ', $mondayOnly->to_array()) ?: '(none)',
);

// ---------------------------------------------------------------------------
// 4. Parallel dashboard refresh with AsyncResult
// ---------------------------------------------------------------------------

echo "\nParallel dashboard refresh:\n";

$hitsFuture = AsyncResult::defer(static function () use ($feed): int {
    // Simulated aggregation over the DB (these run concurrently in one tick)
    usleep(20000);

    return $feed->count();
});
$avgFuture = AsyncResult::defer(static function () use ($feed): float {
    usleep(15000);
    $sum = 0.0;
    foreach ($feed as $e) {
        $sum += $e['ms'];
    }

    return $feed->count() ? $sum / $feed->count() : 0.0;
});

$dashboard = AsyncResult::all([$hitsFuture, $avgFuture])->get();
printf(
    "  dataset 1 (total hits): %d | dataset 2 (avg latency): %.1f ms\n",
    $dashboard[0],
    $dashboard[1],
);
echo '  refresh status: resolved=' . ($hitsFuture->is_resolved() ? 'yes' : 'no') . ', no rejections=' . (!$avgFuture->is_rejected() ? 'true' : 'false') . "\n";

// ---------------------------------------------------------------------------
// 5. Throttle the dashboard API per admin session
// ---------------------------------------------------------------------------

echo "\nDashboard API throttle:\n";

$limiter = new RateLimiter(null, true);
$burst = ['uzma' => 0, 'sam' => 0];
for ($i = 0; $i < 25; $i++) {
    $who = $i % 2 === 0 ? 'uzma' : 'sam';
    if ($limiter->attempt("analytics:{$who}", 12, 60)) {
        $burst[$who]++;
    }
}
printf(
    "  25 requests split across two admin sessions (limit 12/min): uzma=%d allowed, sam=%d allowed\n",
    $burst['uzma'],
    $burst['sam'],
);

echo "\nDone.\n";
