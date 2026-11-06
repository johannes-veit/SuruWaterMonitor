<?php
declare(strict_types=1);

/**
 * Water use from verified cumulative meter readings (m³). No paid SURU API.
 * Installation-to-first-reading is an unknown distribution: it is interpolated
 * ONLY as a labelled estimate when a long rolling window cuts that interval.
 */
final class SuruConsumption
{
    private const DAY = 86400;
    private const MIN_DAILY_GAP = 18 * 3600;
    private const MAX_DAILY_GAP = 30 * 3600;
    private const MAX_BOUNDARY_GAP = 3 * self::DAY;
    private const EPS = 0.0005; // m³

    /** @return array{24h:?float,7d:?float,30d:?float,365d:?float} Litres */
    public static function calculate(array $archivedRows, int $latestTime, float $latestValue, ?array $opening = null): array
    {
        return self::calculateDetailed($archivedRows, $latestTime, $latestValue, $opening)['values'];
    }

    /**
     * @param array<int,array<string,mixed>> $archivedRows Original AC_GetLoggedValues rows, any order.
     * @param array{timestamp:int,value:float}|null $opening Confirmed install value, not a daily archive record.
     * @return array{values:array<string,float|null>,basis:array<string,string>}
     */
    public static function calculateDetailed(array $archivedRows, int $latestTime, float $latestValue, ?array $opening = null): array
    {
        $values = ['24h' => null, '7d' => null, '30d' => null, '365d' => null];
        $basis = array_fill_keys(array_keys($values), 'keine ausreichenden Messwerte');
        if ($latestTime < 946684800 || !is_finite($latestValue) || $latestValue < 0) {
            return ['values' => $values, 'basis' => $basis];
        }

        // The latest accepted SURU counter is authoritative. Symcon may have
        // archived an initial 0.0 as a default variable value, not a reading.
        // The owner's REAL installation zero is supplied separately in $opening.
        $candidates = [$latestTime => $latestValue];
        $conflicts = [];
        $oldestAllowed = $latestTime - 365 * self::DAY - self::MAX_BOUNDARY_GAP;
        foreach ($archivedRows as $row) {
            if (!is_array($row) || !isset($row['TimeStamp'], $row['Value']) || !is_numeric($row['Value'])) continue;
            $time = (int) $row['TimeStamp'];
            $value = (float) $row['Value'];
            if ($time < $oldestAllowed || $time >= $latestTime || !is_finite($value) || $value < 0 ||
                $value > $latestValue + self::EPS || ($value < self::EPS && $latestValue > self::EPS)) continue;
            if (array_key_exists($time, $candidates) && abs($candidates[$time] - $value) > self::EPS) {
                $conflicts[$time] = true;
            } else {
                $candidates[$time] = $value;
            }
        }
        foreach ($conflicts as $time => $unused) unset($candidates[$time]);
        krsort($candidates, SORT_NUMERIC);

        // Validate backwards from the authoritative newest reading. An
        // intermediate outlier never masks later trustworthy daily samples.
        $verified = [];
        $newerTime = null;
        $newerValue = null;
        foreach ($candidates as $time => $value) {
            if ($newerTime !== null) {
                $gap = $newerTime - $time;
                $delta = $newerValue - $value;
                if ($gap <= 0 || $delta < -self::EPS ||
                    $delta > max(0.01, $gap * 10 / 3600)) continue;
            }
            $verified[$time] = $value;
            $newerTime = $time;
            $newerValue = $value;
        }
        ksort($verified, SORT_NUMERIC);
        $times = array_keys($verified);
        $total = count($times);
        $oldestTime = (int) $times[0];
        $oldestValue = $verified[$oldestTime];

        if ($total >= 2) {
            $previousTime = (int) $times[$total - 2];
            $gap = $latestTime - $previousTime;
            if ($gap >= self::MIN_DAILY_GAP && $gap <= self::MAX_DAILY_GAP) {
                $values['24h'] = self::litres($latestValue - $verified[$previousTime]);
                $basis['24h'] = sprintf('letzte echte Tagesdifferenz, Messabstand %.2f h', $gap / 3600);
            }

            foreach (['7d' => 7, '30d' => 30, '365d' => 365] as $key => $days) {
                $boundary = $latestTime - $days * self::DAY;
                if ($boundary <= $oldestTime) {
                    $values[$key] = self::litres($latestValue - $oldestValue);
                    $basis[$key] = 'vorhandene Historie (Teilsumme)';
                    continue;
                }
                // Locate the two real samples surrounding the window boundary.
                $leftTime = null;
                $rightTime = null;
                foreach ($times as $time) {
                    if ($time <= $boundary) {
                        $leftTime = (int) $time;
                    } else {
                        $rightTime = (int) $time;
                        break;
                    }
                }
                if ($leftTime !== null && $leftTime === $boundary) {
                    $boundaryValue = $verified[$leftTime];
                    $basis[$key] = 'Archiv, echte Zeitgrenze';
                } elseif ($leftTime !== null && $rightTime !== null &&
                    $rightTime - $leftTime <= self::MAX_BOUNDARY_GAP) {
                    $fraction = ($boundary - $leftTime) / ($rightTime - $leftTime);
                    $boundaryValue = $verified[$leftTime] +
                        ($verified[$rightTime] - $verified[$leftTime]) * $fraction;
                    $basis[$key] = 'Archiv, Tagesgrenze angenähert';
                } elseif ($rightTime !== null) {
                    // No distribution assumption for a gap longer than 3 days.
                    $boundaryValue = $verified[$rightTime];
                    $basis[$key] = 'Archiv, Teilsumme nach Datenlücke';
                } else {
                    continue;
                }
                $values[$key] = self::litres($latestValue - $boundaryValue);
            }
        }

        // The installation value is a trusted one-off anchor, never an added
        // flat offset. Use only for 30/365 days to keep the user-selected 7d
        // period as the observed history. When its date leaves a long window,
        // its unknown initial gap fades proportionately (estimated), reaching
        // zero contribution once the first genuine reading leaves the window.
        if ($opening !== null && isset($opening['timestamp'], $opening['value'])) {
            $installTime = (int) $opening['timestamp'];
            $installValue = (float) $opening['value'];
            $valid = $installTime >= 946684800 && $installTime < $oldestTime &&
                is_finite($installValue) && $installValue >= 0 &&
                $installValue <= $oldestValue + self::EPS;
            if ($valid) {
                foreach (['30d' => 30, '365d' => 365] as $key => $days) {
                    $boundary = $latestTime - $days * self::DAY;
                    if ($boundary <= $installTime) {
                        $values[$key] = self::litres($latestValue - $installValue);
                        $basis[$key] = 'Einbaustand, vollständiger Zeitraum seit Einbau';
                    } elseif ($boundary < $oldestTime && $total >= 2) {
                        // Need at least two genuine readings to establish when
                        // monitoring began. With only the latest counter, this
                        // gap could be an archive outage: do not extrapolate.
                        $fraction = ($boundary - $installTime) / ($oldestTime - $installTime);
                        $estimatedCounter = $installValue + ($oldestValue - $installValue) * $fraction;
                        $values[$key] = self::litres($latestValue - $estimatedCounter);
                        $basis[$key] = 'Schaetzung: Verbrauch vor erster Cloud-Messung anteilig';
                    }
                }
            }
        }
        return ['values' => $values, 'basis' => $basis];
    }

    private static function litres(float $deltaM3): float
    {
        return round(max(0.0, $deltaM3) * 1000, 3);
    }
}