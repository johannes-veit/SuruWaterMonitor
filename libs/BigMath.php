<?php
/** Self-contained 24-bit-limb Montgomery arithmetic for AWS Cognito SRP. */
final class SuruBigMath
{
    // The 15-bit fallback keeps every multiply/carry within signed 32-bit PHP ints.
    private const SHIFT = PHP_INT_SIZE >= 8 ? 24 : 15;
    private const BASE = 1 << self::SHIFT;
    private const MASK = self::BASE - 1;
    private array $n;
    private int $size;
    private int $nPrime;
    private array $r2;
    private array $oneMont;

    public function __construct(string $modHex)
    {
        $this->n = self::fromHex($modHex);
        if (($this->n[0] & 1) !== 1) {
            throw new InvalidArgumentException('Odd modulus required');
        }
        $this->size = count($this->n);
        $inv = 1;
        for ($i = 0; $i < 5; $i++) {
            $inv = ($inv * ((2 - (($this->n[0] * $inv) & self::MASK)) & self::MASK)) & self::MASK;
        }
        $this->nPrime = (-$inv) & self::MASK;
        $this->r2 = [1];
        for ($i = 0; $i < 2 * $this->size * self::SHIFT; $i++) {
            $this->r2 = $this->doubleMod($this->r2);
        }
        $this->oneMont = $this->montMul([1], $this->r2);
    }

    public static function fromHex(string $hex): array
    {
        $hex = ltrim(strtolower($hex), '0');
        if ($hex === '') return [0];
        if (!ctype_xdigit($hex)) throw new InvalidArgumentException('Invalid hex integer');
        $limbs=[]; $acc=0; $bits=0;
        for ($i=strlen($hex)-1; $i>=0; $i--) {
            $acc |= hexdec($hex[$i]) << $bits;
            $bits+=4;
            if ($bits>=self::SHIFT) {
                $limbs[]=$acc & self::MASK;
                $acc >>= self::SHIFT;
                $bits-=self::SHIFT;
            }
        }
        if ($bits>0) $limbs[]=$acc;
        return self::trim($limbs);
    }

    public static function toHex(array $a): string
    {
        $nibbles=[]; $acc=0; $bits=0;
        foreach (self::trim($a) as $limb) {
            $acc |= ($limb << $bits);
            $bits += self::SHIFT;
            while ($bits>=4) {
                $nibbles[]=dechex($acc & 15);
                $acc >>= 4;
                $bits -= 4;
            }
        }
        if ($bits>0) $nibbles[]=dechex($acc & 15);
        $hex=ltrim(implode('',array_reverse($nibbles)), '0');
        return $hex===''?'0':$hex;
    }

    public static function padHex(array $a): string
    {
        $hex = self::toHex($a);
        if (strlen($hex) % 2) $hex = '0' . $hex;
        elseif (str_contains('89abcdef', $hex[0])) $hex = '00' . $hex;
        return $hex;
    }

    private static function trim(array $a): array
    {
        while (count($a) > 1 && $a[count($a) - 1] === 0) array_pop($a);
        return $a;
    }

    public static function compare(array $a, array $b): int
    {
        $a = self::trim($a); $b = self::trim($b);
        if (count($a) !== count($b)) return count($a) <=> count($b);
        for ($i = count($a) - 1; $i >= 0; $i--) {
            if ($a[$i] !== $b[$i]) return $a[$i] <=> $b[$i];
        }
        return 0;
    }

    public static function add(array $a, array $b): array
    {
        $out = []; $carry = 0;
        $len = max(count($a), count($b));
        for ($i=0; $i<$len; $i++) {
            $v = ($a[$i] ?? 0) + ($b[$i] ?? 0) + $carry;
            $out[] = $v & self::MASK; $carry = $v >> self::SHIFT;
        }
        if ($carry) $out[] = $carry;
        return self::trim($out);
    }

    public static function sub(array $a, array $b): array
    {
        if (self::compare($a, $b) < 0) throw new InvalidArgumentException('Negative subtraction');
        $out=[];$borrow=0;
        for ($i=0,$len=count($a);$i<$len;$i++) {
            $v = $a[$i] - ($b[$i] ?? 0) - $borrow;
            if ($v < 0) { $v += self::BASE; $borrow=1; } else $borrow=0;
            $out[]=$v;
        }
        return self::trim($out);
    }

    public static function mul(array $a, array $b): array
    {
        $out = array_fill(0, count($a)+count($b)+1, 0);
        for ($i=0;$i<count($a);$i++) {
            $carry=0;
            for ($j=0;$j<count($b);$j++) {
                $v = $out[$i+$j] + $a[$i]*$b[$j] + $carry;
                $out[$i+$j] = $v & self::MASK;
                $carry = $v >> self::SHIFT;
            }
            $k=$i+count($b);
            while ($carry) {
                $v=$out[$k]+$carry;$out[$k]=$v & self::MASK;
                $carry=$v >> self::SHIFT;$k++;
            }
        }
        return self::trim($out);
    }

    private function doubleMod(array $a): array
    {
        $out=[];$carry=0;
        for ($i=0;$i<count($a);$i++) {
            $v=($a[$i]<<1)|$carry;
            $out[]=$v&self::MASK;$carry=$v>>self::SHIFT;
        }
        if ($carry) $out[]=$carry;
        if (self::compare($out,$this->n)>=0) $out=self::sub($out,$this->n);
        return $out;
    }

    private function montMul(array $a, array $b): array
    {
        $n=$this->size;
        $t=array_fill(0,2*$n+3,0);
        for($i=0;$i<$n;$i++) {
            $carry=0; $ai=$a[$i]??0;
            for($j=0;$j<$n;$j++) {
                $v=$t[$i+$j]+$ai*($b[$j]??0)+$carry;
                $t[$i+$j]=$v&self::MASK;
                $carry=$v>>self::SHIFT;
            }
            $k=$i+$n;
            while($carry) {
                $v=$t[$k]+$carry; $t[$k]=$v&self::MASK;
                $carry=$v>>self::SHIFT;$k++;
            }
        }
        for($i=0;$i<$n;$i++) {
            $m=($t[$i]*$this->nPrime)&self::MASK;
            $carry=0;
            for($j=0;$j<$n;$j++) {
                $v=$t[$i+$j]+$m*$this->n[$j]+$carry;
                $t[$i+$j]=$v&self::MASK;
                $carry=$v>>self::SHIFT;
            }
            $k=$i+$n;
            while($carry) {
                $v=$t[$k]+$carry;$t[$k]=$v&self::MASK;
                $carry=$v>>self::SHIFT;$k++;
            }
        }
        $out=self::trim(array_slice($t,$n));
        if(self::compare($out,$this->n)>=0) $out=self::sub($out,$this->n);
        return $out;
    }

    private function reduce(array $a): array
    {
        if (self::compare($a,$this->n)<0) return $a;
        $r=[0];
        for($i=count($a)-1;$i>=0;$i--) {
            for($k=self::SHIFT-1;$k>=0;$k--) {
                $r=$this->doubleMod($r);
                if((($a[$i]>>$k)&1)!==0) {
                    $r=self::add($r,[1]);
                    if(self::compare($r,$this->n)>=0) $r=self::sub($r,$this->n);
                }
            }
        }
        return $r;
    }

    public function modPow(array $base, array $exp): array
    {
        $base=$this->reduce($base);
        $b=$this->montMul($base,$this->r2);
        $result=$this->oneMont;
        for($i=count($exp)-1;$i>=0;$i--) {
            $bits=$i===count($exp)-1 ? strlen(decbin($exp[$i])) : self::SHIFT;
            for($k=$bits-1;$k>=0;$k--) {
                $result=$this->montMul($result,$result);
                if(($exp[$i]>>$k)&1) $result=$this->montMul($result,$b);
            }
        }
        return $this->montMul($result,[1]);
    }

    public function modMul(array $a, array $b): array
    {
        $x = $this->montMul($this->reduce($a), $this->r2);
        $y = $this->montMul($this->reduce($b), $this->r2);
        return $this->montMul($this->montMul($x, $y), [1]);
    }

    public function modSub(array $a,array $b):array
    {
        $a=$this->reduce($a);$b=$this->reduce($b);
        return self::compare($a,$b)>=0?self::sub($a,$b):self::sub(self::add($a,$this->n),$b);
    }
}
