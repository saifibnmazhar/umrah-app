<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class GenerateErd extends Command
{
    protected $signature = 'erd:generate
        {--output=docs/erd : Directory to write the generated .mmd files}
        {--render : Also render SVGs (mmdc) and assemble erd.pdf (Chrome)}';

    protected $description = 'Generate Mermaid ERD diagrams (.mmd) from the database schema';

    /**
     * Framework internals that are not part of the domain model.
     */
    private const EXCLUDED_TABLES = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'password_reset_tokens',
    ];

    /**
     * Domain partition: diagram file name => human title => tables.
     * A table may appear in more than one diagram. The "overview"
     * diagram always contains every table and is built separately.
     */
    private const DOMAINS = [
        'users-auth' => [
            'title' => 'Users & Auth',
            'tables' => ['users', 'roles', 'user_roles', 'branches', 'districts'],
        ],
        'booking-core' => [
            'title' => 'Booking Core',
            'tables' => [
                'customers', 'bookings', 'packages', 'passenger_statuses', 'passengers',
                'documents', 'booking_conditions', 'stay_duration_limits', 'flight_date_gaps',
                'currency_rates', 'fingerprint_charges', 'package_update_logs',
            ],
        ],
        'travel-fares' => [
            'title' => 'Travel & Fares',
            'tables' => [
                'airlines', 'city_codes', 'classes', 'airline_cities', 'airline_classes',
                'routes', 'route_multi_segments', 'route_transits', 'ticket_fares',
                'group_tickets', 'baggage_allowances', 'ticket_fare_update_logs',
            ],
        ],
        'ticketing' => [
            'title' => 'Ticketing',
            'tables' => [
                'ticket_agents', 'issued_tickets', 'issued_ticket_logs', 're_issued_tickets',
                'refunded_tickets', 're_issue_refund_reasons', 'ticket_requests',
            ],
        ],
        'visa' => [
            'title' => 'Visa',
            'tables' => [
                'visa_agents', 'visa_agent_costs', 'visa_selling_prices', 'commission_agents',
                'visa_submissions', 'cancelled_submissions', 'visa_update_logs',
            ],
        ],
        'fingerprint' => [
            'title' => 'Fingerprint',
            'tables' => [
                'fingerprints', 'fingerprint_details', 'rescheduled_fingerprints',
                'fingerprint_cost_logs', 'fingerprint_detail_logs',
            ],
        ],
        'finance' => [
            'title' => 'Finance & Cancellations',
            'tables' => [
                'invoices', 'invoice_update_logs', 'payments', 'vouchers', 'banks',
                'transaction_types', 'cancelled_bookings', 'cancelled_passengers',
                'refund_payment_requests', 'booking_update_logs', 'passenger_update_logs',
            ],
        ],
        'logs' => [
            'title' => 'Audit Logs',
            'tables' => [
                'booking_update_logs', 'passenger_update_logs', 'invoice_update_logs',
                'visa_update_logs', 'package_update_logs', 'ticket_fare_update_logs',
                'issued_ticket_logs', 'fingerprint_cost_logs', 'fingerprint_detail_logs',
            ],
        ],
    ];

    /**
     * @var array<string, list<array{name: string, type_name: string, nullable: bool}>>
     */
    private array $columns = [];

    /**
     * @var array<string, array{primary: list<string>, unique: list<string>}>
     */
    private array $indexes = [];

    /**
     * @var array<string, list<array{columns: list<string>, foreign_table: string, foreign_columns: list<string>}>>
     */
    private array $foreignKeys = [];

    /** @var list<string> */
    private array $tables = [];

    public function handle(): int
    {
        $output = $this->option('output');
        if (! str_starts_with($output, '/')) {
            $output = base_path($output);
        }

        $this->collectSchema();

        File::ensureDirectoryExists($output);

        $written = [];

        $overview = $this->renderDiagram($this->tables, 'All tables');
        File::put($output.'/overview.mmd', $overview);
        $written[] = 'overview.mmd';

        foreach (self::DOMAINS as $name => $domain) {
            $tables = array_values(array_intersect($domain['tables'], $this->tables));

            if ($tables === []) {
                $this->warn("Domain '{$name}' matched no tables, skipping.");

                continue;
            }

            File::put($output.'/'.$name.'.mmd', $this->renderDiagram($tables, $domain['title']));
            $written[] = $name.'.mmd';
        }

        $unassigned = array_diff($this->tables, $this->domainTables());
        if ($unassigned !== []) {
            $this->warn('Tables not assigned to any domain (still in overview): '.implode(', ', $unassigned));
        }

        $relationshipCount = $this->countRelationships($this->tables);

        $this->info(sprintf(
            'Generated %d diagram(s): %d tables, %d relationships -> %s',
            count($written),
            count($this->tables),
            $relationshipCount,
            $output
        ));

        if ($this->option('render')) {
            return $this->renderArtifacts($output);
        }

        return self::SUCCESS;
    }

    private function collectSchema(): void
    {
        $this->tables = array_values(array_filter(
            Schema::getTableListing(null, false),
            fn (string $table) => ! in_array($table, self::EXCLUDED_TABLES, true)
        ));
        sort($this->tables);

        foreach ($this->tables as $table) {
            $columns = Schema::getColumns($table);
            $this->columns[$table] = array_map(fn (array $column) => [
                'name' => $column['name'],
                'type_name' => $column['type_name'],
                'nullable' => $column['nullable'],
            ], $columns);

            $primary = [];
            $unique = [];
            foreach (Schema::getIndexes($table) as $index) {
                if ($index['primary']) {
                    $primary = array_merge($primary, $index['columns']);
                } elseif ($index['unique']) {
                    $unique = array_merge($unique, $index['columns']);
                }
            }
            $this->indexes[$table] = [
                'primary' => array_values(array_unique($primary)),
                'unique' => array_values(array_unique($unique)),
            ];

            $this->foreignKeys[$table] = array_map(fn (array $fk) => [
                'columns' => $fk['columns'],
                'foreign_table' => $fk['foreign_table'],
                'foreign_columns' => $fk['foreign_columns'],
            ], Schema::getForeignKeys($table));
        }
    }

    /**
     * @param  list<string>  $tables
     */
    private function renderDiagram(array $tables, string $title): string
    {
        $set = array_flip($tables);

        $lines = ['erDiagram', "    %% {$title} — generated by php artisan erd:generate", ''];

        foreach ($tables as $table) {
            $lines[] = "    {$table} {";

            foreach ($this->columns[$table] as $column) {
                $lines[] = '        '.$this->mapType($column['type_name']).' '.$column['name'].$this->keyFlag($table, $column['name']);
            }

            $lines[] = '    }';
            $lines[] = '';
        }

        $relationships = [];
        foreach ($tables as $table) {
            foreach ($this->foreignKeys[$table] as $fk) {
                $parent = $fk['foreign_table'];
                if (! isset($set[$parent])) {
                    continue;
                }

                $child = $table;
                $childNullable = $this->isColumnNullable($child, $fk['columns'][0] ?? '');
                $cardinality = $childNullable ? 'o{' : '|{';
                $label = implode('_', $fk['columns']);

                $relationships[] = "    {$parent} ||--{$cardinality} {$child} : {$label}";
            }
        }
        $relationships = array_values(array_unique($relationships));
        sort($relationships);

        if ($relationships !== []) {
            $lines[] = '    %% Relationships';
            $lines = array_merge($lines, $relationships, ['']);
        }

        return implode("\n", $lines)."\n";
    }

    private function keyFlag(string $table, string $column): string
    {
        if (in_array($column, $this->indexes[$table]['primary'], true)) {
            return ' PK';
        }

        foreach ($this->foreignKeys[$table] as $fk) {
            if (in_array($column, $fk['columns'], true)) {
                return ' FK';
            }
        }

        if (in_array($column, $this->indexes[$table]['unique'], true)) {
            return ' UK';
        }

        return '';
    }

    private function isColumnNullable(string $table, string $column): bool
    {
        foreach ($this->columns[$table] ?? [] as $definition) {
            if ($definition['name'] === $column) {
                return $definition['nullable'];
            }
        }

        return true;
    }

    private function mapType(string $type): string
    {
        $base = strtolower(strtok($type, '(') ?: $type);

        return match (true) {
            $base === 'bigint' => 'bigint',
            in_array($base, ['int', 'integer', 'tinyint', 'smallint', 'mediumint', 'year'], true) => 'int',
            in_array($base, ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext', 'enum', 'set', 'json', 'jsonb', 'uuid'], true) => 'string',
            in_array($base, ['decimal', 'numeric', 'float', 'double', 'real'], true) => 'decimal',
            $base === 'date' => 'date',
            $base === 'datetime' => 'datetime',
            $base === 'timestamp' => 'timestamp',
            $base === 'time' => 'time',
            in_array($base, ['boolean', 'bool'], true) => 'boolean',
            in_array($base, ['blob', 'binary', 'varbinary'], true) => 'binary',
            default => 'string',
        };
    }

    /**
     * @param  list<string>  $tables
     */
    private function countRelationships(array $tables): int
    {
        $set = array_flip($tables);
        $count = 0;

        foreach ($tables as $table) {
            foreach ($this->foreignKeys[$table] as $fk) {
                if (isset($set[$fk['foreign_table']])) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function domainTables(): array
    {
        $tables = [];
        foreach (self::DOMAINS as $domain) {
            $tables = array_merge($tables, $domain['tables']);
        }

        return array_values(array_unique($tables));
    }

    private function renderArtifacts(string $output): int
    {
        $mmdc = base_path('node_modules/.bin/mmdc');
        if (! File::exists($mmdc)) {
            $this->error('mmdc not found at node_modules/.bin/mmdc (npm install @mermaid-js/mermaid-cli).');

            return self::FAILURE;
        }

        $puppeteerConfig = $output.'/.puppeteer.json';
        $config = [
            'args' => ['--no-sandbox', '--disable-setuid-sandbox'],
        ];
        $chrome = getenv('CHROME_BIN') ?: trim((string) shell_exec('command -v google-chrome 2>/dev/null'));
        if ($chrome !== '') {
            $config['executablePath'] = $chrome;
        }
        File::put($puppeteerConfig, json_encode($config));

        foreach (File::glob($output.'/*.mmd') as $mmd) {
            $svg = preg_replace('/\.mmd$/', '.svg', $mmd);
            $command = sprintf(
                '%s -i %s -o %s -p %s -b white 2>&1',
                escapeshellarg($mmdc),
                escapeshellarg($mmd),
                escapeshellarg($svg),
                escapeshellarg($puppeteerConfig)
            );

            exec($command, $renderOutput, $code);
            if ($code !== 0 || ! File::exists($svg)) {
                $this->error('mmdc failed for '.basename($mmd).': '.implode("\n", $renderOutput));

                return self::FAILURE;
            }

            $this->info('Rendered '.basename($svg));
        }

        return $this->buildPdf($output);
    }

    private function buildPdf(string $output): int
    {
        $chrome = getenv('CHROME_BIN') ?: 'google-chrome';

        $pages = [
            ['file' => 'overview.mmd', 'title' => 'Full Schema Overview'],
        ];
        foreach (self::DOMAINS as $name => $domain) {
            $pages[] = ['file' => $name.'.mmd', 'title' => $domain['title']];
        }

        $sections = [];
        $generatedAt = date('Y-m-d H:i');
        $tableCount = count($this->tables);
        $relationshipCount = $this->countRelationships($this->tables);

        $sections[] = '<section class="page cover">'
            .'<h1>Umrah App &mdash; Database ERD</h1>'
            .'<p class="meta">Generated '.$generatedAt.'</p>'
            .'<p class="meta">'.$tableCount.' tables &middot; '.$relationshipCount.' relationships &middot; '
            .(count($pages) + 1).' pages</p>'
            .'<ul>'
            .implode('', array_map(fn ($page) => '<li>'.$page['title'].'</li>', $pages))
            .'</ul></section>';

        foreach ($pages as $page) {
            $svgPath = $output.'/'.preg_replace('/\.mmd$/', '.svg', $page['file']);
            if (! File::exists($svgPath)) {
                $this->warn('Missing SVG for '.$page['file'].', skipping page.');

                continue;
            }

            $svg = File::get($svgPath);
            $svg = preg_replace('/^.*?<svg/s', '<svg', $svg, 1);

            $sections[] = '<section class="page"><h2>'.$page['title'].'</h2><div class="diagram">'.$svg.'</div></section>';
        }

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Umrah App ERD</title><style>'
            .'@page { size: A3 landscape; margin: 8mm; }'
            .'body { font-family: Arial, Helvetica, sans-serif; margin: 0; color: #0f172a; }'
            .'.page { page-break-after: always; overflow: hidden; }'
            .'.page:last-child { page-break-after: auto; }'
            .'.cover { padding-top: 60mm; text-align: center; }'
            .'.cover h1 { font-size: 42px; }'
            .'.cover .meta { color: #475569; font-size: 18px; }'
            .'.cover ul { display: inline-block; text-align: left; font-size: 16px; color: #334155; margin-top: 24px; }'
            .'h2 { font-size: 20px; margin: 0 0 6px; }'
            .'.diagram svg { width: 100% !important; height: auto !important; max-height: 258mm; max-width: 100%; }'
            .'</style></head><body>'.implode('', $sections).'</body></html>';

        $htmlPath = $output.'/build.html';
        $pdfPath = $output.'/erd.pdf';
        File::put($htmlPath, $html);

        $command = sprintf(
            '%s --headless=new --disable-gpu --no-sandbox --no-pdf-header-footer --print-to-pdf=%s file://%s 2>&1',
            escapeshellarg($chrome),
            escapeshellarg($pdfPath),
            escapeshellarg($htmlPath)
        );

        exec($command, $pdfOutput, $code);
        if ($code !== 0 || ! File::exists($pdfPath)) {
            $this->error('Chrome PDF export failed: '.implode("\n", $pdfOutput));

            return self::FAILURE;
        }

        $this->info('PDF written to '.$pdfPath);

        return self::SUCCESS;
    }
}
