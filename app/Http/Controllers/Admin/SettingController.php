<?php

namespace App\Http\Controllers\Admin;

use App\Helper\CommonHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\SettingRequest;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SettingController extends Controller
{
    public function edit(Request $request)
    {
        // Singleton row: create-on-first-visit so the admin form always has an
        // id to POST to (previously $setting was null on a fresh install and
        // submit() threw "Cannot read properties of null (reading 'id')").
        $setting = Setting::query()->first() ?? Setting::create([]);

        // Merge stored storefront_content with the model's defaults so the
        // admin form always has values to render, even before any edits.
        $payload = $setting->toArray();
        $payload['storefront_content'] = $setting->content();

        return Inertia::render('Admin/Setting/Index', [
            'setting' => $payload,
        ]);
    }

    public function update(Setting $setting, SettingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->file('image')) {
            $data['image'] = CommonHelper::uploadFile($request->file('image'), 'setting', $setting->image);
        } elseif (empty($data['image'])) {
            CommonHelper::removeOldFile("public/setting/$setting->image");
        }
        if ($request->file('icon')) {
            $data['icon'] = CommonHelper::uploadFile($request->file('icon'), 'setting', $setting->icon);
        } elseif (empty($data['icon'])) {
            CommonHelper::removeOldFile("public/setting/$setting->icon");
        }
        // Inner-page banner. Cleared back to null rather than left dangling, so
        // the storefront falls back to the bundled artwork instead of pointing
        // at a file that is no longer on disk.
        if ($request->file('page_hero_image')) {
            $data['page_hero_image'] = CommonHelper::uploadFile($request->file('page_hero_image'), 'setting', $setting->page_hero_image);
        } elseif (empty($data['page_hero_image'])) {
            CommonHelper::removeOldFile("public/setting/$setting->page_hero_image");
            $data['page_hero_image'] = null;
        }
        $setting->update($data);

        return back()->with('success', 'Setting updated successfully.');
    }
}
