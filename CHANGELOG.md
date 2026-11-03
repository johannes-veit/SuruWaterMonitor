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