/**
 * PineCast TV frontend runtime
 *  - Cookie consent + Google Consent Mode v2
 *  - First-party engagement tracking (heartbeat, scroll depth, video milestones, share, outbound)
 *  - GA4 event bridge (only when GA4 is enabled AND consent granted)
 *  - Search autocomplete, mobile nav, lazy video embeds
 *
 * Nothing here ever generates artificial page views, ad impressions or clicks.
 */
const cfg = window.VH || {};
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

/* ---------------- Consent ---------------- */
const Consent = {
    read() {
        const m = document.cookie.match(/(?:^|; )vh_consent=v1\.(\d)(\d)(\d)/);
        if (!m) return null;
        return { analytics: m[1] === '1', advertising: m[2] === '1', personalization: m[3] === '1' };
    },
    state() {
        const c = this.read();
        if (c) return c;
        // No choice yet: allowed only where consent is not required
        return cfg.consentRequired ? { analytics: false, advertising: false, personalization: false } : { analytics: true, advertising: true, personalization: true };
    },
    gtagUpdate(c) {
        if (typeof window.gtag !== 'function') return;
        window.gtag('consent', 'update', {
            analytics_storage: c.analytics ? 'granted' : 'denied',
            ad_storage: c.advertising ? 'granted' : 'denied',
            ad_user_data: c.advertising ? 'granted' : 'denied',
            ad_personalization: c.personalization ? 'granted' : 'denied',
        });
    },
    async save(c, method = 'banner') {
        try {
            const r = await fetch(cfg.routes.consent, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ analytics: c.analytics ? 1 : 0, advertising: c.advertising ? 1 : 0, personalization: c.personalization ? 1 : 0, method }),
            });
            if (!r.ok) throw new Error('consent save failed');
        } catch (e) { /* cookie fallback */
            document.cookie = `vh_consent=v1.${c.analytics ? 1 : 0}${c.advertising ? 1 : 0}${c.personalization ? 1 : 0}; path=/; max-age=${60 * 60 * 24 * 365}; SameSite=Lax`;
        }
        this.gtagUpdate(c);
        loadThirdParty(c);
        Tracker.enable(c.analytics);
        hideBanner();
    },
};

function hideBanner() {
    const b = $('#consent-banner');
    if (b) b.hidden = true;
    const p = $('#consent-prefs');
    if (p) p.hidden = true;
}

function initConsentUI() {
    const banner = $('#consent-banner');
    const prefs = $('#consent-prefs');
    if (!banner) return;
    const show = !Consent.read() && cfg.consentRequired && cfg.consentEnabled;
    if (show) banner.hidden = false;
    $$('[data-consent-accept]').forEach(b => b.addEventListener('click', () => Consent.save({ analytics: true, advertising: true, personalization: true })));
    $$('[data-consent-reject]').forEach(b => b.addEventListener('click', () => Consent.save({ analytics: false, advertising: false, personalization: false })));
    $$('[data-consent-open]').forEach(b => b.addEventListener('click', (e) => {
        e.preventDefault();
        if (!prefs) return;
        const c = Consent.read() || { analytics: false, advertising: false, personalization: false };
        $$('.toggle', prefs).forEach(t => t.setAttribute('aria-checked', String(!!c[t.dataset.key])));
        banner.hidden = true;
        prefs.hidden = false;
    }));
    if (prefs) {
        $$('.toggle', prefs).forEach(t => t.addEventListener('click', () => t.setAttribute('aria-checked', String(t.getAttribute('aria-checked') !== 'true'))));
        $('[data-consent-save]', prefs)?.addEventListener('click', () => {
            const get = k => $(`.toggle[data-key="${k}"]`, prefs)?.getAttribute('aria-checked') === 'true';
            Consent.save({ analytics: get('analytics'), advertising: get('advertising'), personalization: get('advertising') }, 'preferences');
        });
        $('[data-consent-close]', prefs)?.addEventListener('click', () => { prefs.hidden = true; if (!Consent.read() && cfg.consentRequired) banner.hidden = false; });
    }
}

/* ---------------- Third-party loaders (only after consent) ---------------- */
let adsLoaded = false, gaLoaded = false;
function loadThirdParty(c) {
    // AdSense: in required regions, only load when advertising consent given (Consent Mode also covers this,
    // but we avoid loading the script entirely without consent to be safe).
    if (cfg.adsense && cfg.adsense.client && !adsLoaded && (c.advertising || !cfg.consentRequired)) {
        adsLoaded = true;
        const s = document.createElement('script');
        s.async = true;
        s.crossOrigin = 'anonymous';
        s.src = `https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=${encodeURIComponent(cfg.adsense.client)}`;
        s.onload = () => { $$('ins.adsbygoogle:not([data-adsbygoogle-status])').forEach(() => { try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) {} }); };
        document.head.appendChild(s);
    }
    if (cfg.ga4 && !gaLoaded && c.analytics) {
        gaLoaded = true;
        const s = document.createElement('script');
        s.async = true;
        s.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(cfg.ga4)}`;
        document.head.appendChild(s);
        window.dataLayer = window.dataLayer || [];
        window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
        window.gtag('js', new Date());
        // send_page_view: true here is the single page_view; we never call page_view manually.
        window.gtag('config', cfg.ga4, { anonymize_ip: true, send_page_view: true });
    }
}
function ga(event, params = {}) {
    if (gaLoaded && typeof window.gtag === 'function') window.gtag('event', event, params);
}

/* ---------------- First-party tracker ---------------- */
const Tracker = {
    enabled: false,
    pv: null, sk: null,
    engaged: 0, lastTick: null, active: true, maxScroll: 0, lastSentScroll: -1,
    timer: null,
    enable(on) {
        this.enabled = !!on && !!cfg.track;
        if (this.enabled && !this.timer) this.start();
    },
    start() {
        this.pv = parseInt($('meta[name="vh-pv"]')?.content || '0', 10);
        this.sk = $('meta[name="vh-sk"]')?.content || '';
        if (!this.pv) return;
        this.lastTick = Date.now();
        const mark = () => { this.lastTick = Date.now(); this.active = true; };
        ['scroll', 'mousemove', 'keydown', 'touchstart', 'click'].forEach(e => window.addEventListener(e, mark, { passive: true }));
        document.addEventListener('visibilitychange', () => { this.active = !document.hidden; if (!document.hidden) this.lastTick = Date.now(); else this.flush(); });
        window.addEventListener('pagehide', () => this.flush(true));
        window.addEventListener('scroll', () => {
            const h = document.documentElement;
            const depth = Math.min(100, Math.round(((window.scrollY + window.innerHeight) / Math.max(1, h.scrollHeight)) * 100));
            if (depth > this.maxScroll) {
                const milestones = [25, 50, 75, 90].filter(m => this.maxScroll < m && depth >= m);
                this.maxScroll = depth;
                milestones.forEach(m => ga('scroll', { percent_scrolled: m }));
            }
        }, { passive: true });
        const interval = Math.max(5, cfg.heartbeat || 15) * 1000;
        this.timer = setInterval(() => this.tick(interval / 1000), interval);
    },
    tick(sec) {
        // count as engaged only if the tab is visible and the user interacted within the last 60s
        if (this.active && !document.hidden && Date.now() - this.lastTick < 60000) this.engaged += sec;
        this.flush();
    },
    post(url, data, beacon = false) {
        if (!this.enabled) return;
        data.sk = this.sk;
        const body = JSON.stringify(data);
        if (beacon && navigator.sendBeacon) {
            navigator.sendBeacon(url, new Blob([body], { type: 'application/json' }));
            return;
        }
        fetch(url, { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body }).catch(() => {});
    },
    flush(beacon = false) {
        if (!this.pv || (!this.engaged && this.maxScroll === this.lastSentScroll)) return;
        const engaged = Math.min(120, Math.round(this.engaged));
        this.engaged = 0;
        this.lastSentScroll = this.maxScroll;
        if (engaged > 0) ga('user_engagement', { engagement_time_msec: engaged * 1000 });
        this.post(cfg.routes.heartbeat, { pv: this.pv, engaged, scroll: this.maxScroll }, beacon);
    },
    event(type, value, extra = {}) {
        this.post(cfg.routes.event, { type, value, post_id: cfg.postId || null, path: location.pathname, data: extra });
    },
    video(payload) {
        this.post(cfg.routes.video, Object.assign({ post_id: cfg.postId }, payload));
    },
};

/* ---------------- Video tracking ---------------- */
function uid() { return Math.random().toString(36).slice(2) + Date.now().toString(36); }

class VideoSession {
    constructor(provider) {
        this.provider = provider; this.playId = uid(); this.started = false; this.milestones = new Set(); this.completed = false;
        this.lastPos = 0; this.acc = 0; this.duration = 0; this.beatTimer = null;
    }
    onPlay(pos, duration) {
        this.duration = duration || this.duration;
        if (!this.started) {
            this.started = true;
            Tracker.video({ event: 'start', provider: this.provider, position: Math.round(pos), duration: Math.round(this.duration), play_id: this.playId });
            ga('video_start', { video_provider: this.provider, video_title: document.title, video_duration: Math.round(this.duration) });
        }
        this.lastPos = pos;
        clearInterval(this.beatTimer);
        this.beatTimer = setInterval(() => this.beat(), 10000);
    }
    onPause() { clearInterval(this.beatTimer); this.beat(); }
    beat() {
        if (this.acc >= 1) {
            Tracker.video({ event: 'heartbeat', provider: this.provider, watch_seconds: Math.min(120, Math.round(this.acc)), position: Math.round(this.lastPos), duration: Math.round(this.duration), play_id: this.playId });
            this.acc = 0;
        }
    }
    onTime(pos, duration) {
        this.duration = duration || this.duration;
        const delta = pos - this.lastPos;
        if (delta > 0 && delta < 5) this.acc += delta; // ignore seeks
        this.lastPos = pos;
        if (!this.duration) return;
        const pct = (pos / this.duration) * 100;
        [25, 50, 75, 90].forEach(m => {
            if (pct >= m && !this.milestones.has(m)) {
                this.milestones.add(m);
                Tracker.video({ event: `p${m}`, provider: this.provider, position: Math.round(pos), duration: Math.round(this.duration), play_id: this.playId });
                ga('video_progress', { video_provider: this.provider, video_percent: m, video_title: document.title });
            }
        });
        if (pct >= 97 && !this.completed) this.onEnded();
    }
    onEnded() {
        if (this.completed) return;
        this.completed = true;
        this.beat();
        Tracker.video({ event: 'complete', provider: this.provider, position: Math.round(this.duration), duration: Math.round(this.duration), play_id: this.playId });
        ga('video_complete', { video_provider: this.provider, video_title: document.title });
    }
}

function initVideo() {
    const frame = $('[data-video]');
    if (!frame) return;
    const provider = frame.dataset.video;
    const poster = $('.video-poster', frame);
    const start = () => {
        if (poster) poster.remove();
        if (provider === 'youtube') loadYouTube(frame);
        else if (provider === 'vimeo') loadVimeo(frame);
        else if (provider === 'html5') loadHtml5(frame);
        else if (provider === 'external') loadExternal(frame);
    };
    if (poster) poster.addEventListener('click', start, { once: true });
    else start();
}

function loadYouTube(frame) {
    const id = frame.dataset.id;
    const holder = document.createElement('div');
    frame.appendChild(holder);
    const session = new VideoSession('youtube');
    const build = () => {
        let poll = null;
        const player = new YT.Player(holder, {
            videoId: id, playerVars: { autoplay: 1, rel: 0, modestbranding: 1, playsinline: 1, origin: location.origin },
            events: {
                onStateChange: (e) => {
                    const d = player.getDuration();
                    if (e.data === YT.PlayerState.PLAYING) {
                        session.onPlay(player.getCurrentTime(), d);
                        clearInterval(poll);
                        poll = setInterval(() => session.onTime(player.getCurrentTime(), player.getDuration()), 1000);
                    } else if (e.data === YT.PlayerState.PAUSED || e.data === YT.PlayerState.BUFFERING) {
                        clearInterval(poll); session.onPause();
                    } else if (e.data === YT.PlayerState.ENDED) {
                        clearInterval(poll); session.onEnded();
                    }
                },
            },
        });
    };
    if (window.YT && window.YT.Player) build();
    else {
        window.onYouTubeIframeAPIReady = build;
        const s = document.createElement('script');
        s.src = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(s);
    }
}

function loadVimeo(frame) {
    const id = frame.dataset.id;
    const iframe = document.createElement('iframe');
    iframe.src = `https://player.vimeo.com/video/${id}?autoplay=1&dnt=1`;
    iframe.allow = 'autoplay; fullscreen; picture-in-picture';
    iframe.allowFullscreen = true;
    iframe.title = document.title;
    frame.appendChild(iframe);
    const session = new VideoSession('vimeo');
    const s = document.createElement('script');
    s.src = 'https://player.vimeo.com/api/player.js';
    s.onload = () => {
        const p = new window.Vimeo.Player(iframe);
        let dur = 0;
        p.getDuration().then(d => dur = d);
        p.on('play', d => session.onPlay(d.seconds, d.duration || dur));
        p.on('pause', () => session.onPause());
        p.on('timeupdate', d => session.onTime(d.seconds, d.duration || dur));
        p.on('ended', () => session.onEnded());
    };
    document.head.appendChild(s);
}

function loadHtml5(frame) {
    const v = document.createElement('video');
    v.src = frame.dataset.src;
    v.controls = true; v.autoplay = true; v.playsInline = true; v.preload = 'metadata';
    if (frame.dataset.poster) v.poster = frame.dataset.poster;
    frame.appendChild(v);
    const session = new VideoSession('self_hosted');
    v.addEventListener('play', () => session.onPlay(v.currentTime, v.duration));
    v.addEventListener('pause', () => session.onPause());
    v.addEventListener('timeupdate', () => session.onTime(v.currentTime, v.duration));
    v.addEventListener('ended', () => session.onEnded());
}

function loadExternal(frame) {
    const tpl = $('template', frame);
    if (tpl) frame.appendChild(tpl.content.cloneNode(true));
    // External players expose no API; we record a play intent only.
    const session = new VideoSession('external');
    session.onPlay(0, 0);
}

/* ---------------- Share / outbound ---------------- */
function initShareAndOutbound() {
    $$('[data-share]').forEach(a => a.addEventListener('click', async (e) => {
        const net = a.dataset.share;
        if (net === 'native' && navigator.share) {
            e.preventDefault();
            try { await navigator.share({ title: document.title, url: location.href }); } catch (_) { return; }
        }
        if (net === 'copy') {
            e.preventDefault();
            try { await navigator.clipboard.writeText(location.href); a.textContent = 'Copied!'; } catch (_) {}
        }
        Tracker.event('share', net, { url: location.href });
        ga('share', { method: net, content_type: cfg.postType || 'page', item_id: String(cfg.postId || location.pathname) });
    }));
    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        if (!a) return;
        let url;
        try { url = new URL(a.href, location.href); } catch (_) { return; }
        if (url.host && url.host !== location.host && !a.closest('.ad-slot')) {
            Tracker.event('outbound_click', url.href.slice(0, 500));
            ga('outbound_click', { link_url: url.href, link_domain: url.host, outbound: true });
        }
    }, { passive: true });
}

/* ---------------- Search suggestions ---------------- */
function initSearch() {
    $$('[data-search]').forEach(form => {
        const input = $('input[name="q"]', form);
        const box = document.createElement('div');
        box.className = 'suggest';
        box.hidden = true;
        form.style.position = 'relative';
        form.appendChild(box);
        let t = null, ctrl = null;
        input.addEventListener('input', () => {
            clearTimeout(t);
            const q = input.value.trim();
            if (q.length < 2) { box.hidden = true; return; }
            t = setTimeout(async () => {
                ctrl?.abort();
                ctrl = new AbortController();
                try {
                    const r = await fetch(`${cfg.routes.suggest}?q=${encodeURIComponent(q)}`, { signal: ctrl.signal, headers: { Accept: 'application/json' } });
                    const { suggestions } = await r.json();
                    if (!suggestions.length) { box.hidden = true; return; }
                    box.innerHTML = suggestions.map(s => `<a href="${s.url}">${s.image ? `<img src="${s.image}" alt="" class="h-8 w-12 rounded object-cover" loading="lazy">` : ''}<span class="flex-1 truncate">${escapeHtml(s.title)}</span><span class="text-[10px] uppercase text-slate-400">${s.type}</span></a>`).join('');
                    box.hidden = false;
                } catch (_) {}
            }, 200);
        });
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && input.value.trim()) ga('search', { search_term: input.value.trim() });
        });
        document.addEventListener('click', (e) => { if (!form.contains(e.target)) box.hidden = true; });
    });
}
function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

/* ---------------- Misc UI ---------------- */
function initUI() {
    const btn = $('[data-nav-toggle]');
    const nav = $('[data-nav]');
    if (btn && nav) btn.addEventListener('click', () => { const open = nav.classList.toggle('hidden') === false; btn.setAttribute('aria-expanded', String(open)); });
    $$('[data-search-toggle]').forEach(b => b.addEventListener('click', () => { const s = $('[data-search-bar]'); if (s) { s.hidden = !s.hidden; if (!s.hidden) $('input', s)?.focus(); } }));
    $$('.animate-on-scroll').forEach(el => {
        const io = new IntersectionObserver((entries) => entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('animate-fade-up'); io.unobserve(en.target); } }), { threshold: 0.1 });
        io.observe(el);
    });
    // Lazy-init AdSense units once they are near the viewport (reduces CLS/TBT)
    if (cfg.adsense && cfg.adsense.lazy) {
        const units = $$('ins.adsbygoogle');
        if (units.length && 'IntersectionObserver' in window) {
            const io = new IntersectionObserver((entries) => entries.forEach(en => {
                if (en.isIntersecting && adsLoaded && !en.target.dataset.adsbygoogleStatus) {
                    try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (_) {}
                    io.unobserve(en.target);
                }
            }), { rootMargin: '300px' });
            units.forEach(u => io.observe(u));
        }
    }
}

/* ---------------- Boot ---------------- */
document.addEventListener('DOMContentLoaded', () => {
    const c = Consent.state();
    initConsentUI();
    loadThirdParty(c);
    Tracker.enable(c.analytics);
    initVideo();
    initShareAndOutbound();
    initSearch();
    initUI();
});
