<?php

namespace App;

class ProgressBar
{
    private int $total;
    private int $current = 0;
    private int $width = 50;

    public function __construct(int $total)
    {
        $this->total = $total;
    }

    public function advance(): void
    {
        $this->current++;
        $this->display();
    }

    public function display(): void
    {
        $percentage = $this->current / $this->total;
        $bar = floor($percentage * $this->width);

        $statusBar = "\r[";
        $statusBar .= str_repeat('=', $bar);
        if ($bar < $this->width) {
            $statusBar .= '>';
            $statusBar .= str_repeat(' ', $this->width - $bar - 1);
        } else {
            $statusBar .= '=';
        }
        $statusBar .= "] " . number_format($percentage * 100, 0) . "% ({$this->current}/{$this->total}) ";

        // Voeg extra spaties toe om eventuele overlappende tekst te overschrijven
        $statusBar .= str_repeat(' ', 10);

        echo $statusBar;

        if ($this->current === $this->total) {
            echo "\n";
        }
    }
}
