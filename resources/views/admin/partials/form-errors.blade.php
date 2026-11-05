{{-- admin.js shows these next to their fields and focuses the first one. --}}
@if ($errors->any())
<script>window.CMS_ERRORS = {!! json_encode($errors->toArray(), JSON_HEX_TAG | JSON_HEX_AMP) !!};</script>
@endif
