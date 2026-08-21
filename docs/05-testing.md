# Testing

> Part of the [Development Handbook](README.md) · **Mode:** How-to

This guide covers how to write, run, and maintain tests in Umrah App.
See also [AGENTS.md](../AGENTS.md) for the TDD workflow.

## Test Setup

- **Framework:** PHPUnit 11 (via Laravel's built-in testing)
- **Test DB:** SQLite in-memory (`DB_DATABASE=:memory:`) — configured in `phpunit.xml`
- **Test cases:** `tests/TestCase.php` (base case), `tests/Feature/*`, `tests/Unit/*`

The `phpunit.xml` already configures the test environment:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
<env name="CACHE_STORE" value="array"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="SESSION_DRIVER" value="array"/>
<env name="MAIL_MAILER" value="array"/>
```

No additional environment setup is needed.

## Running Tests

```bash
# Run all tests
php artisan test

# Run with verbose output
php artisan test -v

# Run a specific test file
php artisan test tests/Feature/BookingEditPackagePreloadTest.php

# Run a specific test method
php artisan test --filter=test_blade_renders_preselected_package_id

# Using PHPUnit directly
vendor/bin/phpunit
vendor/bin/phpunit --filter=test_method_name
```

## Writing Tests

### Feature vs Unit Tests

- **Feature tests** (`tests/Feature/`) — Test the full HTTP request lifecycle
  (routes, middleware, controllers, views). Use for end-to-end behavior.
- **Unit tests** (`tests/Unit/`) — Test isolated classes (models, services)
  without the HTTP layer.

### Test Template (Feature)

```php
<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MyFeatureTest extends TestCase
{
    use RefreshDatabase;

    // Define any test-only tables needed
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('offices', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function test_feature_does_something(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->actingAs($user)->get('/my-feature');

        // Assert
        $response->assertOk();
        $response->assertSee('expected content');
    }
}
```

### Test Template (Unit)

```php
<?php

namespace Tests\Unit;

use App\Services\BookingService;
use Tests\TestCase;

class MyUnitTest extends TestCase
{
    public function test_service_calculates_correctly(): void
    {
        // Arrange
        $service = new BookingService();

        // Act
        $result = $service->calculatePassengerType('2010-01-01');

        // Assert
        $this->assertEquals('adult', $result);
    }
}
```

## TDD Workflow

This project follows **Test-Driven Development**:

1. **Write a failing test** — describe the expected behavior
2. **Run it to confirm failure** — `php artisan test --filter=...`
3. **Write minimal code** to make it pass
4. **Run the test to confirm pass** — should be green
5. **Run full suite** — `php artisan test` (no regressions)
6. **Refactor** — improve code quality (keep tests green)
7. **Commit**

See [AGENTS.md](../AGENTS.md) for the full TDD cycle and commit checklist.

## Database in Tests

- **RefreshDatabase** resets the database between tests (migrations run automatically)
- **Factories** generate test data — `UserFactory` exists; create new ones for models:
  ```bash
  php artisan make:factory ModelFactory --model=Model
  ```
- **Manual schema** — for test-only tables that don't need full migrations:
  ```php
  Schema::create('temp_table', function ($table) {
      $table->id();
      $table->string('name');
      $table->timestamps();
  });
  ```

### Authentication in Tests

```php
$user = User::factory()->create();
$this->actingAs($user)->get('/protected-route');
```

**Note:** Always use `actingAs($user)` instead of `withoutMiddleware()` for tests that exercise
controller logic. `withoutMiddleware()` bypasses auth, but `auth()->user()` still returns `null`
inside controllers, causing null pointer errors to be caught by try/catch and returned as non-422
redirects — producing false positives.

### File Upload Testing

For testing file uploads, fake the storage disks and use `UploadedFile::fake()`:

```php
protected function setUp(): void
{
    parent::setUp();
    Storage::fake('public');  // for files stored with ->store(..., 'public')
    Storage::fake('local');   // for files stored with ->store() (default disk)
}

$file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
$payload['booking_customer_docs'] = [$file];
```

Validation `max:5120` means files must be ≤ 5120 KB. To test rejection, create a file
200 KB over the limit (e.g., `5120 + 200`). To test acceptance, use a file under the limit
(e.g., `4000` KB).

## Existing Test Patterns

- **`BookingEditPackagePreloadTest`** — Tests that Blade views render correctly
  with preloaded package IDs. Uses `RefreshDatabase`, manual `Schema::create()`,
  and `view()->render()` to check HTML output.
- **`SeedDocumentDataTest`** — Tests that seeders create documents linked to
  bookings, customers, and passengers via morphMany. Checks file_path length limits
  and owner validity.
- **`BookingMultiUploadTest`** — Tests multi-file upload validation (accept multiple
  files, reject oversized files, accept at-limit files). Sets up full prerequisites
  (branch, customer, package, ticket fare, route chain) for booking creation.
- **`FlightDateGapSeedTest`** — Tests seed data creates flight date gap options
  (7, 10, 14, 30 days) for passenger creation forms.
- **`BookingFormSubmissionWorkflowTest`** — Full booking form submission workflow tests.
- **`VisaAgentReportQueryTest`** — Unit tests for the `VisaAgentReportQuery` query
  repository (6 tests covering agent row building, submitted/issued/cancelled counts,
  payment sums, and empty results).
- **`TicketAgentReportQueryTest`** — Unit tests for the `TicketAgentReportQuery`
  query repository (5 tests covering payable/paid aggregation, refund/reissue counts,
  and daily ticket/payment summaries).

## Creating New Tests

```bash
# Feature test
php artisan make:test MyFeatureTest --unit=false

# Unit test
php artisan make:test MyUnitTest --unit
```

---

## Navigation

Previous: [Coding Conventions](04-coding-conventions.md) ·
Next: [Git Workflow](06-git-workflow.md) ·
Full index: [README](README.md)
