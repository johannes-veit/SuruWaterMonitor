<?php
require_once __DIR__ . '/BigMath.php';
/** Cognito SRP-A 3072-bit pure PHP, modeled on the MIT-licensed SURU HA integration. */
final class SuruCognito
{
    public const POOL = 'eu-central-1_8hkX7Bvjh';
    public const CLIENT = '1p3lj6d18fehl88ugahglf6kc8';
    public const ENDPOINT = 'https://cognito-idp.eu-central-1.amazonaws.com/';
    private const N =
        'FFFFFFFFFFFFFFFFC90FDAA22168C234C4C6628B80DC1CD1' .
        '29024E088A67CC74020BBEA63B139B22514A08798E3404DD' .
        'EF9519B3CD3A431B302B0A6DF25F14374FE1356D6D51C245' .
        'E485B576625E7EC6F44C42E9A637ED6B0BFF5CB6F406B7ED' .
        'EE386BFB5A899FA5AE9F24117C4B1FE649286651ECE45B3D' .
        'C2007CB8A163BF0598DA48361C55D39A69163FA8FD24CF5F' .
        '83655D23DCA3AD961C62F356208552BB9ED529077096966D' .
        '670C354E4ABC9804F1746C08CA18217C32905E462E36CE3B' .
        'E39E772C180E86039B2783A2EC07A28FB5C55DF06F4C52C9' .
        'DE2BCBF6955817183995497CEA956AE515D2261898FA0510' .
        '15728E5A8AAAC42DAD33170D04507A33A85521ABDF1CBA64' .
        'ECFB850458DBEF0A8AEA71575D060C7DB3970F85A6E1E4C7' .
        'ABF5AE8CDB0933D71E8C94E04A25619DCEE3D2261AD2EE6B' .
        'F12FFA06D98A0864D87602733EC86A64521F2B18177B200C' .
        'BBE117577A615D6C770988C0BAD946E208E24FA074E5AB31' .
        '43DB5BFCE0FD108E4B82D120A93AD2CAFFFFFFFFFFFFFFFF';
    private SuruBigMath $math;
    private array $a;
    private array $A;
    private string $password;
    private string $username;

    public function __construct(string $username, string $password, ?string $fixedPrivateHex = null)
    {
        $this->username = $username;
        $this->password = $password;
        $this->math = new SuruBigMath(self::N);
        $this->a = SuruBigMath::fromHex($fixedPrivateHex ?? bin2hex(random_bytes(128)));
        $this->A = $this->math->modPow([2], $this->a);
        if (SuruBigMath::compare($this->A, [0])===0) throw new RuntimeException('Invalid SRP A');
    }

    public function authParameters(): array
    {
        return ['USERNAME' => $this->username, 'SRP_A' => SuruBigMath::toHex($this->A)];
    }

    public static function timestamp(?DateTimeImmutable $utc = null): string
    {
        return ($utc ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('UTC'))->format('D M j H:i:s \\U\\T\\C Y');
    }

    public function challenge(array $ch, ?DateTimeImmutable $now = null): array
    {
        foreach (['USER_ID_FOR_SRP', 'SALT', 'SRP_B', 'SECRET_BLOCK'] as $key) {
            if (!isset($ch[$key]) || !is_string($ch[$key])) throw new RuntimeException('Incomplete Cognito challenge');
        }
        $B = SuruBigMath::fromHex($ch['SRP_B']);
        $u = SuruBigMath::fromHex(hash('sha256', hex2bin(
            SuruBigMath::padHex($this->A) . SuruBigMath::padHex($B)
        )));
        if (SuruBigMath::compare($u,[0]) === 0) throw new RuntimeException('Invalid SRP u');
        $k = SuruBigMath::fromHex(hash('sha256', hex2bin('00' . self::N . '02')));
        $secretHash = hash('sha256', explode('_', self::POOL, 2)[1] . $ch['USER_ID_FOR_SRP'] . ':' . $this->password);
        $x = SuruBigMath::fromHex(hash('sha256', hex2bin(SuruBigMath::padHex(SuruBigMath::fromHex($ch['SALT'])) . $secretHash)));
        $gx = $this->math->modPow([2], $x);
        $base = $this->math->modSub($B, $this->math->modMul($k,$gx));
        $exp = SuruBigMath::add($this->a,SuruBigMath::mul($u,$x));
        $S = $this->math->modPow($base,$exp);
        $rawS = hex2bin(SuruBigMath::padHex($S));
        $rawU = hex2bin(SuruBigMath::padHex($u));
        $prk = hash_hmac('sha256', $rawS, $rawU, true);
        $hkdf = substr(hash_hmac('sha256', 'Caldera Derived Key' . "\x01", $prk, true),0,16);
        $stamp = self::timestamp($now);
        $secret = base64_decode($ch['SECRET_BLOCK'], true);
        if ($secret === false) throw new RuntimeException('Invalid secret block');
        $msg = explode('_', self::POOL,2)[1] . $ch['USER_ID_FOR_SRP'] . $secret . $stamp;
        return [
            'TIMESTAMP'=>$stamp,
            'USERNAME'=>$ch['USER_ID_FOR_SRP'],
            'PASSWORD_CLAIM_SECRET_BLOCK'=>$ch['SECRET_BLOCK'],
            'PASSWORD_CLAIM_SIGNATURE'=>base64_encode(hash_hmac('sha256',$msg,$hkdf,true))
        ];
    }
}
