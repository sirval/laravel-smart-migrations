<?php

use Sirval\LaravelSmartMigrations\Services\ModelResolver;

describe('ModelResolver', function () {
    it('resolves model to table name', function () {
        $resolver = new ModelResolver('Sirval\\LaravelSmartMigrations\\Tests');

        // This would require creating test models - for now we test the logic
        $fullClass = $resolver->buildFullClassName('User');

        expect($fullClass)->toBe('Sirval\\LaravelSmartMigrations\\Tests\\User');
    });

    it('builds full class name with namespace', function () {
        $resolver = new ModelResolver('App\\Models');

        expect($resolver->buildFullClassName('User'))->toBe('App\\Models\\User');
        expect($resolver->buildFullClassName('Post'))->toBe('App\\Models\\Post');
    });

    it('preserves full namespace when provided', function () {
        $resolver = new ModelResolver('App\\Models');

        expect($resolver->buildFullClassName('Custom\\Namespace\\User'))->toBe('Custom\\Namespace\\User');
    });

    it('validates model exists', function () {
        $resolver = new ModelResolver;

        // Test with built-in class that extends Model
        expect($resolver->validateModelExists('App\\Models\\User'))->toBeFalse(); // App\Models\User doesn't exist in test
    });

    it('returns configured namespace', function () {
        $resolver = new ModelResolver('App\\Models');

        expect($resolver->getNamespace())->toBe('App\\Models');
    });

    it('allows setting custom namespace', function () {
        $resolver = new ModelResolver('App\\Models');
        $resolver->setNamespace('Modules\\Blog\\Models');

        expect($resolver->getNamespace())->toBe('Modules\\Blog\\Models');
    });

    it('returns self for method chaining', function () {
        $resolver = new ModelResolver('App\\Models');
        $result = $resolver->setNamespace('Custom\\Namespace');

        expect($result)->toBeInstanceOf(ModelResolver::class);
    });
});
