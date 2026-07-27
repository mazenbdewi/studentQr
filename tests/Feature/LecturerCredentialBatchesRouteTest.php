<?php

use Illuminate\Support\Facades\Route;

it('does not register the obsolete lecturer credential batches administration route', function (): void {
    $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($route): string => $route->uri());

    expect($uris)->not->toContain('admin/lecturer-credential-batches');

    $this->get('/admin/lecturer-credential-batches')->assertNotFound();
});
