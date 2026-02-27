<?php

namespace App\Imports;

use App\Models\BudgetItem;
use App\Models\Campus;
use App\Models\Department;
use App\Models\ProductVariant;
use App\Models\PurchaseRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PurchaseRequestImport implements ToCollection, WithHeadingRow, WithChunkReading
{
    private const CHUNK_SIZE = 1000;
    private const DEFAULT_EMAIL_DOMAIN = '@mjqeducation.edu.kh';

    // Import result data
    private array $data = ['created' => [], 'created_refs' => [], 'skipped' => [], 'errors' => []];

    // Row tracking
    private int $processedRows = 0;

    // Cache arrays
    private array $createdRefToId = [];
    private array $existingRefs = [];
    private array $skippedRefs = [];
    private array $variantIdByCode = [];
    private array $budgetIdByRef = [];
    private array $idsByType = []; // unified cache for campus/dept
    private array $userByKey = [];
    private ?int $fallbackCreatorId = null;

    public function chunkSize(): int
    {
        return self::CHUNK_SIZE;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        // Prepare and filter rows
        $baseRow = $this->processedRows + 2;
        $preparedRows = $this->prepareRows($rows, $baseRow);
        $this->processedRows += $preparedRows->count();

        if ($preparedRows->isEmpty()) {
            return;
        }

        // Warm all caches
        $this->warmCaches($preparedRows);

        // Group rows by reference and process
        $groups = $this->groupRowsByReference($preparedRows);
        foreach ($groups as $ref => $groupRows) {
            $this->importReferenceGroup($ref, $groupRows);
        }
    }

    public function getData(): array
    {
        return $this->data;
    }

    // ============ ROW PREPARATION ============

    /**
     * Prepare rows: normalize data, skip empty rows, add metadata
     */
    private function prepareRows(Collection $rows, int $baseRow): Collection
    {
        return $rows->values()
            ->map(function ($row, int $i) use ($baseRow) {
                $r = array_map(static fn($v) => is_string($v) ? trim($v) : $v, $row->toArray());
                $r['_row'] = $baseRow + $i;
                $r['_ref'] = $this->normalize($r['reference_no'] ?? null);
                return $r;
            })
            ->filter(function ($r) {
                // Skip completely empty rows
                $dataWithoutMeta = array_filter($r, fn($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
                return !empty(array_filter($dataWithoutMeta, fn($v) => $v !== '' && $v !== null));
            });
    }

    /**
     * Group rows by reference number
     */
    private function groupRowsByReference(Collection $rows): array
    {
        $groups = [];
        foreach ($rows as $r) {
            if (empty($r['_ref'])) {
                $this->data['errors'][] = "Row {$r['_row']}: reference_no is required.";
                continue;
            }
            $groups[$r['_ref']][] = $r;
        }
        return $groups;
    }

    // ============ MAIN IMPORT LOGIC ============

    private function importReferenceGroup(string $ref, array $groupRows): void
    {
        if (isset($this->skippedRefs[$ref])) {
            return;
        }

        $alreadyCreated = isset($this->createdRefToId[$ref]);
        if (!$alreadyCreated && isset($this->existingRefs[$ref])) {
            $this->skippedRefs[$ref] = true;
            $this->data['skipped'][] = $ref;
            $this->data['errors'][] = "Reference {$ref} already exists. Skipped.";
            return;
        }

        $items = $this->prepareItems($groupRows);
        if (empty($items)) {
            $start = $groupRows[0]['_row'] ?? '?';
            $this->data['errors'][] = "Reference {$ref} (row {$start}): no valid items.";
            return;
        }

        $first = $groupRows[0];

        try {
            $result = DB::transaction(function () use ($ref, $first, $items, $alreadyCreated) {
                $prId = $this->createdRefToId[$ref] ?? null;
                $createdById = null;
                $isNew = false;

                if (!$prId) {
                    $creator = $this->resolveUser($this->cleanName($first['created_by_name'] ?? $first['created_by_email'] ?? null));
                    $createdById = $creator?->id ?? $this->fallbackCreatorId();
                    if (!$createdById) {
                        throw new \RuntimeException('Unable to resolve created_by user.');
                    }

                    $pr = PurchaseRequest::create([
                        'reference_no' => $ref,
                        'request_date' => $this->parseDate($first['request_date'] ?? null) ?? now()->format('Y-m-d'),
                        'deadline_date' => $first['deadline_date'] ?? null,
                        'purpose' => $first['purpose'] ?? null,
                        'is_urgent' => $this->toBool($first['is_urgent'] ?? false) ? 1 : 0,
                        'created_by' => $createdById,
                        'position_id' => $creator?->current_position_id ?? 5,
                    ]);

                    $prId = (int) $pr->id;
                    $isNew = true;
                } else {
                    $createdById = (int) PurchaseRequest::whereKey($prId)->value('created_by');
                }

                $this->insertItems($prId, $items);

                if ($isNew) {
                    $this->insertApprovals($prId, $ref, $createdById, $first['approvals'] ?? null, $first['date_approved'] ?? null);
                }

                return ['id' => $prId, 'new' => !$alreadyCreated && $isNew];
            });

            if (!empty($result['new'])) {
                $this->createdRefToId[$ref] = $result['id'];
                $this->data['created'][] = $result['id'];
                $this->data['created_refs'][] = $ref;
            }
        } catch (\Throwable $e) {
            $this->data['errors'][] = "Reference {$ref}: {$e->getMessage()}";
        }
    }

    // ============ ITEM PREPARATION ============

    private function prepareItems(array $rows): array
    {
        $items = [];
        foreach ($rows as $r) {
            $row = (int) ($r['_row'] ?? 0);

            // Validate item code
            $code = $this->normalize($r['item_code'] ?? null);
            if (!$code || !isset($this->variantIdByCode[$code])) {
                $this->data['errors'][] = $code
                    ? "Row {$row}: item_code {$code} not found."
                    : "Row {$row}: item_code is required.";
                continue;
            }

            // Validate quantity and price
            $qty = $this->toFloat($r['quantity'] ?? null);
            if ($qty === null || $qty <= 0) {
                $this->data['errors'][] = "Row {$row}: quantity must be > 0.";
                continue;
            }

            $price = $this->toFloat($r['unit_price'] ?? null);
            if ($price === null || $price < 0) {
                $this->data['errors'][] = "Row {$row}: unit_price must be >= 0.";
                continue;
            }

            // Validate and process budget
            $budgetRef = $this->normalize($r['budget_code_ref'] ?? null);
            if ($budgetRef && !isset($this->budgetIdByRef[$budgetRef])) {
                $this->data['errors'][] = "Row {$row}: budget_code_ref {$budgetRef} not found.";
                continue;
            }

            // Build item
            $items[] = $this->buildItem($r, $code, $qty, $price, $budgetRef);
        }

        return $items;
    }

    /**
     * Build a single item array from row data
     */
    private function buildItem(array $r, string $code, float $qty, float $price, ?string $budgetRef): array
    {
        $currency = strtoupper($this->normalize($r['currency'] ?? 'USD') ?? 'USD');
        $rate = $this->toFloat($r['exchange_rate'] ?? 1.0, 1.0);

        // Build description
        $desc = !empty($r['description_1'])
            ? $r['description_1'] . (!empty($r['description_2']) ? ' / ' . $r['description_2'] : '')
            : ($r['remarks'] ?? null);

        return [
            'product_id' => $this->variantIdByCode[$code],
            'budget_code_id' => $budgetRef ? $this->budgetIdByRef[$budgetRef] : null,
            'quantity' => $qty,
            'unit_price' => $price,
            'currency' => $currency,
            'exchange_rate' => $rate,
            'description' => $desc,
            'purchasing_status' => $this->toInt($r['item_status'] ?? 0),
            'purchaser_id' => $this->resolveUser($this->cleanName($r['purchasers'] ?? null))?->id,
            'campus_ids' => $this->mapIds($r['campus_names'] ?? null, 'campus'),
            'department_ids' => $this->mapIds($r['department_names'] ?? null, 'dept'),
        ];
    }

    // ============ DATABASE OPERATIONS ============

    private function insertItems(int $prId, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $now = now();
        $lastId = (int) DB::table('purchase_request_items')->max('id');
        $rows = [];
        $meta = [];

        foreach ($items as $i) {
            $total = $i['quantity'] * $i['unit_price'];
            $usd = $i['currency'] === 'KHR' && $i['exchange_rate'] > 0 ? $total / $i['exchange_rate'] : $total;

            $rows[] = [
                'purchase_request_id' => $prId,
                'product_id' => $i['product_id'],
                'budget_code_id' => $i['budget_code_id'],
                'description' => $i['description'],
                'currency' => $i['currency'],
                'exchange_rate' => $i['exchange_rate'],
                'quantity' => $i['quantity'],
                'unit_price' => $i['unit_price'],
                'total_price' => $total,
                'total_price_usd' => $usd,
                'purchasing_status' => $i['purchasing_status'],
                'purchaser_id' => $i['purchaser_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $meta[] = ['usd' => $usd, 'campus_ids' => $i['campus_ids'], 'dept_ids' => $i['department_ids']];
        }

        // Insert items
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('purchase_request_items')->insert($chunk);
        }

        // Retrieve inserted item IDs
        $ids = DB::table('purchase_request_items')
            ->where('id', '>', $lastId)
            ->where('purchase_request_id', $prId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if (count($ids) !== count($rows)) {
            throw new \RuntimeException('Inserted item ID mapping mismatch.');
        }

        // Insert campus and department relationships
        $this->insertCampusRelationships($ids, $meta, $now);
        $this->insertDepartmentRelationships($ids, $meta, $now);
    }

    private function insertCampusRelationships(array $ids, array $meta, $now): void
    {
        $rows = [];
        foreach ($ids as $idx => $itemId) {
            $m = $meta[$idx];
            $campusUsd = $m['usd'] / max(1, count($m['campus_ids']));

            foreach ($m['campus_ids'] as $campusId) {
                $rows[] = [
                    'purchase_request_item_id' => $itemId,
                    'campus_id' => $campusId,
                    'total_usd' => $campusUsd,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('purchase_item_campuses')->insert($chunk);
        }
    }

    private function insertDepartmentRelationships(array $ids, array $meta, $now): void
    {
        $rows = [];
        foreach ($ids as $idx => $itemId) {
            $m = $meta[$idx];
            $deptUsd = $m['usd'] / max(1, count($m['dept_ids']));

            foreach ($m['dept_ids'] as $deptId) {
                $rows[] = [
                    'purchase_request_item_id' => $itemId,
                    'department_id' => $deptId,
                    'total_usd' => $deptUsd,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('purchase_item_departments')->insert($chunk);
        }
    }

    private function insertApprovals(int $prId, string $ref, int $requesterId, mixed $raw, mixed $dateApproved): void
    {
        $names = $this->splitCsv($raw, '/[,;]+/');
        if (empty($names)) {
            return;
        }

        $responded = $this->parseDate($dateApproved);

        $now = now();
        $rows = [];
        foreach ($names as $name) {
            $user = $this->resolveUser($name);
            if (!$user?->id) {
                continue;
            }

            $rows[] = [
                'approvable_type' => PurchaseRequest::class,
                'approvable_id' => $prId,
                'document_name' => 'Purchase Request',
                'document_reference' => $ref,
                'request_type' => 'approve',
                'approval_status' => 'Approved',
                'is_seen' => 1,
                'ordinal' => $this->ordinal('approve'),
                'requester_id' => $requesterId,
                'responder_id' => $user->id,
                'responded_date' => $responded,
                'position_id' => $user->current_position_id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            DB::table('approvals')->insert($rows);
        }
    }

    // ============ CACHE WARMING ============

    private function warmCaches(Collection $rows): void
    {
        $this->warmProductVariants($rows);
        $this->warmBudgetItems($rows);
        $this->warmLocationData($rows);
        $this->warmUsersFromRows($rows);
        $this->warmExistingReferences($rows);
    }

    private function warmProductVariants(Collection $rows): void
    {
        $codes = [];
        foreach ($rows as $r) {
            $code = $this->normalize($r['item_code'] ?? null);
            if ($code && !isset($this->variantIdByCode[$code])) {
                $codes[] = $code;
            }
        }

        $codes = array_values(array_unique($codes));
        if (empty($codes)) return;

        ProductVariant::select('id', 'item_code')
            ->whereIn('item_code', $codes)
            ->get()
            ->each(fn($v) => $this->variantIdByCode[$this->normalize($v->item_code)] = $v->id);
    }

    private function warmBudgetItems(Collection $rows): void
    {
        $refs = [];
        foreach ($rows as $r) {
            $ref = $this->normalize($r['budget_code_ref'] ?? null);
            if ($ref && !isset($this->budgetIdByRef[$ref])) {
                $refs[] = $ref;
            }
        }

        $refs = array_values(array_unique($refs));
        if (empty($refs)) return;

        BudgetItem::select('id', 'reference_no')
            ->whereIn('reference_no', $refs)
            ->get()
            ->each(fn($b) => $this->budgetIdByRef[$this->normalize($b->reference_no)] = $b->id);
    }

    private function warmLocationData(Collection $rows): void
    {
        $campuses = [];
        $depts = [];

        foreach ($rows as $r) {
            foreach ($this->splitCsv($r['campus_names'] ?? null) as $c) {
                $k = "campus:" . strtolower($c);
                if (!isset($this->idsByType[$k])) {
                    $campuses[] = $c;
                }
            }
            foreach ($this->splitCsv($r['department_names'] ?? null) as $d) {
                $k = "dept:" . strtolower($d);
                if (!isset($this->idsByType[$k])) {
                    $depts[] = $d;
                }
            }
        }

        $campuses = array_values(array_unique($campuses));
        Campus::select('id', 'short_name')->whereIn('short_name', $campuses)->get()
            ->each(fn($c) => $this->idsByType['campus:' . strtolower($c->short_name)] = $c->id);

        $depts = array_values(array_unique($depts));
        Department::select('id', 'short_name')->whereIn('short_name', $depts)->get()
            ->each(fn($d) => $this->idsByType['dept:' . strtolower($d->short_name)] = $d->id);
    }

    private function warmUsersFromRows(Collection $rows): void
    {
        $names = [];
        foreach ($rows as $r) {
            $names[] = $this->cleanName($r['created_by_name'] ?? $r['created_by_email'] ?? null);
            $names[] = $this->cleanName($r['received_by'] ?? null);
            $names[] = $this->cleanName($r['purchasers'] ?? null);
            foreach ($this->splitCsv($r['approvals'] ?? null, '/[,;]+/') as $n) {
                $names[] = $n;
            }
        }
        $this->warmUsers($names);
    }

    private function warmUsers(array $names): void
    {
        $names = array_values(array_unique(array_filter(array_map([$this, 'cleanName'], $names))));
        if (empty($names)) {
            return;
        }

        // Load existing users
        User::select('id', 'name', 'email', 'current_position_id')
            ->whereIn('name', $names)
            ->get()
            ->each(fn($u) => $this->userByKey[$this->userKey($u->name)] = $u);

        // Create missing users
        foreach ($names as $name) {
            $key = $this->userKey($name);
            if (isset($this->userByKey[$key])) {
                continue;
            }

            $user = User::create([
                'name' => $name,
                'email' => $this->buildEmail($name),
                'password' => bcrypt(Str::random(20)),
            ]);
            $this->userByKey[$key] = $user;
        }
    }

    private function warmExistingReferences(Collection $rows): void
    {
        $refs = [];
        foreach ($rows as $r) {
            $ref = $r['_ref'] ?? null;
            if ($ref && !isset($this->createdRefToId[$ref]) && !isset($this->existingRefs[$ref]) && !isset($this->skippedRefs[$ref])) {
                $refs[] = $ref;
            }
        }

        $refs = array_values(array_unique($refs));
        if (empty($refs)) return;

        PurchaseRequest::withTrashed()
            ->whereIn('reference_no', $refs)
            ->pluck('reference_no')
            ->each(fn($ref) => $this->existingRefs[$this->normalize($ref)] = true);
    }

    // ============ HELPER METHODS ============

    private function resolveUser(?string $name): ?User
    {
        $name = $this->cleanName($name);
        if (!$name) {
            return null;
        }

        $key = $this->userKey($name);
        if (!isset($this->userByKey[$key])) {
            $this->warmUsers([$name]);
        }

        return $this->userByKey[$key] ?? null;
    }

    private function mapIds(mixed $csv, string $type): array
    {
        $ids = [];
        foreach ($this->splitCsv($csv) as $name) {
            $k = "{$type}:" . strtolower($name);
            if (isset($this->idsByType[$k])) {
                $ids[] = $this->idsByType[$k];
            }
        }
        return array_values(array_unique($ids));
    }

    private function splitCsv(mixed $value, string $regex = '/,/'): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $parts = preg_split($regex, $value) ?: [];
        return array_values(array_unique(array_filter(array_map([$this, 'cleanName'], $parts))));
    }

    // ============ DATA NORMALIZATION ============

    /**
     * Normalize reference values (uppercase, trimmed)
     */
    private function normalize(mixed $v): ?string
    {
        if (!is_scalar($v)) {
            return null;
        }
        $s = strtoupper(trim((string) $v));
        return $s !== '' ? $s : null;
    }

    /**
     * Clean name values (trimmed, preserve case)
     */
    private function cleanName(mixed $v): ?string
    {
        if (!is_scalar($v)) {
            return null;
        }
        $s = trim((string) $v);
        return $s !== '' ? $s : null;
    }

    private function userKey(string $name): string
    {
        return strtolower(trim($name));
    }

    private function toFloat(?string $value, float $default = null): ?float
    {
        if (!is_numeric($value)) {
            return $default;
        }
        $f = (float) $value;
        return $f > 0 ? $f : $default;
    }

    private function toInt(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    private function toBool(mixed $v): bool
    {
        if (is_bool($v)) {
            return $v;
        }
        if (is_numeric($v)) {
            return (int) $v === 1;
        }
        return is_string($v) && in_array(strtolower(trim($v)), ['1', 'true', 'yes', 'y'], true);
    }

    private function parseDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildEmail(string $name): string
    {
        $local = Str::of($name)
            ->lower()
            ->replaceMatches('/\s+/', '.')
            ->replaceMatches('/[^a-z0-9\.]/', '')
            ->trim('.')
            ->value();

        if ($local === '') {
            $local = 'user';
        }

        return $local . '.' . substr(md5(strtolower($name)), 0, 8) . self::DEFAULT_EMAIL_DOMAIN;
    }

    private function fallbackCreatorId(): ?int
    {
        if ($this->fallbackCreatorId !== null) {
            return $this->fallbackCreatorId;
        }
        $this->fallbackCreatorId = User::query()->value('id');
        return $this->fallbackCreatorId;
    }

    private function ordinal(string $type): int
    {
        return match (strtolower($type)) {
            'initial' => 1,
            'check' => 2,
            'review' => 3,
            'approve' => 4,
            'acknowledge' => 5,
            default => 1,
        };
    }
}
