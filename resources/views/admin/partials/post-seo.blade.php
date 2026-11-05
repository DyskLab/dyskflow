{{-- SEO fields with the search preview and checklist. Vars: post, seoBase, translation (hides the shared social image). --}}
<div class="card">
    <div class="card-header"><h2 class="card-title">SEO</h2></div>
    <div class="card-content stack" data-seo data-site="{{ setting('site_title') }}" data-base="{{ $seoBase }}/">
        <div class="serp" aria-label="{{ __('Search result preview') }}">
            <div class="serp-url" data-serp-url></div>
            <div class="serp-title" data-serp-title></div>
            <div class="serp-desc" data-serp-desc></div>
        </div>
        <div class="field">
            <label class="label" for="focus_keyword">{{ __('Focus keyword') }}</label>
            <input class="input" id="focus_keyword" name="focus_keyword" value="{{ old('focus_keyword', $post->focus_keyword) }}">
        </div>
        <div class="field">
            <div class="row between"><label class="label" for="meta_title">{{ __('Meta title') }}</label><span class="counter" data-count="meta_title" data-max="60"></span></div>
            <input class="input" id="meta_title" name="meta_title" value="{{ old('meta_title', $post->meta_title) }}">
        </div>
        <div class="field">
            <div class="row between"><label class="label" for="meta_description">{{ __('Meta description') }}</label><span class="counter" data-count="meta_description" data-max="160"></span></div>
            <textarea class="textarea" id="meta_description" name="meta_description" rows="2">{{ old('meta_description', $post->meta_description) }}</textarea>
        </div>
        <div class="field">
            <label class="label" for="canonical_url">{{ __('Canonical URL') }}</label>
            <input class="input" id="canonical_url" type="url" name="canonical_url" value="{{ old('canonical_url', $post->canonical_url) }}" placeholder="https://">
        </div>
        @unless ($translation ?? false)
            @include('admin.partials.field', ['field' => ['name' => 'og_image', 'label' => __('Social image'), 'type' => 'media'], 'value' => $post->og_image])
        @endunless
        @include('admin.partials.field', ['field' => ['name' => 'noindex', 'label' => __('Hide from search engines'), 'type' => 'switch'], 'value' => $post->noindex ? '1' : '0'])
        <div class="stack-sm">
            <span class="label">{{ __('SEO checklist') }}</span>
            <div class="stack-sm" data-seo-checks></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
window.CMS_SEO = {!! json_encode([
    'title' => __('Title is 30–60 characters'),
    'description' => __('Meta description is 70–160 characters'),
    'keyword_title' => __('Focus keyword appears in the title'),
    'keyword_content' => __('Focus keyword appears in the content'),
    'keyword_slug' => __('Focus keyword appears in the slug'),
    'length' => __('Content has at least 300 words'),
    'heading' => __('Content has an H2 heading'),
    'image' => __('Has at least one image'),
    'image_alt' => __('All images have alt text'),
], JSON_HEX_TAG) !!};
</script>
@endpush
