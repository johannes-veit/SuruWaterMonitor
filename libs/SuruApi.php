<?php
require_once __DIR__ . '/Cognito.php';
final class SuruException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 0) { parent::__construct($message); }
}
/** Read-only unofficial SURU client; no credentials are logged. */
class SuruApi
{
    public const BASE = 'https://api.freeze.suru-cloud.com/v2';
    private ?string $token = null;
    private int $expires = 0;
    public function __construct(private string $email, private string $password) {}

    protected function request(string $url, string $method, ?array $data, array $headers): array
    {
        $headers[] = 'Accept: application/json';
        $headers[] = 'User-Agent: SymconSuruBasic/0.1.3';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) throw new SuruException('HTTPS-Client konnte nicht gestartet werden');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($method==='POST') {
                curl_setopt($ch, CURLOPT_POST,true);
                curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($data,JSON_THROW_ON_ERROR));
            }
            $body = curl_exec($ch);
            $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($body === false) throw new SuruException('HTTPS-Verbindungsfehler: '.substr($err,0,120));
        } else {
            if (!filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) throw new SuruException('HTTPS-Unterstützung nicht verfügbar');
            $opts = ['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),
                        'timeout'=>20,'ignore_errors'=>true,'follow_location'=>0],
                     'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]];
            if ($method === 'POST') $opts['http']['content']=json_encode($data,JSON_THROW_ON_ERROR);
            $ctx=stream_context_create($opts);
            $body=@file_get_contents($url,false,$ctx);
            $status=0;
            foreach (($http_response_header??[]) as $h) {
                if (preg_match('~^HTTP/\S+\s+(\d{3})~',$h,$m)) $status=(int)$m[1];
            }
            if ($body === false) throw new SuruException('HTTPS-Verbindung fehlgeschlagen');
        }
        if ($status !== 200) {
            $decoded=json_decode($body,true);
            $type=(string)($decoded['__type']??$decoded['error']['code']??'');
            $message=match($status) {
                400 => 'Anfrage nicht akzeptiert',401=>'Anmeldung abgelaufen oder zurückgewiesen',
                403=>'Zugriff mit WaterMonitor BASIC nicht erlaubt',404=>'Zähler bzw. Ressource nicht gefunden',
                429=>'SURU-Abfragelimit erreicht', default=>'Cloud meldet HTTP '.$status
            };
            if (str_contains($type,'NotAuthorized') || str_contains($type,'UserNotFound')) $message='SURU-Anmeldung nicht freigeschaltet oder Login falsch';
            throw new SuruException($message,$status);
        }
        $json=json_decode($body,true);
        if (!is_array($json)) throw new SuruException('Ungültige JSON-Antwort der Cloud');
        return $json;
    }

    private function cognito(string $action, array $data): array
    {
        return $this->request(SuruCognito::ENDPOINT,'POST',$data,
            ['Content-Type: application/x-amz-json-1.1','X-Amz-Target: AWSCognitoIdentityProviderService.'.$action]);
    }

    public function login(): void
    {
        $srp=new SuruCognito($this->email,$this->password);
        $init=$this->cognito('InitiateAuth',[
            'AuthFlow'=>'USER_SRP_AUTH','ClientId'=>SuruCognito::CLIENT,
            'AuthParameters'=>$srp->authParameters()
        ]);
        if (($init['ChallengeName']??'')!=='PASSWORD_VERIFIER') {
            throw new SuruException('SURU benötigt einen nicht unterstützten Authentifizierungsschritt');
        }
        $challenge=$srp->challenge($init['ChallengeParameters']??[]);
        $result=$this->cognito('RespondToAuthChallenge',[
            'ChallengeName'=>'PASSWORD_VERIFIER', 'ClientId'=>SuruCognito::CLIENT,
            'ChallengeResponses'=>$challenge
        ]);
        $token=$result['AuthenticationResult']['AccessToken']??null;
        if (!is_string($token)||$token==='') throw new SuruException('Kein Zugangstoken erhalten');
        $this->token=$token;
        $this->expires=time()+(int)($result['AuthenticationResult']['ExpiresIn']??3600);
    }

    public function get(string $path, array $query=[]):array
    {
        if ($this->token===null||time()>=$this->expires-90) $this->login();
        $url=self::BASE.'/'.ltrim($path,'/');
        if ($query) $url.='?'.http_build_query($query,'','&',PHP_QUERY_RFC3986);
        try {
            return $this->request($url,'GET',null,['Authorization: Bearer '.$this->token]);
        } catch(SuruException $e) {
            if ($e->httpStatus===401) { $this->token=null; $this->login(); return $this->request($url,'GET',null,['Authorization: Bearer '.$this->token]); }
            throw $e;
        }
    }

    public function units(): array { return $this->get('units')['units']??[]; }
    public function detail(string $unitId): array { return $this->get('units/'.rawurlencode($unitId)); }
    public function reading(string $unitId):array
    {
        return $this->get('units/'.rawurlencode($unitId).'/reading',
            ['date'=>gmdate('Y-m-d\TH:i:s\Z')]);
    }
}
