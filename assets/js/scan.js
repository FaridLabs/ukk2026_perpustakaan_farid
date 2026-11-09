/* Dipakai peminjaman.php dan pengembalian.php: cegah kirim ganda, status scan, fokus tetap di kolom. */
(function () {
    window.pasangScan=function(kode, pesanSiap) {
        var form = document.getElementById('form-' + kode);
        if (!form) return;
        var inp = document.getElementById('input-' + kode), btn = document.getElementById('btn-' + kode);
        var bungkus = document.getElementById('wrap-' + kode), st = document.getElementById('status-' + kode);
        var label = btn.innerHTML, kirim = false;

        form.addEventListener('submit', function (e) {
            if (kirim) { e.preventDefault(); return; }
            kirim = true; btn.disabled = true; btn.textContent = 'Memproses...';
            st.textContent = 'Memproses, mohon tunggu...';
        });
        inp.addEventListener('input', function () {
            bungkus.classList.toggle('live', inp.value.length > 0);
            st.textContent = inp.value ? 'Membaca input... tekan Enter jika scanner tidak mengirim otomatis.' : pesanSiap;
        });
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); if (form.requestSubmit) form.requestSubmit(); else form.submit(); }
        });
        // klik di area kosong mengembalikan fokus ke kolom scan
        document.addEventListener('click', function (e) {
            if (!e.target.closest('a,button,input,select,textarea,label,table')) inp.focus();
        });
        // kembali dari halaman proses (tombol Back): pulihkan tombol
        window.addEventListener('pageshow', function () { kirim = false; btn.disabled = false; btn.innerHTML = label; inp.focus(); });
        inp.focus();
    };
})();