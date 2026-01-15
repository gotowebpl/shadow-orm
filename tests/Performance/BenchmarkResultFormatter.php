<?php

declare(strict_types=1);

namespace ShadowORM\Tests\Performance;

final class BenchmarkResultFormatter
{
    /**
     * @param array<string, array{time: float, memory: int, count: int}> $results
     */
    public static function format(array $results): string
    {
        $output = "\n" . str_repeat('=', 70) . "\n";
        $output .= sprintf("%-35s %12s %12s %10s\n", 'Benchmark', 'Time (ms)', 'Memory (KB)', 'Ops/sec');
        $output .= str_repeat('-', 70) . "\n";

        foreach ($results as $name => $data) {
            $timeMs = $data['time'] * 1000;
            $memoryKb = $data['memory'] / 1024;
            $opsPerSec = $data['count'] / $data['time'];

            $output .= sprintf(
                "%-35s %12.2f %12.2f %10.0f\n",
                $name,
                $timeMs,
                $memoryKb,
                $opsPerSec
            );
        }

        $output .= str_repeat('=', 70) . "\n";

        return $output;
    }

    public static function formatSingle(string $name, float $time, int $memory, int $count): string
    {
        $timeMs = $time * 1000;
        $memoryKb = $memory / 1024;
        $opsPerSec = $count / $time;
        $avgTimeUs = ($time / $count) * 1_000_000;

        return sprintf(
            "[%s] %d ops in %.2f ms (%.0f ops/sec, %.2f µs/op, %.2f KB peak)",
            $name,
            $count,
            $timeMs,
            $opsPerSec,
            $avgTimeUs,
            $memoryKb
        );
    }

    /**
     * @return array{time: float, memory: int}
     */
    public static function measure(callable $callback): array
    {
        gc_collect_cycles();
        $memoryBefore = memory_get_usage(true);
        $start = hrtime(true);

        $callback();

        $time = (hrtime(true) - $start) / 1_000_000_000;
        $memory = memory_get_usage(true) - $memoryBefore;

        return [
            'time' => $time,
            'memory' => max(0, $memory),
        ];
    }
}
