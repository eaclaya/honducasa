<?php

use Inertia\Testing\AssertableInertia as Assert;

test('spanish is the default public locale', function () {
    config(['app.locale' => 'es']);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'es'));
});

test('a visitor can switch between spanish and english', function () {
    $this->from(route('home'))
        ->post(route('locale.update', ['locale' => 'en']))
        ->assertRedirect(route('home'));

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));

    $this->post(route('locale.update', ['locale' => 'fr']))->assertNotFound();
});
