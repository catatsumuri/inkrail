<?php

use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

test('sandbox entry is unavailable outside demo mode', function () {
    config(['app.demo_mode' => false]);

    $this->post(route('sandbox.start'))->assertNotFound();

    $this->assertDatabaseCount('users', 0);
    $this->assertGuest();
});

test('starting a sandbox authenticates a temporary visitor with private copies and reuses the workspace', function () {
    config(['app.demo_mode' => true]);
    $this->seed();
    $original = Article::where('slug', 'showcase-markdown')->sole();
    $owner = $original->user;

    $this->post(route('sandbox.start'))->assertRedirect(route('articles.index'));

    $visitor = Auth::user();
    expect($visitor)->is_sandbox->toBeTrue()->is_showcase_owner->toBeFalse();
    expect($visitor->hasVerifiedEmail())->toBeTrue();
    expect($visitor->articles()->count())->toBe(8);
    expect($visitor->articles()->where('is_published', true)->exists())->toBeFalse();
    $copy = $visitor->articles()->whereRelation('translations', 'title', $original->title)->sole();
    expect($copy->content)->toBe($original->content);
    expect($copy->slug)->not->toBe($original->slug);
    expect($owner->is_showcase_owner)->toBeTrue();
    expect(Hash::check('password', $owner->password))->toBeFalse();
    $event = Activity::inLog('security')->where('event', 'sandbox.started')->sole();
    expect($event->subject->is($visitor))->toBeTrue();
    expect($event->causer->is($visitor))->toBeTrue();
    expect($event->properties->all())->toBe(['result' => 'success']);

    $this->post(route('sandbox.start'))->assertRedirect(route('articles.index'));
    $this->assertAuthenticatedAs($visitor);
    $this->assertDatabaseCount('users', 2);
    expect(Activity::inLog('security')->where('event', 'sandbox.started')->count())->toBe(1);
});

test('visitors can edit only their own sandbox copies and cannot publish them', function () {
    config(['app.demo_mode' => true]);
    $this->seed();
    $original = Article::where('slug', 'showcase-markdown')->sole();
    $this->post(route('sandbox.start'));
    $firstVisitor = Auth::user();
    $firstCopy = $firstVisitor->articles()->firstOrFail();
    Auth::logout();
    $this->flushSession();
    $this->post(route('sandbox.start'));
    $visitor = Auth::user();
    $copy = $visitor->articles()->whereRelation('translations', 'title', $original->title)->sole();

    expect($visitor->id)->not->toBe($firstVisitor->id);
    $this->patch(route('articles.update', $firstCopy), ['title' => 'Changed'])->assertForbidden();
    $this->get(route('articles.show', $firstCopy))->assertForbidden();
    $this->delete(route('articles.destroy', $firstCopy))->assertForbidden();
    $this->patch(route('articles.update', $original), ['title' => 'Changed'])->assertForbidden();
    $this->delete(route('articles.destroy', $original))->assertForbidden();
    $this->patch(route('articles.update', $copy), ['title' => 'My copy', 'content' => 'My notes'])
        ->assertRedirect(route('articles.show', $copy));
    expect($copy->fresh()->content)->toBe('My notes');
    expect($original->fresh()->content)->toBe($original->content);

    $this->patch(route('articles.update', $copy), ['title' => 'My copy', 'is_published' => true])
        ->assertSessionHasErrors('is_published');
    expect($copy->fresh()->is_published)->toBeFalse();
    $this->get(route('public.articles.show', $copy->slug))->assertNotFound();
    $this->post(route('articles.store'), ['title' => 'Published copy', 'is_published' => true])
        ->assertSessionHasErrors('is_published');
    $this->assertDatabaseMissing('articles', ['title' => 'Published copy']);
});

test('the showcase owner cannot edit or delete originals or delete the owner account', function () {
    config(['app.demo_mode' => true]);
    $this->seed();
    $owner = User::where('is_showcase_owner', true)->sole();
    $original = $owner->articles()->firstOrFail();

    expect(Gate::forUser($owner)->allows('update', $original))->toBeFalse();
    expect(Gate::forUser($owner)->allows('delete', $original))->toBeFalse();

    $this->actingAs($owner)->patch(route('articles.update', $original), ['title' => 'Changed'])->assertForbidden();
    $this->delete(route('articles.destroy', $original))->assertForbidden();
    $this->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();

    $this->assertModelExists($original);
    $this->assertModelExists($owner);
    $this->get(route('public.articles.show', 'showcase-markdown'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('editableArticleId', null));
});

test('existing accounts keep their identity when entering the sandbox', function () {
    config(['app.demo_mode' => true]);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('sandbox.start'))->assertRedirect(route('articles.index'));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('users', 1);
});

test('only protected examples appear in the live public showcase', function () {
    config(['app.demo_mode' => true, 'articles.public_index_enabled' => true]);
    $this->seed();
    $other = User::factory()->create()->articles()->create([
        'title' => 'Other article', 'content' => 'Other content', 'is_published' => true,
    ]);

    $this->get(route('public.articles.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('articles.data', 7));
    $this->get(route('public.articles.show', $other->slug))->assertNotFound();
});
