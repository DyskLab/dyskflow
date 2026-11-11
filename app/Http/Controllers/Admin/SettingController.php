<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\AdminMenu;
use App\Support\SettingsSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Throwable;

class SettingController extends Controller
{
    public function index()
    {
        return view('admin.settings-index', [
            'title' => __('Settings'),
            'cards' => AdminMenu::settingsCards(),
        ]);
    }

    public function edit(string $section)
    {
        $schema = $this->section($section);

        return view('admin.settings-form', [
            'title' => $schema['title'],
            'section' => $section,
            'schema' => $schema,
            'values' => collect($schema['fields'])->mapWithKeys(fn ($f) => [$f['name'] => setting($f['name'], $f['default'] ?? null)])->all(),
        ]);
    }

    public function update(Request $request, string $section)
    {
        $schema = $this->section($section);
        $data = $request->validate($this->rules($schema['fields']));
        $values = [];

        foreach ($schema['fields'] as $field) {
            $name = $field['name'];
            $value = $data[$name] ?? null;
            $values[$name] = match ($field['type'] ?? 'text') {
                'switch' => $value ? '1' : '0',
                'checkboxes' => implode(',', array_filter((array) $value)),
                'secret' => $value ? encrypt($value) : setting($name),
                default => $value,
            };
        }
        if (isset($values['site_url'])) {
            $values['site_url'] = rtrim($values['site_url'], '/');
        }

        Setting::write($values);
        activity('settings.updated', $section);
        // A section can act on its new values, such as downloading the chosen fonts.
        $problems = isset($schema['saved']) ? (array) ($schema['saved'])() : [];

        return back()->with('success', __('Saved.'))->with('error', $problems ? implode(' ', $problems) : null);
    }

    public function testEmail(Request $request)
    {
        $to = $request->validate(['to' => 'required|email'])['to'];
        try {
            Mail::raw(__('This is a test email from :site.', ['site' => setting('site_title')]), function ($message) use ($to) {
                $message->to($to)->subject(__('Test email'));
            });
        } catch (Throwable $e) {
            return back()->with('error', __('Sending failed: :message', ['message' => $e->getMessage()]));
        }

        return back()->with('success', __('Test email sent to :email.', ['email' => $to]));
    }

    private function section(string $section): array
    {
        $schema = SettingsSchema::sections()[$section] ?? null;
        abort_if(! $schema || ! empty($schema['hidden']), 404);

        return $schema;
    }

    private function rules(array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $type = $field['type'] ?? 'text';
            $presence = ! empty($field['required']) ? 'required' : 'nullable';
            $rules[$field['name']] = match ($type) {
                'email' => [$presence, 'email', 'max:190'],
                'url' => [$presence, 'url:http,https', 'max:255'],
                'number' => [$presence, 'integer', 'min:0'],
                'select' => [$presence, Rule::in(array_map('strval', array_keys($field['options'])))],
                'checkboxes' => ['nullable', 'array'],
                'switch' => ['nullable', 'in:0,1'],
                'color' => [$presence, 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
                'textarea' => [$presence, 'string', 'max:10000'],
                default => [$presence, 'string', 'max:500'],
            };
            if ($type === 'checkboxes') {
                $rules[$field['name'].'.*'] = ['nullable', Rule::in(array_merge([''], array_map('strval', array_keys($field['options']))))];
            }
        }

        return $rules;
    }
}
