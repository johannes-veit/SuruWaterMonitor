# SURU WaterMonitor BASIC – IP-Symcon 9.0

Inoffizielles, **rein lesendes** IP-Symcon-Modul zur Auswertung eines SURU-/WaterMonitor-Kontos. Entwickelt mit Fokus auf das **kostenlose BASIC-Konto** und die SymBox. Kein Home Assistant, keine zusätzliche Hardware, keine kostenpflichtigen SURU-Funktionen.

## Stand v0.1.4: Rollierende Verbräuche + Visualisierungs-Kachel

**Erfolgreich live verifiziert:** SURU BASIC Login und Gesamtzählerstand am 03.11.2026 anhand der vom Nutzer übermittelten Symcon-LIVE-Diagnose. Fortlaufende historische Messwerte sind noch nicht belegt.

Neu erzeugt die Modulinstanz direkt folgende **vier Float-Variablen in Litern**:
- `Consumption24h`: Verbrauch zwischen zwei Messungen in 18–30 Stunden Abstand (etwa letzte 24 h).
- `Consumption7d`: Verbrauch der letzten 7 × 24 Stunden.
- `Consumption30d`: Verbrauch der letzten 30 × 24 Stunden.
- `Consumption365d`: Verbrauch der letzten 365 × 24 Stunden.

Die Zeitfenster enden beim **letzten tatsächlichen Messzeitpunkt** des Zählers, nicht beim Zeitpunkt des Cloud-Abrufs. Für 7/30/365 Tage wird ein gespeicherter Zählerstand im Zeitfenster ±18 Stunden um die Zielgrenze gewählt; das Ergebnis ist deshalb als **≈ Näherung** gekennzeichnet. Es findet keine künstliche Hochrechnung bei fehlender Datenhistorie statt. Fehlt ein geeigneter Zählerstand, wird die Variable in der Objektansicht ausgeblendet und in der Kachel `— / Noch nicht genügend Messwerte` angezeigt. Ein gemessener Nullverbrauch ist dagegen 0 Liter.

**Kachel-Visualisierung und Standard-WebFront:** Die Modulinstanz erstellt die String-Variable `Dashboard` (`Wasserverbrauch – Übersicht`) mit Symcon-Darstellung *Webinhalt / HTML*. Als eigene Kachel in der Visualisierung anzeigen. Der Großwert zeigt m³ und den Messzeitpunkt; darunter stehen die vier Verbrauchszeiträume. Der Inhalt wird bei jeder neu angenommenen Messung und bei Fehlerstatus aktualisiert, ohne externe Skripte oder Internet-CDNs.

**Updatesicherheit:** Vorhandene Variablen-Idents, GUIDs und Archivwerte aus v0.1.3 werden übernommen. In `ApplyChanges` wird nur die bestehende Historie ausgewertet; es erfolgt dort kein Cloud-Aufruf. Die bisherigen Variablen `Delta` (seit letzter Messung) und `Month` (seit erster Messung im laufenden Monat) bleiben unverändert.

## Wichtiger Status

- **Code und Offline-Tests vorhanden und bestanden.**
- **Live-Anmeldung am 03.11.2026 vom Nutzer als erfolgreich gemeldet**. Die neue Berechnung ist offline getestet, die Folgemessungen noch nicht live bestätigt.
- Die Cloud-Schnittstelle ist inoffiziell und kann sich ändern.
- Die BASIC-App des Nutzers zeigt inzwischen echte Werte an; die Cloud-Abfragen des Symcon-Moduls müssen jedoch erstmals auf der SymBox getestet werden.

## Installation

1. Einen **neuen, eigenständigen GitHub-Repository** mit dem Namen `SuruWaterMonitor` erstellen (öffentlich oder passend für die eigene Symcon-Git-Anmeldung). Der Inhalt dieses Pakets muss in die **Repository-Wurzel**; `library.json` muss dort liegen. Es ist nicht möglich, eine lokale ZIP direkt in `Module Control` zu laden.
2. In IP-Symcon unter **Kern-Instanzen → Modules / Modulverwaltung** das GitHub-Repository über `+` hinzufügen.
3. Eine Instanz **SuruWaterMonitor** hinzufügen.
4. In der Instanz die E-Mail-Adresse und das Passwort des **eigenen kostenlosen WaterMonitor-BASIC-Kontos** eintragen. Auf **Übernehmen** klicken.
5. Das Modul startet die erste Live-Diagnose mit kurzer Verzögerung und anschließend automatisch alle 60 Minuten. Die Diagnose erscheint in der Variable **Selbsttest / Diagnose** und im Konfigurationsformular.

**Anmeldung:** `AWS Cognito USER_SRP_AUTH`; SRP-BigInteger ist als reine PHP-Implementierung enthalten, keine zusätzliche PHP-Bibliothek nötig. Bei aktivem Konto verwendet der Client nur `GET /v2/units` und `GET /v2/units/{id}/reading`. Bei einem einzigen Zähler wählt das Modul automatisch. Die optionale Zählernummer wird bei mehreren Zählern in der Instanz konfiguriert; persönliche Zählerkennungen sind absichtlich nicht im öffentlichen Repository enthalten.

## Verfügbare Variablen

- `Meter` – letzter gültiger Gesamtzählerstand (m³)
- `Delta` – Verbrauch zwischen den beiden letzten unterschiedlichen Messungen (Liter)
- `Month` – Verbrauch seit der ersten verfügbaren Messung im aktuellen Monat (Liter), **kein exakt abgegrenzter Kalendermonatsverbrauch**
- `MeasuredAt` – tatsächlicher Messzeitpunkt (aus SURU)
- `State` – Betriebs-/Fehlerstatus
- `Report` – vollständiger Offline-/Live-Diagnosebericht
- `History` – versteckte Archivvariable: Messwerte mit Originalzeitstempel

Das Archiv wird automatisch aktiviert und nur bei neuen, plausiblen Werten beschrieben. Bei einem Archivfehler werden Messwerte nicht als abgeschlossen markiert, sodass die Speicherung erneut versucht werden kann. Keine Duplikate nach Neustart, sofern die Instanzattribute intakt sind.

## Was der Selbsttest prüft

Symcon-Kernfunktionen, HTTPS-Unterstützung, bekannte SRP-Testvektoren, Messwertprüfungen, bestehendes Archiv, anschließenden echten Login, Konto-Zuordnung und BASIC-Messwerte. Ungültige und doppelte Werte werden verworfen. Der Code ruft **keinen kostenpflichtigen Tagesverbrauch-Endpunkt** auf, da dieser für BASIC-Nutzer HTTP 403 liefern kann.

## Sicherheit

- Zugangsdaten werden über ein `PasswordTextBox`-Feld verdeckt eingegeben. Die dauerhafte Speicherung erfolgt als **reguläre Symcon-Instanzeigenschaft**; die Maskierung ist **keine eigene Verschlüsselung**. SymBox-Zugriff und Backups entsprechend schützen.
- Keine Benutzerpasswörter oder Cloud-Tokens in Diagnoseberichten.
- HTTPS mit Zertifikatsprüfung, keine HTTP-Weiterleitungen.
- Ausschließlich lesende Cloud-Abfragen, keine Zählerkonfiguration.
- Die Zugangsdaten verbleiben in der lokalen SymBox und werden nur an die SURU/Cognito-Server für den eigenen Konto-Login übertragen.
- Normale Modulupdates verändern vorhandene Archive oder Instanzen nicht absichtlich.

## Tests (Entwicklungs-PC mit PHP 8.4)

```sh
php tests/unit_tests.php
php tests/module_smoke.php
```

Mit `tests/random_math.php` wurden außerdem sechs zufällige 3072-Bit-Arithmetikfälle gegen Pythons `pow(..., ..., mod)` verglichen.

## Herkunft und Lizenz

Basiert in der Protokollbeschreibung auf dem MIT-lizenzierten Projekt [teuffel/homeassistant-suru-watermonitor](https://github.com/teuffel/homeassistant-suru-watermonitor). Die PHP-Implementierung ist eigenständig für IP-Symcon verfasst. Sie ist kein offizielles SURU-Produkt. Siehe `LICENSE`.

## Technische Einschränkungen

- Das kostenlose Konto muss vom Betreiber verifiziert und einem Zähler zugeordnet sein.
- Wenn SURU nur einmal täglich neue Messwerte liefert, kann das Modul trotz stündlicher Abfrage nicht mehr Messpunkte erzeugen.
- API-Felder und Rollen können sich jederzeit ändern.
- Stand v0.1.4: Anmeldung und erster Zählerwert wurden mit v0.1.3 live bestätigt. Der neue rollierende Verlauf und die HTML-Kachel sind offline simuliert und müssen in der Visualisierung überprüft werden.

## SymBox-/PHP-Architektur

Die mitgelieferte Montgomery-BigInteger-Routine verwendet auf PHP mit 64-Bit-Integern 24-Bit-Limbs und auf 32-Bit-PHP automatisch 15-Bit-Limbs. Beide Varianten erzeugten in lokalen Tests dieselbe Cognito-Challenge-Signatur. Keine GMP-/BCMath-Installation nötig. Die konkrete PHP-Ausführung auf der SymBox wird vom automatischen Ersttest geprüft.

## Nachprüfung v0.1.2

- Status und Diagnose im Konfigurationsformular auf das aktuelle `caption`-Feld umgestellt.
- Die Seriennummer ist optional und leer; bei einem einzelnen Zähler erfolgt die Zuordnung automatisch. Nach Änderungen wird eine alte Zählerzuordnung nicht blind wiederverwendet.
- Verhalten bei HTTP 400 und bei Konten ohne aktivierte Verbrauchsdaten separat diagnostiziert.
- Weiterhin keine LIVE-Verifikation ohne freigeschaltetes BASIC-Konto.

## Nachprüfung v0.1.3

- Archivkonfiguration: Beim Korrigieren des Zählertyps wird `IPS_ApplyChanges` sicher ausgeführt.
- Keine persönliche Zähler-Seriennummer mehr im öffentlichen GitHub-Quelltext oder in Tests. Bei Einzelzähler automatische Auswahl.
- Bestehende Modul-GUIDs und Variablen-Idents aus v0.1.2 unverändert.

## Nachprüfung v0.1.4

- Neue reine Rechenbibliothek `libs/Consumption.php`, eigene Grenz-/Lücken-/Nullverbrauch-Tests.
- Rechenwerte und responsive Symcon-Webinhalt-Kachel (keine Cloud-Erweiterung, unveränderter BASIC-API-Zugriff).
- Die neue HTML-Kachel enthält nur aggregierte Verbräuche und nie Anmeldedaten.
- Testlauf: `php tests/consumption_tests.php`, `php tests/module_smoke.php`, `php tests/unit_tests.php`, `php tests/api_protocol.php`.