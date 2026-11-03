<?php
declare(strict_types=1);

/** Read-only rolling consumption calculations from archived cumulative meter values. */
final class SuruConsumption
{
    private const DAY = 86400;
    private const BOUNDARY_TOLERANCE = 18 * 3600; // daily samples: label estimates honestly

    /**
     * @param array<int,array{TimeStamp:int,Value:float|int}> $archivedRows Raw AC_GetLoggedValues records (newest first).
     * @return array<string,float|null> Liters or null when historic coverage is insufficient.
     */
    public static function calculate(array $archivedRows, int $latestTime, float $latestValue): array
    {
        $result = ['24h' => null, '7d' => null, '30d' => null, '365d' => null];
        if ($latestTime <= 0 || !is_finite($latestValue) || $latestValue < 0) {
            return $result;
        }
        $history = [];
        foreach ($archivedRows as $row) {
            if (!isset($row['TimeStamp'], $row['Value']) || !is_numeric($row['Value'])) {
                continue;
            }
            $time = (int) $row['TimeStamp'];
            $value = (float) $row['Value'];
            if ($time <= 0 || $time >= $latestTime || !is_finite($value) || $value < 0 || $value > $latestValue + 0.0005) {
                continue;
            }
            // Keyed by timestamp to remove duplicate archive entries safely.
            $history[$time] = $value;
        }
        krsort($history, SORT_NUMERIC);
        if ($history === []) {
            return $result;
        }
        $times = array_keys($history);
        $previousTime = (int) $times[0];
        $hoursSincePrevious = ($latestTime - $previousTime) / 3600;
        // A true ~24h value needs two daily samples. Irregular gaps are not called 24h.
        if ($hoursSincePrevious >= 18 && $hoursSincePrevious <= 30) {
            $result['24h'] = max(0.0, round(($latestValue - $history[$previousTime]) * 1000, 3));
        }
        foreach (['7d' => 7, '30d' => 30, '365d' => 365] as $key => $days) {
            $target = $latestTime - $days * self::DAY;
            $bestTime = null;
            $bestDistance = self::BOUNDARY_TOLERANCE + 1;
            foreach ($history as $time => $value) {
                $distance = abs($time - $target);
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestTime = $time;
                }
            }
            if ($bestTime !== null && $bestDistance <= self::BOUNDARY_TOLERANCE) {
                $result[$key] = max(0.0, round(($latestValue - $history[$bestTime]) * 1000, 3));
            }
        }
        return $result;
    }
}