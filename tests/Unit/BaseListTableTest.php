<?php

namespace Tests\Unit;

use App\Livewire\BaseListTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BaseListTableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('test_items');
        \DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Schema::create('test_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
        });
    }

    protected function tearDown(): void
    {
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('test_items');
        \DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function makeComponent(string $search = ''): BaseListTableTestStub
    {
        $component = new BaseListTableTestStub;
        $component->search = $search;

        return $component;
    }

    public function test_apply_search_returns_all_when_search_empty(): void
    {
        BaseListTableTestItem::create(['name' => 'Alpha', 'code' => 'A1']);
        BaseListTableTestItem::create(['name' => 'Beta', 'code' => 'B2']);

        $component = $this->makeComponent('');
        $result = $component->publicApplySearch(
            BaseListTableTestItem::query(),
            ['name', 'code']
        );

        $this->assertSame(2, $result->count());
    }

    public function test_apply_search_filters_by_name(): void
    {
        BaseListTableTestItem::create(['name' => 'Alpha', 'code' => 'A1']);
        BaseListTableTestItem::create(['name' => 'Beta', 'code' => 'B2']);

        $component = $this->makeComponent('Alpha');
        $result = $component->publicApplySearch(
            BaseListTableTestItem::query(),
            ['name', 'code']
        );

        $this->assertSame(1, $result->count());
        $this->assertSame('Alpha', $result->first()->name);
    }

    public function test_apply_search_filters_by_code(): void
    {
        BaseListTableTestItem::create(['name' => 'Alpha', 'code' => 'A1']);
        BaseListTableTestItem::create(['name' => 'Beta', 'code' => 'B2']);

        $component = $this->makeComponent('B2');
        $result = $component->publicApplySearch(
            BaseListTableTestItem::query(),
            ['name', 'code']
        );

        $this->assertSame(1, $result->count());
        $this->assertSame('Beta', $result->first()->name);
    }

    public function test_apply_search_supports_or_where_conditions(): void
    {
        BaseListTableTestItem::create(['name' => 'Alpha', 'code' => 'A1']);
        BaseListTableTestItem::create(['name' => 'Beta', 'code' => 'XYZ']);

        $component = $this->makeComponent('XYZ');
        $result = $component->publicApplySearch(
            BaseListTableTestItem::query(),
            ['name', 'code']
        );

        $this->assertSame(1, $result->count());
        $this->assertSame('Beta', $result->first()->name);
    }
}

class BaseListTableTestItem extends Model
{
    protected $table = 'test_items';

    protected $fillable = ['name', 'code'];

    public $timestamps = false;
}

/**
 * Concrete subclass of BaseListTable that exposes applySearch publicly
 * for testing without Livewire's full component lifecycle.
 */
class BaseListTableTestStub extends BaseListTable
{
    public function publicApplySearch($query, array $columns)
    {
        return $this->applySearch($query, $columns);
    }

    public function render()
    {
        return '';
    }
}
