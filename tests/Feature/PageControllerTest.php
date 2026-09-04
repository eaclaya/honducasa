<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the terms of service page renders', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('legal/Terms'));
});

test('the privacy policy page renders', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('legal/Privacy'));
});

test('the faq page renders', function () {
    $this->get(route('faq'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('legal/Faq'));
});
