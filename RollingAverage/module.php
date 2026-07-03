<?php

class RollingAverage extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('TickInterval', 10);
        $this->RegisterPropertyString('Channels', '[]');
        $this->RegisterAttributeInteger('NextRowID', 1);
        $this->RegisterAttributeString('KnownVarIDs', '[]');

        $this->RegisterTimer('Tick', 0, 'RAVG_Tick($_IPS[\'TARGET\']);');
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $channels = json_decode($this->ReadPropertyString('Channels'), true);
        if (!is_array($channels)) {
            $channels = [];
        }

        $nextID = $this->ReadAttributeInteger('NextRowID');
        $propChanged = false;

        foreach ($channels as $i => $ch) {
            // Jede Zeile bekommt EINMALIG eine feste RowID (nur für den
            // Ident-Namen der neu angelegten Variable relevant).
            if (empty($ch['RowID'])) {
                $channels[$i]['RowID'] = $nextID++;
                $propChanged = true;
            }
            $rid = $channels[$i]['RowID'];
            $caption = ($ch['Caption'] ?? '') !== '' ? $ch['Caption'] : ('Mittelwert ' . $rid);

            // Die tatsächliche Objekt-ID wird direkt in der Konfiguration
            // gespeichert (AvgID/BufID) — dadurch spielt es KEINE Rolle,
            // wohin der Nutzer die Variable im Objektbaum verschiebt, auch
            // nicht in den Baum einer völlig anderen Instanz.
            $vid = (int)($channels[$i]['AvgID'] ?? 0);
            if (!$vid || !IPS_VariableExists($vid)) {
                $vid = $this->RegisterVariableFloat('avg_' . $rid, $caption, '', $rid * 2);
                $channels[$i]['AvgID'] = $vid;
                $propChanged = true;
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

            $bid = (int)($channels[$i]['BufID'] ?? 0);
            if (!$bid || !IPS_VariableExists($bid)) {
                $bid = $this->RegisterVariableString('buf_' . $rid, $caption . ' (Puffer)', '', $rid * 2 + 1);
                SetValueString($bid, '[]');
                $channels[$i]['BufID'] = $bid;
                $propChanged = true;
            }
            IPS_SetHidden($bid, true);
        }

        if ($propChanged) {
            $this->WriteAttributeInteger('NextRowID', $nextID);
            IPS_SetProperty($this->InstanceID, 'Channels', json_encode($channels));
        }

        // Kanäle, deren Zeile gelöscht wurde, aufräumen — unabhängig davon,
        // wohin die zugehörigen Variablen im Baum verschoben wurden.
        $activeIDs = [];
        foreach ($channels as $ch) {
            if (!empty($ch['AvgID'])) {
                $activeIDs[] = (int)$ch['AvgID'];
            }
            if (!empty($ch['BufID'])) {
                $activeIDs[] = (int)$ch['BufID'];
            }
        }
        $knownIDs = json_decode($this->ReadAttributeString('KnownVarIDs'), true);
        if (!is_array($knownIDs)) {
            $knownIDs = [];
        }
        foreach ($knownIDs as $oldID) {
            if (!in_array($oldID, $activeIDs) && IPS_VariableExists($oldID)) {
                IPS_DeleteVariable($oldID);
            }
        }
        $this->WriteAttributeString('KnownVarIDs', json_encode($activeIDs));

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

        $now = time();
        foreach ($channels as $ch) {
            $srcID = (int)($ch['SourceID'] ?? 0);
            $windowSec = max(1, (int)($ch['WindowMinutes'] ?? 10)) * 60;
            $vid = (int)($ch['AvgID'] ?? 0);
            $bid = (int)($ch['BufID'] ?? 0);

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

            $count = count($buffer);
            if ($count > 0) {
                $sum = array_sum(array_column($buffer, 1));
                SetValueFloat($vid, $sum / $count);
            }
        }
    }
}
