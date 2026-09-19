<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentGatewayRequest;
use App\Models\PaymentGateway;
use App\Services\AuditLogger;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PaymentGatewayController extends Controller
{
    public function __construct(protected PaymentGatewayManager $manager)
    {
    }

    public function index()
    {
        $gateways = PaymentGateway::query()
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get()
            ->map(fn ($g) => $g->toSafeArray());

        return Inertia::render('Admin/Settings/PaymentGateways/Index', [
            'gateways' => $gateways,
        ]);
    }

    public function edit(PaymentGateway $paymentGateway)
    {
        $adapter = $this->manager->forModel($paymentGateway);
        $schema = $adapter->credentialSchema();
        return Inertia::render('Admin/Settings/PaymentGateways/Edit', [
            'gateway' => $paymentGateway->toSafeArray($schema),
            'schema' => $schema,
            'supports' => $adapter->supports(),
        ]);
    }

    public function update(PaymentGatewayRequest $request, PaymentGateway $paymentGateway)
    {
        $validated = $request->validated();
        $adapter = $this->manager->forModel($paymentGateway);
        $schema = $adapter->credentialSchema();

        $existingCreds = $paymentGateway->credentials ?? [];
        $incomingCreds = $validated['credentials'] ?? [];
        $mergedCreds = $existingCreds;
        foreach ($schema as $field => $meta) {
            $incoming = $incomingCreds[$field] ?? null;
            // empty string = keep existing (masked input); non-null = replace.
            if ($incoming !== null && $incoming !== '') {
                $mergedCreds[$field] = $incoming;
            }
        }
        foreach ($schema as $field => $meta) {
            if (! empty($meta['required']) && empty($mergedCreds[$field] ?? null)) {
                return back()->with('error', "Field '{$meta['label']}' is required.")->withInput();
            }
        }

        $before = [
            'is_active' => $paymentGateway->is_active,
            'is_default' => $paymentGateway->is_default,
            'mode' => $paymentGateway->mode,
            'name' => $paymentGateway->name,
        ];

        DB::transaction(function () use ($paymentGateway, $validated, $mergedCreds) {
            $paymentGateway->name = $validated['name'];
            $paymentGateway->description = $validated['description'] ?? null;
            $paymentGateway->mode = $validated['mode'];
            $paymentGateway->is_active = (bool) ($validated['is_active'] ?? false);
            $paymentGateway->priority = (int) ($validated['priority'] ?? 0);
            $paymentGateway->config = array_merge((array) $paymentGateway->config, $validated['config'] ?? []);
            $paymentGateway->credentials = $mergedCreds;

            $wantDefault = (bool) ($validated['is_default'] ?? false);
            if ($wantDefault) {
                PaymentGateway::where('id', '!=', $paymentGateway->id)->update(['is_default' => false]);
                $paymentGateway->is_default = true;
                $paymentGateway->is_active = true;
            } else {
                $paymentGateway->is_default = false;
            }
            $paymentGateway->save();
        });

        AuditLogger::record('payment_gateway.update', $paymentGateway->fresh(), [
            'before' => $before,
            'after' => [
                'is_active' => $paymentGateway->is_active,
                'is_default' => $paymentGateway->is_default,
                'mode' => $paymentGateway->mode,
                'name' => $paymentGateway->name,
            ],
        ], 'Payment gateway updated.');

        return back()->with('success', 'Gateway updated.');
    }

    public function toggle(PaymentGateway $paymentGateway)
    {
        $paymentGateway->is_active = ! $paymentGateway->is_active;
        if (! $paymentGateway->is_active && $paymentGateway->is_default) {
            $paymentGateway->is_default = false;
        }
        $paymentGateway->save();
        AuditLogger::record('payment_gateway.toggle', $paymentGateway,
            ['is_active' => $paymentGateway->is_active], 'Gateway toggled.');
        return back()->with('success', $paymentGateway->is_active ? 'Gateway enabled.' : 'Gateway disabled.');
    }

    public function setDefault(PaymentGateway $paymentGateway)
    {
        DB::transaction(function () use ($paymentGateway) {
            PaymentGateway::where('id', '!=', $paymentGateway->id)->update(['is_default' => false]);
            $paymentGateway->is_default = true;
            $paymentGateway->is_active = true;
            $paymentGateway->save();
        });
        AuditLogger::record('payment_gateway.set_default', $paymentGateway, null, 'Set as default gateway.');
        return back()->with('success', $paymentGateway->name . ' set as default.');
    }

    public function testConnection(Request $request, PaymentGateway $paymentGateway)
    {
        $adapter = $this->manager->forModel($paymentGateway);
        $result = $adapter->testConnection();
        AuditLogger::record('payment_gateway.test', $paymentGateway,
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
    public function revealCredential(Request $request, PaymentGateway $paymentGateway)
    {
        $field = (string) $request->input('field');
        $schema = $this->manager->forModel($paymentGateway)->credentialSchema();

        if (! isset($schema[$field]) || ($schema[$field]['type'] ?? null) !== 'password') {
            return response()->json(['message' => 'Unknown credential field.'], 404);
        }

        $value = ($paymentGateway->credentials ?? [])[$field] ?? null;
        if ($value === null || $value === '') {
            return response()->json(['message' => 'Nothing stored for this field yet.'], 404);
        }

        AuditLogger::record(
            'payment_gateway.credential_revealed',
            $paymentGateway,
            ['field' => $field],
            "Revealed {$schema[$field]['label']}."
        );

        return response()->json(['value' => (string) $value]);
    }
}
