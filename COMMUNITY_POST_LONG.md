# Neues Modul: Gleitender Mittelwert (Mittelwertberechnungen)

Hallo zusammen,

ich möchte euch mein neues Modul vorstellen: **Gleitender Mittelwert**, verfügbar über den Module Store oder direkt per GitHub-URL.

**GitHub:** https://github.com/DG65/GleitenderMittelwert
**Lizenz:** MIT
**Aktuelle Version:** siehe [CHANGELOG](https://github.com/DG65/GleitenderMittelwert/blob/main/CHANGELOG.md)

## Die Ausgangslage

Wer PV-Anlagen, Wärmepumpen oder einfach nur ein paar Sensoren betreibt, kennt das Problem: Rohe Live-Messwerte springen munter hin und her — eine Wolke zieht vorbei, ein Verbraucher schaltet kurz ein, ein Sensor rauscht. Für Anzeige oder gar Steuerungsentscheidungen ist das oft zu viel Rauschen.

Der klassische Weg in IP-Symcon ist ein Skript mit `AC_GetLoggedValues`, das die letzten N Minuten aus dem Archiv zieht und mittelt. Das funktioniert — hat aber zwei Nachteile, die mich irgendwann gestört haben:

1. Es hängt vollständig vom Archiv ab. Ist das Logging für die Quelle nicht aktiv, das Archiv-Modul gerade nicht verfügbar oder die Instanz neu angelegt, gibt es keinen Wert.
2. Jede einzelne Berechnung fragt die komplette Archiv-Datenbank ab — für eine simple laufende Mittelung eigentlich unnötig viel Aufwand, gerade wenn man mehrere solcher Werte braucht.

Ich hatte mehrere solcher Skripte über die Jahre verstreut (Außentemperatur über 1h und 24h, Solareinstrahlung über 15 Minuten, PV-Erzeugung über 10 Minuten, ein PV-Überschusswert aus zwei verrechneten Quellen …). Jedes ein eigenes Ereignis, jedes mit copy-paste-Code. Das wollte ich konsolidieren.

## Die Lösung

Das Modul sampelt den Live-Wert der Quelle in einem festen, konfigurierbaren Takt und hält die letzten Werte in einem kleinen, versteckten Ringpuffer (JSON in einer String-Variable). Bei jedem Tick werden Einträge außerhalb des gewünschten Zeitfensters verworfen und der Mittelwert aus dem Rest neu berechnet. Kein Archiv-Zugriff, keine Abhängigkeit von Logging-Einstellungen — es braucht nur den aktuellen Wert der Quellvariable.

Eine Instanz kann beliebig viele solcher „Kanäle" verwalten. Jeder Kanal ist eine Zeile in einer Konfigurationsliste: Bezeichnung, Quelle, Fenster-Dauer, Berechnungsmethode. Das Modul legt pro Kanal automatisch zwei Variablen an — den Mittelwert (sichtbar, mit automatisch übernommenem Profil/Einheit der Quelle) und den Ringpuffer (versteckt, rein intern).

## Acht Berechnungsmethoden

Nicht überall ist der klassische arithmetische Mittelwert die beste Wahl, daher stehen pro Kanal zur Auswahl:

- **Arithmetisch** – jeder Messpunkt zählt gleich viel. Der Standardfall.
- **Zeitgewichtet** – jeder Wert zählt proportional zu der Zeitspanne, in der er galt. Unempfindlich gegen verpasste Ticks, Neustarts oder unregelmäßige Update-Intervalle der Quelle.
- **Median** – robust gegen einzelne Ausreißer, die den arithmetischen Mittelwert verzerren würden.
- **Minimum / Maximum** – z. B. die Spitzenlast der letzten 10 Minuten oder der niedrigste SOC-Wert über Nacht.
- **Standardabweichung** – ein Maß dafür, wie „unruhig" ein Wert gerade ist.
- **Exponentiell gewichtet (EMA)** – neuere Werte zählen stärker als ältere, das Fenster dient als Zeitkonstante. Reagiert schneller auf echte Änderungen als ein starres Zeitfenster.
- **Summe** – aufsummierte statt gemittelte Werte, für Fragen wie „wie viel Energie in der letzten Stunde" statt „wie viel Leistung im Schnitt".

## Zwei Quellen verrechnen

Ein Fall, der mich ursprünglich zum Umbauen gebracht hat: ein PV-Überschusswert, der sich aus zwei Variablen ergibt (z. B. `-(Erzeugung + Netzbezug)`). Statt zwei getrennte Mittelwerte zu bilden und die Ergebnisse hinterher zu kombinieren (was bei unterschiedlicher Abtastung leicht ungenau wird), kann man im Modul eine zweite Quelle angeben, dazu eine Verknüpfung (Addition/Subtraktion) und optional Invertieren. Die Verrechnung passiert **vor** der Mittelung, auf Basis des jeweils aktuellen Momentanwerts — ein einziger Kanal reicht für so einen zusammengesetzten Wert.

## Sonstiges

- Kanal-Zeilen lassen sich per Drag & Drop umsortieren, ohne dass Variablen oder Puffer durcheinandergeraten.
- Die erzeugten Variablen dürfen frei im Objektbaum verschoben werden, auch in die Kategorie einer völlig anderen Instanz — die Zuordnung läuft über die Objekt-ID, nicht über den Ort im Baum.
- Konfigurierbares Abtastintervall pro Instanz.

## Installation

- **Module Store:** Modulverwaltung → Hinzufügen → nach „Mittelwertberechnungen" suchen
- **Per URL:** Modulverwaltung → Hinzufügen → `https://github.com/DG65/GleitenderMittelwert`

Danach eine Instanz vom Typ „Gleitender Mittelwert" anlegen und Kanäle konfigurieren.

Ich freue mich über Rückmeldungen, Fehlerberichte und Ideen für weitere Berechnungsarten oder Funktionen!
