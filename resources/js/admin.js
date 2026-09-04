import Alpine from 'alpinejs';
import { Chart, LineController, BarController, DoughnutController, LineElement, BarElement, PointElement, ArcElement, CategoryScale, LinearScale, Tooltip, Legend, Filler } from 'chart.js';
import Quill from 'quill';

Chart.register(LineController, BarController, DoughnutController, LineElement, BarElement, PointElement, ArcElement, CategoryScale, LinearScale, Tooltip, Legend, Filler);
Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
Chart.defaults.color = '#64748b';
Chart.defaults.plugins.legend.labels.boxWidth = 10;

const PALETTE = ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6'];
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

window.Chart = Chart;
window.Alpine = Alpine;

/* ------------ Charts from data attributes ------------ */
function buildChart(el) {
    const data = JSON.parse(el.dataset.chart || '{}');
    const type = el.dataset.type || 'line';
    const labels = data.labels || [];
    const datasets = (data.datasets || []).map((d, i) => {
        const color = d.color || PALETTE[i % PALETTE.length];
        return Object.assign({
            label: d.label, data: d.data, borderColor: color, backgroundColor: type === 'line' ? color + '22' : (Array.isArray(d.data) && type === 'doughnut' ? PALETTE : color),
            fill: type === 'line', tension: 0.35, borderWidth: 2, pointRadius: labels.length > 60 ? 0 : 2, pointHoverRadius: 4, borderDash: d.dashed ? [4, 4] : [],
            yAxisID: d.axis || 'y',
        }, d.options || {});
    });
    const opts = {
        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: datasets.length > 1 || type === 'doughnut', position: type === 'doughnut' ? 'right' : 'top' } },
        scales: type === 'doughnut' ? {} : {
            x: { grid: { display: false }, ticks: { maxTicksLimit: 12, maxRotation: 0 } },
            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { precision: 0 } },
        },
    };
    if (datasets.some(d => d.yAxisID === 'y1')) opts.scales.y1 = { position: 'right', beginAtZero: true, grid: { display: false } };
    if (data.money) opts.scales.y.ticks = { callback: v => Number(v).toFixed(2) };
    new Chart(el, { type, data: { labels, datasets }, options: opts });
}
document.querySelectorAll('canvas[data-chart]').forEach(buildChart);

/* ------------ Rich text editor ------------ */
document.querySelectorAll('[data-editor]').forEach(el => {
    const input = document.querySelector(el.dataset.editor);
    const quill = new Quill(el, {
        theme: 'snow',
        modules: {
            toolbar: {
                container: [
                    [{ header: [2, 3, 4, false] }], ['bold', 'italic', 'underline', 'strike'], ['blockquote', 'code-block'],
                    [{ list: 'ordered' }, { list: 'bullet' }], [{ align: [] }], ['link', 'image', 'video'], ['clean'],
                ],
                handlers: {
                    image() { openMediaPicker({ type: 'image' }, (m) => { const r = quill.getSelection(true); quill.insertEmbed(r.index, 'image', m.url, 'user'); }); },
                },
            },
        },
    });
    if (input.value) quill.root.innerHTML = input.value;
    const sync = () => { input.value = quill.root.innerHTML === '<p><br></p>' ? '' : quill.root.innerHTML; input.dispatchEvent(new Event('input')); };
    quill.on('text-change', sync);
    el.closest('form')?.addEventListener('submit', sync);
});

/* ------------ Media picker (modal) ------------ */
let pickerCb = null;
function openMediaPicker(opts, cb) {
    pickerCb = cb;
    window.dispatchEvent(new CustomEvent('open-media-picker', { detail: opts }));
}
window.openMediaPicker = openMediaPicker;

Alpine.data('mediaPicker', () => ({
    open: false, items: [], page: 1, last: 1, type: 'image', q: '', loading: false, uploading: false, selected: null, error: '',
    init() { window.addEventListener('open-media-picker', (e) => { this.type = e.detail?.type || ''; this.open = true; this.page = 1; this.load(); }); },
    async load() {
        this.loading = true;
        const r = await fetch(`${window.VHA.mediaUrl}?page=${this.page}&type=${this.type}&s=${encodeURIComponent(this.q)}`, { headers: { Accept: 'application/json' } });
        const j = await r.json();
        this.items = j.data; this.last = j.last_page; this.loading = false;
    },
    async upload(files) {
        this.error = '';
        for (const f of files) {
            this.uploading = true;
            const fd = new FormData(); fd.append('file', f);
            const r = await fetch(window.VHA.mediaUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
            const j = await r.json();
            if (!r.ok) { this.error = j.message || 'Upload failed'; }
            this.uploading = false;
        }
        this.page = 1; this.load();
    },
    choose(m) { this.open = false; if (pickerCb) pickerCb(m); pickerCb = null; },
    async remove(m) {
        if (!confirm('Delete this file permanently?')) return;
        await fetch(`${window.VHA.mediaUrl}/${m.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
        this.load();
    },
}));

/* Field helper: <div x-data="mediaField('input-id')"> */
Alpine.data('mediaField', (inputId, type = 'image') => ({
    value: '', url: '',
    init() { const i = document.getElementById(inputId); this.value = i.value; this.url = i.dataset.url || (this.value ? window.VHA.storageUrl + '/' + this.value : ''); },
    pick() { openMediaPicker({ type }, (m) => { this.value = m.path; this.url = m.url; const i = document.getElementById(inputId); i.value = m.path; i.dispatchEvent(new Event('input')); if (this.$refs.mediaId) this.$refs.mediaId.value = m.id; }); },
    clear() { this.value = ''; this.url = ''; const i = document.getElementById(inputId); i.value = ''; i.dispatchEvent(new Event('input')); },
}));

/* ------------ Slug helper ------------ */
Alpine.data('slugger', (initial = '', locked = false) => ({
    slug: initial, locked: locked || !!initial,
    from(title) { if (!this.locked) this.slug = title.toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 180); },
}));

/* ------------ Sortable lists (HTML5 DnD) ------------ */
Alpine.data('sortable', (onChange) => ({
    dragging: null,
    start(e, i) { this.dragging = i; e.dataTransfer.effectAllowed = 'move'; },
    over(e) { e.preventDefault(); },
    drop(e, i) {
        e.preventDefault();
        const list = this.$refs.list;
        const items = Array.from(list.children);
        const from = items[this.dragging];
        const to = items[i];
        if (from && to && from !== to) { if (this.dragging < i) to.after(from); else to.before(from); }
        this.dragging = null;
        if (onChange) onChange(Array.from(list.children).map(el => el.dataset.id));
    },
}));

/* ------------ Realtime widget ------------ */
Alpine.data('realtime', (url, initial) => ({
    data: initial, timer: null,
    init() { this.timer = setInterval(() => this.refresh(), 10000); },
    async refresh() { try { const r = await fetch(url, { headers: { Accept: 'application/json' } }); this.data = await r.json(); } catch (_) {} },
}));

/* ------------ Notifications bell ------------ */
Alpine.data('notifications', (url, unread) => ({
    open: false, unread, items: [],
    async toggle() { this.open = !this.open; if (this.open) { const r = await fetch(url, { headers: { Accept: 'application/json' } }); const j = await r.json(); this.items = j.items; this.unread = j.unread; } },
}));

/* ------------ Confirm forms ------------ */
document.addEventListener('submit', (e) => {
    const f = e.target;
    if (f.matches('[data-confirm]') && !confirm(f.dataset.confirm)) e.preventDefault();
});

/* ------------ Dropzone ------------ */
Alpine.data('dropzone', () => ({
    over: false, uploading: false, done: 0, total: 0, errors: [],
    async files(list) {
        this.errors = []; this.total = list.length; this.done = 0; this.uploading = true;
        for (const f of list) {
            const fd = new FormData(); fd.append('file', f);
            const r = await fetch(window.VHA.mediaUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
            if (!r.ok) { const j = await r.json().catch(() => ({})); this.errors.push(`${f.name}: ${j.message || 'failed'}`); }
            this.done++;
        }
        this.uploading = false;
        if (!this.errors.length) location.reload();
    },
}));

Alpine.start();
