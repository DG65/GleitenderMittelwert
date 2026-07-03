<?php

class RollingAverage extends IPSModule
{
    private const UNIT_SECONDS = [0 => 1, 1 => 60, 2 => 3600, 3 => 86400];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('TickInterval', 10);
        $this->RegisterPropertyString('Channels', '[]');

        // Zuordnung Kanal -> tatsächliche Variablen-IDs. Bewusst ein
        // Attribut (nicht Teil der Channels-Property!) — würde man das
        // stattdessen in die listengebundene Channels-Property zurück-
        // schreiben, sperrt das in IP-Symcon das Drag & Drop der Liste.
        $this->RegisterAttributeString('ChannelState', '[]');

        $this->RegisterTimer('Tick', 0, 'RAVG_Tick($_IPS[\'TARGET\']);');
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    // Stabiler Schlüssel aus Bezeichnung + Quelle. Ändert der Nutzer eine
    // von beiden bewusst, gilt das als neuer Kanal (alte Variable wird
    // aufgeräumt) — reines Umsortieren der Zeilen ändert den Schlüssel
    // dagegen nicht.
    private function ChannelKey(array $ch): string
    {
        return md5(($ch['Caption'] ?? '') . '|' . ($ch['SourceID'] ?? 0));
    }

    private function WindowSeconds(array $ch): int
    {
        $unit = (int)($ch['WindowUnit'] ?? 1);
        $mult = self::UNIT_SECONDS[$unit] ?? 60;
        // WindowMinutes = Kompatibilität mit Kanälen aus einer älteren Version
        $value = (float)($ch['WindowValue'] ?? ($ch['WindowMinutes'] ?? 10));
        return max(1, (int)round($value * $mult));
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $channels = json_decode($this->ReadPropertyString('Channels'), true);
        if (!is_array($channels)) {
            $channels = [];
        }

        $state = json_decode($this->ReadAttributeString('ChannelState'), true);
        if (!is_array($state)) {
            $state = [];
        }

        $newState = [];
        $seq = 0;
        foreach ($channels as $i => $ch) {
            $key = $this->ChannelKey($ch);
            $caption = ($ch['Caption'] ?? '') !== '' ? $ch['Caption'] : ('Mittelwert ' . ($i + 1));

            $entry = $state[$key] ?? [];
            $vid = (int)($entry['AvgID'] ?? 0);
            $bid = (int)($entry['BufID'] ?? 0);

            if (!$vid || !IPS_VariableExists($vid)) {
                $vid = $this->RegisterVariableFloat('avg_' . $seq, $caption, '', $i * 2);
            }
            IPS_SetName($vid, $caption);

            // Mittelwert bekommt dasselbe Profil (Einheit/Format) wie die Quelle
            $srcID = (int)($ch['SourceID'] ?? 0);
            if ($srcID && IPS_VariableExists($srcID)) {
                $srcVar = IPS_GetVariable($srcID);
                $profile = $srcVar['VariableCustomProfile'] !== '' ? $srcVar['VariableCustomProfile'] : $srcVar['VariableProfile'];
                if ($profile !== '') {
                    IPS_SetVariableCustomProfile($vid, $profile);
                }
            }

            if (!$bid || !IPS_VariableExists($bid)) {
                $bid = $this->RegisterVariableString('buf_' . $seq, $caption . ' (Puffer)', '', $i * 2 + 1);
                SetValueString($bid, '[]');
            }
            IPS_SetHidden($bid, true);

            $newState[$key] = ['AvgID' => $vid, 'BufID' => $bid];
            $seq++;
        }

        // Kanäle, deren Zeile gelöscht oder deren Bezeichnung/Quelle
        // geändert wurde, aufräumen — unabhängig davon, wohin die
        // zugehörigen Variablen im Baum verschoben wurden.
        foreach ($state as $key => $entry) {
            if (!isset($newState[$key])) {
                if (!empty($entry['AvgID']) && IPS_VariableExists($entry['AvgID'])) {
                    IPS_DeleteVariable($entry['AvgID']);
                }
                if (!empty($entry['BufID']) && IPS_VariableExists($entry['BufID'])) {
                    IPS_DeleteVariable($entry['BufID']);
                }
            }
        }
        $this->WriteAttributeString('ChannelState', json_encode($newState));

        if (count($channels) === 0) {
            $this->SetTimerInterval('Tick', 0);
            $this->SetStatus(104);
            return;
        }

        $this->SetTimerInterval('Tick', $this->ReadPropertyInteger('TickInterval') * 1000);
        $this->SetStatus(102);
    }

    public function Tick()
    {
        $channels = json_decode($this->ReadPropertyString('Channels'), true);
        if (!is_array($channels)) {
            return;
        }
        $state = json_decode($this->ReadAttributeString('ChannelState'), true);
        if (!is_array($state)) {
            return;
        }

        $now = time();
        foreach ($channels as $ch) {
            $key = $this->ChannelKey($ch);
            $entry = $state[$key] ?? null;
            if (!$entry) {
                continue;
            }

            $srcID = (int)($ch['SourceID'] ?? 0);
            $windowSec = $this->WindowSeconds($ch);
            $vid = (int)$entry['AvgID'];
            $bid = (int)$entry['BufID'];

            if (!$srcID || !IPS_VariableExists($srcID)) {
                continue;
            }
            if (!$vid || !IPS_VariableExists($vid) || !$bid || !IPS_VariableExists($bid)) {
                continue;
            }

            $buffer = json_decode(GetValueString($bid), true);
            if (!is_array($buffer)) {
                $buffer = [];
            }

            $buffer[] = [$now, (float)GetValue($srcID)];
            $buffer = array_values(array_filter($buffer, function ($e) use ($now, $windowSec) {
                return ($now - $e[0]) <= $windowSec;
            }));

            SetValueString($bid, json_encode($buffer));

            $mode = (int)($ch['Mode'] ?? 0);
            $avg = $this->ComputeAverage($buffer, $mode, $now);
            if ($avg !== null) {
                SetValueFloat($vid, $avg);
            }
        }
    }

    // Mode 0 = arithmetisch (jeder Messpunkt zählt gleich viel).
    // Mode 1 = zeitgewichtet (jeder Messpunkt zählt proportional zu der
    // Zeitspanne, in der sein Wert galt — bis zum nächsten Sample bzw.
    // bis jetzt beim letzten Sample). Robuster bei unregelmäßiger
    // Taktung (verpasste Ticks, Neustart, unterschiedliche Update-
    // Intervalle der Quelle).
    private function ComputeAverage(array $buffer, int $mode, int $now): ?float
    {
        $count = count($buffer);
        if ($count === 0) {
            return null;
        }

        if ($mode !== 1) {
            $sum = array_sum(array_column($buffer, 1));
            return $sum / $count;
        }

        $weightedSum = 0.0;
        $totalWeight = 0.0;
        for ($i = 0; $i < $count; $i++) {
            $t = $buffer[$i][0];
            $v = $buffer[$i][1];
            $tNext = ($i + 1 < $count) ? $buffer[$i + 1][0] : $now;
            $weight = max(0, $tNext - $t);
            $weightedSum += $v * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight > 0) {
            return $weightedSum / $totalWeight;
        }
        // Nur ein Sample ohne Zeitspanne (z.B. gerade erst angelegt)
        return $buffer[$count - 1][1];
    }
}
