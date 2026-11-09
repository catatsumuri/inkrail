<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('the welcome page follows the public index setting', function (bool $enabled) {
    config(['articles.public_index_enabled' => $enabled]);

    $this->get(route('home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome')
            ->where('publicIndexEnabled', $enabled));
})->with([true, false]);
