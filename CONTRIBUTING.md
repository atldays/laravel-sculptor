# Contributing

Thank you for contributing to Laravel Sculptor.

This package is designed to provide reusable query objects for Laravel Eloquent, with a strong focus on:

- extracting complex read logic into dedicated classes
- keeping query behavior testable and explicit
- supporting filters, pagination, and caching in a consistent way
- staying pleasant to use in real application code

## Project Goals

When contributing to this package, keep these goals in mind:

1. Query objects should make large read operations easier to understand and reuse.
2. The default API should feel simple in day-to-day Laravel development.
3. Advanced behavior should remain explicit rather than magical.
4. Public APIs should stay coherent across filters, pagination, and caching.
5. Builder-level cache integration is supported, but the package should also remain useful without it.

## Design Principles

Please try to preserve these principles when proposing changes:

- Prefer small, explicit abstractions over hidden framework magic.
- Keep the preset classes easy to understand:
  - `BaseQuery`
  - `Query`
  - `CachedQuery`
  - `BuilderCachedQuery`
- Put reusable read logic in query objects, not in controllers.
- Keep pagination, result execution, filters, and cache behavior aligned with the package philosophy.
- Treat result cache and builder cache as separate strategies with different tradeoffs.
- Favor backward-compatible changes when possible, but do not keep unnecessary abstractions just for historical reasons.

## Before You Start

Before making changes, please:

1. Read the `README.md` to understand the intended public API.
2. Review the existing tests to understand the supported scenarios.
3. Prefer extending existing patterns instead of introducing a parallel style.

## Development Workflow

Typical workflow:

1. Create a focused branch for your change.
2. Make the smallest meaningful change that solves the problem.
3. Add or update tests.
4. Run formatting.
5. Run the test suite.
6. Update documentation when the public API or behavior changes.

## Commit Style

This repository uses Conventional Commits.

Please format commit messages like this:

```text
type(scope): short description
```

Examples:

```text
feat(pagination): add static paginate shortcut
fix(cache): include pagination data in cache key
docs(readme): clarify builder cache requirements
test(query): cover paginated cached results
refactor(api): simplify cached query presets
```

Recommended commit types:

- `feat`
- `fix`
- `refactor`
- `docs`
- `test`
- `chore`

## Testing

Every change should be covered by tests when it affects behavior.

At minimum, contributors should run:

```bash
composer format:test
composer test
```

This package is tested across multiple Laravel and PHP versions in CI, so local changes should avoid assumptions tied to only one framework version.

## What To Test

When adding or changing functionality, please cover the most relevant public scenario:

- query construction
- filters integration
- result execution
- pagination
- result cache
- builder cache integration
- error scenarios when contracts are not satisfied

If a change affects cache behavior, also think about:

- cache invalidation
- cache key shape
- runtime arguments
- pagination state

## Documentation Expectations

If your change affects the public API or developer experience, update `README.md`.

Documentation updates are expected when you change:

- public classes
- public traits
- execution methods
- pagination behavior
- cache behavior
- setup or installation steps

## API Guidance

When in doubt:

- use short preset classes for common usage
- use explicit traits for capability composition
- keep query objects focused on read behavior
- keep naming aligned with the actual behavior of the abstraction

Avoid:

- adding multiple ways to do the same thing without a strong reason
- mixing unrelated concerns into one abstraction
- introducing hidden behavior that makes query objects harder to reason about

## Pull Requests

Good pull requests usually include:

- a clear motivation
- a focused implementation
- tests for the changed behavior
- documentation updates when needed
- commit messages following Conventional Commits

## Questions To Ask Before Merging

Before considering a contribution complete, ask:

1. Does this make query objects clearer or more useful?
2. Does this preserve the intended developer experience?
3. Is the behavior covered by tests?
4. Does the README still describe the package accurately?
5. Is this the smallest clean solution?
