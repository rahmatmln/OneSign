<!-- Modal Alasan Penolakan Dokumen (OneSign Design System) -->
<div id="rejectionReasonModal" 
     class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs transition-opacity animate-in fade-in duration-150"
     role="dialog" 
     aria-modal="true" 
     aria-labelledby="rejectionModalTitle">
    
    <div class="relative w-full max-w-lg bg-surface-container-lowest rounded-2xl shadow-2xl border border-outline-variant/30 overflow-hidden flex flex-col max-h-[90vh]">
        
        <!-- Header Modal -->
        <div class="px-5 py-4 bg-surface-container-low border-b border-outline-variant/20 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-rose-100 text-rose-800 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[20px]">error_outline</span>
                </div>
                <div>
                    <h3 id="rejectionModalTitle" class="font-headline-sm text-sm sm:text-base font-bold text-on-surface">
                        Alasan Penolakan Dokumen
                    </h3>
                    <p class="text-xs text-secondary">Catatan revisi dari penandatangan</p>
                </div>
            </div>
            <button type="button" 
                    onclick="closeRejectionModal()" 
                    class="w-8 h-8 rounded-lg hover:bg-surface-container-high flex items-center justify-center text-secondary hover:text-on-surface transition-colors cursor-pointer"
                    title="Tutup">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <!-- Konten / Body Modal -->
        <div class="p-5 overflow-y-auto space-y-4 font-body-md text-body-md">
            <!-- Informasi Dokumen -->
            <div class="p-3 rounded-xl bg-surface-container-low/70 border border-outline-variant/20 space-y-1.5 text-xs">
                <div class="flex items-start justify-between gap-2">
                    <span class="text-secondary font-medium shrink-0">Judul Dokumen:</span>
                    <span id="rejectionDocTitle" class="font-semibold text-on-surface text-right break-words">-</span>
                </div>
                <div class="flex items-center justify-between gap-2 pt-1 border-t border-outline-variant/15">
                    <span class="text-secondary font-medium shrink-0">Ditinjau oleh:</span>
                    <span id="rejectionSignerName" class="font-medium text-on-surface text-right truncate">-</span>
                </div>
            </div>

            <!-- Catatan Alasan Penolakan -->
            <div class="space-y-1.5">
                <label class="font-label-sm text-xs font-semibold text-rose-950 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px] text-rose-700">chat</span>
                    <span>Catatan Revisi / Alasan:</span>
                </label>
                <div class="p-4 rounded-xl bg-rose-50/70 border border-rose-200/80 text-rose-950 text-sm leading-relaxed whitespace-pre-wrap break-words min-h-[90px]" id="rejectionNoteText">
                    -
                </div>
            </div>

            <!-- Petunjuk Lanjutan -->
            <div class="flex items-start gap-2 p-3 rounded-lg bg-surface-container text-xs text-secondary leading-normal">
                <span class="material-symbols-outlined text-[16px] text-primary shrink-0 mt-0.5">help_outline</span>
                <p>Silakan sesuaikan berkas naskah PDF sesuai catatan revisi di atas, lalu ajukan kembali sebagai dokumen baru.</p>
            </div>
        </div>

        <!-- Footer Modal -->
        <div class="px-5 py-3 bg-surface-container-low border-t border-outline-variant/20 flex items-center justify-end">
            <button type="button" 
                    onclick="closeRejectionModal()" 
                    class="px-4 py-2 rounded-lg bg-surface-container-high hover:bg-surface-container-highest text-on-surface font-label-md text-xs font-semibold transition-colors shadow-xs cursor-pointer">
                Tutup
            </button>
        </div>

    </div>
</div>

<script>
(function() {
    const modal = document.getElementById('rejectionReasonModal');
    const docTitleEl = document.getElementById('rejectionDocTitle');
    const signerNameEl = document.getElementById('rejectionSignerName');
    const noteTextEl = document.getElementById('rejectionNoteText');

    window.openRejectionModal = function(title, signer, note) {
        if (!modal) return;
        if (docTitleEl) docTitleEl.textContent = title || '-';
        if (signerNameEl) signerNameEl.textContent = signer || 'Pejabat Penandatangan';
        if (noteTextEl) noteTextEl.textContent = (note && note.trim() !== '') ? note : 'Tidak ada catatan revisi tertulis.';
        
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    };

    window.closeRejectionModal = function() {
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    };

    // Tutup saat klik latar belakang gelap
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                window.closeRejectionModal();
            }
        });
    }

    // Tutup saat tombol Escape ditekan
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
            window.closeRejectionModal();
        }
    });
})();
</script>
