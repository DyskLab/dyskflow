<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Support\Forms;
use App\Support\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Receives the builder's Form widget. JSON for the widget's script, a redirect without JavaScript. */
class FormController extends Controller
{
    public function submit(Request $request)
    {
        $config = Forms::open((string) $request->input('_form'));
        abort_unless($config, 422, __('This form is out of date. Reload the page and try again.'));
        app()->setLocale(in_array($config['locale'] ?? '', ['id', 'en'], true) ? $config['locale'] : app()->getLocale());
        $back = $this->backUrl($request, $config);

        // A filled hidden field means a bot: answer as if it worked, keep nothing.
        if (filled($request->input(Forms::HONEYPOT))) {
            return $this->done($request, $config, $back);
        }
        $key = 'form:'.sha1($request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['_form' => __('Too many messages. Please wait a minute and try again.')]);
        }

        $rules = [];
        $names = [];
        foreach ($config['fields'] as $i => $field) {
            $name = 'f'.$i;
            $names[$name] = $field['label'];
            $required = ! empty($field['required']) ? 'required' : 'nullable';
            $rules[$name] = match ($field['type']) {
                'email' => [$required, 'email', 'max:190'],
                'number' => [$required, 'numeric'],
                'date' => [$required, 'date'],
                'checkbox' => ! empty($field['required']) ? ['accepted'] : ['nullable'],
                'select', 'radio' => [$required, Rule::in((array) ($field['options'] ?? []))],
                'textarea' => [$required, 'string', 'max:5000'],
                default => [$required, 'string', 'max:300'],
            };
        }
        $data = validator($request->all(), $rules, [], $names)->validate();
        RateLimiter::hit($key, 60);

        $answers = [];
        $email = null;
        foreach ($config['fields'] as $i => $field) {
            $value = $field['type'] === 'checkbox' ? ($request->boolean('f'.$i) ? __('Yes') : __('No')) : trim((string) ($data['f'.$i] ?? ''));
            $answers[] = ['label' => $field['label'], 'value' => $value];
            if ($field['type'] === 'email' && $email === null && $value !== '') {
                $email = $value;
            }
        }
        if ($this->looksLikeSpam($answers)) {
            return $this->done($request, $config, $back);
        }

        $submission = FormSubmission::create([
            'form' => mb_substr((string) $config['name'], 0, 190),
            'email' => $email,
            'data' => $answers,
            'page_url' => mb_substr($back, 0, 500),
            'page_title' => mb_substr((string) ($config['page'] ?? ''), 0, 255),
            'locale' => app()->getLocale(),
        ]);
        Notifier::send('form', 'New message from the form “:form”', ['form' => $submission->form], $submission->summary(140), 'admin/forms', 'forms.manage');
        $this->mail($config, $submission);

        return $this->done($request, $config, $back);
    }

    private function done(Request $request, array $config, string $back)
    {
        $message = (string) ($config['success'] ?? '') ?: __('Thank you. Your message has been sent.');
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => $config['redirect'] ?? null]);
        }
        if (! empty($config['redirect'])) {
            return redirect()->away($config['redirect']);
        }
        $separator = str_contains($back, '?') ? '&' : '?';

        return redirect()->to($back.$separator.'sent='.urlencode((string) ($config['id'] ?? '')).'#'.($config['anchor'] ?? 'form'));
    }

    /** Back to the page of the form, but only on this site. */
    private function backUrl(Request $request, array $config): string
    {
        $url = (string) ($config['url'] ?? '');
        $root = rtrim(url('/'), '/');

        return $url !== '' && (str_starts_with($url, $root.'/') || $url === $root) ? strtok($url, '?#') : url('/');
    }

    private function looksLikeSpam(array $answers): bool
    {
        $text = mb_strtolower(collect($answers)->pluck('value')->implode(' '));
        if (preg_match_all('#https?://#', $text) > 3) {
            return true;
        }
        foreach (preg_split('/\R/', (string) setting('comments_blocklist')) ?: [] as $word) {
            $word = mb_strtolower(trim($word));
            if ($word !== '' && str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }

    /** An email to the address in the widget, or Settings > Contact. A failure never loses the message. */
    private function mail(array $config, FormSubmission $submission): void
    {
        $to = filter_var($config['email_to'] ?? '', FILTER_VALIDATE_EMAIL) ?: filter_var((string) setting('contact_email'), FILTER_VALIDATE_EMAIL);
        if (! $to) {
            return;
        }
        rescue(function () use ($to, $submission) {
            $body = collect($submission->data)->map(fn ($a) => $a['label'].":\n".$a['value'])->implode("\n\n")
                ."\n\n—\n".$submission->page_url;
            Mail::raw($body, function ($message) use ($to, $submission) {
                $message->to($to)->subject(__('New message: :form', ['form' => $submission->form]).' · '.setting('site_title'));
                if ($submission->email) {
                    $message->replyTo($submission->email);
                }
            });
        });
    }
}
