<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the initial schema can be created rolled back and created again with its foreign keys intact', function () {
    config(['database.connections.migration_check' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
    ]]);
    $schema = Schema::connection('migration_check');

    $this->artisan('migrate', ['--database' => 'migration_check', '--force' => true])->assertSuccessful();
    $initialTables = $schema->getTableListing(schemaQualified: false);
    expect($initialTables)->toContain('users', 'article_namespaces', 'articles', 'article_revisions', 'article_translations', 'article_translation_reviews', 'activity_log');
    $foreignKeys = $schema->getForeignKeys('article_translations');
    expect(collect($foreignKeys)->pluck('foreign_table')->all())->toContain('articles', 'article_revisions');

    $this->artisan('migrate:rollback', ['--database' => 'migration_check', '--force' => true])->assertSuccessful();

    expect($schema->getTableListing(schemaQualified: false))->toBe(['migrations']);

    $this->artisan('migrate', ['--database' => 'migration_check', '--force' => true])->assertSuccessful();

    expect($schema->getTableListing(schemaQualified: false))->toBe($initialTables);
    expect($schema->getForeignKeys('article_translations'))->toBe($foreignKeys);
    DB::purge('migration_check');
});
