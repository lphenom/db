# Contributing to lphenom/db

Thank you for your interest in contributing! Please follow these guidelines.

## Code of Conduct

Be respectful and constructive in all interactions.

## Requirements

- PHP >= 8.1
- Docker + Docker Compose (for local dev environment)

## Getting Started

```bash
git clone git@github.com:lphenom/db.git
cd db
make up          # start Docker environment
make test        # run tests inside container
make lint        # run php-cs-fixer check inside container
```

## Code Style

- PSR-12 coding standard
- `declare(strict_types=1);` in every PHP file
- No `reflection`, `eval`, `variable variables`, `dynamic class loading`
- Strict types everywhere — no loose comparisons

Run the linter before committing:
```bash
make lint
```

## Testing

All new code must be covered by unit tests:
```bash
make test
```

## Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(db): add new feature
fix(db): fix a bug
test(db): add or fix tests
docs(db): update documentation
chore: update tooling / CI
refactor(db): refactor without feature change
```

Keep commits **small and focused**. One logical change per commit.

## Pull Request Process

1. Fork the repository.
2. Create a feature branch: `git checkout -b feat/my-feature`
3. Make your changes with tests.
4. Ensure CI passes: lint, PHPStan, PHPUnit.
5. Open a Pull Request against `main`.
6. Wait for review.

## Versioning

This project uses [SemVer](https://semver.org/). Do not bump versions manually — maintainers handle releases.

