<?php
/** Pure validation rules. All readings are cumulative m³, supplied with original timestamps. */
final class SuruMeter
{
    public static function parseReading(array $response): array
    {
        $src=isset($response['reading']) && is_array($response['reading']) ? $response['reading'] : $response;
        $value=$src['reading']??null;
        if (!is_numeric($value)) throw new InvalidArgumentException('Kein numerischer Zählerstand');
        $value=(float)$value;
        if (!is_finite($value)||$value<0) throw new InvalidArgumentException('Unplausibler Zählerstand');
        $raw=$src['measuredAt']??null;
        if (!is_string($raw)||$raw==='') throw new InvalidArgumentException('Messzeitpunkt fehlt');
        try { $date=new DateTimeImmutable($raw); } catch(Throwable $e) { throw new InvalidArgumentException('Messzeitpunkt ungültig'); }
        $ts=$date->getTimestamp();
        if ($ts<946684800 || $ts>time()+7200) throw new InvalidArgumentException('Messzeitpunkt außerhalb des zulässigen Bereichs');
        return ['value'=>$value,'timestamp'=>$ts];
    }

    /** @return array{kind:string,delta_l:float} */
    public static function evaluate(float $value,int $timestamp,?float $oldValue,int $oldTime):array
    {
        if ($oldValue===null || $oldTime<=0) return ['kind'=>'initial','delta_l'=>0.0];
        if ($timestamp<$oldTime) return ['kind'=>'stale','delta_l'=>0.0];
        if ($timestamp===$oldTime) return abs($value-$oldValue)<0.0005 ? ['kind'=>'duplicate','delta_l'=>0.0] : ['kind'=>'conflict','delta_l'=>0.0];
        $delta=$value-$oldValue;
        if ($delta < -0.0005) return ['kind'=>'decrease','delta_l'=>0.0];
        if ($delta < 0) $delta=0;
        // Conservative technical ceiling: Q3=4 m³/h, allow 10 m³/h average.
        // Not an absolute meter limit; unexpected values are held for inspection.
        if ($delta > max(0.01,($timestamp-$oldTime)*10/3600)) return ['kind'=>'jump','delta_l'=>0.0];
        return ['kind'=>'new','delta_l'=>round($delta*1000,3)];
    }
}
