<?php

namespace App\Http\Controllers\Api\MasterData;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

abstract class MasterDataController extends Controller
{
    public function __construct(protected AuditLogger $audit)
    {
    }

    abstract protected function modelClass(): string;

    abstract protected function label(): string;

    abstract protected function table(): string;

    abstract protected function extraRules(): array;

    protected function nameMax(): int
    {
        return 100;
    }

    protected function rules(?Model $record): array
    {
        return array_merge([
            'name' => [
                'required',
                'string',
                'max:'.$this->nameMax(),
                Rule::unique($this->table(), 'name')->ignore($record?->getKey()),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->extraRules());
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.unique' => 'Nama sudah digunakan.',
            'name.max' => 'Nama maksimal :max karakter.',
            'description.max' => 'Deskripsi maksimal :max karakter.',
            'abbreviation.max' => 'Singkatan maksimal :max karakter.',
            'is_active.boolean' => 'Status tidak valid.',
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $model = $this->modelClass();

        $paginator = $model::query()
            ->withCount('products')
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->input('search').'%'))
            ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 10));

        return response()->json($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(null), $this->messages());
        $data['is_active'] = $data['is_active'] ?? true;

        $model = $this->modelClass();
        $record = $model::create($data);

        $this->audit->log(
            'master.'.$this->table().'.created',
            $record,
            "{$this->label()} {$record->name} ditambahkan.",
            null,
            $record->only(array_keys($data)),
        );

        return response()->json([
            'message' => "{$this->label()} berhasil ditambahkan.",
            'data' => $record,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = $this->findRecord($id);
        $data = $request->validate($this->rules($record), $this->messages());

        $record->fill($data);
        $dirty = $record->getDirty();
        $oldValues = array_intersect_key($record->getOriginal(), $dirty);
        $record->save();

        if ($dirty !== []) {
            $this->audit->log(
                'master.'.$this->table().'.updated',
                $record,
                "{$this->label()} {$record->name} diperbarui.",
                $oldValues,
                $dirty,
            );
        }

        return response()->json([
            'message' => "{$this->label()} berhasil diperbarui.",
            'data' => $record,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $record = $this->findRecord($id);
        $used = $record->products()->withTrashed()->count();

        if ($used > 0) {
            return response()->json([
                'message' => "{$this->label()} tidak dapat dihapus karena masih digunakan oleh {$used} produk. Nonaktifkan saja.",
            ], 422);
        }

        $snapshot = $record->only(['id', 'name']);
        $record->delete();

        $this->audit->log(
            'master.'.$this->table().'.deleted',
            $record,
            "{$this->label()} {$snapshot['name']} dihapus.",
            $snapshot,
        );

        return response()->json(['message' => "{$this->label()} berhasil dihapus."]);
    }

    protected function findRecord(int $id): Model
    {
        $model = $this->modelClass();

        return $model::query()->findOrFail($id);
    }
}
