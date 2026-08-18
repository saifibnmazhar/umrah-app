# Livewire Best Practices

> **For Hermes:** Use this doc when migrating Blade sections to Livewire.

## Overview

Livewire provides a bridge between Blade templates and reactive frontend behavior
**without requiring a separate JavaScript/Vue/React build step**. It uses the
existing Laravel + Alpine.js stack.

## Namespace Convention

All Livewire components live under `app/Livewire/`. Subdirectories map to dot
notation in `<livewire:...>` tags.

```
app/Livewire/Dashboard/DashboardSummary.php
  → <livewire:dashboard.dashboard-summary />

app/Livewire/District/DistrictForm.php
  → <livewire:district.district-form />
```

The root namespace is `App\Livewire` (not `App\Http\Livewire`).

## Component Structure

### Public Properties

Use public properties for **all** data passed to the component — both read-only
server data and user-input state (search, pagination).

```php
class DistrictIndex extends Component
{
    public array $stats = [];
    public bool $showSummaryCards = true;
    public string $search = '';
    public int $perPage = 15;
}
```

### Mount vs Lazy Loading

- Use `mount()` for **initial** data that is cheap and always needed
- Use `$this->call('method')` for expensive data fetched on-demand (search, filtering)
- Public properties are re-serialized to every request round-trip; avoid storing
  large Eloquent collections as public properties if possible

### Computed Properties

For data derived from public properties, use Livewire `computed` properties:

```php
public function getItemCountProperty()
{
    return $this->items->count();
}
// Usage: {{ $this->item_count }}
```

## Blade Templates

### Component View Location

`resources/views/livewire/{kebab-path}/{name}.blade.php`

Example: `app/Livewire/Dashboard/DashboardSummary` →
`resources/views/livewire/dashboard/summary.blade.php`

### Alpine.js Coexistence

Alpine.js (`x-data`, `x-show`, `@click`) works **invisibly** inside Livewire
components. Livewire replaces the server-rendered HTML, but Alpine takes over
for client-side interactions (tabs, sliders, toggles). No configuration needed.

```blade
<div x-data="{ currentSlide: 0 }">
    <div x-show="currentSlide === 0" x-cloak>...</div>
    <div x-show="currentSlide === 1" x-cloak>...</div>
</div>
```

### Escaping Blade `{{ }}` in Alpine

When using Blade `@json()` or `json_encode()` in attributes, Blade's `{{ }}` 
can conflict with JS template literals. Use `$wire` for server-to-client data
passing instead.

## Passing Props from Parent Blade

Pass server-computed role/visibility flags as props, not as inline Blade logic:

```blade
{{-- Parent: resources/views/dashboard/index.blade.php --}}
<livewire:dashboard.dashboard-summary
    :stats="$stats"
    :totals="$totals"
    :show-summary-cards="$showSummaryCards"
    :show-profit-cards="$showProfitCards"
/>
```

```php
// Controller: compute once, pass as props
$showSummaryCards = auth()->user()->roles
    ->pluck('name')
    ->intersect(['Super Admin', 'Co Admin', 'Auditor', ...])
    ->isNotEmpty();
```

## Testing

### Feature Tests

```php
/** @test */
public function test_dashboard_summary_renders_as_livewire_component(): void
{
    $user = $this->setupUser();
    $deps = $this->seedAllPrerequisites($user);
    $this->createBooking($user, $deps, 1, 2);

    Auth::login($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('wire:id');
    $response->assertSee('Total Invoice');
}
```

### Livewire Specific Tests (Unit)

```php
use Livewire\Livewire;

public function test_dashboard_summary_component(): void
{
    Livewire::test(DashboardSummary::class, [
        'stats' => ['totalInvoice' => 42],
        'totals' => ['totalProfit' => 1000],
        'showProfitCards' => true,
    ])->assertSee('42')
      ->assertSee('Total Profit');
}
```

### Query Count Assertions

The `DashboardQueryOptimizationTest` guards query count. After wrapping a
section in a Livewire component, the **initial page load** query count should
remain unchanged (Livewire just renders the Blade template server-side and
wraps it with a `wire:id`).

## Root View Scripts

`resources/views/layouts/app.blade.php` includes:
- `@livewireStyles` — inside `<head>`
- `@livewireScripts` — before `</body>`

Never place `@livewireScripts` inside a section — it must be in the main layout.

## Migration Strategy

1. Extract the Blade section into a Livewire component view
2. Add public properties for every variable the section uses
3. Pass the props from the parent Blade (controller already computes them)
4. Replace the section markup with `<livewire:namespace.component-name />`
5. Test: full page renders, query count stable, `wire:id` present

## Pitfalls

- **Component namespace**: `App\Livewire`, not `App\Http\Livewire`. Components
  in subdirectories use dot notation (e.g., `dashboard.dashboard-summary`).
- **Stale view cache**: Run `php artisan view:clear` after moving components.
- **Public property serialization**: Eloquent collections in public properties
  are serialized on every request. Use `mount()` for read-only data fetched
  once, or use `protected` with a getter.
- **`@entangle`**: For Alpine ↔ Livewire two-way binding. Use sparingly.
- **`protected $listeners`**: In Livewire 3, use the `#[On('event-name')]` 
  attribute instead.
