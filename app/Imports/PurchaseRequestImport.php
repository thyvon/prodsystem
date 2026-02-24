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

    private array $data = [
        'created' => [],
        'created_refs' => [],
        'skipped' => [],
        'errors' => [],
    ];

    private int $processedRows = 0;
    private array $createdRefToId = [];
    private array $existingRefs = [];
    private array $skippedRefs = [];
    private array $variantIdByCode = [];
    private array $budgetIdByRef = [];
    private array $campusIdByShort = [];
    private array $deptIdByShort = [];
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

        $baseRow = $this->processedRows + 2;
        $rows = $rows->values()->map(function ($row, int $i) use ($baseRow) {
            $r = array_map(static fn($v) => is_string($v) ? trim($v) : $v, $row->toArray());
            $r['_row'] = $baseRow + $i;
            $r['_ref'] = $this->nRef($r['reference_no'] ?? null);
            return $r;
        });
        $this->processedRows += $rows->count();

        $this->warmLookupCaches($rows);
        $this->warmUsersFromRows($rows);
        $this->warmExistingRefs($rows);

        $groups = [];
        foreach ($rows as $r) {
            if (empty($r['_ref'])) {
                $this->data['errors'][] = "Row {$r['_row']}: reference_no is required.";
                continue;
            }
            $groups[$r['_ref']][] = $r;
        }

        foreach ($groups as $ref => $groupRows) {
            $this->importReferenceGroup($ref, $groupRows);
        }
    }

    public function getData(): array
    {
        return $this->data;
    }

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
                    $creator = $this->resolveUser($this->nName($first['created_by_name'] ?? $first['created_by_email'] ?? null));
                    $createdById = $creator?->id ?? $this->fallbackCreatorId();
                    if (!$createdById) {
                        throw new \RuntimeException('Unable to resolve created_by user.');
                    }

                    $pr = PurchaseRequest::create([
                        'reference_no' => $ref,
                        'request_date' => !empty($first['request_date']) ? $first['request_date'] : now()->format('Y-m-d'),
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
                    $this->insertApprovals(
                        $prId,
                        $ref,
                        $createdById,
                        $first['approvals'] ?? null,
                        $first['date_approved'] ?? null
                    );
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

    private function prepareItems(array $rows): array
    {
        $items = [];
        foreach ($rows as $r) {
            $row = (int) ($r['_row'] ?? 0);
            $code = $this->nRef($r['item_code'] ?? null);
            if (!$code) {
                $this->data['errors'][] = "Row {$row}: item_code is required.";
                continue;
            }

            $productId = $this->variantIdByCode[$code] ?? null;
            if (!$productId) {
                $this->data['errors'][] = "Row {$row}: item_code {$code} not found.";
                continue;
            }

            $qty = is_numeric($r['quantity'] ?? null) ? (float) $r['quantity'] : null;
            if ($qty === null || $qty <= 0) {
                $this->data['errors'][] = "Row {$row}: quantity must be > 0.";
                continue;
            }

            $price = is_numeric($r['unit_price'] ?? null) ? (float) $r['unit_price'] : null;
            if ($price === null || $price < 0) {
                $this->data['errors'][] = "Row {$row}: unit_price must be >= 0.";
                continue;
            }

            $currency = strtoupper($this->nName($r['currency'] ?? 'USD') ?? 'USD');
            $rate = is_numeric($r['exchange_rate'] ?? null) ? (float) $r['exchange_rate'] : 1.0;
            if ($rate <= 0) {
                $rate = 1.0;
            }

            $budgetRef = $this->nRef($r['budget_code_ref'] ?? null);
            $budgetId = $budgetRef ? ($this->budgetIdByRef[$budgetRef] ?? null) : null;
            if ($budgetRef && !$budgetId) {
                $this->data['errors'][] = "Row {$row}: budget_code_ref {$budgetRef} not found.";
                continue;
            }

            $desc = $r['remarks'] ?? null;
            if (!empty($r['description_1'])) {
                $desc = $r['description_1'];
                if (!empty($r['description_2'])) {
                    $desc .= ' / ' . $r['description_2'];
                }
            }

            $status = isset($r['item_status']) && is_numeric($r['item_status']) ? (int) $r['item_status'] : 0;
            $purchaser = $this->resolveUser($this->nName($r['purchasers'] ?? null));

            $items[] = [
                'product_id' => $productId,
                'budget_code_id' => $budgetId,
                'quantity' => $qty,
                'unit_price' => $price,
                'currency' => $currency,
                'exchange_rate' => $rate,
                'description' => $desc,
                'purchasing_status' => $status,
                'purchaser_id' => $purchaser?->id,
                'campus_ids' => $this->mapCampusIds($r['campus_names'] ?? null),
                'department_ids' => $this->mapDeptIds($r['department_names'] ?? null),
            ];
        }

        return $items;
    }

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

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('purchase_request_items')->insert($chunk);
        }

        $ids = DB::table('purchase_request_items')
            ->where('id', '>', $lastId)
            ->where('purchase_request_id', $prId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if (count($ids) !== count($rows)) {
            throw new \RuntimeException('Inserted item ID mapping mismatch.');
        }

        $campusRows = [];
        $deptRows = [];
        foreach ($ids as $idx => $itemId) {
            $m = $meta[$idx];
            $campusUsd = $m['usd'] / max(1, count($m['campus_ids']));
            $deptUsd = $m['usd'] / max(1, count($m['dept_ids']));

            foreach ($m['campus_ids'] as $campusId) {
                $campusRows[] = [
                    'purchase_request_item_id' => $itemId,
                    'campus_id' => $campusId,
                    'total_usd' => $campusUsd,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($m['dept_ids'] as $deptId) {
                $deptRows[] = [
                    'purchase_request_item_id' => $itemId,
                    'department_id' => $deptId,
                    'total_usd' => $deptUsd,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($campusRows, 1000) as $chunk) {
            DB::table('purchase_item_campuses')->insert($chunk);
        }
        foreach (array_chunk($deptRows, 1000) as $chunk) {
            DB::table('purchase_item_departments')->insert($chunk);
        }
    }

    private function insertApprovals(int $prId, string $ref, int $requesterId, mixed $raw, mixed $dateApproved): void
    {
        $names = $this->splitCsv($raw, '/[,;]+/');
        if (empty($names)) {
            return;
        }

        $responded = null;
        if (!empty($dateApproved)) {
            try {
                $responded = Carbon::parse($dateApproved)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                $responded = null;
            }
        }

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

    private function warmLookupCaches(Collection $rows): void
    {
        $codes = [];
        $budgets = [];
        $campuses = [];
        $depts = [];

        foreach ($rows as $r) {
            $code = $this->nRef($r['item_code'] ?? null);
            if ($code && !isset($this->variantIdByCode[$code])) {
                $codes[] = $code;
            }

            $budget = $this->nRef($r['budget_code_ref'] ?? null);
            if ($budget && !isset($this->budgetIdByRef[$budget])) {
                $budgets[] = $budget;
            }

            foreach ($this->splitCsv($r['campus_names'] ?? null) as $c) {
                $k = strtolower($c);
                if (!isset($this->campusIdByShort[$k])) {
                    $campuses[] = $k;
                }
            }
            foreach ($this->splitCsv($r['department_names'] ?? null) as $d) {
                $k = strtolower($d);
                if (!isset($this->deptIdByShort[$k])) {
                    $depts[] = $k;
                }
            }
        }

        $codes = array_values(array_unique($codes));
        if (!empty($codes)) {
            ProductVariant::query()->select('id', 'item_code')->whereIn('item_code', $codes)->get()->each(function (ProductVariant $v) {
                $k = $this->nRef($v->item_code);
                if ($k) {
                    $this->variantIdByCode[$k] = $v->id;
                }
            });
        }

        $budgets = array_values(array_unique($budgets));
        if (!empty($budgets)) {
            BudgetItem::query()->select('id', 'reference_no')->whereIn('reference_no', $budgets)->get()->each(function (BudgetItem $b) {
                $k = $this->nRef($b->reference_no);
                if ($k) {
                    $this->budgetIdByRef[$k] = $b->id;
                }
            });
        }

        $campuses = array_values(array_unique($campuses));
        if (!empty($campuses)) {
            Campus::query()->select('id', 'short_name')->whereIn('short_name', $campuses)->get()->each(function (Campus $c) {
                $this->campusIdByShort[strtolower($c->short_name)] = $c->id;
            });
        }

        $depts = array_values(array_unique($depts));
        if (!empty($depts)) {
            Department::query()->select('id', 'short_name')->whereIn('short_name', $depts)->get()->each(function (Department $d) {
                $this->deptIdByShort[strtolower($d->short_name)] = $d->id;
            });
        }
    }

    private function warmUsersFromRows(Collection $rows): void
    {
        $names = [];
        foreach ($rows as $r) {
            $names[] = $this->nName($r['created_by_name'] ?? $r['created_by_email'] ?? null);
            $names[] = $this->nName($r['received_by'] ?? null);
            $names[] = $this->nName($r['purchasers'] ?? null);
            foreach ($this->splitCsv($r['approvals'] ?? null, '/[,;]+/') as $n) {
                $names[] = $n;
            }
        }
        $this->warmUsers($names);
    }

    private function warmUsers(array $names): void
    {
        $names = array_values(array_unique(array_filter(array_map([$this, 'nName'], $names))));
        if (empty($names)) {
            return;
        }

        $missing = [];
        foreach ($names as $n) {
            if (!isset($this->userByKey[$this->userKey($n)])) {
                $missing[] = $n;
            }
        }
        if (empty($missing)) {
            return;
        }

        User::query()->select('id', 'name', 'email', 'current_position_id')->whereIn('name', $missing)->get()->each(function (User $u) {
            $this->userByKey[$this->userKey($u->name)] = $u;
        });

        foreach ($missing as $name) {
            $key = $this->userKey($name);
            if (isset($this->userByKey[$key])) {
                continue;
            }
            $u = User::create([
                'name' => $name,
                'email' => $this->buildEmail($name),
                'password' => bcrypt(Str::random(20)),
            ]);
            $this->userByKey[$key] = $u;
        }
    }

    private function warmExistingRefs(Collection $rows): void
    {
        $refs = [];
        foreach ($rows as $r) {
            $ref = $r['_ref'] ?? null;
            if ($ref && !isset($this->createdRefToId[$ref]) && !isset($this->existingRefs[$ref]) && !isset($this->skippedRefs[$ref])) {
                $refs[] = $ref;
            }
        }
        $refs = array_values(array_unique($refs));
        if (empty($refs)) {
            return;
        }

        $existing = PurchaseRequest::withTrashed()->whereIn('reference_no', $refs)->pluck('reference_no')->all();
        foreach ($existing as $ref) {
            $n = $this->nRef($ref);
            if ($n) {
                $this->existingRefs[$n] = true;
            }
        }
    }

    private function resolveUser(?string $name): ?User
    {
        $name = $this->nName($name);
        if (!$name) {
            return null;
        }
        $key = $this->userKey($name);
        if (!isset($this->userByKey[$key])) {
            $this->warmUsers([$name]);
        }
        return $this->userByKey[$key] ?? null;
    }

    private function mapCampusIds(mixed $csv): array
    {
        $ids = [];
        foreach ($this->splitCsv($csv) as $n) {
            $k = strtolower($n);
            if (isset($this->campusIdByShort[$k])) {
                $ids[] = $this->campusIdByShort[$k];
            }
        }
        return array_values(array_unique($ids));
    }

    private function mapDeptIds(mixed $csv): array
    {
        $ids = [];
        foreach ($this->splitCsv($csv) as $n) {
            $k = strtolower($n);
            if (isset($this->deptIdByShort[$k])) {
                $ids[] = $this->deptIdByShort[$k];
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
        $parts = array_filter(array_map([$this, 'nName'], $parts));
        return array_values(array_unique($parts));
    }

    private function nRef(mixed $v): ?string
    {
        if (!is_scalar($v)) {
            return null;
        }
        $s = strtoupper(trim((string) $v));
        return $s !== '' ? $s : null;
    }

    private function nName(mixed $v): ?string
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

    private function buildEmail(string $name): string
    {
        $local = Str::of($name)->lower()->replaceMatches('/\s+/', '.')->replaceMatches('/[^a-z0-9\.]/', '')->trim('.')->value();
        if ($local === '') {
            $local = 'user';
        }
        return $local . '.' . substr(md5(strtolower($name)), 0, 8) . self::DEFAULT_EMAIL_DOMAIN;
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

