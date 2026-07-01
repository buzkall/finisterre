<?php

namespace Arzcode\Finisterre\Traits;

use Arzcode\Finisterre\Contracts\FinisterreReportable;
use Filament\Facades\Filament;
use Throwable;

/**
 * Default implementation of {@see FinisterreReportable}.
 *
 * Host models still need to declare `implements FinisterreReportable` for the
 * `instanceof` checks to pass; this trait only supplies sensible defaults that
 * can be overridden per model.
 */
trait InteractsWithFinisterreReports
{
    public function getFinisterreReportLabel(): string
    {
        return class_basename($this) . " (#{$this->getKey()})";
    }

    /**
     * Infer the URL from the model's Filament resource, preferring its edit page
     * and falling back to its view page. Override per model for custom links.
     */
    public function getFinisterreReportUrl(): ?string
    {
        try {
            $resource = Filament::getModelResource($this);

            if ($resource === null) {
                return null;
            }

            foreach (['edit', 'view'] as $page) {
                if ($resource::hasPage($page)) {
                    return $resource::getUrl($page, ['record' => $this]);
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
