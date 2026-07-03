<?php

class RollingAverage extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('TickInterval', 10);
        $this->RegisterPropertyString('Channels', '[]');
        $this->RegisterAttributeInteger('NextRowID', 1);

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

        // Jede Zeile bekommt EINMALIG eine feste, von der Listenposition
        // unabhängige RowID. Dadurch bleiben Variable und Ringpuffer beim
        // Verschieben/Umsortieren der Liste korrekt zugeordnet.
        $nextID = $this->ReadAttributeInteger('NextRowID');
        $changed = false;
        foreach ($channels as $i => $ch) {
            if (empty($ch['RowID'])) {
                $channels[$i]['RowID'] = $nextID++;
                $changed = true;
            }
        }
        if ($changed) {
            $this->WriteAttributeInteger('NextRowID', $nextID);
            IPS_SetProperty($this->InstanceID, 'Channels', json_encode($channels));
        }

        $activeIdents = [];
        foreach ($channels as $ch) {
            $rid = $ch['RowID'];
            $ident = 'avg_' . $rid;
            $bufIdent = 'buf_' . $rid;
            $activeIdents[] = $ident;
            $activeIdents[] = $bufIdent;

            $caption = ($ch['Caption'] ?? '') !== '' ? $ch['Caption'] : ('Mittelwert ' . $rid);

            // rekursive Suche statt GetIDForIdent: die Variable darf vom
            // Nutzer frei im Objektbaum verschoben werden (z.B. in eine
            // eigene Kategorie), ohne dass eine doppelte neu angelegt wird.
            $vid = $this->FindVarByIdent($ident);
            if (!$vid) {
                $vid = $this->RegisterVariableFloat($ident, $caption, '', $rid * 2);
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

            $bid = $this->FindVarByIdent($bufIdent);
            if (!$bid) {
                $bid = $this->RegisterVariableString($bufIdent, $caption . ' (Puffer)', '', $rid * 2 + 1);
                SetValueString($bid, '[]');
            }
            IPS_SetHidden($bid, true);
        }

        // Kanäle, deren Zeile gelöscht wurde, aufräumen
        foreach ($this->FindOwnIdentsWithPrefix('avg_') as $ident => $id) {
            if (!in_array($ident, $activeIdents)) {
                IPS_DeleteVariable($id);
            }
        }
        foreach ($this->FindOwnIdentsWithPrefix('buf_') as $ident => $id) {
            if (!in_array($ident, $activeIdents)) {
                IPS_DeleteVariable($id);
            }
        }

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
            $rid = $ch['RowID'] ?? null;
            if ($rid === null) {
                continue;
            }
            $srcID = (int)($ch['SourceID'] ?? 0);
            $windowSec = max(1, (int)($ch['WindowMinutes'] ?? 10)) * 60;
            if (!$srcID || !IPS_VariableExists($srcID)) {
                continue;
            }

            $vid = $this->FindVarByIdent('avg_' . $rid);
            $bid = $this->FindVarByIdent('buf_' . $rid);
            if (!$vid || !$bid) {
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

    // Variablen dürfen vom Nutzer frei im Objektbaum verschoben werden
    // (z.B. in eine eigene Kategorie einsortiert) — GetIDForIdent findet
    // nur direkte Kinder der Instanz, daher rekursive Suche.
    private function FindVarByIdent(string $ident): int
    {
        return $this->FindIdentRecursive($this->InstanceID, $ident);
    }

    private function FindIdentRecursive(int $parentID, string $ident): int
    {
        foreach (IPS_GetChildrenIDs($parentID) as $childID) {
            $obj = IPS_GetObject($childID);
            if ($obj['ObjectIdent'] === $ident) {
                return $childID;
            }
            if ($obj['ObjectType'] === 0) {
                $found = $this->FindIdentRecursive($childID, $ident);
                if ($found) {
                    return $found;
                }
            }
        }
        return 0;
    }

    // liefert [ident => objectID] für alle eigenen (auch verschobenen)
    // Variablen mit dem gegebenen Ident-Präfix
    private function FindOwnIdentsWithPrefix(string $prefix): array
    {
        $result = [];
        $this->CollectIdentsWithPrefix($this->InstanceID, $prefix, $result);
        return $result;
    }

    private function CollectIdentsWithPrefix(int $parentID, string $prefix, array &$result): void
    {
        foreach (IPS_GetChildrenIDs($parentID) as $childID) {
            $obj = IPS_GetObject($childID);
            if ($obj['ObjectIdent'] !== '' && strpos($obj['ObjectIdent'], $prefix) === 0) {
                $result[$obj['ObjectIdent']] = $childID;
            }
            if ($obj['ObjectType'] === 0) {
                $this->CollectIdentsWithPrefix($childID, $prefix, $result);
            }
        }
    }
}
