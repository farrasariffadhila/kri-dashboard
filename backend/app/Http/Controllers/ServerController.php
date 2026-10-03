<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServerController extends Controller
{
    /** GET /api/servers: paginated, filterable server inventory. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'compliance' => ['nullable', Rule::in(['compliant', 'non_compliant'])],
            'os_version' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $page = Server::query()
            ->with('department:id,name')
            ->when($filters['department_id'] ?? null, fn ($q, $id) => $q->where('department_id', $id))
            ->when($filters['compliance'] ?? null, fn ($q, $c) => $q->where('is_compliant', $c === 'compliant'))
            ->when($filters['os_version'] ?? null, fn ($q, $os) => $q->where('os_version', $os))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '\\%_').'%';
                $q->where(fn ($w) => $w->where('hostname', 'like', $like)->orWhere('ip_address', 'like', $like));
            })
            // Non-compliant first: that is what a reviewer is looking for.
            ->orderBy('is_compliant')
            ->orderBy('hostname')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString()
            ->through(fn (Server $s) => [
                'id' => $s->id,
                'department' => ['id' => $s->department->id, 'name' => $s->department->name],
                'hostname' => $s->hostname,
                'ip_address' => $s->ip_address,
                'os_version' => $s->os_version,
                'is_compliant' => $s->is_compliant,
            ]);

        return response()->json([
            ...$page->toArray(),
            'facets' => [
                'os_versions' => Server::query()->distinct()->orderBy('os_version')->pluck('os_version'),
            ],
        ]);
    }
}
