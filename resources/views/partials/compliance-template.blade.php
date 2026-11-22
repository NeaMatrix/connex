{{--
    Connex Compliance Protect: the template id the anti-fraud script reads to
    match the page with the one Connex approved. Pair it with
    <body class="cptpl_page"> in any custom layout.
--}}
@if (filled($template = config('connex.pages.compliance_template')))
    <input type="hidden" value="{{ $template }}" name="cptpl_template" class="cptpl_template">
@endif
