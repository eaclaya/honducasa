<?php

namespace App\Support\Media;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * Serves media whose `disk` is `external` from their stored `external_url`
 * custom property instead of resolving a locally-stored file. Used by the
 * demo property seeder, which points at stock photos it doesn't own.
 */
class ExternalUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        if ($this->media->disk === 'external') {
            return (string) $this->media->getCustomProperty('external_url');
        }

        return parent::getUrl();
    }
}
