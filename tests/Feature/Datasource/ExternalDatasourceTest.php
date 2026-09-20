<?php

namespace Tests\Feature\Datasource;

use App\Exceptions\DatasourceNotConfiguredException;
use App\Exceptions\ReadOnlyDatasourceException;
use App\Http\Middleware\EnsureDatasource;
use App\Models\Concerns\ExternalModel;
use App\Services\DatasourceRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The foundation for external databases (config/datasources.php).
 *
 * Datasources are per-environment by nature, so these register throwaway ones
 * against in-memory SQLite at runtime rather than depending on a real upstream
 * system being reachable from CI.
 */
class ExternalDatasourceTest extends TestCase
{
    private function declareSource(string $key, bool $readOnly = true, ?string $database = ':memory:'): void
    {
        Config::set("datasources.sources.{$key}", [
            'label' => strtoupper($key),
            'read_only' => $readOnly,
            'connection' => ['driver' => 'sqlite', 'database' => $database],
        ]);

        // DatasourceServiceProvider::register() already ran for this process, so
        // a source declared mid-test registers its own connection the same way.
        Config::set("database.connections.ds_{$key}", [
            'driver' => 'sqlite',
            'database' => $database,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge("ds_{$key}");
    }

    /** Creates a table on a datasource, bypassing its read-only guard. */
    private function seedTable(string $key): void
    {
        $readOnly = config("datasources.sources.{$key}.read_only");

        Config::set("datasources.sources.{$key}.read_only", false);

        $connection = DB::connection("ds_{$key}");
        $connection->statement('create table pasien (id integer primary key, nama text)');
        $connection->table('pasien')->insert(['id' => 1, 'nama' => 'Budi']);

        Config::set("datasources.sources.{$key}.read_only", $readOnly);
    }

    public function test_an_unregistered_datasource_fails_by_name(): void
    {
        $this->expectException(DatasourceNotConfiguredException::class);
        $this->expectExceptionMessageMatches('/tidak terdaftar/');

        app(DatasourceRegistry::class)->connection('tidak-ada');
    }

    public function test_a_datasource_without_a_database_name_is_treated_as_absent(): void
    {
        $this->declareSource('kosong', database: null);

        $registry = app(DatasourceRegistry::class);

        $this->assertTrue($registry->has('kosong'), 'Datasource tetap terdaftar di config.');
        $this->assertFalse($registry->isConfigured('kosong'), 'Tanpa nama database ia dianggap belum dikonfigurasi.');

        $this->expectException(DatasourceNotConfiguredException::class);

        $registry->connection('kosong');
    }

    public function test_a_read_only_datasource_still_answers_selects(): void
    {
        $this->declareSource('simrs');
        $this->seedTable('simrs');

        $rows = app(DatasourceRegistry::class)->connection('simrs')->table('pasien')->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Budi', $rows->first()->nama);
    }

    public function test_a_read_only_datasource_refuses_a_write_from_the_query_builder(): void
    {
        $this->declareSource('simrs');
        $this->seedTable('simrs');

        $this->expectException(ReadOnlyDatasourceException::class);

        // The guard sits on the connection, not on a base model, so hand-written
        // query-builder writes are covered too - that is the whole point.
        app(DatasourceRegistry::class)->connection('simrs')->table('pasien')->insert(['nama' => 'Ani']);
    }

    public function test_a_read_only_datasource_refuses_ddl(): void
    {
        $this->declareSource('simrs');

        $this->expectException(ReadOnlyDatasourceException::class);

        DB::connection('ds_simrs')->statement('drop table if exists pasien');
    }

    public function test_a_read_write_datasource_allows_writes(): void
    {
        $this->declareSource('gudang', readOnly: false);
        $this->seedTable('gudang');

        DB::connection('ds_gudang')->table('pasien')->insert(['id' => 2, 'nama' => 'Ani']);

        $this->assertSame(2, DB::connection('ds_gudang')->table('pasien')->count());
    }

    public function test_an_external_model_resolves_its_own_datasource(): void
    {
        $this->declareSource('simrs');
        $this->seedTable('simrs');

        $this->assertSame('ds_simrs', (new FakePasien)->getConnectionName());
        $this->assertSame('Budi', FakePasien::query()->find(1)->nama);
    }

    public function test_an_external_model_on_a_read_only_datasource_refuses_to_save(): void
    {
        $this->declareSource('simrs');
        $this->seedTable('simrs');

        $this->expectException(ReadOnlyDatasourceException::class);
        $this->expectExceptionMessageMatches('/read-only/');

        FakePasien::query()->create(['nama' => 'Ani']);
    }

    public function test_the_middleware_answers_503_for_a_datasource_this_environment_lacks(): void
    {
        $this->declareSource('simrs', database: null);

        $response = app(EnsureDatasource::class)->handle(
            Request::create('/api/v1/ext/simrs/pasien'),
            fn () => response('boleh lewat'),
            'simrs',
        );

        $this->assertSame(503, $response->getStatusCode());
        $this->assertStringContainsString('SIMRS', $response->getContent());
    }

    public function test_the_middleware_passes_a_configured_datasource_through(): void
    {
        $this->declareSource('simrs');

        $response = app(EnsureDatasource::class)->handle(
            Request::create('/api/v1/ext/simrs/pasien'),
            fn () => response('boleh lewat'),
            'simrs',
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_no_datasource_may_shadow_a_first_party_connection(): void
    {
        // `tenant` is the dangerous collision: it would repoint every content
        // model at a foreign database while every query kept working.
        $registry = app(DatasourceRegistry::class);

        $this->assertNotContains(
            config('datasources.prefix').'tenant',
            DatasourceRegistry::RESERVED,
            'Prefix datasource tidak boleh menghasilkan nama koneksi sistem.'
        );

        foreach ($registry->keys() as $key) {
            $this->assertNotContains(
                $registry->connectionName($key),
                DatasourceRegistry::RESERVED,
                "Datasource '{$key}' menimpa koneksi sistem."
            );
        }
    }
}

/** Fixture: the shape every App\Models\External\* model is expected to take. */
class FakePasien extends ExternalModel
{
    protected string $datasource = 'simrs';

    protected $table = 'pasien';

    protected $guarded = [];
}
