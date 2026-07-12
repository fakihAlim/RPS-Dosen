// State management untuk Wizard RPS
const rpsState = {
    id: typeof rpsId !== 'undefined' ? rpsId : 0,
    kode_mk: '',
    nama_mk: '',
    sks: 2,
    semester: 1,
    program_studi: '',
    deskripsi_mk: '',
    no_dokumen: 'F-M2.STD-PD-3.6',
    revisi: '02',
    tanggal_penyusunan: '30 November 2023',
    prasyarat: '-',
    cpl: '',
    cpmk: '',
    sub_cpmk: '',
    bahan_kajian: '',
    referensi_utama: '',
    referensi_pendukung: '',
    sarana_umum: 'Ruang Kelas, Spidol, Proyektor',
    sarana_khusus: '-',
    is_draft: false,
    meetings: []
};

// Wizard navigation state
let currentStep = 1;
const totalSteps = 4;

// DOM Elements
const panels = {
    1: document.getElementById('step-panel-1'),
    2: document.getElementById('step-panel-2'),
    3: document.getElementById('step-panel-3'),
    4: document.getElementById('step-panel-4')
};

const btnPrev = document.getElementById('btn-wizard-prev');
const btnNext = document.getElementById('btn-wizard-next');
const btnSave = document.getElementById('btn-wizard-save');
const btnDraft = document.getElementById('btn-wizard-draft');
const progressBar = document.getElementById('wizard-progress');

// Inisialisasi awal
document.addEventListener('DOMContentLoaded', () => {
    initEventListeners();
    
    // Jika dalam mode edit, muat data yang ada
    if (typeof editMode !== 'undefined' && editMode && existingCourseData) {
        loadExistingData();
    }
});

function initEventListeners() {
    // Tombol Navigasi Wizard
    btnPrev.addEventListener('click', navigatePrev);
    btnNext.addEventListener('click', navigateNext);
    btnSave.addEventListener('click', () => saveRps(false));
    if (btnDraft) {
        btnDraft.addEventListener('click', saveDraft);
    }

    // AI Generator untuk Langkah 2
    document.getElementById('btn-generate-cpl').addEventListener('click', generateCplCpmkWithAI);

    // AI Generator untuk Langkah 3
    document.getElementById('btn-generate-meetings').addEventListener('click', generateMeetingsWithAI);

    // Modal Edit Pertemuan
    document.getElementById('btn-close-modal').addEventListener('click', closeModal);
    document.getElementById('btn-cancel-modal').addEventListener('click', closeModal);
    document.getElementById('edit-meeting-form').addEventListener('submit', handleModalSubmit);
}

// Memuat data lama (Edit Mode)
function loadExistingData() {
    rpsState.kode_mk = existingCourseData.kode_mk;
    rpsState.nama_mk = existingCourseData.nama_mk;
    rpsState.sks = parseInt(existingCourseData.sks);
    rpsState.semester = parseInt(existingCourseData.semester);
    rpsState.program_studi = existingCourseData.program_studi;
    rpsState.no_dokumen = existingCourseData.no_dokumen || 'F-M2.STD-PD-3.6';
    rpsState.revisi = existingCourseData.revisi || '02';
    rpsState.tanggal_penyusunan = existingCourseData.tanggal_penyusunan || '30 November 2023';
    rpsState.prasyarat = existingCourseData.prasyarat || '-';
    rpsState.deskripsi_mk = existingCourseData.deskripsi_mk || '';
    
    rpsState.cpl = existingCourseData.cpl || '';
    rpsState.cpmk = existingCourseData.cpmk || '';
    rpsState.sub_cpmk = existingCourseData.sub_cpmk || '';
    rpsState.bahan_kajian = existingCourseData.bahan_kajian || '';
    rpsState.referensi_utama = existingCourseData.referensi_utama || '';
    rpsState.referensi_pendukung = existingCourseData.referensi_pendukung || '';
    rpsState.sarana_umum = existingCourseData.sarana_umum || 'Ruang Kelas, Spidol, Proyektor';
    rpsState.sarana_khusus = existingCourseData.sarana_khusus || '-';

    // Isi Form Langkah 1
    document.getElementById('kode_mk').value = rpsState.kode_mk;
    document.getElementById('nama_mk').value = rpsState.nama_mk;
    document.getElementById('sks').value = rpsState.sks;
    document.getElementById('semester').value = rpsState.semester;
    document.getElementById('program_studi').value = rpsState.program_studi;
    document.getElementById('no_dokumen').value = rpsState.no_dokumen;
    document.getElementById('revisi').value = rpsState.revisi;
    document.getElementById('tanggal_penyusunan').value = rpsState.tanggal_penyusunan;
    document.getElementById('prasyarat').value = rpsState.prasyarat;
    document.getElementById('deskripsi_mk').value = rpsState.deskripsi_mk;
    
    // Isi Form Langkah 2
    document.getElementById('cpl').value = rpsState.cpl;
    document.getElementById('cpmk').value = rpsState.cpmk;
    document.getElementById('sub_cpmk').value = rpsState.sub_cpmk;
    document.getElementById('bahan_kajian').value = rpsState.bahan_kajian;
    document.getElementById('referensi_utama').value = rpsState.referensi_utama;
    document.getElementById('referensi_pendukung').value = rpsState.referensi_pendukung;
    document.getElementById('sarana_umum').value = rpsState.sarana_umum;
    document.getElementById('sarana_khusus').value = rpsState.sarana_khusus;

    // Isi Rencana Pertemuan
    if (existingMeetingsData && existingMeetingsData.length === 16) {
        rpsState.meetings = existingMeetingsData.map(m => ({
            pertemuan_ke: parseInt(m.pertemuan_ke),
            sub_cpmk: m.sub_cpmk,
            bahan_kajian: m.bahan_kajian,
            metode_luring: m.metode_luring || m.metode_pembelajaran || '',
            metode_daring: m.metode_daring || '',
            estimasi_waktu: m.estimasi_waktu,
            indikator_penilaian: m.indikator_penilaian || m.indikator_kriteria || '',
            bentuk_penilaian: m.bentuk_penilaian || '',
            bobot_penilaian: parseFloat(m.bobot_penilaian)
        }));
        
        renderMeetingsTable();
        // Langsung lompat ke langkah 4 karena data sudah lengkap
        goToStep(4);
    }
}

// Navigasi Wizard
function goToStep(step) {
    if (step < 1 || step > totalSteps) return;
    
    // Hapus active dari panel lama & tambah ke panel baru
    panels[currentStep].classList.remove('active');
    panels[step].classList.add('active');
    
    // Update stepper visual
    document.querySelectorAll('.step-node').forEach(node => {
        const nodeStep = parseInt(node.getAttribute('data-step'));
        node.classList.remove('active', 'completed');
        
        if (nodeStep === step) {
            node.classList.add('active');
        } else if (nodeStep < step) {
            node.classList.add('completed');
        }
    });

    // Update Progress Bar
    const progressPercent = ((step - 1) / (totalSteps - 1)) * 100;
    progressBar.style.width = `${progressPercent}%`;

    currentStep = step;

    // Atur visibilitas tombol navigasi
    btnPrev.style.display = currentStep === 1 ? 'none' : 'inline-flex';
    
    if (currentStep === totalSteps) {
        btnNext.style.display = 'none';
        btnSave.style.display = 'inline-flex';
    } else {
        btnNext.style.display = 'inline-flex';
        btnSave.style.display = 'none';
    }
}

function navigatePrev() {
    goToStep(currentStep - 1);
}

function navigateNext() {
    // Validasi input di setiap langkah sebelum melanjutkan
    if (currentStep === 1) {
        const kode = document.getElementById('kode_mk').value.trim();
        const nama = document.getElementById('nama_mk').value.trim();
        const prodi = document.getElementById('program_studi').value.trim();
        
        if (!kode || !nama || !prodi) {
            alert('Harap isi semua kolom informasi umum mata kuliah.');
            return;
        }

        // Simpan input ke state
        rpsState.kode_mk = kode;
        rpsState.nama_mk = nama;
        rpsState.sks = parseInt(document.getElementById('sks').value);
        rpsState.semester = parseInt(document.getElementById('semester').value);
        rpsState.program_studi = prodi;
        rpsState.deskripsi_mk = document.getElementById('deskripsi_mk').value.trim();
        rpsState.no_dokumen = document.getElementById('no_dokumen').value.trim();
        rpsState.revisi = document.getElementById('revisi').value.trim();
        rpsState.tanggal_penyusunan = document.getElementById('tanggal_penyusunan').value.trim();
        rpsState.prasyarat = document.getElementById('prasyarat').value.trim();
    } 
    else if (currentStep === 2) {
        const cpl = document.getElementById('cpl').value.trim();
        const cpmk = document.getElementById('cpmk').value.trim();
        const subCpmk = document.getElementById('sub_cpmk').value.trim();
        const bahan_kajian = document.getElementById('bahan_kajian').value.trim();
        const ref_utama = document.getElementById('referensi_utama').value.trim();
        const ref_pendukung = document.getElementById('referensi_pendukung').value.trim();
        const sarana_umum = document.getElementById('sarana_umum').value.trim();
        const sarana_khusus = document.getElementById('sarana_khusus').value.trim();

        if (!cpl || !cpmk || !subCpmk) {
            alert('Harap isi atau generate CPL, CPMK, dan Sub-CPMK terlebih dahulu.');
            return;
        }

        // Simpan ke state
        rpsState.cpl = cpl;
        rpsState.cpmk = cpmk;
        rpsState.sub_cpmk = subCpmk;
        rpsState.bahan_kajian = bahan_kajian;
        rpsState.referensi_utama = ref_utama;
        rpsState.referensi_pendukung = ref_pendukung;
        rpsState.sarana_umum = sarana_umum;
        rpsState.sarana_khusus = sarana_khusus;
    }
    else if (currentStep === 3) {
        if (rpsState.meetings.length !== 16) {
            alert('Silakan generate 16 rencana pertemuan terlebih dahulu.');
            return;
        }
    }

    goToStep(currentStep + 1);
}

// AI Call Langkah 2: CPL & CPMK
async function generateCplCpmkWithAI() {
    const namaMk = document.getElementById('nama_mk').value.trim();
    const deskripsi = document.getElementById('deskripsi_mk').value.trim();

    if (!namaMk) {
        alert('Harap isi Nama Mata Kuliah di Langkah 1 terlebih dahulu.');
        goToStep(1);
        return;
    }

    const containerInputs = document.getElementById('cpl-inputs-container');
    const loadingEl = document.getElementById('loading-cpl');
    const btnGen = document.getElementById('btn-generate-cpl');

    // Tampilkan Loading state
    containerInputs.style.display = 'none';
    loadingEl.style.display = 'flex';
    btnGen.disabled = true;
    btnNext.disabled = true;

    try {
        const response = await fetch('api_generate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate_cpl_cpmk',
                nama_mk: namaMk,
                deskripsi: deskripsi
            })
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            const data = result.data;
            
            // Masukkan data hasil generate ke textarea
            document.getElementById('cpl').value = Array.isArray(data.cpl) ? data.cpl.join('\n') : '';
            document.getElementById('cpmk').value = Array.isArray(data.cpmk) ? data.cpmk.join('\n') : '';
            document.getElementById('sub_cpmk').value = Array.isArray(data.sub_cpmk) ? data.sub_cpmk.join('\n') : '';
            document.getElementById('bahan_kajian').value = Array.isArray(data.bahan_kajian) ? data.bahan_kajian.join('\n') : (data.bahan_kajian || '');
            document.getElementById('referensi_utama').value = Array.isArray(data.referensi_utama) ? data.referensi_utama.join('\n') : (data.referensi_utama || '');
            document.getElementById('referensi_pendukung').value = Array.isArray(data.referensi_pendukung) ? data.referensi_pendukung.join('\n') : (data.referensi_pendukung || '');
            document.getElementById('sarana_umum').value = Array.isArray(data.sarana_umum) ? data.sarana_umum.join('\n') : (data.sarana_umum || '');
            document.getElementById('sarana_khusus').value = Array.isArray(data.sarana_khusus) ? data.sarana_khusus.join('\n') : (data.sarana_khusus || '');
            
            // Simpan ke state
            rpsState.cpl = document.getElementById('cpl').value;
            rpsState.cpmk = document.getElementById('cpmk').value;
            rpsState.sub_cpmk = document.getElementById('sub_cpmk').value;
            rpsState.bahan_kajian = document.getElementById('bahan_kajian').value;
            rpsState.referensi_utama = document.getElementById('referensi_utama').value;
            rpsState.referensi_pendukung = document.getElementById('referensi_pendukung').value;
            rpsState.sarana_umum = document.getElementById('sarana_umum').value;
            rpsState.sarana_khusus = document.getElementById('sarana_khusus').value;
        } else {
            alert('Gagal membuat CPL: ' + result.message);
        }
    } catch (err) {
        alert('Terjadi kesalahan koneksi API: ' + err.message);
    } finally {
        containerInputs.style.display = 'block';
        loadingEl.style.display = 'none';
        btnGen.disabled = false;
        btnNext.disabled = false;
    }
}

// AI Call Langkah 3: Generate 16 Pertemuan
async function generateMeetingsWithAI() {
    // Pastikan langkah 1 & 2 telah di-sinkronisasi ke state
    rpsState.kode_mk = document.getElementById('kode_mk').value.trim();
    rpsState.nama_mk = document.getElementById('nama_mk').value.trim();
    rpsState.sks = parseInt(document.getElementById('sks').value);
    rpsState.semester = parseInt(document.getElementById('semester').value);
    rpsState.program_studi = document.getElementById('program_studi').value.trim();
    rpsState.cpl = document.getElementById('cpl').value.trim();
    rpsState.cpmk = document.getElementById('cpmk').value.trim();
    rpsState.sub_cpmk = document.getElementById('sub_cpmk').value.trim();

    const triggerContainer = document.getElementById('generate-trigger-container');
    const loadingEl = document.getElementById('loading-meetings');

    triggerContainer.style.display = 'none';
    loadingEl.style.display = 'flex';
    btnPrev.style.display = 'none';

    const bobotUts = parseFloat(document.getElementById('opt_bobot_uts').value) || 25;
    const bobotUas = parseFloat(document.getElementById('opt_bobot_uas').value) || 25;
    const jumlahTugas = parseInt(document.getElementById('opt_jumlah_tugas').value) || 3;
    const jumlahKuis = parseInt(document.getElementById('opt_jumlah_kuis').value) || 2;
    const spesifikasi = document.getElementById('opt_spesifikasi').value.trim();

    if (bobotUts + bobotUas >= 100) {
        alert('Total bobot UTS dan UAS tidak boleh melebihi atau sama dengan 100% agar tersisa bobot untuk Tugas/Kuis.');
        triggerContainer.style.display = 'block';
        loadingEl.style.display = 'none';
        btnPrev.style.display = 'inline-flex';
        return;
    }

    // Pecah CPL, CPMK, Sub-CPMK dari text/textarea menjadi array baris
    const cplArray = rpsState.cpl.split('\n').filter(line => line.trim() !== '');
    const cpmkArray = rpsState.cpmk.split('\n').filter(line => line.trim() !== '');
    const subCpmkArray = rpsState.sub_cpmk.split('\n').filter(line => line.trim() !== '');

    try {
        const response = await fetch('api_generate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate_meetings',
                nama_mk: rpsState.nama_mk,
                sks: rpsState.sks,
                semester: rpsState.semester,
                cpl: cplArray,
                cpmk: cpmkArray,
                sub_cpmk: subCpmkArray,
                bobot_uts: bobotUts,
                bobot_uas: bobotUas,
                jumlah_tugas: jumlahTugas,
                jumlah_kuis: jumlahKuis,
                spesifikasi: spesifikasi
            })
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            rpsState.meetings = result.data.map(m => ({
                pertemuan_ke: parseInt(m.pertemuan_ke),
                sub_cpmk: m.sub_cpmk,
                bahan_kajian: m.bahan_kajian,
                metode_luring: m.metode_luring,
                metode_daring: m.metode_daring,
                estimasi_waktu: m.estimasi_waktu,
                indikator_penilaian: m.indikator_penilaian,
                bentuk_penilaian: m.bentuk_penilaian,
                bobot_penilaian: parseFloat(m.bobot_penilaian || 0)
            }));

            // Urutkan berdasarkan nomor pertemuan
            rpsState.meetings.sort((a, b) => a.pertemuan_ke - b.pertemuan_ke);

            renderMeetingsTable();
            goToStep(4);
        } else {
            alert('Gagal menyusun pertemuan: ' + result.message);
            triggerContainer.style.display = 'block';
            btnPrev.style.display = 'inline-flex';
        }
    } catch (err) {
        alert('Terjadi kesalahan koneksi API: ' + err.message);
        triggerContainer.style.display = 'block';
        btnPrev.style.display = 'inline-flex';
    } finally {
        loadingEl.style.display = 'none';
    }
}

// Render Tabel 16 Pertemuan di Langkah 4
function renderMeetingsTable() {
    const tableBody = document.getElementById('meetings-table-body');
    tableBody.innerHTML = '';

    let totalWeight = 0;

    rpsState.meetings.forEach((meeting, index) => {
        totalWeight += meeting.bobot_penilaian;

        const row = document.createElement('tr');
        
        // Tandai UTS (8) dan UAS (16) dengan styling berbeda
        if (meeting.pertemuan_ke === 8 || meeting.pertemuan_ke === 16) {
            row.style.backgroundColor = 'rgba(99, 102, 241, 0.05)';
            row.style.fontWeight = '600';
        }

        row.innerHTML = `
            <td style="text-align: center; font-weight: bold; color: var(--accent-primary);">${meeting.pertemuan_ke}</td>
            <td><div style="max-height: 80px; overflow-y: auto; font-size: 0.85rem;">${escapeHtml(meeting.sub_cpmk)}</div></td>
            <td><div style="max-height: 80px; overflow-y: auto; font-size: 0.85rem;">${escapeHtml(meeting.bahan_kajian)}</div></td>
            <td><div style="max-height: 80px; overflow-y: auto; font-size: 0.85rem;">${escapeHtml(meeting.metode_luring)}</div></td>
            <td><div style="max-height: 80px; overflow-y: auto; font-size: 0.85rem;">${escapeHtml(meeting.metode_daring)}</div></td>
            <td><span style="font-size: 0.85rem; white-space: nowrap;">${escapeHtml(meeting.estimasi_waktu)}</span></td>
            <td><div style="max-height: 80px; overflow-y: auto; font-size: 0.85rem;">${escapeHtml(meeting.indikator_penilaian)}</div></td>
            <td><div style="max-height: 80px; overflow-y: auto; font-size: 0.85rem;">${escapeHtml(meeting.bentuk_penilaian)}</div></td>
            <td style="text-align: center; font-weight: bold;">${meeting.bobot_penilaian}%</td>
            <td style="text-align: center;">
                <button class="btn btn-secondary btn-sm" onclick="openEditModal(${index})" style="padding: 0.3rem 0.6rem;">Edit</button>
            </td>
        `;
        tableBody.appendChild(row);
    });

    // Update total bobot badge dan validasi
    const totalBadge = document.getElementById('total-bobot-badge');
    const bobotWarning = document.getElementById('bobot-warning');
    const currentTotalWeightText = document.getElementById('current-total-weight');
    
    totalBadge.textContent = `Total Bobot: ${totalWeight.toFixed(1)}%`;
    currentTotalWeightText.textContent = `${totalWeight.toFixed(1)}%`;

    if (Math.abs(totalWeight - 100.0) > 0.1) {
        bobotWarning.style.display = 'flex';
        totalBadge.style.backgroundColor = 'rgba(239, 68, 68, 0.2)';
        totalBadge.style.color = 'var(--error)';
        btnSave.classList.add('btn-disabled');
    } else {
        bobotWarning.style.display = 'none';
        totalBadge.style.backgroundColor = 'rgba(16, 185, 129, 0.2)';
        totalBadge.style.color = 'var(--success)';
        btnSave.classList.remove('btn-disabled');
    }
}

// Modal Manager
const modal = document.getElementById('edit-meeting-modal');

function openEditModal(index) {
    const meeting = rpsState.meetings[index];
    
    document.getElementById('edit-meeting-index').value = index;
    document.getElementById('edit-modal-title').textContent = `Edit Detail Pertemuan Ke-${meeting.pertemuan_ke}`;
    document.getElementById('modal-sub-cpmk').value = meeting.sub_cpmk;
    document.getElementById('modal-bahan-kajian').value = meeting.bahan_kajian;
    document.getElementById('modal-metode-luring').value = meeting.metode_luring || '';
    document.getElementById('modal-metode-daring').value = meeting.metode_daring || '';
    document.getElementById('modal-waktu').value = meeting.estimasi_waktu;
    document.getElementById('modal-bobot').value = meeting.bobot_penilaian;
    document.getElementById('modal-indikator-penilaian').value = meeting.indikator_penilaian || '';
    document.getElementById('modal-bentuk-penilaian').value = meeting.bentuk_penilaian || '';

    modal.classList.add('active');
}

function closeModal() {
    modal.classList.remove('active');
}

function handleModalSubmit(e) {
    e.preventDefault();
    
    const index = parseInt(document.getElementById('edit-meeting-index').value);
    
    // Update data array state
    rpsState.meetings[index].sub_cpmk = document.getElementById('modal-sub-cpmk').value.trim();
    rpsState.meetings[index].bahan_kajian = document.getElementById('modal-bahan-kajian').value.trim();
    rpsState.meetings[index].metode_luring = document.getElementById('modal-metode-luring').value.trim();
    rpsState.meetings[index].metode_daring = document.getElementById('modal-metode-daring').value.trim();
    rpsState.meetings[index].estimasi_waktu = document.getElementById('modal-waktu').value.trim();
    rpsState.meetings[index].bobot_penilaian = parseFloat(document.getElementById('modal-bobot').value);
    rpsState.meetings[index].indikator_penilaian = document.getElementById('modal-indikator-penilaian').value.trim();
    rpsState.meetings[index].bentuk_penilaian = document.getElementById('modal-bentuk-penilaian').value.trim();

    closeModal();
    renderMeetingsTable();
}

function syncCurrentStepData() {
    // Langkah 1
    const kode = document.getElementById('kode_mk').value.trim();
    const nama = document.getElementById('nama_mk').value.trim();
    const prodi = document.getElementById('program_studi').value.trim();
    if (kode) rpsState.kode_mk = kode;
    if (nama) rpsState.nama_mk = nama;
    rpsState.sks = parseInt(document.getElementById('sks').value);
    rpsState.semester = parseInt(document.getElementById('semester').value);
    if (prodi) rpsState.program_studi = prodi;
    rpsState.deskripsi_mk = document.getElementById('deskripsi_mk').value.trim();
    rpsState.no_dokumen = document.getElementById('no_dokumen').value.trim();
    rpsState.revisi = document.getElementById('revisi').value.trim();
    rpsState.tanggal_penyusunan = document.getElementById('tanggal_penyusunan').value.trim();
    rpsState.prasyarat = document.getElementById('prasyarat').value.trim();

    // Langkah 2
    rpsState.cpl = document.getElementById('cpl').value.trim();
    rpsState.cpmk = document.getElementById('cpmk').value.trim();
    rpsState.sub_cpmk = document.getElementById('sub_cpmk').value.trim();
    rpsState.bahan_kajian = document.getElementById('bahan_kajian').value.trim();
    rpsState.referensi_utama = document.getElementById('referensi_utama').value.trim();
    rpsState.referensi_pendukung = document.getElementById('referensi_pendukung').value.trim();
    rpsState.sarana_umum = document.getElementById('sarana_umum').value.trim();
    rpsState.sarana_khusus = document.getElementById('sarana_khusus').value.trim();
}

function saveDraft() {
    syncCurrentStepData();
    saveRps(true);
}

// Simpan RPS ke Database
async function saveRps(isDraft = false) {
    rpsState.is_draft = isDraft;
    
    btnSave.disabled = true;
    if (btnDraft) btnDraft.disabled = true;
    
    const originalSaveText = btnSave.textContent;
    const originalDraftText = btnDraft ? btnDraft.textContent : 'Simpan Draft';
    
    if (isDraft) {
        if (btnDraft) btnDraft.textContent = 'Menyimpan Draft...';
    } else {
        btnSave.textContent = 'Menyimpan...';
    }

    try {
        const response = await fetch('save_rps.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(rpsState)
        });

        const result = await response.json();
        
        if (result.status === 'success') {
            alert(result.message);
            window.location.href = 'dashboard.php';
        } else {
            alert('Gagal menyimpan RPS: ' + result.message);
            btnSave.disabled = false;
            if (btnDraft) btnDraft.disabled = false;
            btnSave.textContent = originalSaveText;
            if (btnDraft) btnDraft.textContent = originalDraftText;
        }
    } catch (err) {
        alert('Terjadi kesalahan sistem saat menyimpan: ' + err.message);
        btnSave.disabled = false;
        if (btnDraft) btnDraft.disabled = false;
        btnSave.textContent = originalSaveText;
        if (btnDraft) btnDraft.textContent = originalDraftText;
    }
}

// Helpers
function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
