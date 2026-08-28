# Git Workflow

> Part of the [Development Handbook](README.md) · **Mode:** How-to

## Branching Strategy

This project uses a **trunk-based** workflow:

- **`main`** — the main branch, always deployable
- **Feature branches** — short-lived branches for larger changes

```bash
# Create and switch to a feature branch
git checkout -b feat/my-feature

# Work, commit, test...

# Push and open a PR
git push -u origin feat/my-feature
```

For small changes (a single fix or small addition), commit directly to `main`:

```bash
git checkout main
git pull
# ... make changes
git commit -am "fix: resolve null pointer in BookingController"
git push
```

## Commit Messages

Use **Conventional Commits**:

| Prefix | When to use |
|--------|-------------|
| `feat:` | New feature |
| `fix:` | Bug fix |
| `refactor:` | Code restructuring (no behavior change) |
| `docs:` | Documentation only |
| `test:` | Adding or fixing tests |
| `chore:` | Tooling, dependencies, config |
| `perf:` | Performance improvement |
| `build:` | Build system changes |
| `ci:` | CI/CD changes |

**Examples:**
```
feat: add currency rate sync from API
fix: resolve null pointer in VisaAdminController
chore: update composer dependencies
docs: add development handbook
test: add coverage for invoice calculation
```

## Pre-Commit Checklist

Before each commit:

1. **Write tests** — TDD: test exists (and fails) before implementation
2. **Run tests** — `php artisan test` — all green
3. **Format code** — `vendor/bin/pint` — no style violations
4. **Build assets** — `npm run build` — no build errors
5. **Clear caches** — `php artisan optimize:clear` (if config/routes changed)
6. **Validate Docker** — `docker compose config --quiet` (if compose changed)
7. **Stage files** — `git add` only the files relevant to this change
8. **Commit** — clear, conventional commit message

> See [AGENTS.md](../AGENTS.md) for a detailed commit checklist.

## Pull Requests

For feature branches:

1. Push your branch: `git push -u origin feat/my-feature`
2. Open a PR on GitHub targeting `main`
3. CI runs automatically (PHP tests, JS tests, Docker build)
4. Code review by team members
5. **Squash and merge** (keeps history clean)

## Merge Conflicts

If `main` has changed since your branch:

```bash
git checkout main
git pull
git checkout feat/my-feature
git rebase main
# Resolve conflicts if any
git push --force-with-lease
```

---

## Navigation

Previous: [Testing](05-testing.md) ·
Next: [CI/CD](07-ci-cd.md) ·
Full index: [README](README.md)
