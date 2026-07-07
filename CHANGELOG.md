# Changelog

Alle nennenswerten Änderungen an diesem Modul werden hier dokumentiert.
Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [1.4.0] - 2026-07-07

### Added
- Optionale zweite Quelle pro Kanal: Verknüpfung (Nur Quelle 1 / Addition / Subtraktion) und Invertieren, wirkt auf den Momentanwert **vor** der Pufferung/Mittelung. Ermöglicht z. B. einen PV-Überschuss-Mittelwert (−(Erzeugung + Bezug)) mit einem einzigen Kanal statt zwei getrennten Mittelwerten plus externer Kombination.

## [1.3.1] - 2026-07-03

### Fixed
- Profil der Quellvariable wird nur übernommen, wenn es selbst vom Typ Float ist — Quellen mit Integer-/Boolean-Profil (z. B. `GoodweET.WattEMS`) führten sonst zum Fehler "Variablentyp und Profiltyp stimmen nicht überein" beim Übernehmen

## [1.3.0] - 2026-07-03

### Added
- Weitere Berechnungsmethoden je Kanal: Median, Minimum, Maximum, Standardabweichung, Exponentiell gewichteter Mittelwert (EMA), Summe
- Doku-Panel neben der Kanal-Liste im Konfigurationsformular

## [1.2.0] - 2026-07-03

### Added
- Fenster-Dauer wählbar in Sekunden, Minuten, Stunden oder Tagen (statt nur Minuten)
- Zeitgewichteter Mittelwert als zusätzliche Berechnungsmethode

### Fixed
- Zeilen der Kanal-Liste sind jetzt per Drag & Drop sortierbar (`changeOrder` im Formular ergänzt)
- Variablen-Zuordnung erfolgt über gespeicherte Objekt-IDs statt Ident-Suche — bleibt dadurch auch beim Verschieben einer Mittelwert-Variable in den Objektbaum einer *anderen* Instanz korrekt erhalten

## [1.1.0] - 2026-07-03

### Added
- Mittelwert-Variable übernimmt automatisch das Profil (Einheit/Format) der Quellvariable

### Fixed
- Stabile Kanal-Kennung statt Listenposition — beim Umsortieren der Kanäle werden Variable und Ringpuffer nicht mehr vertauscht
- Variablen dürfen in Unterkategorien der eigenen Instanz verschoben werden, ohne dass beim nächsten Übernehmen eine doppelte Variable entsteht

## [1.0.0] - 2026-07-03

### Added
- Initiales Modul: konfigurierbare Mittelwert-Kanäle (Bezeichnung, Quelle, Fenster in Minuten)
- Archivfreie Berechnung über einen versteckten Ringpuffer (kein `AC_GetLoggedValues` nötig)
