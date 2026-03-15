<div id="{{ $id }}" class="{{ $class }}"></div>
<script>
(function() {
    var el = document.getElementById('{{ $id }}');
    var shadow = el.attachShadow({ mode: 'open' });
    shadow.innerHTML =
        '<style>*{box-sizing:border-box}body,div,p,span,td,th{font-family:inherit;font-size:0.875rem;line-height:1.5;max-width:100%}img{max-width:100%;height:auto}a{color:#2563eb}table{border-collapse:collapse;max-width:100%}</style>'
        + @json($content);
})();
</script>
