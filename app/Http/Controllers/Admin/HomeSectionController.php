<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\MenuItem;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class HomeSectionController extends Controller
{
    public function __construct(protected AuditLogger $audit)
    {
    }

    public function index()
    {
        $sections = HomeSection::orderBy('sort_order')->get();
        $categories = Category::orderBy('name')->get();
        $header = MenuItem::where('location', 'header')->orderBy('sort_order')->get();
        $footer = MenuItem::where('location', 'footer')->orderBy('sort_order')->get();
        return view('admin.appearance.index', compact('sections', 'categories', 'header', 'footer'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'sections' => 'required|array',
            'sections.*.id' => 'required|integer|exists:home_sections,id',
            'sections.*.title' => 'required|string|max:150',
            'sections.*.subtitle' => 'nullable|string|max:255',
            'sections.*.enabled' => 'nullable|boolean',
            'sections.*.limit' => 'nullable|integer|min:1|max:24',
            'sections.*.category_id' => 'nullable|integer',
        ]);
        foreach (array_values($data['sections']) as $i => $s) {
            $section = HomeSection::find($s['id']);
            $settings = $section->settings ?? [];
            $settings['limit'] = (int) ($s['limit'] ?? 6);
            $settings['category_id'] = ! empty($s['category_id']) ? (int) $s['category_id'] : null;
            $section->update(['title' => $s['title'], 'subtitle' => $s['subtitle'] ?? null, 'enabled' => (bool) ($s['enabled'] ?? false), 'sort_order' => $i, 'settings' => $settings]);
        }
        Cache::forget('home.sections');
        $this->audit->log('settings_changed', 'appearance', null, 'Home page sections updated');
        return back()->with('success', 'Home page sections saved.');
    }

    public function storeMenu(Request $request)
    {
        $data = $request->validate(['location' => ['required', Rule::in(['header', 'footer'])], 'label' => 'required|string|max:100', 'url' => 'required|string|max:500', 'target' => ['nullable', Rule::in(['_self', '_blank'])]]);
        $data['sort_order'] = (int) MenuItem::where('location', $data['location'])->max('sort_order') + 1;
        MenuItem::create($data);
        Cache::forget('site.nav');
        $this->audit->log('settings_changed', 'appearance', null, 'Menu item added: '.$data['label']);
        return back()->with('success', 'Menu item added.');
    }

    public function updateMenu(Request $request)
    {
        $data = $request->validate(['items' => 'required|array', 'items.*.id' => 'required|integer', 'items.*.label' => 'required|string|max:100', 'items.*.url' => 'required|string|max:500', 'items.*.is_active' => 'nullable|boolean']);
        foreach (array_values($data['items']) as $i => $it) {
            MenuItem::whereKey($it['id'])->update(['label' => $it['label'], 'url' => $it['url'], 'is_active' => (bool) ($it['is_active'] ?? false), 'sort_order' => $i]);
        }
        Cache::forget('site.nav');
        return back()->with('success', 'Menu saved.');
    }

    public function destroyMenu(MenuItem $item)
    {
        $item->delete();
        Cache::forget('site.nav');
        return back()->with('success', 'Menu item removed.');
    }
}
