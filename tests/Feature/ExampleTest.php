<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('returns a successful response', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
});

test('the welcome page follows the public index setting', function (bool $enabled) {
    config(['articles.public_index_enabled' => $enabled, 'articles.home_page' => 'welcome']);

    $this->get(route('home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome')
            ->where('publicIndexEnabled', $enabled));
})->with([true, false]);

test('the root URL can show the public index in place of the welcome page', function () {
    config(['articles.public_index_enabled' => true, 'articles.home_page' => 'articles']);

    $this->get(route('home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('articles/public-index'));

    config(['articles.public_index_enabled' => false]);

    $this->get(route('home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('welcome'));
});
