<?php

namespace Arzcode\Finisterre\Contracts;

interface FinisterreReportable
{
    /**
     * A human-readable label identifying this record in a task (e.g. "Juan Pérez (#123)").
     */
    public function getFinisterreReportLabel(): string;

    /**
     * A deep link the task resolver can click to open this record, or null when none applies.
     */
    public function getFinisterreReportUrl(): ?string;
}
