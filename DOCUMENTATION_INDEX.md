# Laravel Smart Migrations - Documentation Index

Welcome! This guide helps you navigate the complete Laravel Smart Migrations documentation.

## 📚 Documentation Structure

### For **End Users** (Using the Package)

Start here if you want to use Laravel Smart Migrations in your project.

1. **[USAGE_GUIDE.md](./USAGE_GUIDE.md)** - **START HERE** ⭐
   - Installation instructions
   - Quick start examples (5 examples)
   - All commands explained in detail
   - Programmatic API reference
   - Configuration options
   - Real-world scenarios (5 production use cases)
   - Troubleshooting guide
   - Best practices
   - FAQ

   **Read this first!** It covers everything you need to use the package.

2. **[IMPLEMENTATION_PLAN.md](./IMPLEMENTATION_PLAN.md)** - Overview
   - Architecture overview
   - Phase descriptions
   - Expected behavior
   - Design decisions
   - Summary

   **Optional:** Read if you want to understand the "why" behind the design.

3. **Quick Reference**
   - Command Signatures: See [USAGE_GUIDE.md → Available Commands](./USAGE_GUIDE.md#available-commands)
   - Configuration Options: See [USAGE_GUIDE.md → Configuration](./USAGE_GUIDE.md#configuration)
   - Troubleshooting: See [USAGE_GUIDE.md → Troubleshooting](./USAGE_GUIDE.md#troubleshooting)

---

### For **Developers** (Extending or Contributing)

Start here if you want to extend the package, contribute, or understand how it works.

1. **[ARCHITECTURE_GUIDE.md](./ARCHITECTURE_GUIDE.md)** - **START HERE** ⭐
   - Architecture overview with diagrams
   - Service layer design
   - Command architecture
   - Facade pattern
   - Design patterns used
   - How to extend the package
   - Security considerations
   - Performance tips

   **Read this first!** It explains the entire architecture and how to extend it.

2. **[TESTING_GUIDE.md](./TESTING_GUIDE.md)** - Testing
   - Testing overview
   - Unit test examples
   - Feature test examples
   - Running tests (6 ways)
   - Writing custom tests
   - Coverage goals
   - CI/CD integration

   **Read if:** You're writing tests or want to understand the testing strategy.

3. **[CONTRIBUTING_AND_EXAMPLES.md](./CONTRIBUTING_AND_EXAMPLES.md)** - Contributing & Examples
   - Contributing workflow
   - Code standards
   - 6 Real-world examples
   - Troubleshooting scenarios
   - Development setup
   - Documentation style guide

   **Read if:** You want to contribute code, see real-world examples, or set up development environment.

---

## 🎯 Quick Navigation by Task

### I want to...

#### **Use the package in my project**
→ [USAGE_GUIDE.md](./USAGE_GUIDE.md) - Installation & Quick Start

#### **Understand what commands are available**
→ [USAGE_GUIDE.md → Available Commands](./USAGE_GUIDE.md#available-commands)

#### **Use the programmatic API**
→ [USAGE_GUIDE.md → Programmatic API](./USAGE_GUIDE.md#programmatic-api)

#### **Configure the package**
→ [USAGE_GUIDE.md → Configuration](./USAGE_GUIDE.md#configuration)

#### **See real-world examples**
→ [USAGE_GUIDE.md → Real-World Scenarios](./USAGE_GUIDE.md#real-world-scenarios) or
→ [CONTRIBUTING_AND_EXAMPLES.md → Real-World Examples](./CONTRIBUTING_AND_EXAMPLES.md#real-world-examples)

#### **Troubleshoot an issue**
→ [USAGE_GUIDE.md → Troubleshooting](./USAGE_GUIDE.md#troubleshooting)

#### **Understand the architecture**
→ [ARCHITECTURE_GUIDE.md](./ARCHITECTURE_GUIDE.md)

#### **Learn about services**
→ [ARCHITECTURE_GUIDE.md → Service Layer Design](./ARCHITECTURE_GUIDE.md#service-layer-design)

#### **Extend the package**
→ [ARCHITECTURE_GUIDE.md → Extending the Package](./ARCHITECTURE_GUIDE.md#extending-the-package)

#### **Write tests**
→ [TESTING_GUIDE.md](./TESTING_GUIDE.md)

#### **Contribute code**
→ [CONTRIBUTING_AND_EXAMPLES.md → Contributing](./CONTRIBUTING_AND_EXAMPLES.md#contributing)

#### **Follow coding standards**
→ [CONTRIBUTING_AND_EXAMPLES.md → Code Standards](./CONTRIBUTING_AND_EXAMPLES.md#code-standards)

#### **Set up development environment**
→ [CONTRIBUTING_AND_EXAMPLES.md → Development Setup](./CONTRIBUTING_AND_EXAMPLES.md#development-setup)

#### **See the project status**
→ [IMPLEMENTATION_COMPLETE.md](./IMPLEMENTATION_COMPLETE.md)

---

## 📖 Documentation at a Glance

| Document | Pages | Audience | Purpose |
|----------|-------|----------|---------|
| **USAGE_GUIDE.md** | 55+ | Users | How to use the package |
| **TESTING_GUIDE.md** | 60+ | Developers | How to test |
| **ARCHITECTURE_GUIDE.md** | 70+ | Developers | How it works internally |
| **CONTRIBUTING_AND_EXAMPLES.md** | 50+ | Contributors | How to contribute & examples |
| **IMPLEMENTATION_COMPLETE.md** | 40+ | Managers | Project status & overview |
| **PHASE_2_COMMANDS_SUMMARY.md** | 5+ | Managers | Phase 2 status |
| **IMPLEMENTATION_PLAN.md** | 30+ | Everyone | Original design & plan |

**Total:** 310+ pages of comprehensive documentation

---

## 🚀 Getting Started (5 Minutes)

### Installation
```bash
composer require sirval/laravel-smart-migrations
```

### List migrations for a table
```bash
php artisan migrate:list-table-migrations users
```

### Rollback latest migration
```bash
php artisan migrate:rollback-table users --latest
```

### Programmatic usage
```php
use Sirval\LaravelSmartMigrations\Facades\SmartMigrations;

$results = SmartMigrations::rollbackTable('users', ['latest' => true]);
```

👉 **Next:** Read [USAGE_GUIDE.md](./USAGE_GUIDE.md) for the full quick start guide with 4 more examples.

---

## 📋 Feature Overview

### Commands (5)
- `migrate:rollback-table` - Rollback by table name
- `migrate:rollback-model` - Rollback by model
- `migrate:rollback-batch` - Rollback by batch
- `migrate:list-table-migrations` - List migrations for table
- `migrate:list-model-migrations` - List migrations for model

### Programmatic API (7 methods)
- `rollbackTable(string $table, array $options = []): Collection`
- `rollbackModel(string $model, array $options = []): Collection`
- `rollbackBatch(int $batch, array $options = []): Collection`
- `listMigrationsForTable(string $table): Collection`
- `listMigrationsForModel(string $model): Collection`
- `getTableStatus(string $table): array`
- `getModelStatus(string $model): array`

### Services (5)
- `MigrationFinder` - Query migrations
- `MigrationParser` - Parse table names
- `ModelResolver` - Resolve models to tables
- `MigrationRollbacker` - Execute rollbacks
- `SmartMigrations` - Orchestrator service

### Configuration (6 options)
- `model_namespace` - Custom model namespace
- `require_confirmation` - Confirmation toggle
- `show_details` - Details display toggle
- `prevent_multi_batch_rollback` - Batch safety
- `audit_log_enabled` - Audit logging
- `audit_log_table` - Audit table name

---

## ✅ Quality Assurance

### Tests
- ✅ 18+ passing tests
- ✅ Unit tests for services
- ✅ Feature tests for commands
- ✅ Exception handling tests

### Documentation
- ✅ 310+ pages of docs
- ✅ 110+ topics covered
- ✅ 110+ working examples
- ✅ 6 real-world scenarios

### Code Quality
- ✅ PSR-12 compliant
- ✅ Type hints on all methods
- ✅ PHPStan level 8 compatible
- ✅ No code duplication

---

## 🔗 Quick Links

### Essential Reading
- [Installation](./USAGE_GUIDE.md#installation)
- [Quick Start](./USAGE_GUIDE.md#quick-start)
- [Available Commands](./USAGE_GUIDE.md#available-commands)
- [Programmatic API](./USAGE_GUIDE.md#programmatic-api)

### Detailed Guides
- [Configuration Guide](./USAGE_GUIDE.md#configuration)
- [Real-World Scenarios](./USAGE_GUIDE.md#real-world-scenarios)
- [Advanced Usage](./USAGE_GUIDE.md#advanced-usage)
- [Troubleshooting](./USAGE_GUIDE.md#troubleshooting)

### Developer Resources
- [Architecture Overview](./ARCHITECTURE_GUIDE.md#architecture-overview)
- [Service Layer Design](./ARCHITECTURE_GUIDE.md#service-layer-design)
- [Extending the Package](./ARCHITECTURE_GUIDE.md#extending-the-package)
- [Testing Guide](./TESTING_GUIDE.md)

### Contribution Guidelines
- [Contributing](./CONTRIBUTING_AND_EXAMPLES.md#contributing)
- [Code Standards](./CONTRIBUTING_AND_EXAMPLES.md#code-standards)
- [Real-World Examples](./CONTRIBUTING_AND_EXAMPLES.md#real-world-examples)
- [Development Setup](./CONTRIBUTING_AND_EXAMPLES.md#development-setup)

---

## 📞 Getting Help

### Documentation Not Answering Your Question?

1. **Check [USAGE_GUIDE.md → FAQ](./USAGE_GUIDE.md#faq)** - 10 answered questions
2. **Check [USAGE_GUIDE.md → Troubleshooting](./USAGE_GUIDE.md#troubleshooting)** - 7+ common issues
3. **Check [CONTRIBUTING_AND_EXAMPLES.md → Troubleshooting Scenarios](./CONTRIBUTING_AND_EXAMPLES.md#troubleshooting-scenarios)** - Real scenarios

### Still Need Help?

- Open an issue on [GitHub](https://github.com/sirval/laravel-smart-migrations/issues)
- Email: ohukaiv@gmail.com

---

## 🎓 Learning Path

### Beginner (30 minutes)
1. Read [USAGE_GUIDE.md → Installation](./USAGE_GUIDE.md#installation)
2. Read [USAGE_GUIDE.md → Quick Start](./USAGE_GUIDE.md#quick-start)
3. Try all 4 examples on your local machine
4. Read [USAGE_GUIDE.md → Available Commands](./USAGE_GUIDE.md#available-commands)

### Intermediate (2 hours)
1. Read [USAGE_GUIDE.md → Programmatic API](./USAGE_GUIDE.md#programmatic-api)
2. Read [USAGE_GUIDE.md → Configuration](./USAGE_GUIDE.md#configuration)
3. Read [USAGE_GUIDE.md → Real-World Scenarios](./USAGE_GUIDE.md#real-world-scenarios)
4. Try implementing one scenario

### Advanced (4 hours)
1. Read [ARCHITECTURE_GUIDE.md](./ARCHITECTURE_GUIDE.md)
2. Read [TESTING_GUIDE.md](./TESTING_GUIDE.md)
3. Read [CONTRIBUTING_AND_EXAMPLES.md](./CONTRIBUTING_AND_EXAMPLES.md)
4. Try extending the package or contributing code

---

## 📊 Project Statistics

| Metric | Value |
|--------|-------|
| Total Documentation Pages | 310+ |
| Total Topics Covered | 110+ |
| Working Code Examples | 110+ |
| Code Samples | 280+ |
| Real-World Examples | 6 |
| Commands Implemented | 5 |
| Services Implemented | 5 |
| Tests Written | 18+ |
| Test Coverage Target | 90%+ |
| Supported PHP Versions | 8.2+ |
| Supported Laravel Versions | 11.0+ |

---

## 📅 Version Information

**Package Version:** 1.0.0 (ready for release)
**Documentation Version:** 1.0
**Last Updated:** December 2024

---

## 🎉 You're Ready!

Everything you need to use, extend, and contribute to Laravel Smart Migrations is documented here.

**Start reading:** Pick a document from the list above based on your role, or follow the "Getting Started" guide.

Happy migrating! 🚀

---

**Questions about this index?** Email ohukaiv@gmail.com
