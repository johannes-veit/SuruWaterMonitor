# SURU WaterMonitor BASIC – IP-Symcon 9.0

## v0.1.8 – Gepruefte Verrechnung der Einbaukorrektur (27.10.2026)

**Wichtig:** Die fruehere Version v0.1.7 liess den gesamten Anfangsverbrauch abrupt entfallen, sobald das Einbaudatum aus einem Zeitfenster fiel. Das ist bei einer anfangs fehlenden Tageshistorie rechnerisch zu grob. In v0.1.8 gilt stattdessen:

- Die Messwerte werden nie durch eine feste Korrektur **addiert**. 30/365 Tage ergeben sich jeweils aus dem aktuellen kumulativen Stand minus dem Stand an der Fenstergrenze.
- Liegt der Einbau am 27.10.2026 innerhalb der letzten 30 bzw. 365 Tage, wird der bekannte Einbaustand 0,000 m³ verwendet: fuer 05.11.2026 ergibt das 1.949 Liter.
- Wandert die Fenstergrenze in die ungemessene Phase vom **27.10. bis 03.11.2026**, wird deren 1.715-Liter-Verbrauch **gleichmaessig auf die Zeit verteilt – nur als Naeherung**, weil die echten Tagesverbraeuche nicht bekannt sind. Dadurch faellt der fehlende Anteil **anteilig** aus dem Fenster, statt sprunghaft.
- **30 Tage:** Die Naeherung beginnt um den 26.11.2026 (je nach Messzeit) abzunehmen und ist ab etwa 03.12.2026 vollstaendig verschwunden. **365 Tage:** entsprechend ab etwa 27.10.2027 bis 03.11.2027. Die Startkorrektur wird danach nicht mehr verwendet.
- **7 Tage** und **24 Stunden** bleiben die beobachteten echten Zaehlermessungen. Bei unvollstaendiger Historie kann der 7-Tage-Wert eine Teilsumme sein. 24h akzeptiert nur zwei genuine Messungen in etwa taeglichem Abstand (18–30h).
- Bei fehlenden einzelnen Tagesabrufen wird die **bekannte Differenz der Zaehlerstaende ueber die Datenluecke** beruecksichtigt (ohne vorgetaeuschte Einzel-Tageswerte). Nur fuer die Grenzzeit des Fensters wird bei ausreichend nahen Tagesmessungen interpoliert.
- Archivierte standardmaessige Nullwerte, Rueckspruenge und widerspruechliche Zeitstempel werden verworfen. Das Roharchiv selbst wird nicht veraendert.
- Die LIVE-Diagnose listet fuer jeden Zeitraum die Berechnungsbasis auf. **Schaetzung** bedeutet: fehlende Verbrauchsverteilung vor Beginn der Cloud-Aufzeichnung, nicht exakte Tagesmessung.
- Kein HTML, alle Werte bleiben normale IP-Symcon-Variablen. Der Startstand ist weiterhin optional und pro Instanz konfigurierbar. Unter Optionale Einstellungen fuer diesen Zaehler: Startwert aktiv, Datum `2026-10-27`, Stand `0.000 m³`.

---

## Stand v0.1.7: Einbaustand ohne Archiv-Rueckdatierung

**Neu:** Optionaler Startstand beim Einbau. Fuer einen nachweislich bei 0,000 m³ neu eingebauten Zaehler, bei dem die Symcon-Aufzeichnung erst spaeter begann, wird der lange Zeitraum automatisch mit dem Einbaustand aufgefuellt, **sofern das Einbaudatum innerhalb des jeweiligen 7-/30-/365-Tage-Fensters liegt**. Der Startwert wird niemals als gefaelschte tagesgenaue Messung ins Archiv geschrieben. Sind bereits Tagesverbraeuche vorhanden, wird `letzter Zaehlerstand - Einbaustand` verwendet, NICHT nochmals die Tageswerte darauf addiert. 24h bleibt die reine Messdifferenz.

**Einmalige Einrichtung in bestehender Instanz** (unter *Optionale Einstellungen*): `Bekannten Zaehlerstand ... beruecksichtigen` aktivieren; Einbaudatum als `JJJJ-MM-TT` eingeben (beispielsweise `2026-10-23`, wenn der Einbau nur ungefaehr zwei Wochen vor dem 06.11.2026 erfolgte); Einbaustand `0,000 m³`. Das ungefaehre Datum ist eine Schätzung und wird nicht als exakte Historienmessung ausgegeben. Ohne diese ausdrueckliche Aktivierung bleiben die bisherigen Standardberechnungen fuer andere Nutzer unveraendert. Ohne Zugriff auf die SymBox kann GitHub diese instanzspezifische Einstellung nicht selber setzen.

Zum konkreten Zählerstand **1,949 m³ vom 05.11.2026 um 13:51** ergibt sich: **24h 96 L, 7 Tage 234 L, 30 Tage 1.949 L, 365 Tage 1.949 L**. Wenn das Einbaudatum aus dem 30-Tage-Fenster faellt, wird nicht versucht, die nicht taeglich beobachteten alten Verbrauchsmengen kuenstlich auf Tage zu verteilen. Aus der vorhandenen Tageshistorie werden weiterhin alle belegten Teilverbraeuche summiert.

Weiterhin nur lokale Standard-Symcon-Variablen, weder HTML noch kostenpflichtige SURU-Endpunkte. Bestehende Variablen-Idents, Zugangsdaten, Zaehlerbindung und Archiv bleiben erhalten.

# SURU WaterMonitor BASIC – IP-Symcon 9.0

## Stand v0.1.6: tägliche Differenzen und Teilzeiträume

- **Fehlerbehebung:** Der 24-h-Verbrauch wird nicht mehr aus einem irrtümlichen Nullwert im Archiv vom Gesamtzählerstand abgezogen. Nur plausible Differenzen zwischen echten Tageszählerständen (18–30 h Messabstand) werden gezählt.
- **7/30/365 Tage:** Summe aller in der jeweiligen Zeitspanne bereits tatsächlich verfügbaren täglichen Differenzen. Beispiel bei zehn erfassten Tagen: 7 Tage = sieben bekannte Tageswerte, 30 und 365 Tage = Summe aller zehn Tageswerte. Bereits ein gültiger Tag befüllt alle drei Summen.
- **Keine Hochrechnung:** Fehlende Tage werden weder als 0 Verbrauch dargestellt noch mit geschätztem Verbrauch aufgefüllt. Es handelt sich bei kurzer Historie ausdrücklich um **Teilsummen**.
- **Reparatur beim Update:** Die Modulinstanz berechnet alle vier Variablen beim nächsten **Übernehmen** erneut aus den vorhandenen Archivwerten; alte Fehlwerte werden überschrieben. Benutzer-Sichtbarkeit, Kontoanmeldung und Archiv bleiben unangetastet.
- **Keine Webkachel:** Ausschließlich normale Symcon-Variablen, separat in der Standard-Visualisierung auswählbar.


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

**Messgenauigkeit:** Alle Fenster enden mit dem letzten tatsächlichen Messzeitpunkt, nicht mit dem Cloud-Abruf. Für 7/30/365 Tage werden alle verfügbaren Tagesdifferenzen innerhalb der Zeitfenster addiert. Falls die Historie kürzer ist, erscheint eine Teilsumme; fehlende Tage werden nicht ergänzt. Der Wert 24h wird nur bei 18–30 Stunden Messabstand berechnet. Keine Hochrechnung ohne Daten.

**Datenverfügbarkeit:** Ein erstmals angelegter Zeitraum wird bis zum ersten gültigen Tagesintervall in der Objektansicht verborgen und danach genau einmal automatisch eingeblendet. Ab dann werden manuelle Sichtbarkeitseinstellungen ausdrücklich respektiert, auch bei Modulupdates und weiteren Abfragen. Bereits vom Benutzer ausgeblendete, zuvor berechnete Zeiträume bleiben ausgeblendet. Bei fehlender Historie wird kein neuer Nullwert geschrieben. Die Objektvariable kann technisch jedoch den Startwert 0 zeigen, falls man sie vor der ersten gültigen Messung manuell sichtbar macht; ob der Wert bereits berechnet werden konnte, ist im internen `PeriodReadiness`-Status hinterlegt.

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