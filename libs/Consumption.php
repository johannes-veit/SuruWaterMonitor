<?php
declare(strict_types=1);

/**
 * Daily consumption from cumulative SURU meter readings in the Symcon archive.
 * Sum observed daily intervals only. Never extrapolate missing days.
 */
final class SuruConsumption
{
    private const DAY = 86400;
    private const MIN_DAILY_GAP = 18 * 3600;
    private const MAX_DAILY_GAP = 30 * 3600;
    private const EPS = 0.0005;

    /**
     * @param array<int,array<string,mixed>> $archivedRows Raw AC_GetLoggedValues (any order).
     * @param array{timestamp:int,value:float}|null $opening Verified installation reference, not an archive record.
     * @return array{24h:?float,7d:?float,30d:?float,365d:?float} Litres; null when no valid daily data.
     */
    public static function calculate(array $archivedRows, int $latestTime, float $latestValue, ?array $opening = null): array
    {
        $result = ['24h' => null, '7d' => null, '30d' => null, '365d' => null];
        if ($latestTime <= 0 || !is_finite($latestValue) || $latestValue < 0) {
            return $result;
        }
        // The last accepted cloud measurement is authoritative. A logged 0.0
        // from archive activation is not necessarily a meter measurement.
        $samples = [$latestTime => $latestValue];
        $conflicts = [];
        $oldest = $latestTime - 365 * self::DAY - self::MAX_DAILY_GAP;
        foreach ($archivedRows as $row) {
            if (!isset($row['TimeStamp'], $row['Value']) || !is_numeric($row['Value'])) {
                continue;
            }
            $time = (int) $row['TimeStamp'];
            $value = (float) $row['Value'];
            if ($time < $oldest || $time >= $latestTime || !is_finite($value) ||
                $value < 0 || $value > $latestValue + self::EPS) {
                continue;
            }
            if (isset($samples[$time]) && abs($samples[$time] - $value) > self::EPS) {
                $conflicts[$time] = true;
            } else {
                $samples[$time] = $value;
            }
        }
        foreach ($conflicts as $time => $unused) {
            unset($samples[$time]);
        }
        ksort($samples, SORT_NUMERIC);

        // A cumulative counter cannot fall to zero between genuine readings.
        // Ignore inconsistent intermediate archive rows; preserve raw archive.
        $verified = [];
        $maxEarlier = null;
        foreach ($samples as $time => $value) {
            if ($maxEarlier !== null && $value < $maxEarlier - self::EPS) {
                continue;
            }
            $verified[$time] = $value;
            $maxEarlier = $maxEarlier === null ? $value : max($maxEarlier, $value);
        }

        $sums = ['7d' => 0.0, '30d' => 0.0, '365d' => 0.0];
        $counts = ['7d' => 0, '30d' => 0, '365d' => 0];
        $prevTime = null;
        $prevValue = null;
        foreach ($verified as $time => $value) {
            if ($prevTime !== null) {
                $gap = $time - $prevTime;
                $delta = $value - $prevValue;
                if ($gap >= self::MIN_DAILY_GAP && $gap <= self::MAX_DAILY_GAP &&
                    $delta >= -self::EPS && $delta <= max(0.01, $gap * 10 / 3600)) {
                    $litres = max(0.0, $delta * 1000);
                    if ($time === $latestTime) {
                        $result['24h'] = round($litres, 3);
                    }
                    foreach (['7d' => 7, '30d' => 30, '365d' => 365] as $key => $days) {
                        // Count intervals by the timestamp of their newer sample.
                        if ($time > $latestTime - $days * self::DAY) {
                            $sums[$key] += $litres;
                            $counts[$key]++;
                        }
                    }
                }
            }
            $prevTime = $time;
            $prevValue = $value;
        }
        foreach ($sums as $key => $sum) {
            if ($counts[$key] > 0) {
                $result[$key] = round($sum, 3);
            }
        }
        // The installation reference can fill long windows that include the
        // installation date, even when monitoring started later. Replace the
        // observed-day subtotal; never add it twice. 24h is never overridden.
        if ($opening !== null && isset($opening['timestamp'], $opening['value'])) {
            $openingTime = (int) $opening['timestamp'];
            $openingValue = (float) $opening['value'];
            $oldestRecorded = $verified === [] ? $latestTime : (int) array_key_first($verified);
            $valid = $openingTime >= 946684800 && $openingTime < $latestTime &&
                $openingTime <= $oldestRecorded && is_finite($openingValue) &&
                $openingValue >= 0 && $openingValue <= $latestValue + self::EPS;
            if ($valid) {
                foreach (['7d' => 7, '30d' => 30, '365d' => 365] as $key => $days) {
                    if ($openingTime >= $latestTime - $days * self::DAY) {
                        $result[$key] = round(max(0.0, ($latestValue - $openingValue) * 1000), 3);
                    }
                }
            }
        }
        return $result;
    }
}
