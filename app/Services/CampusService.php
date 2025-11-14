<?php

namespace App\Services;

use App\Models\Campus;
use Illuminate\Http\Request;

class CampusService
{
    /**
     * Fetch campuses with pagination only.
     */
    public function getCampuses(Request $request)
    {
        $campuses = Campus::where('is_active', 1)->get();

        return $campuses->map(fn($c) => [
            'id'   => $c->id,
            'text' => $c->name, // Select2 needs "text"
        ]);
    }
}
