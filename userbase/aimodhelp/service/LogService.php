<?php

namespace userbase\aimodhelp\service;

class LogService
{
    private array $logs = [];

    public function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $this->logs[] = "[$timestamp] $message";
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}
