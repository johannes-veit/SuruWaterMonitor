## Stand v0.1.6 – Korrektur der Verbrauchsberechnung (04.11.2026)

**Nur normale Symcon-Variablen.** Die bisherige 24-h-Berechnung konnte einen automatisch protokollierten Nullwert als echten Zählerstand interpretieren. Jetzt werden ausschließlich plausible tägliche Differenzen zwischen gültigen aufeinanderfolgenden Zählerständen addiert.

- **24 Stunden:** Differenz der letzten beiden gültigen Messungen im Abstand von 18–30 Stunden, in Litern. Niemals der gesamte m³-Zählerstand.
- **7 Tage:** Summe der verfügbaren Tagesdifferenzen innerhalb der letzten sieben Tage.
- **30 und 365 Tage:** ebenfalls Summen der bereits verfügbaren Tagesdifferenzen; bei kürzerer Historie bewusst Teilsummen (z. B. 10 vorhandene Tage).
- **Fehlende Tage:** keine Hochrechnung, keine künstlichen Nulltage. Echte Tage ohne Verbrauch ergeben 0 L.
- **Archivschutz:** widersprüchliche Null-/Rücksprungwerte werden für die Berechnung verworfen; der bestehende Archivinhalt wird nicht verändert.
- **Update:** dieselbe Modulinstanz aktualisieren und einmal **Übernehmen** drücken. Bereits gespeicherte fehlerhafte Variablenwerte werden aus der Historie neu berechnet. Zugangsdaten und Zählerregistrierung bleiben unverändert.

---

# SURU WaterMonitor BASIC – IP-Symcon 9.0

Inoffizielles, **rein lesendes** IP-Symcon-Modul zur Auswertung eines SURU-/WaterMonitor-Kontos. Entwickelt mit Fokus auf das **kostenlose BASIC-Konto** und die SymBox. Kein Home Assistant, keine zusätzliche Hardware, keine kostenpflichtigen SURU-Funktionen.

## Stand v0.1.5: Ausschließlich normale Symcon-Variablen

**Kein HTML, keine Webinhalt-Variable und keine externe Webansicht.**

Die gewünschte Darstellung erfolgt ausschließlich durch das gezielte Ein-/Ausblenden der normalen Statusvariablen in der Standard-Visualisierung / WebFront. Der alte HTML-Ausgang `Dashboard` aus v0.1.4 wird beim Modul-Update in `ApplyChanges()` über `UnregisterVariable` entfernt (nur wenn er existiert). Bestehende Zugangsdaten, Zählerstände, Messzeitpunkte, Archive und GUIDs bleiben unverändert.

Für die Visualisierung sind besonders geeignet:

- `Meter` (Float) – letzter Gesamtzählerstand in m³
- `MeasuredAt` (String) – Datum und Uhrzeit der tatsächlichen letzten Zählermessung
- `Consumption24h` (Float) – Verbrauch zwischen zwei Messungen in 18–30 Stunden Abstand
- `Consumption7d` (Float) – circa letzte 7 Tage
- `Consumption30d` (Float) – circa letzte 30 Tage
- `Consumption365d` (Float) – circa letzte 365 Tage

Optional bleiben `Delta` (Liter seit letzter Messung) und `Month` (Liter seit erster Messung im aktuellen Monat) sowie Diagnose/Status verfügbar.

**Messgenauigkeit:** Alle Fenster enden mit dem letzten tatsächlichen Messzeitpunkt, nicht mit dem Cloud-Abruf. Für 7/30/365 Tage gilt eine Toleranz von ±18 Stunden am ältesten Vergleichspunkt; die Werte sind daher Näherungen aus Tagesablesungen. Der Wert 24h wird nur bei 18–30 Stunden Messabstand berechnet. Keine Hochrechnung ohne Daten.

**Datenverfügbarkeit:** Ein erstmals angelegter Zeitraum wird bis zur ersten gültigen Berechnung in der Objektansicht verborgen und danach genau einmal automatisch eingeblendet. Ab dann werden manuelle Sichtbarkeitseinstellungen ausdrücklich respektiert, auch bei Modulupdates und weiteren Abfragen. Bereits vom Benutzer ausgeblendete, zuvor berechnete Zeiträume bleiben ausgeblendet. Bei fehlender Historie wird kein neuer Nullwert geschrieben. Die Objektvariable kann technisch jedoch den Startwert 0 zeigen, falls man sie vor der ersten gültigen Messung manuell sichtbar macht; ob der Wert bereits berechnet werden konnte, ist im internen `PeriodReadiness`-Status hinterlegt.

**Historie:** Das vorhandene Symcon-Archiv (`History`) bleibt unverändert und wird nur mit bestätigten echten Zählerständen erweitert. Keine kostenpflichtigen BASIC-Endpunkte.

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
- Stand v0.1.5: Live-Anmeldung mit v0.1.3 bestätigt, rollierende Zeitfenster offline getestet; die Standard-Symcon-Variablen müssen nach dem Update in der Visualisierung kontrolliert werden.

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

## Nachprüfung v0.1.5

- Reine Statusvariablen statt Webinhalt-Kachel. Keine HTML/CSS-/Web-Rendering-Routine mehr.
- Altes `Dashboard` wird beim Aktualisieren der Instanz sicher deregistriert; das Messwertarchiv bleibt bestehen.
- Manuelle Sichtbarkeit der Verbrauchsvariablen wird nach der erstmaligen Datenverfügbarkeit nicht mehr durch regelmäßige Abfragen zurückgesetzt.
- Bestehende Zählernummer, Credentials, Modul-GUIDs und Variablen-Idents unverändert.