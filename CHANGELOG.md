## 0.1.6 (2026-11-04)

- Verhindert, dass ein Archiv-Nullwert den 24h-Verbrauch zum Gesamtzählerstand werden lässt.
- Summiert echte tägliche Differenzen; 7, 30 und 365 Tage zeigen bereits bei kurzer Historie sinnvolle Teilsummen.
- Keine Hochrechnung bei fehlenden Tagesintervallen; Nullverbrauch wird korrekt berücksichtigt.
- Historische Werte werden beim Modulupdate erneut berechnet, ohne das Archiv zu verändern.
- Zusätzliche Tests: 10 Tageswerte, Nullwert-Ausreißer, Archiv-Neuberechnung und Datenlücken.

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