<?php

// config for Sirval/LaravelSmartMigrations
return [
    // Model namespace (when user passes "User" instead of full path)
    'model_namespace' => 'App\\Models',

    // Require explicit confirmation before rollback
    'require_confirmation' => true,

    // Show migration details before rollback
    'show_details' => true,

    // Batch safety: prevent rolling back migrations from multiple batches
    'prevent_multi_batch_rollback' => true,

    // Enable audit logging
    'audit_log_enabled' => false,
    'audit_log_table' => 'smart_migrations_audits',
];
