<?php
declare(strict_types=1);
require_once __DIR__ . '/../libs/SuruApi.php';
require_once __DIR__ . '/../libs/Meter.php';
require_once __DIR__ . '/../libs/Consumption.php';

/** Unofficial read-only SURU BASIC connection for IP-Symcon >= 8.1. */
class SuruWaterMonitor extends IPSModuleStrict
{
    private const VERSION = '0.1.8';
    private const ARCHIVE_MODULE = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';

    public function Create(): void
    {
        parent::Create();
        $this->RegisterPropertyString('Email', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString('MeterSerial', '');
        $this->RegisterPropertyInteger('IntervalMinutes', 60);
        // Optional installation baseline, scoped to THIS meter instance only.
        // Never add it as a synthetic archive measurement.
        $this->RegisterPropertyBoolean('UseInstallReading', false);
        $this->RegisterPropertyString('InstallDate', '');
        $this->RegisterPropertyFloat('InstallReadingM3', 0.0);
        // Existing property names, idents and GUIDs are deliberately kept intact.
        $this->RegisterAttributeString('UnitID', '');
        $this->RegisterAttributeString('SelectedSerial', '');
        $this->RegisterAttributeFloat('LastValue', -1.0);
        $this->RegisterAttributeInteger('LastTimestamp', 0);
        $this->RegisterAttributeInteger('LastArchivedTimestamp', 0);
        $this->RegisterAttributeInteger('BusySince', 0); // old 0.1.1 attribute, retained for compatibility
        $this->RegisterAttributeString('MonthKey', '');
        $this->RegisterAttributeFloat('MonthAnchor', -1.0);
        $this->RegisterAttributeString('LastReport', '');
        $this->RegisterAttributeString('MeterBinding', '');
        $this->RegisterAttributeString('LastFullSelftest', 'noch nicht ausgeführt');
        $this->RegisterAttributeString('PeriodReadiness', '{}');
        $this->RegisterAttributeString('PeriodEverReady', '{}'); // one-time unhide; manual visibility is respected
        $this->RegisterAttributeString('ConsumptionBasis', '{}'); // computation provenance for diagnostics
        $this->RegisterTimer('Poll', 0, 'IPS_RequestAction($_IPS["TARGET"], "Poll", true);');
        $this->RegisterTimer('FirstPoll', 0, 'IPS_RequestAction($_IPS["TARGET"], "Poll", true);');
        $this->RegisterTimer('Retry', 0, 'IPS_RequestAction($_IPS["TARGET"], "Poll", true);');
        $this->RegisterVariableFloat('Meter', 'Zählerstand (m³)', [], 10);
        $this->RegisterVariableFloat('Delta', 'Verbrauch seit letzter Messung (Liter)', [], 20);
        $this->RegisterVariableFloat('Month', 'Verbrauch seit erster Monatsmessung (Liter)', [], 30);
        // Hide only a newly created, still-unmeasured variable. Never reset the user's visibility choice.
        if ($this->RegisterVariableFloat('Consumption24h', 'Verbrauch letzte 24 Stunden (Liter)', [], 32)) {
            IPS_SetHidden($this->GetIDForIdent('Consumption24h'), true);
        }
        // Hide only a newly created, still-unmeasured variable. Never reset the user's visibility choice.
        if ($this->RegisterVariableFloat('Consumption7d', 'Verbrauch letzte 7 Tage (Liter)', [], 34)) {
            IPS_SetHidden($this->GetIDForIdent('Consumption7d'), true);
        }
        // Hide only a newly created, still-unmeasured variable. Never reset the user's visibility choice.
        if ($this->RegisterVariableFloat('Consumption30d', 'Verbrauch letzte 30 Tage (Liter)', [], 36)) {
            IPS_SetHidden($this->GetIDForIdent('Consumption30d'), true);
        }
        // Hide only a newly created, still-unmeasured variable. Never reset the user's visibility choice.
        if ($this->RegisterVariableFloat('Consumption365d', 'Verbrauch letzte 365 Tage (Liter)', [], 38)) {
            IPS_SetHidden($this->GetIDForIdent('Consumption365d'), true);
        }
        $this->RegisterVariableString('MeasuredAt', 'Messzeitpunkt', [], 40);
        $this->RegisterVariableString('LastSync', 'Letzter erfolgreicher Cloud-Abruf', [], 45);
        $this->RegisterVariableString('State', 'SURU-Status', [], 50);
        $this->RegisterVariableString('Report', 'Selbsttest / Diagnose', [], 60);
        $this->RegisterVariableFloat('History', 'Messwertarchiv (m³)', [], 100);
        IPS_SetHidden($this->GetIDForIdent('History'), true);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        // Migration from 0.1.4: remove only the old HTML/dashboard variable.
        // The native numeric variables, credentials and the hidden archive stay untouched.
        if (function_exists('IPS_GetObjectIDByIdent') &&
            @IPS_GetObjectIDByIdent('Dashboard', $this->InstanceID) !== false) {
            $this->UnregisterVariable('Dashboard');
        }
        $valid = trim($this->ReadPropertyString('Email')) !== '' && $this->ReadPropertyString('Password') !== '';
        $serial = trim($this->ReadPropertyString('MeterSerial'));
        if ($serial !== $this->ReadAttributeString('SelectedSerial')) {
            $this->WriteAttributeString('UnitID', '');
            $this->WriteAttributeString('SelectedSerial', $serial);
        }
        $minutes = max(30, min(1440, $this->ReadPropertyInteger('IntervalMinutes')));
        $this->SetTimerInterval('Poll', $valid ? $minutes * 60000 : 0);
        $this->SetTimerInterval('FirstPoll', $valid ? 10000 : 0);
        $this->SetTimerInterval('Retry', 0);
        $this->setupArchive();
        $test = $this->offlineSelfTest(true);
        $this->WriteAttributeString('LastFullSelftest', $this->testsOk($test) ? 'OK' : 'FEHLER');
        $this->writeReport('OFFLINE-PRÜFUNG', $test);
        if (!$valid) $this->SetValue('State', 'WARTE AUF WATERMONITOR BASIC KONTO');
        $this->refreshConsumption(); // only local archive; no cloud requests during updates
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode((string) file_get_contents(__DIR__ . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
        $form['actions'][0]['caption'] = 'Aktueller Status: ' . (string) $this->GetValue('State');
        if ($this->ReadPropertyBoolean('UseInstallReading')) {
            $opening = $this->openingReading();
            $form['elements'][3]['items'][] = [
                'type' => 'Label',
                'caption' => $opening === null
                    ? 'ACHTUNG: Startdatum ungueltig. Format JJJJ-MM-TT, z.B. 2026-10-27. Die bisherigen Verbrauchswerte bleiben erhalten.'
                    : 'Startstand aktiv: ' . number_format($opening['value'], 3, ',', '.') .
                      ' m³ am ' . date('d.m.Y', $opening['timestamp']) .
                      '. Startkorrektur nur fuer 30/365 Tage; zeitlich begrenzt, Uebergang geschaetzt.',
            ];
        }
        // Full reports can be large. They are stored in the Report variable for copying.
        $report = $this->ReadAttributeString('LastReport');
        $form['actions'][1]['caption'] = 'Selbsttest: ' . $this->ReadAttributeString('LastFullSelftest') .
            ' | Kurzbericht: ' . (strlen($report) > 330 ? substr($report, 0, 330) . ' ...' : $report) .
            "\nVollständige Diagnose: Variable Selbsttest / Diagnose.";
        return json_encode($form, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        switch ($Ident) {
            case 'Poll': $this->poll(); return;
            case 'OfflineTest':
                $tests = $this->offlineSelfTest(true);
                $this->WriteAttributeString('LastFullSelftest', $this->testsOk($tests) ? 'OK' : 'FEHLER');
                $this->writeReport('OFFLINE-PRÜFUNG', $tests);
                return;
            default: throw new InvalidArgumentException('Unbekannte Aktion');
        }
    }

    protected function makeApi(): SuruApi
    {
        return new SuruApi(trim($this->ReadPropertyString('Email')), $this->ReadPropertyString('Password'));
    }

    private function setupArchive(): void
    {
        if (!function_exists('IPS_GetInstanceListByModuleID')) return;
        $ids = IPS_GetInstanceListByModuleID(self::ARCHIVE_MODULE);
        if (!$ids) return;
        $archive = (int) $ids[0];
        $var = $this->GetIDForIdent('History');
        try {
            $dirty = false;
            if (!AC_GetLoggingStatus($archive, $var)) {
                if (AC_SetLoggingStatus($archive, $var, true) === false) return;
                $dirty = true;
            }
            if (function_exists('AC_GetAggregationType') && AC_GetAggregationType($archive, $var) !== 1) {
                if (AC_SetAggregationType($archive, $var, 1) === false) return;
                $dirty = true;
            } elseif (!function_exists('AC_GetAggregationType') && $dirty) {
                // Older cores: always mark newly created History as a counter.
                if (AC_SetAggregationType($archive, $var, 1) === false) return;
            }
            if ($dirty) IPS_ApplyChanges($archive);
        } catch (Throwable $e) {
            // Non-fatal here: actual archiving is checked again before accepting readings.
        }
    }

    /** @return array<string,string> */
    private function offlineSelfTest(bool $full = false): array
    {
        $test = [
            'SURU BASIC Modus' => 'OK – nur /units und /reading',
            'JSON' => function_exists('json_decode') ? 'OK' : 'FEHLER',
            'HTTPS Client' => (function_exists('curl_init') || filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) ? 'OK' : 'FEHLER',
        ];
        if ($full) {
            try {
                $srp = new SuruCognito('test@example.com', 'test', '12345');
                $test['Cognito SRP'] = str_starts_with($srp->authParameters()['SRP_A'], '70a5e2dcadb95d26') ? 'OK' : 'FEHLER';
                $challenge = [
                    'USER_ID_FOR_SRP'=>'uid-1', 'SALT'=>'a1b2c3d4',
                    'SRP_B'=>str_repeat('ff', 64), 'SECRET_BLOCK'=>'c2VjcmV0'
                ];
                $res = (new SuruCognito('u@example.com', 'Secret1!', '12345'))
                    ->challenge($challenge, new DateTimeImmutable('2026-06-25T12:00:01Z'));
                $test['SRP Server-Challenge'] = $res['PASSWORD_CLAIM_SIGNATURE'] ===
                    '4+1L8da/3P4LM6+pS5zCalmqlL2FAZqmigN8tstWBjM=' ? 'OK' : 'FEHLER';
            } catch (Throwable $e) {
                $test['Cognito SRP'] = 'FEHLER';
                $test['SRP Server-Challenge'] = 'FEHLER';
            }
        } else {
            $test['Vollständiger Kryptotest (letzter Stand)'] = $this->ReadAttributeString('LastFullSelftest');
        }
        $now = time();
        $a = SuruMeter::evaluate(1.123, $now, 1.120, $now - 3600);
        $test['Messwert-Prüfung'] = ($a['kind'] === 'new' && abs($a['delta_l'] - 3.0) < 0.001) ? 'OK' : 'FEHLER';
        $test['Rücksprung-Schutz'] = SuruMeter::evaluate(0.1, $now, 1, $now - 3600)['kind'] === 'decrease' ? 'OK' : 'FEHLER';
        $test['Doppelwert-Schutz'] = SuruMeter::evaluate(1, 1001, 1, 1001)['kind'] === 'duplicate' ? 'OK' : 'FEHLER';
        $test['Sprung-Schutz'] = SuruMeter::evaluate(99, $now, 1, $now - 60)['kind'] === 'jump' ? 'OK' : 'FEHLER';
        $test['Veraltete Zeitstempel'] = SuruMeter::evaluate(1.5, 1000, 1.2, 1001)['kind'] === 'stale' ? 'OK' : 'FEHLER';
        $ids = function_exists('IPS_GetInstanceListByModuleID') ? IPS_GetInstanceListByModuleID(self::ARCHIVE_MODULE) : [];
        $test['Archiv'] = 'FEHLER – kein Archiv';
        if ($ids) {
            try {
                $active = AC_GetLoggingStatus((int) $ids[0], $this->GetIDForIdent('History'));
                $counter = !function_exists('AC_GetAggregationType') ||
                    AC_GetAggregationType((int) $ids[0], $this->GetIDForIdent('History')) === 1;
                $test['Archiv'] = ($active && $counter) ? 'OK – Zeitstempelarchiv und Zähleraggregation' :
                    'FEHLER – Logging oder Zähleraggregation nicht aktiv';
            } catch (Throwable $e) { $test['Archiv'] = 'FEHLER – Archivprüfung'; }
        }
        return $test;
    }

    private function testsOk(array $tests): bool
    {
        foreach ($tests as $result) if (!str_starts_with((string) $result, 'OK')) return false;
        return true;
    }

    private function writeReport(string $title, array $tests): void
    {
        $text = 'SURU WaterMonitor v' . self::VERSION . ' – ' . $title . "\nErstellt: " . date('d.m.Y H:i:s') . "\n";
        foreach ($tests as $label => $result) $text .= "{$label}: {$result}\n";
        if ($title === 'LIVE-PRÜFUNG') {
            $sources = json_decode($this->ReadAttributeString('ConsumptionBasis'), true);
            if (is_array($sources)) {
                foreach (['24h', '7d', '30d', '365d'] as $span) {
                    if (isset($sources[$span])) $text .= "Berechnung {$span}: {$sources[$span]}\n";
                }
            }
        }
        $this->WriteAttributeString('LastReport', $text);
        $this->SetValue('Report', $text);
    }

    private function setIssue(string $state, string $message, int $retryMinutes = 0): void
    {
        $this->SetValue('State', $state);
        $test = $this->offlineSelfTest();
        $test['Live-Abfrage'] = $message;
        $test['Erneuter Versuch'] = $retryMinutes > 0 ? 'in ca. ' . $retryMinutes . ' Minuten' : 'nach dem regulären Abrufintervall';
        $this->writeReport($state, $test);
        $this->SetTimerInterval('Retry', $retryMinutes > 0 ? $retryMinutes * 60000 : 0);
    }

    /** A semaphore prevents parallel manual and scheduled polls from writing the same sample. */
    private function poll(): void
    {
        $lock = 'SuruWaterMonitor_' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($lock, 0)) return;
        try {
            $this->pollLocked();
        } finally {
            IPS_SemaphoreLeave($lock);
        }
    }

    private function pollLocked(): void
    {
        $this->SetTimerInterval('FirstPoll', 0);
        $this->SetTimerInterval('Retry', 0);
        if (trim($this->ReadPropertyString('Email')) === '' || $this->ReadPropertyString('Password') === '') {
            $this->setIssue('WARTE AUF ZUGANGSDATEN', 'E-Mail und Passwort in der Instanz speichern');
            return;
        }
        try {
            $api = $this->makeApi();
            $units = $api->units();
            if (!is_array($units) || count($units) === 0) {
                $this->setIssue('WARTE AUF ZÄHLERFREIGABE', 'Login erfolgreich, aber im Konto ist kein Zähler zugeordnet');
                return;
            }
            $unitID = $this->selectUnit($api, $units);
            $binding = hash('sha256', strtolower(trim($this->ReadPropertyString('Email'))) . "\x00" . $unitID);
            $priorBinding = $this->ReadAttributeString('MeterBinding');
            if ($priorBinding !== '' && !hash_equals($priorBinding, $binding)) {
                $this->setIssue('ZÄHLERZUORDNUNG GEÄNDERT',
                    'Anderes WaterMonitor-Konto/anderer Zähler erkannt. Für einen anderen Zähler neue Symcon-Instanz anlegen, damit historische Messwerte nicht vermischt werden.');
                return;
            }
            $reading = $api->reading($unitID);
            $parsed = SuruMeter::parseReading($reading);
            $old = $this->ReadAttributeFloat('LastValue');
            $lastTime = $this->ReadAttributeInteger('LastTimestamp');
            $evaluation = SuruMeter::evaluate($parsed['value'], $parsed['timestamp'], $old < 0 ? null : $old, $lastTime);
            $kind = $evaluation['kind'];
            if (in_array($kind, ['stale', 'conflict', 'decrease', 'jump'], true)) {
                $this->setIssue('MESSWERT ZUR PRÜFUNG', 'Messwert abgelehnt: ' . $kind);
                return;
            }
            if (in_array($kind, ['new', 'initial'], true)) {
                if (!$this->archiveReading($parsed['timestamp'], $parsed['value'])) {
                    $this->setIssue('ARCHIV NICHT BEREIT', 'Messung empfangen, Archivspeicherung wird erneut versucht', 10);
                    return;
                }
                $this->SetValue('Meter', $parsed['value']);
                $this->SetValue('Delta', $evaluation['delta_l']);
                $this->SetValue('MeasuredAt', date('d.m.Y H:i:s', $parsed['timestamp']));
                $this->WriteAttributeFloat('LastValue', $parsed['value']);
                $this->WriteAttributeInteger('LastTimestamp', $parsed['timestamp']);
                $this->updateMonth($parsed['value'], $parsed['timestamp']);
                $this->refreshConsumption();
            }
            if ($priorBinding === '') $this->WriteAttributeString('MeterBinding', $binding);
            $this->SetValue('LastSync', date('d.m.Y H:i:s'));
            $age = max(0, time() - $parsed['timestamp']);
            $this->SetValue('State', $age > 48 * 3600 ? 'DATEN VERALTET' : 'BETRIEBSBEREIT – BASIC');
                $t = $this->offlineSelfTest();
            $t['Anmeldung'] = 'OK – eigenes SURU-Konto';
            $t['BASIC Gesamtzählerstand'] = 'OK';
            $t['Letzte tatsächliche Messung'] = date('d.m.Y H:i:s', $parsed['timestamp']);
            $t['Cloud-Aktualität'] = $age > 48 * 3600 ? 'VERALTET' : 'OK';
            $t['Neue Messung'] = $kind;
            $this->writeReport('LIVE-PRÜFUNG', $t);
        } catch (SuruException $e) {
            $status = match ($e->httpStatus) {
                401 => 'ANMELDUNG / FREIGABE PRÜFEN',
                403 => 'BASIC-ZUGRIFF VERWEIGERT',
                429 => 'ABFRAGELIMIT',
                400 => 'ANFRAGEFORMAT / LOGIN PRÜFEN',
                default => 'CLOUD NICHT VERFÜGBAR'
            };
            $retry = in_array($e->httpStatus, [0, 500, 502, 503, 504], true) ? 10 : 0;
            $this->setIssue($status, $e->getMessage(), $retry);
        } catch (Throwable $e) {
            // Do not include provider responses, credentials or JWTs in user-facing messages.
            $this->setIssue('ABFRAGEFEHLER', get_class($e) . ': Datenformat oder Verarbeitung prüfen', 10);
        }
    }

    private function selectUnit(SuruApi $api, array $units): string
    {
        $known = $this->ReadAttributeString('UnitID');
        $ids = [];
        foreach ($units as $u) if (is_array($u) && isset($u['id']) && (string) $u['id'] !== '') $ids[] = (string) $u['id'];
        $ids = array_values(array_unique($ids));
        if ($known !== '' && in_array($known, $ids, true)) return $known;
        if (count($ids) === 1) {
            $this->WriteAttributeString('UnitID', $ids[0]);
            return $ids[0];
        }
        $serial = trim($this->ReadPropertyString('MeterSerial'));
        if ($serial !== '') {
            $matched = [];
            foreach ($units as $u) {
                if (!is_array($u) || !isset($u['id'])) continue;
                $detail = $api->detail((string) $u['id']);
                // The private API does not guarantee the field that holds serial numbers.
                if (str_contains(json_encode($detail, JSON_THROW_ON_ERROR), $serial)) $matched[] = (string) $u['id'];
            }
            $matched = array_values(array_unique($matched));
            if (count($matched) === 1) {
                $this->WriteAttributeString('UnitID', $matched[0]);
                return $matched[0];
            }
        }
        throw new SuruException('Zählerauswahl nicht eindeutig. Seriennummer im Modul prüfen.');
    }

    private function archiveReading(int $timestamp, float $value): bool
    {
        if ($this->ReadAttributeInteger('LastArchivedTimestamp') === $timestamp) return true;
        $ids = IPS_GetInstanceListByModuleID(self::ARCHIVE_MODULE);
        if (!$ids) return false;
        $archive = (int) $ids[0];
        $var = $this->GetIDForIdent('History');
        try {
            if (!AC_GetLoggingStatus($archive, $var) ||
                (function_exists('AC_GetAggregationType') && AC_GetAggregationType($archive, $var) !== 1)) {
                $this->setupArchive();
            }
            if (!AC_GetLoggingStatus($archive, $var)) return false;
            if (function_exists('AC_GetAggregationType') && AC_GetAggregationType($archive, $var) !== 1) return false;
            if (AC_AddLoggedValues($archive, $var, [['TimeStamp' => $timestamp, 'Value' => $value]]) !== true) return false;
            $this->WriteAttributeInteger('LastArchivedTimestamp', $timestamp);
            try { AC_ReAggregateVariable($archive, $var); } catch (Throwable $ignored) {}
            return true;
        } catch (Throwable $e) { return false; }
    }

    private function updateMonth(float $value, int $timestamp): void
    {
        $key = date('Y-m', $timestamp);
        if ($this->ReadAttributeString('MonthKey') !== $key || $this->ReadAttributeFloat('MonthAnchor') < 0) {
            $this->WriteAttributeString('MonthKey', $key);
            $this->WriteAttributeFloat('MonthAnchor', $value);
        }
        $this->SetValue('Month', max(0, round(($value - $this->ReadAttributeFloat('MonthAnchor')) * 1000, 3)));
    }

    /** Confirmed installation baseline; date-only input uses local noon when the hour is unknown. */
    private function openingReading(): ?array
    {
        if (!$this->ReadPropertyBoolean('UseInstallReading')) return null;
        $raw = trim($this->ReadPropertyString('InstallDate'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $raw)) return null;
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $raw . ' 12:00',
            new DateTimeZone(date_default_timezone_get()));
        if ($date === false || $date->format('Y-m-d') !== $raw) return null;
        $value = $this->ReadPropertyFloat('InstallReadingM3');
        if (!is_finite($value) || $value < 0) return null;
        return ['timestamp' => $date->getTimestamp(), 'value' => $value];
    }

    /** Calculate from the existing timestamp archive; never touches vendor's paid endpoints. */
    private function refreshConsumption(): void
    {
        $lastTime = $this->ReadAttributeInteger('LastTimestamp');
        $lastValue = $this->ReadAttributeFloat('LastValue');
        $periods = ['24h' => null, '7d' => null, '30d' => null, '365d' => null];
        $history = [];
        $archiveOk = false;
        $sources = array_fill_keys(array_keys($periods), 'keine ausreichenden Messwerte');
        if ($lastTime > 0 && $lastValue >= 0) {
            if (function_exists('AC_GetLoggedValues')) {
                try {
                    $archives = IPS_GetInstanceListByModuleID(self::ARCHIVE_MODULE);
                    if ($archives) {
                        // Include three extra days for the 365-day boundary, no paid API.
                        $from = $lastTime - 368 * 86400;
                        $rows = AC_GetLoggedValues((int) $archives[0], $this->GetIDForIdent('History'), $from, $lastTime, 0);
                        if (is_array($rows)) {
                            $history = $rows;
                            $archiveOk = true;
                        }
                    }
                } catch (Throwable $ignored) {
                    // Never represent a missing archive as a newly observed zero.
                }
            }
            $opening = $this->openingReading();
            $details = SuruConsumption::calculateDetailed($history, $lastTime, $lastValue, $opening);
            $periods = $details['values'];
            $sources = $details['basis'];
            if (!$archiveOk) {
                // Known total since installation is exact while installation
                // lies inside the window. No interpolation without history.
                foreach (['24h', '7d', '30d', '365d'] as $span) {
                    $days = ['24h'=>1, '7d'=>7, '30d'=>30, '365d'=>365][$span];
                    if ($opening === null || $span === '24h' || $span === '7d' ||
                        $opening['timestamp'] > $lastTime - $days * 86400 ||
                        $opening['value'] > $lastValue) {
                        $periods[$span] = null;
                        $sources[$span] = 'Archiv nicht verfügbar';
                    }
                }
            }
        }
        $this->WriteAttributeString('ConsumptionBasis', json_encode($sources, JSON_THROW_ON_ERROR));
        $idents = ['24h' => 'Consumption24h', '7d' => 'Consumption7d', '30d' => 'Consumption30d', '365d' => 'Consumption365d'];
        $previous = json_decode($this->ReadAttributeString('PeriodReadiness'), true);
        $previous = is_array($previous) ? $previous : [];
        $everReady = json_decode($this->ReadAttributeString('PeriodEverReady'), true);
        $everReady = is_array($everReady) ? $everReady : [];
        foreach ($idents as $key => $ident) {
            $liters = $periods[$key];
            if ($liters === null) {
                // The value remains untouched; do not write a misleading zero or change visibility.
                continue;
            }
            if (abs((float) $this->GetValue($ident) - $liters) > 0.0001) {
                $this->SetValue($ident, $liters);
            }
            // Show a period exactly once when enough data first becomes available.
            // Values already available in v0.1.4 are not unhidden: user choices prevail.
            if (empty($everReady[$key]) && empty($previous[$key])) {
                IPS_SetHidden($this->GetIDForIdent($ident), false);
            }
            $everReady[$key] = true;
        }
        $this->WriteAttributeString('PeriodEverReady', json_encode($everReady, JSON_THROW_ON_ERROR));
        $this->WriteAttributeString('PeriodReadiness', json_encode(array_map(
            static fn($value) => $value !== null, $periods
        ), JSON_THROW_ON_ERROR));
    }

}