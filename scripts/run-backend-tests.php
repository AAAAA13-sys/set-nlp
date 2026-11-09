<?php

declare(strict_types=1);

$rootDir = dirname(__DIR__);

if (is_file($rootDir . '/bootstrap/app.php')) {
    require_once $rootDir . '/bootstrap/app.php';
}

require_once $rootDir . '/tests/TestCase.php';
require_once $rootDir . '/tests/Feature/EvaluaTechBackendSuiteTest.php';

// Only run the CLI test harness when invoked directly as the main script
$invokedDirectly = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);

if ($invokedDirectly) {
    $filter = null;
    foreach (array_slice($argv ?? [], 1) as $arg) {
        if (str_starts_with($arg, '--filter=')) {
            $filter = substr($arg, strlen('--filter='));
        }
    }

    $testClass = \Tests\Feature\EvaluaTechBackendSuiteTest::class;
    $ref = new ReflectionClass($testClass);
    $methods = array_values(array_filter(
        $ref->getMethods(ReflectionMethod::IS_PUBLIC),
        static fn (ReflectionMethod $m): bool => str_starts_with($m->getName(), 'test_')
    ));

    echo "========================================================================\n";
    echo " EvaluaTech Backend PHPUnit Verification Suite (SQLite :memory: PDO)    \n";
    echo "========================================================================\n";

    $passed = 0;
    $failed = 0;
    $startTime = microtime(true);

    foreach ($methods as $method) {
        $name = $method->getName();
        if ($filter !== null && !str_contains($name, $filter)) {
            continue;
        }

        /** @var \Tests\Feature\EvaluaTechBackendSuiteTest $instance */
        $instance = $ref->newInstance();
        try {
            $instance->runSetUp();
            $method->invoke($instance);
            $instance->runTearDown();
            echo sprintf("  [PASS] %s::%s\n", $ref->getShortName(), $name);
            $passed++;
        } catch (\Throwable $e) {
            echo sprintf("  [FAIL] %s::%s -> %s\n%s\n", $ref->getShortName(), $name, $e->getMessage(), $e->getTraceAsString());
            $failed++;
        }
    }

    $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
    $assertions = \Tests\TestCase::getAssertionCount();

    echo "------------------------------------------------------------------------\n";
    echo sprintf(
        " Summary: %d passed, %d failed (%d total tests, %d assertions) in %s ms\n",
        $passed,
        $failed,
        $passed + $failed,
        $assertions,
        $elapsedMs
    );
    echo "========================================================================\n";

    exit($failed > 0 ? 1 : 0);
}
