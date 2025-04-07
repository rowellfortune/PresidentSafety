<?php

namespace App;

class Logger
{
    private array $messages = [];

    public function log($message): void
    {
        $this->messages[] = $message;
    }

    public function output(): void
    {
        foreach ($this->messages as $message) {
            echo $message . PHP_EOL;
        }
    }
}
