[GleitenderMittelwert] Konfigurierbares Modul für gleitende Mittelwerte (archivfrei)

Hallo zusammen,

ich möchte euch ein kleines Modul vorstellen, das ich für ein wiederkehrendes Problem gebaut habe: gleitende Mittelwerte beliebiger Variablen berechnen, ohne dafür jedes Mal ein eigenes Skript oder Ereignis zu bauen.

**GitHub:** https://github.com/DG65/GleitenderMittelwert
**Lizenz:** MIT

## Wofür?

Rohe Live-Messwerte schwanken oft stärker, als es für Anzeige oder Steuerung sinnvoll ist. Ein gleitender Mittelwert glättet das, ohne auf eine feste Zeitreihe (Stunde/Tag) beschränkt zu sein wie bei der Archiv-Aggregation. Typische Fälle:

- PV-/Batterieleistung glätten (Wolken, kurzzeitige Lastspitzen)
- Steuerungsentscheidungen entkoppeln (kein Schaltzyklen-„Flattern" durch Sekundenspitzen)
- Sensorrauschen filtern (Temperatur, Einstrahlung, Leistung)
- Trends statt Momentaufnahmen (z. B. 24h-Außentemperatur)
- PV-Überschuss = −(Erzeugung + Bezug), gemittelt über ein paar Minuten

## Warum kein Archiv?

Der naheliegende Ansatz wäre `AC_GetLoggedValues` über die letzten N Minuten. Das hat zwei Nachteile: es hängt von einem funktionierenden, korrekt konfigurierten Archiv ab, und jede Berechnung fragt die komplette Archiv-Datenbank ab — unnötige Last für eine einfache laufende Mittelung.

Das Modul sampelt stattdessen den Live-Wert der Quelle in einem festen Takt und hält die Werte in einem kleinen, versteckten Ringpuffer (JSON in einer String-Variable). Alte Einträge außerhalb des Fensters werden bei jedem Tick verworfen. Funktioniert unabhängig davon, ob die Quelle überhaupt archiviert wird.

## Features

- Beliebig viele Kanäle pro Instanz, jeder mit eigener Quelle und Fenster-Dauer (Sekunden/Minuten/Stunden/Tage)
- Zwei Quellen pro Kanal verrechenbar (Addition/Subtraktion + Invertieren), **bevor** gemittelt wird — z. B. für einen PV-Überschuss-Mittelwert mit nur einem Kanal
- 8 Berechnungsmethoden: Arithmetisch, Zeitgewichtet (robust bei unregelmäßiger Taktung), Median (robust gegen Ausreißer), Minimum, Maximum, Standardabweichung, Exponentiell gewichtet (EMA), Summe
- Mittelwert-Variable übernimmt automatisch das Profil (Einheit/Format) der Quelle
- Zeilen der Kanal-Liste per Drag & Drop sortierbar
- Variablen dürfen frei im Objektbaum verschoben werden (auch in die Kategorie einer anderen Instanz) — die Zuordnung erfolgt über die Objekt-ID, nicht über den Ort im Baum

## Installation

**Über den Module Store:** Modulverwaltung → Hinzufügen → nach „Mittelwertberechnungen" bzw. „Gleitender Mittelwert" suchen und installieren.

**Alternativ per URL:** Modulverwaltung → Hinzufügen → `https://github.com/DG65/GleitenderMittelwert`

Danach:
1. Neue Instanz vom Typ „Gleitender Mittelwert" anlegen
2. Kanäle konfigurieren (Bezeichnung, Quelle, ggf. Quelle 2 + Verknüpfung, Fenster, Methode)

Über Feedback, Fehlermeldungen oder Ideen freue ich mich!
