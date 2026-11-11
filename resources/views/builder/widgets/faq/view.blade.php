@php
    $items = array_values(array_filter($w['items'], fn ($i) => $i['question'] !== '' && $i['answer'] !== ''));
    // Built here, not in the template: Blade would read "@context" as a directive.
    $schema = $w['schema'] && $items ? json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($item) => ['@type' => 'Question', 'name' => $item['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']]], $items),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) : null;
@endphp
@if ($items)
<div class="b-faq">
    @foreach ($items as $i => $item)
        <details class="b-faq-item"{{ $i === 0 && $w['open_first'] ? ' open' : '' }}>
            <summary>{{ $item['question'] }}</summary>
            <p>{{ $item['answer'] }}</p>
        </details>
    @endforeach
</div>
@if ($schema)<script type="application/ld+json">{!! $schema !!}</script>@endif
@endif
