<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:120'],
            'auditable_type' => ['nullable', 'string', Rule::in(AuditLog::ALLOWED_AUDITABLE_TYPES)],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->latest('created_at')
            ->latest('id');

        $query->when($validated['action'] ?? null, fn($q, string $action) => $q->where('action', $action));
        $query->when($validated['auditable_type'] ?? null, fn($q, string $type) => $q->where('auditable_type', $type));
        $query->when($validated['user_id'] ?? null, fn($q, int $userId) => $q->where('user_id', $userId));
        $query->when($validated['date_from'] ?? null, fn($q, string $date) => $q->where('created_at', '>=', $date . ' 00:00:00'));
        $query->when($validated['date_to'] ?? null, fn($q, string $date) => $q->where('created_at', '<=', $date . ' 23:59:59'));

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('action', 'like', "%{$search}%")
                    ->orWhere('request_id', 'like', "{$search}%")
                    ->orWhere('auditable_type', 'like', "{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "{$search}%");
                    });
            });
        }

        $logs = $query->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'success' => true,
            'data' => AuditLogResource::collection($logs->getCollection()),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
            'meta' => [
                'auditable_types' => AuditLog::ALLOWED_AUDITABLE_TYPES,
            ],
        ]);
    }

    public function show(AuditLog $auditLog): JsonResponse
    {
        $this->authorizeAccess();

        $auditLog->load('user:id,name,email');

        return response()->json([
            'success' => true,
            'data' => new AuditLogResource($auditLog),
        ]);
    }

    private function authorizeAccess(): void
    {
        abort_unless(auth()->user()?->hasPermission('audit_logs.view'), 403, 'Forbidden: insufficient permissions.');
    }
}
