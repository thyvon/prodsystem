<?php

namespace App\Services;

use App\Models\Campus;
use Illuminate\Http\Request;

class CampusService
{
    /**
     * Fetch campuses with pagination only.
     */
    public function getCampuses(Request $request): array
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'draw'  => 'nullable|integer',
        ]);

        // 📄 Pagination
        $limit = $validated['limit'] ?? 10;
        $campuses = Campus::paginate($limit);

        // 🧩 Transform
        $data = $campuses->map(fn($c) => [
            'id'          => $c->id,
            'name'        => $c->name,
            'short_name'  => $c->short_name,
            'is_active'   => $c->is_active,
        ]);

        return [
            'data'            => $data,
            'recordsTotal'    => $campuses->total(),
            'recordsFiltered' => $campuses->total(),
            'draw'            => $validated['draw'] ?? 1,
        ];
    }
}
