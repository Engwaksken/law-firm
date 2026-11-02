<?php

namespace App\Livewire\Concerns;

trait LinksToAdmin
{
    /** URL of a Filament resource page, falling back to the dashboard if it isn't registered. */
    protected function adminUrl(string $resource, string $page = 'index', array $parameters = []): string
    {
        try {
            return class_exists($resource) ? $resource::getUrl($page, $parameters, panel: 'admin') : url('/admin');
        } catch (\Throwable) {
            return url('/admin');
        }
    }
}
