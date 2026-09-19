<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourierPartnerRequest;
use App\Models\DeliveryPartner;
use App\Services\AuditLogger;
use App\Services\Courier\CourierManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CourierPartnerController extends Controller
{
    public function __construct(protected CourierManager $manager)
    {
    }

    public function index()
    {
        $partners = DeliveryPartner::query()
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => $p->toSafeArray());

        return Inertia::render('Admin/Settings/Couriers/Index', [
            'partners' => $partners,
        ]);
    }

    public function edit(DeliveryPartner $courier)
    {
        $adapter = $this->tryAdapter($courier);
        $schema = $adapter?->credentialSchema() ?? [];
        return Inertia::render('Admin/Settings/Couriers/Edit', [
            'partner' => $courier->toSafeArray($schema),
            'schema' => $schema,
            'supports' => $adapter?->supports() ?? [],
            'unsupported' => $adapter === null,
        ]);
    }

    public function update(CourierPartnerRequest $request, DeliveryPartner $courier)
    {
        $validated = $request->validated();
        $adapter = $this->tryAdapter($courier);
        $schema = $adapter?->credentialSchema() ?? [];

        $existingCreds = $courier->credentials ?? [];
        $incoming = $validated['credentials'] ?? [];
        $mergedCreds = $existingCreds;
        foreach ($schema as $field => $meta) {
            $val = $incoming[$field] ?? null;
            if ($val !== null && $val !== '') {
                $mergedCreds[$field] = $val;
            }
        }
        foreach ($schema as $field => $meta) {
            if (! empty($meta['required']) && empty($mergedCreds[$field] ?? null)) {
                return back()->with('error', "Field '{$meta['label']}' is required.")->withInput();
            }
        }

        $before = [
            'is_active' => $courier->is_active,
            'is_default' => $courier->is_default,
            'mode' => $courier->mode,
            'name' => $courier->name,
        ];

        DB::transaction(function () use ($courier, $validated, $mergedCreds) {
            $courier->name = $validated['name'];
            $courier->description = $validated['description'] ?? null;
            $courier->mode = $validated['mode'];
            $courier->is_active = (bool) ($validated['is_active'] ?? false);
            $courier->priority = (int) ($validated['priority'] ?? 0);
            $courier->api_base_url = $validated['api_base_url'] ?? $courier->api_base_url;
            $courier->config = array_merge((array) $courier->config, $validated['config'] ?? []);
            $courier->credentials = $mergedCreds;

            $wantDefault = (bool) ($validated['is_default'] ?? false);
            if ($wantDefault) {
                DeliveryPartner::where('id', '!=', $courier->id)->update(['is_default' => false]);
                $courier->is_default = true;
                $courier->is_active = true;
            } else {
                $courier->is_default = false;
            }
            $courier->save();
        });

        AuditLogger::record('courier.update', $courier->fresh(), [
            'before' => $before,
            'after' => [
                'is_active' => $courier->is_active,
                'is_default' => $courier->is_default,
                'mode' => $courier->mode,
                'name' => $courier->name,
            ],
        ], 'Courier updated.');

        return back()->with('success', 'Courier updated.');
    }

    public function toggle(DeliveryPartner $courier)
    {
        $courier->is_active = ! $courier->is_active;
        if (! $courier->is_active && $courier->is_default) {
            $courier->is_default = false;
        }
        $courier->save();
        AuditLogger::record('courier.toggle', $courier,
            ['is_active' => $courier->is_active], 'Courier toggled.');
        return back()->with('success', $courier->is_active ? 'Courier enabled.' : 'Courier disabled.');
    }

    public function setDefault(DeliveryPartner $courier)
    {
        DB::transaction(function () use ($courier) {
            DeliveryPartner::where('id', '!=', $courier->id)->update(['is_default' => false]);
            $courier->is_default = true;
            $courier->is_active = true;
            $courier->save();
        });
        AuditLogger::record('courier.set_default', $courier, null, 'Set as default courier.');
        return back()->with('success', $courier->name . ' set as default.');
    }

    public function testConnection(Request $request, DeliveryPartner $courier)
    {
        $adapter = $this->tryAdapter($courier);
        if (! $adapter) {
            return back()->with('error', 'No adapter is available for this courier code.');
        }
        $result = $adapter->testConnection();
        AuditLogger::record('courier.test', $courier,
            ['ok' => $result['ok'] ?? false], $result['message'] ?? '');
        return back()->with($result['ok'] ?? false ? 'success' : 'error', $result['message'] ?? 'Test completed.');
    }

    /**
     * Hand a single stored secret back to the admin who asked for it.
     *
     * Secrets are deliberately kept out of the edit payload, so this is the
     * only way to see one. Every call is audited with the field name, and the
     * route is throttled so a stolen session cannot sweep the whole store.
     */
    public function revealCredential(Request $request, DeliveryPartner $courier)
    {
        $field = (string) $request->input('field');
        $schema = $this->tryAdapter($courier)?->credentialSchema() ?? [];

        if (! isset($schema[$field]) || ($schema[$field]['type'] ?? null) !== 'password') {
            return response()->json(['message' => 'Unknown credential field.'], 404);
        }

        $value = ($courier->credentials ?? [])[$field] ?? null;
        if ($value === null || $value === '') {
            return response()->json(['message' => 'Nothing stored for this field yet.'], 404);
        }

        AuditLogger::record(
            'courier.credential_revealed',
            $courier,
            ['field' => $field],
            "Revealed {$schema[$field]['label']}."
        );

        return response()->json(['value' => (string) $value]);
    }

    protected function tryAdapter(DeliveryPartner $partner)
    {
        try {
            return $this->manager->forModel($partner);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
