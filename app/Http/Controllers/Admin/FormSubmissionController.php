<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Support\DataTable;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Content > Forms: messages sent with the builder's Form widget. */
class FormSubmissionController extends Controller
{
    public function index()
    {
        $bulk = route('admin.forms.bulk');
        $forms = FormSubmission::query()->distinct()->orderBy('form')->pluck('form', 'form')->all();

        return view('admin.table', [
            'title' => __('Forms'),
            'description' => __('Messages sent with the Form widget in the builder. Add a Form widget to any page to collect them.'),
            'actions' => $forms ? [['label' => __('Export CSV'), 'icon' => 'download', 'secondary' => true, 'url' => route('admin.forms.export', ['form' => request()->input('f.form')])]] : [],
            'table' => DataTable::make([
                'id' => 'forms',
                'source' => FormSubmission::query(),
                'columns' => [
                    ['key' => 'summary', 'label' => __('Message'), 'render' => fn ($s) => new HtmlString(
                        '<div style="font-weight:'.($s->is_read ? '400' : '600').'">'.e($s->summary(90)).'</div>'
                        .'<div class="muted small">'.e($s->email ?: '—').'</div>')],
                    ['key' => 'form', 'label' => __('Form'), 'sort' => true, 'render' => fn ($s) => new HtmlString(e($s->form).($s->page_title ? '<div class="muted small">'.e($s->page_title).'</div>' : ''))],
                    ['key' => 'is_read', 'label' => __('Status'), 'sort' => true, 'render' => fn ($s) => new HtmlString($s->is_read
                        ? '<span class="badge badge-muted">'.e(__('Read')).'</span>'
                        : '<span class="badge badge-warning">'.e(__('New')).'</span>')],
                    ['key' => 'created_at', 'label' => __('Received'), 'sort' => true, 'render' => fn ($s) => cms_date($s->created_at)],
                ],
                'search' => ['form', 'email', 'data'],
                'filters' => [
                    'form' => ['label' => __('Form'), 'options' => $forms],
                    'is_read' => ['label' => __('Status'), 'options' => ['0' => __('New'), '1' => __('Read')]],
                ],
                'default_sort' => ['created_at', 'desc'],
                'empty' => __('No messages yet. Add a Form widget to a page in the builder to collect them.'),
                'bulk_url' => $bulk,
                'bulk' => [
                    'read' => ['label' => __('Mark as read')],
                    'unread' => ['label' => __('Mark as new')],
                    'delete' => ['label' => __('Delete'), 'danger' => true, 'confirm' => __('Delete the selected messages? This cannot be undone.')],
                ],
                'row_actions' => fn ($s) => [
                    ['label' => __('Open'), 'icon' => 'mail', 'sheet' => route('admin.forms.show', $s)],
                    ['label' => __('Delete'), 'icon' => 'trash', 'post' => $bulk, 'fields' => ['ids[]' => [$s->id], 'action' => 'delete'], 'danger' => true, 'confirm' => __('Delete this message? This cannot be undone.')],
                ],
            ]),
        ]);
    }

    public function show(FormSubmission $submission)
    {
        if (! $submission->is_read) {
            $submission->update(['is_read' => true]);
        }

        return view('admin.form-submission', ['submission' => $submission]);
    }

    public function bulk(Request $request)
    {
        $action = (string) $request->input('action');
        abort_unless(in_array($action, ['read', 'unread', 'delete'], true), 422);
        $query = FormSubmission::whereIn('id', $this->keys($request));
        $count = $action === 'delete' ? $query->delete() : $query->update(['is_read' => $action === 'read']);
        if ($action === 'delete') {
            activity('forms.deleted', (string) $count);
        }

        return back()->with('success', __('Done: :count item(s).', ['count' => $count]));
    }

    /** Every message (of one form, when chosen), one column per field label. Opens in Excel. */
    public function export(Request $request): StreamedResponse
    {
        $form = is_string($request->query('form')) && $request->query('form') !== '' ? $request->query('form') : null;
        $rows = FormSubmission::query()->when($form, fn ($q) => $q->where('form', $form))->orderBy('created_at')->get();
        $labels = $rows->flatMap(fn ($s) => collect($s->data)->pluck('label'))->unique()->values()->all();
        activity('forms.exported', $form ?? __('All forms'));

        return response()->streamDownload(function () use ($rows, $labels) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge([__('Received'), __('Form'), __('Page')], $labels));
            foreach ($rows as $s) {
                $values = collect($s->data)->mapWithKeys(fn ($a) => [$a['label'] => $a['value']]);
                // A leading = + - @ would run as a formula in a spreadsheet.
                $cells = array_map(fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : (string) $v,
                    array_merge([cms_date($s->created_at), $s->form, $s->page_url], array_map(fn ($l) => $values[$l] ?? '', $labels)));
                fputcsv($out, $cells);
            }
            fclose($out);
        }, 'forms-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
