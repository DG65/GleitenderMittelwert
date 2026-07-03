<?php

class RollingAverage extends IPSModule
{
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('TickInterval', 10);
        $this->RegisterPropertyString('Channels', '[]');

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

        foreach ($channels as $i => $ch) {
            $caption = ($ch['Caption'] ?? '') !== '' ? $ch['Caption'] : ('Mittelwert ' . ($i + 1));

            $vid = @$this->GetIDForIdent('avg_' . $i);
            if (!$vid) {
                $vid = $this->RegisterVariableFloat('avg_' . $i, $caption, '', $i * 2);
            }
            IPS_SetName($vid, $caption);

            $bid = @$this->GetIDForIdent('buf_' . $i);
            if (!$bid) {
                $bid = $this->RegisterVariableString('buf_' . $i, $caption . ' (Puffer)', '', $i * 2 + 1);
                SetValueString($bid, '[]');
            }
            IPS_SetHidden($bid, true);
        }

        // überzählige Kanäle aus einer früheren, längeren Konfiguration entfernen
        $i = count($channels);
        while (@$this->GetIDForIdent('avg_' . $i)) {
            $this->UnregisterVariable('avg_' . $i);
            $this->UnregisterVariable('buf_' . $i);
            $i++;
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
        foreach ($channels as $i => $ch) {
            $srcID = (int)($ch['SourceID'] ?? 0);
            $windowSec = max(1, (int)($ch['WindowMinutes'] ?? 10)) * 60;
            if (!$srcID || !IPS_VariableExists($srcID)) {
                continue;
            }

            $vid = @$this->GetIDForIdent('avg_' . $i);
            $bid = @$this->GetIDForIdent('buf_' . $i);
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
}
