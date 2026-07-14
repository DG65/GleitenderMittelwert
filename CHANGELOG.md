# Changelog

Alle nennenswerten Änderungen an diesem Modul werden hier dokumentiert.
Format angelehnt an [Keep a Changelog](https://keepachangelog.com/de/1.0.0/).

## [1.7.1] - 2026-07-11

### Changed
- Autor in `library.json` von "Dietmar Gureth" auf "DG65" geändert

## [1.7.0] - 2026-07-11

### Added
- Formeln für jede der 8 Berechnungsmethoden im Doku-Panel und in der README ergänzt
- Kleines Diagramm je Berechnungsmethode (im Formular als eingebettetes Bild, in der README als Datei unter `docs/img/`) zur Veranschaulichung

## [1.6.0] - 2026-07-09

### Changed
- Profil-Zuweisung erfolgt jetzt nur noch bei der Erstanlage der Mittelwert-Variable (als Standardprofil über `RegisterVariableFloat`), statt bei jedem `ApplyChanges` per `IPS_SetVariableCustomProfile` zu überschreiben (Symcon-Review-Feedback: benutzerdefinierte Profile sind die Hoheit des Nutzers)

## [1.5.4] - 2026-07-09

### Changed
- Spalte "Quelle" nutzt jetzt `width: auto` und füllt den verbleibenden Platz der Liste — bessere Lesbarkeit langer Variablenpfade, ohne Pixel-Summen von Hand abstimmen zu müssen

## [1.5.3] - 2026-07-09

### Fixed
- Prozent-Spaltenbreiten aus 1.5.2 zurückgenommen — führten zu einer Liste, die breiter als der Bildschirm war, und schoben "Nachkommastellen" aus dem sichtbaren Bereich. Zurück zu festen, kompakteren Pixel-Breiten (Summe ~1160px), damit alle Spalten ohne horizontales Scrollen sichtbar sind

## [1.5.2] - 2026-07-09

### Changed
- Spaltenbreiten der Kanal-Liste auf Prozentwerte umgestellt, damit die Liste immer die volle Breite ausfüllt (gleiche Breite wie das Doku-Panel), unabhängig von der Bildschirmgröße

## [1.5.1] - 2026-07-09

### Changed
- Formular-Layout überarbeitet: Kanal-Liste geht wieder über die volle Breite (kein Nebeneinander mit dem Doku-Panel mehr), Doku-Panel steht jetzt oben und ist standardmäßig eingeklappt

## [1.5.0] - 2026-07-09

### Added
- Nachkommastellen pro Kanal konfigurierbar (0–4 oder "Automatisch"). Erzeugt bei fester Anzahl ein eigenes Nachkommastellen-Profil und überschreibt damit die automatische Profil-Übernahme — hilft besonders, wenn die Quellvariable selbst kein Profil besitzt (Standardformatierung zeigt sonst viele Nachkommastellen)

## [1.4.1] - 2026-07-07

### Fixed
- `vendor` in module.json auf leeren String korrigiert (Symcon-Review-Feedback): das Feld ist für den Hersteller des angebundenen Systems vorgesehen, nicht den Modulautor — für ein reines Hilfsmodul ohne angebundenes Fremdsystem bleibt es leer

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
