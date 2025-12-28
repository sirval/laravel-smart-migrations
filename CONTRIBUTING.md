# Contributing

Thank you for your interest in contributing to Laravel Smart Migrations! We welcome contributions from everyone. Please follow these guidelines to ensure a smooth contribution process.

## Getting Started

1. Fork the repository on GitHub
2. Clone your forked repository locally
3. Create a new branch for your feature or fix: `git checkout -b feature/your-feature-name`
4. Install dependencies: `composer install`

## Development Setup

```bash
# Clone the repository
git clone https://github.com/sirval/laravel-smart-migrations.git
cd laravel-smart-migrations

# Install dependencies
composer install

# Run tests
composer test

# Run code style checks
composer run lint
```

## Making Changes

### Code Style

- Follow PSR-12 coding standards
- Use meaningful variable and function names
- Add type hints to all methods and properties
- Add PHPDoc comments for public methods

### Testing

- Write tests for any new features
- Ensure all existing tests pass: `composer test`
- Maintain or improve code coverage
- Test your changes manually in a Laravel project

### Commit Messages

- Use clear, descriptive commit messages
- Reference issues when applicable: `fix: Resolve orphaned tables issue (#123)`
- Use conventional commit format: `feat:`, `fix:`, `docs:`, `test:`, `refactor:`, `chore:`

Examples:
- `feat: Add support for rolling back multiple tables at once`
- `fix: Resolve issue where tables were left orphaned after rollback`
- `docs: Update README with all command flag examples`
- `test: Add unit tests for batch filtering`

## Pull Request Process

1. Create a descriptive pull request title
2. Provide a detailed description of your changes
3. Reference any related issues
4. Ensure all tests pass
5. Ensure code style is correct: `composer run lint`
6. Wait for review and address any feedback

### PR Title Format

- `feat: Add feature description`
- `fix: Fix description`
- `docs: Update documentation`
- `test: Add tests`
- `refactor: Improve code structure`

### PR Description Template

```markdown
## Description
Brief description of what this PR does

## Related Issues
Closes #123

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to change)
- [ ] Documentation update

## How Has This Been Tested?
Describe how you tested these changes

## Checklist
- [ ] My code follows the code style guidelines
- [ ] I have added/updated tests for my changes
- [ ] I have updated the documentation
- [ ] All tests pass locally
- [ ] I have added/updated the CHANGELOG
```

## Reporting Bugs

When reporting bugs, please include:
- A clear, descriptive title
- A description of the bug
- Steps to reproduce the issue
- Expected behavior
- Actual behavior
- Your Laravel and PHP versions
- Your environment setup

## Feature Requests

When suggesting a feature:
- Use a clear, descriptive title
- Provide a detailed description of the proposed feature
- Explain why this feature would be useful
- Provide examples of how it would work

## Questions or Need Help?

Feel free to open an issue to ask questions about the codebase or how to implement a feature.

## License

By contributing to Laravel Smart Migrations, you agree that your contributions will be licensed under the MIT license.

## Code of Conduct

We are committed to providing a welcoming and inclusive environment for all contributors. Please be respectful and constructive in your interactions.

Thank you for contributing to Laravel Smart Migrations! 🎉
