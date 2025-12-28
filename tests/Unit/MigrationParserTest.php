<?php

use Sirval\LaravelSmartMigrations\Services\MigrationParser;

describe('MigrationParser', function () {
    it('extracts table from schema create in migration file', function () {
        $parser = new MigrationParser;
        $filePath = __DIR__.'/../Fixtures/migrations/2024_01_01_000001_create_users_table.php';

        $table = $parser->parseTableFromMigrationFile($filePath);

        expect($table)->toBe('users');
    });

    it('extracts table from schema table in migration file', function () {
        $parser = new MigrationParser;
        $filePath = __DIR__.'/../Fixtures/migrations/2024_01_02_000001_add_email_verified_to_users_table.php';

        $table = $parser->parseTableFromMigrationFile($filePath);

        expect($table)->toBe('users');
    });

    it('returns null for non-existent file', function () {
        $parser = new MigrationParser;

        $table = $parser->parseTableFromMigrationFile('/non/existent/file.php');

        expect($table)->toBeNull();
    });

    it('extracts table from CreateXTable class name', function () {
        $parser = new MigrationParser;

        expect($parser->parseTableFromClassName('CreateUsersTable'))->toBe('users');
        expect($parser->parseTableFromClassName('CreateUserProfilesTable'))->toBe('user_profiles');
        expect($parser->parseTableFromClassName('CreatePostsTable'))->toBe('posts');
    });

    it('extracts table from AddXToYTable class name', function () {
        $parser = new MigrationParser;

        expect($parser->parseTableFromClassName('AddEmailToUsersTable'))->toBe('users');
        expect($parser->parseTableFromClassName('AddProfilePhotoToUsersTable'))->toBe('users');
        expect($parser->parseTableFromClassName('AddTimestampsToPostsTable'))->toBe('posts');
    });

    it('handles class names without Table suffix', function () {
        $parser = new MigrationParser;

        expect($parser->parseTableFromClassName('CreateUsers'))->toBe('users');
        expect($parser->parseTableFromClassName('AddEmailToUsers'))->toBe('users');
    });

    it('extracts table from migration filename', function () {
        $parser = new MigrationParser;

        expect($parser->parseTableFromMigrationName('2024_01_01_000001_create_users_table'))->toBe('users');
        expect($parser->parseTableFromMigrationName('2024_01_01_000001_add_email_to_users_table'))->toBe('users');
        expect($parser->parseTableFromMigrationName('2024_01_01_000001_create_user_profiles'))->toBe('user_profiles');
    });

    it('handles migration names without table suffix', function () {
        $parser = new MigrationParser;

        expect($parser->parseTableFromMigrationName('2024_01_01_000001_create_users'))->toBe('users');
        expect($parser->parseTableFromMigrationName('2024_01_01_000001_add_email_to_users'))->toBe('users');
    });

    it('extracts model name from namespace', function () {
        $parser = new MigrationParser;

        expect($parser->extractModelFromNamespace('App\\Models\\User'))->toBe('User');
        expect($parser->extractModelFromNamespace('App\\Models\\UserProfile'))->toBe('UserProfile');
        expect($parser->extractModelFromNamespace('Modules\\Blog\\Models\\Post'))->toBe('Post');
    });
});
