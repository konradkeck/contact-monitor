@if($renderedHtml)
<div id="{{ $id }}" class="{{ $class }}"></div>
<script>
(function() {
    var el = document.getElementById('{{ $id }}');
    var shadow = el.attachShadow({ mode: 'open' });
    shadow.innerHTML =
        '<style>'
        + '*{box-sizing:border-box}'
        + 'body,div,p,span{font-family:inherit;font-size:0.875rem;line-height:1.6;max-width:100%;color:inherit}'
        + 'p{margin:0 0 0.55em}p:last-child{margin-bottom:0}'
        + 'h1,h2,h3,h4{font-weight:600;margin:0.8em 0 0.35em;line-height:1.3}'
        + 'h1{font-size:1.1em}h2{font-size:1em}h3,h4{font-size:0.9375em}'
        + 'ul,ol{padding-left:1.4em;margin:0 0 0.55em}'
        + 'li{margin-bottom:0.2em}'
        + 'code{background:rgba(0,0,0,.07);padding:.1em .3em;border-radius:3px;font-family:ui-monospace,monospace;font-size:.85em}'
        + 'pre{background:rgba(0,0,0,.06);padding:.6em .75em;border-radius:6px;overflow-x:auto;margin:0 0 .55em}'
        + 'pre code{background:none;padding:0;font-size:.8125rem}'
        + 'blockquote{border-left:3px solid #d1d5db;padding-left:.75em;margin:0 0 .55em;color:#6b7280}'
        + 'a{color:#2563eb;text-decoration:none}a:hover{text-decoration:underline}'
        + 'strong{font-weight:600}em{font-style:italic}'
        + 'table{border-collapse:collapse;margin-bottom:.55em;max-width:100%;font-size:.8125rem}'
        + 'th,td{border:1px solid #d1d5db;padding:.2em .5em;text-align:left}'
        + 'th{background:rgba(0,0,0,.04);font-weight:600}'
        + 'img{max-width:100%;height:auto}'
        + 'hr{border:none;border-top:1px solid #e5e7eb;margin:.6em 0}'
        + 'del{text-decoration:line-through;color:#9ca3af}'
        + '</style>'
        + @json($renderedHtml);
})();
</script>
@elseif($plainText)
<p class="whitespace-pre-wrap {{ $class }}">{{ $plainText }}</p>
@endif
