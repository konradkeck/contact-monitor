{{--
  Activity quick-view modal overlay.
  Place once in the layout. Activated by buttons with data-modal-src attribute via openActivityModal().
--}}
<div id="activity-modal-overlay"
     class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40"
     onclick="if(event.target===this)closeActivityModal()">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl mx-4 relative">
        <button onclick="closeActivityModal()"
                class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 text-lg leading-none z-10">✕</button>
        <div id="activity-modal-body" class="min-h-[120px] flex items-center justify-center text-gray-400 text-sm italic">
            Loading…
        </div>
    </div>
</div>
<script>
function openActivityModal(btn) {
    const src = btn.dataset.modalSrc;
    const overlay = document.getElementById('activity-modal-overlay');
    const body    = document.getElementById('activity-modal-body');
    body.className = 'min-h-[80px] flex items-center justify-center';
    body.innerHTML = '<span class="text-gray-400 italic text-sm">Loading…</span>';
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
    fetch(src, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
        .then(r => r.text())
        .then(html => {
            body.className = 'overflow-y-auto max-h-[80vh]';
            body.innerHTML = html;
            // Re-execute <script> tags — innerHTML doesn't run them automatically
            body.querySelectorAll('script').forEach(old => {
                const s = document.createElement('script');
                s.textContent = old.textContent;
                document.head.appendChild(s);
                document.head.removeChild(s);
            });
        })
        .catch(() => { body.className = 'min-h-[80px] flex items-center justify-center'; body.innerHTML = '<span class="text-red-400 text-sm">Failed to load.</span>'; });
}
function closeActivityModal() {
    const overlay = document.getElementById('activity-modal-overlay');
    overlay.classList.add('hidden');
    overlay.classList.remove('flex');
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeActivityModal(); });
</script>
