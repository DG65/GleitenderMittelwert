# Gleitender Mittelwert (RollingAverage)

IP-Symcon-Modul zur Berechnung gleitender Mittelwerte beliebiger Variablen — konfigurierbar über eine Liste von Kanälen, ganz ohne Abhängigkeit vom Archiv-System.

## Wofür braucht man das?

Rohe Live-Messwerte schwanken oft stärker, als es für Anzeige oder Steuerung sinnvoll ist. Ein gleitender Mittelwert glättet solche Schwankungen, ohne auf eine feste Zeitreihe (Stunde/Tag) beschränkt zu sein wie bei der Archiv-Aggregation. Typische Anwendungsfälle:

- **PV-/Batterieleistung glätten**: Wolken oder kurzzeitige Lastspitzen lassen die Momentanleistung springen; ein 5- oder 10-Minuten-Mittel gibt ein ruhigeres, aussagekräftigeres Bild für Anzeige oder Kachel.
- **Steuerungsentscheidungen entkoppeln**: Ein EMS, das z. B. Batterieladung an die Netzeinspeisung koppelt, sollte nicht auf jede Sekundenspitze reagieren (Regel-"Flattern", unnötige Schaltzyklen) — ein gemittelter Wert als Entscheidungsgrundlage ist stabiler.
- **Sensorrauschen filtern**: Temperatur-, Einstrahlungs- oder Leistungssensoren mit Messrauschen liefern über ein Zeitfenster gemittelt einen deutlich saubereren Wert.
- **Trends statt Momentaufnahmen**: Eine 24-Stunden-Außentemperatur oder ein 15-Minuten-Solar­einstrahlungswert zeigt die Tendenz, ohne dass ein einzelner Ausreißer die Anzeige verzerrt.
- **Überschussberechnung**: Mehrere geglättete Werte lassen sich kombinieren (z. B. PV-Überschuss = gemittelte Erzeugung minus gemittelter Verbrauch), ohne dass Momentan-Rauschen beider Seiten sich gegenseitig verstärkt.

## Warum kein Archiv?

Der naheliegende Ansatz für einen gleitenden Mittelwert ist `AC_GetLoggedValues` über die letzten N Minuten. Das hat in der Praxis zwei Nachteile:

- Er hängt von einem funktionierenden, korrekt konfigurierten Archiv ab (Logging muss für die Quelle aktiv sein, das Archiv-Modul muss verfügbar sein).
- Jede Berechnung fragt die komplette Archiv-Datenbank ab — unnötige Last für eine einfache laufende Mittelung.

Dieses Modul sampelt stattdessen den aktuellen Live-Wert der Quelle in einem festen Takt und hält die Werte in einem kleinen, versteckten Ringpuffer (JSON in einer String-Variable). Alte Einträge außerhalb des Fensters werden bei jedem Tick verworfen. Das funktioniert unabhängig davon, ob die Quelle überhaupt archiviert wird.

## Installation

1. In der IP-Symcon-Konsole: **Modulverwaltung → Hinzufügen** und die URL dieses Repositories eintragen: `https://github.com/DG65/GleitenderMittelwert`
2. Eine neue Instanz vom Typ **„Gleitender Mittelwert"** anlegen.

## Konfiguration

| Feld | Bedeutung |
|---|---|
| Abtastintervall (Sekunden) | Wie oft die Quelle abgefragt und der Puffer aktualisiert wird (instanzweit, für alle Kanäle gleich) |
| Mittelwert-Kanäle | Liste der zu berechnenden Mittelwerte |

Pro Kanal:

| Spalte | Bedeutung |
|---|---|
| Bezeichnung | Name der erzeugten Mittelwert-Variable |
| Quelle | Die zu mittelnde Variable |
| Quelle 2 (optional) | Zweite Variable, die vor der Mittelung mit Quelle 1 verrechnet wird |
| Verknüpfung | Nur Quelle 1 / Quelle1 + Quelle2 / Quelle1 − Quelle2 |
| Invertieren | Kehrt das Vorzeichen des verrechneten Momentanwerts um, bevor er in den Puffer geht |
| Fenster | Zahlenwert der Fenster-Dauer |
| Einheit | Sekunden / Minuten / Stunden / Tage |
| Methode | Berechnungsart (siehe unten) |
| Nachkommastellen | „Automatisch" übernimmt das Profil der Quelle (falls vorhanden); eine feste Zahl (0–4) überschreibt das und greift auch, wenn die Quelle kein Profil hat |

Zeilen können per Drag & Drop umsortiert werden.

### Zwei Quellen verrechnen

Soll ein Mittelwert aus der Verknüpfung zweier Variablen gebildet werden (z. B. ein PV-Überschuss = −(Erzeugung + Bezug)), sollte man **erst verrechnen, dann mitteln** — nicht zwei getrennte Mittelwerte bilden und danach kombinieren. Das Modul macht genau das: Quelle 2, Verknüpfung und Invertieren wirken auf den Momentanwert, bevor er in den Ringpuffer geschrieben wird. Es reicht dafür ein einziger Kanal.

### Berechnungsmethoden

Alle Methoden arbeiten auf denselben gepufferten Werten im konfigurierten Fenster — nur die Art der Zusammenfassung unterscheidet sich.

- **Arithmetisch**: Jeder gesampelte Wert zählt gleich viel (Summe geteilt durch Anzahl). Einfach und für die meisten Fälle ausreichend, solange das Abtastintervall zuverlässig eingehalten wird.
- **Zeitgewichtet**: Jeder Wert zählt proportional zu der Zeitspanne, in der er tatsächlich galt (bis zum nächsten Sample bzw. bis jetzt beim letzten). Dadurch verzerren verpasste Ticks, ein Neustart der Instanz oder unregelmäßige Abtastung das Ergebnis nicht — der Mittelwert entspricht dem tatsächlichen Zeitintegral über das Fenster, nicht nur dem Durchschnitt der Stichproben. Im Regelfall liefern Arithmetisch und Zeitgewichtet nahezu identische Ergebnisse; der Unterschied wird erst bei unregelmäßiger Taktung relevant.
- **Median**: Der mittlere Wert der sortierten Stichproben. Robust gegen einzelne Ausreißer/Messfehler — ein kurzer Spike verzerrt den Wert nicht wie beim arithmetischen Mittel.
- **Minimum** / **Maximum**: Kleinster bzw. größter Wert im Fenster — z. B. Spitzenlast der letzten 10 Minuten oder niedrigster SOC-Wert über Nacht.
- **Standardabweichung**: Maß für die Schwankung/das Rauschen der Werte im Fenster selbst, nicht für deren mittleres Niveau.
- **Exponentiell (EMA)**: Neuere Werte zählen stärker als ältere (exponentiell abklingende Gewichtung). Das Fenster dient dabei als Zeitkonstante — größeres Fenster = träger reagierender Wert. Reagiert schneller auf echte Änderungen als ein starres Zeitfenster.
- **Summe**: Aufsummierte Werte im Fenster statt eines Mittelwerts — beantwortet eine andere Frage (z. B. Gesamtenergie der letzten Stunde statt Durchschnittsleistung).

Pro Kanal legt das Modul zwei Variablen an:

- **Mittelwert** — die eigentliche Ausgabevariable, übernimmt automatisch das Profil (Einheit/Format) der Quellvariable.
- **Puffer** (versteckt) — interner Ringpuffer, nicht zur direkten Verwendung gedacht.

Beide Variablen dürfen frei im Objektbaum verschoben werden (auch in die Kategorie einer anderen Instanz) — das Modul verfolgt sie über ihre Objekt-ID, nicht über ihren Ort im Baum.

## Technische Hinweise

- Die Zuordnung Kanal → Variablen-ID liegt in einem internen Attribut, **nicht** in der sichtbaren Konfigurationsliste. Würde man sie dort ablegen, würde das in IP-Symcon das Drag & Drop der Liste sperren.
- Der Schlüssel eines Kanals ergibt sich aus Bezeichnung + Quelle. Ändert man eine der beiden bewusst, legt das Modul eine neue Variable an und räumt die alte auf — reines Umsortieren der Zeilen ändert daran nichts.
- Existierende Kanäle aus einer älteren Modulversion (nur „Fenster in Minuten", ohne Einheit) funktionieren unverändert weiter (Rückwärtskompatibilität).

## Lizenz

MIT, siehe [LICENSE](LICENSE).
