## 0.1.7 (2026-11-06)

- Optionaler per-Instanz-Einbaustand (`UseInstallReading`, `InstallDate`, `InstallReadingM3`), standardmaessig deaktiviert.
- Bisher nicht durch Symcon erfasster Anfangsverbrauch wird fuer Zeitfenster ab Einbau ohne Doppelzaehlung beruecksichtigt. Die 24-Stunden- und 7-Tage-Werte bleiben bei Einbau vor mehr als 7 Tagen unveraendert.
- Einbaustand wird NICHT ins Messwertarchiv geschrieben, sondern nur rechnerisch als Vergleichspunkt verwendet.
- Strenge Datums- und Plausibilitaetsvalidierung, bestehende Messwerte und Sichtbarkeitseinstellungen unveraendert.
- Ende-zu-Ende-Test mit 1,715/1,853/1,949 m³ sowie Regressionstests gegen Rueckrechnung, doppelte Summen und falsches Datum.

## 0.1.6 (2026-11-04)

- 24h: Fehlerkorrektur bei automatisch archiviertem Nullwert; nur gültige 18–30h-Zählerdifferenz.
- 7/30/365 Tage: rollierende Summen sämtlicher bereits vorhandener Tagesdifferenzen, auch bei weniger als 7/30/365 Tagen Historie.
- Echte Tage ohne Verbrauch bleiben 0 L; fehlende Tage werden nicht hochgerechnet oder als gültiger Nulltag gezählt.
- Schutz gegen unsortierte/duplizierte/verfälschte/abfallende Archivwerte und Messlücken.
- Beim Update automatische Neuberechnung aus unverändertem Archiv; keine neue API, Instanz, Cloud-Registrierung oder HTML-Ansicht.
- Zusätzliche Grenzfall- und End-to-End-Tests mit eingefügtem Archiv-Nullwert und 10-Tage-Historie.

## 0.1.5 (2026-11-03)

- Die HTML-/Webinhalt-Kachel `Dashboard` wird nicht mehr erstellt; ältere Dashboard-Variable wird bei `ApplyChanges()` deregistriert.
- Wasserzählerstand, Messzeitpunkt und alle Verbrauchszeiträume bleiben normale Symcon-Variablen.
- Eigene Auswahl der eingeblendeten Werte im Standard-WebFront bleibt nach einmaliger Datenverfügbarkeit bestehen; weitere Abfragen überschreiben keine Sichtbarkeit.
- Kein Einfluss auf Archiv, Cloud-Anmeldung, bestehende Zählerzuordnung oder BASIC-Datenzugriff.
- Modultests um Migration und Benutzer-Sichtbarkeit erweitert.

## 0.1.4 (2026-11-03)

- Vier neue Verbrauchsvariablen (ca. 24 h, 7, 30 und 365 Tage) aus dem vorhandenen m³-Zählerarchiv.
- Responsive Webinhalt-Kachel (`Dashboard`) für Symcon Tile/WebFront mit Zählerstand, Messdatum/-uhrzeit und allen Verbrauchsperioden.
- Kein Scheinnullwert bei unvollständiger Archivhistorie, Näherungen aus Tagesablesungen gekennzeichnet.
- Keine neue Cloud-API und keine Änderung bestehender Variablen, Profile oder Module-GUIDs.
- Automatisierte zusätzliche Berechnungs-/Kacheltests.

## 0.1.3 – GitHub-Erstveröffentlichung (03.11.2026)
- Bugfix: Archiv-Zähleraggregation wird auch bei bereits aktivierter Protokollierung mit IPS_ApplyChanges übernommen.
- Datenschutz: persönliche Zählernummer nicht mehr hart kodiert oder öffentlich dokumentiert; Einzelzähler automatisch auswählbar.
- Modul-GUIDs und Variablen-Idents unverändert.

# Änderungsverlauf

## 0.1.2 – 03.11.2026

- Bestehende Modul-GUIDs, Eigenschaften und Variablen-Idents bleiben erhalten.
- Atomarer Poll-Semaphor gegen parallele Abrufe.
- Archiv-Erfolg und Zähleraggregation überprüft; fehlgeschlagene Speicherung
  führt nicht zu einem fälschlich akzeptierten Messwert.
- Fehlerabhängige Wiederholung mit 10 Minuten bei vorübergehenden Ausfällen;
  kein zusätzlicher Retry bei API-Rate-Limit oder Zugriffsverbot.
- Zähler-/Konto-Bindung gegen versehentliches Vermischen historischer Messwerte.
- Kürzere Diagnoseanzeige in der Konfiguration; vollständige Ausgabe als Variable.
- Letzter erfolgreicher Cloud-Abruf separat als Variable.
- Die nicht benötigte doppelte Formularbeschriftung wurde bereinigt.
- Erweiterte Offline-Tests für Netzwerkstörungen, Archivfehler und Wiederanlauf.

## 0.1.1 – 28.10.2026
- Symcon-Konfigurationsbeschriftungen, Seriennummer und Diagnose ergänzt.

## 0.1.0 – 28.10.2026
- Erste offline getestete Fassung.