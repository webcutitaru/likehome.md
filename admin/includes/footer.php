</main> 

<div id="deleteModal" class="fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-[9999] hidden items-center justify-center p-4">
    <div class="bg-white w-full max-w-sm rounded-[2.5rem] p-10 text-center shadow-2xl">
        <div class="bg-red-100 w-16 h-16 rounded-full flex items-center justify-center text-red-500 mx-auto mb-6">
            <i data-lucide="alert-triangle" class="w-8 h-8"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-900 mb-2 leading-tight tracking-tight">Ești sigur?</h3>
        <p class="text-slate-500 text-sm mb-8 leading-relaxed px-2">
            Această acțiune este permanentă. Te rugăm să scrii <strong class="text-slate-900 italic">DELETE</strong> pentru a confirma ștergerea: <br>
            <span id="targetTitle" class="text-slate-900 font-bold block mt-2 underline decoration-red-500 decoration-2 underline-offset-4"></span>
        </p>
        
        <input type="text" id="confirmInput" class="w-full p-4 bg-slate-50 border border-slate-100 rounded-2xl text-center font-bold text-red-500 focus:ring-2 focus:ring-red-500 outline-none mb-4" oninput="checkInput()" placeholder="SCRIE AICI...">
        
        <div class="space-y-3">
            <button id="realDeleteBtn" onclick="finalDelete()" class="w-full py-4 bg-red-500 text-white rounded-2xl font-bold opacity-20 pointer-events-none transition-all shadow-lg shadow-red-200">Șterge definitiv</button>
            <button onclick="closeModal()" class="w-full py-3 text-slate-400 font-bold hover:text-slate-900 transition-all text-xs uppercase tracking-widest">Anulează</button>
        </div>
    </div>
</div>

<script>
    // Logic-ul JS rămâne cel din fișierul tău, adaptat doar pentru noul ID și clase
    let deleteId = null;

    function askDelete(id, title) {
        deleteId = id;
        document.getElementById('targetTitle').innerText = title;
        document.getElementById('confirmInput').value = '';
        document.getElementById('deleteModal').classList.remove('hidden');
        document.getElementById('deleteModal').classList.add('flex');
        checkInput();
        lucide.createIcons(); // Reactivăm iconițele în modal
    }

    function closeModal() {
        document.getElementById('deleteModal').classList.add('hidden');
        document.getElementById('deleteModal').classList.remove('flex');
    }

    function checkInput() {
        const val = document.getElementById('confirmInput').value;
        const btn = document.getElementById('realDeleteBtn');
        if (val === "DELETE") {
            btn.classList.remove('opacity-20', 'pointer-events-none');
        } else {
            btn.classList.add('opacity-20', 'pointer-events-none');
        }
    }

    function finalDelete() {
        if (document.getElementById('confirmInput').value === "DELETE" && deleteId !== null) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.action = 'delete-property.php';
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = 'csrf_token';
            csrf.value = '<?php echo htmlspecialchars(lh_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>';
            const id = document.createElement('input');
            id.type = 'hidden';
            id.name = 'id';
            id.value = String(deleteId);
            f.appendChild(csrf);
            f.appendChild(id);
            document.body.appendChild(f);
            f.submit();
        }
    }
</script>

<div id="lh-admin-img-lightbox" class="hidden fixed inset-0 bg-black/90 p-6 pt-16" style="z-index:9998" aria-hidden="true" role="dialog" aria-modal="true" aria-label="Imagine mărită">
    <button type="button" id="lh-admin-img-lightbox-close" class="absolute top-3 right-3 z-10 flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white text-slate-900 shadow-lg ring-2 ring-white/30 hover:bg-slate-100 focus:outline-none focus:ring-4 focus:ring-cta/80 active:scale-95" aria-label="Închide (Esc)">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>
    <img id="lh-admin-img-lightbox-img" src="" alt="" class="max-h-[calc(100vh-5rem)] max-w-full cursor-default object-contain select-none">
</div>
<script src="../assets/js/admin-property-image-lightbox.js" defer></script>
<?php require_once __DIR__ . '/booking_detail_modal.php'; ?>
</body>
</html>