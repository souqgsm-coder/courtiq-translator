/**
 * COURTIQ TRANSLATION ENGINE — v34.1 "STABLE"
 * Compatible avec functions.php v34
 */
(function() {
    'use strict';

    const CFG = window.CTQ_CONFIG || {};
    const AJAX_URL = CFG.ajaxUrl || '/wp-admin/admin-ajax.php';
    const NONCE = CFG.nonce || '';
    const BATCH_SIZE = CFG.batchSize || 30;
    const PAGE_LIMIT = CFG.pageLimit || 120;
    const DICT = CFG.dict || { en: {}, ar: {} };
    const VERSION = CFG.version || '34.1';

    let currentNonce = NONCE;
    let isTranslating = false;
    let observer = null;
    let debounceTimer = null;

    function log(msg) {
        console.log('%c🌐 CTQ v' + VERSION + '%c ' + msg, 'color:#1e3d89;font-weight:700', 'color:inherit');
    }

    function ajaxPost(action, data) {
        const body = new URLSearchParams({ action: action, nonce: currentNonce });
        if (data) {
            Object.keys(data).forEach(k => body.append(k, data[k]));
        }
        return fetch(AJAX_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body,
            credentials: 'same-origin'
        }).then(r => r.json());
    }

    function refreshConfig() {
        return ajaxPost('ctq_refresh').then(res => {
            if (res.success && res.data) {
                if (res.data.nonce) currentNonce = res.data.nonce;
                if (res.data.dict) {
                    Object.assign(DICT.en, res.data.dict.en || {});
                    Object.assign(DICT.ar, res.data.dict.ar || {});
                }
                log('🔑 Nonce & dictionnaire rafraîchis.');
            }
            return res;
        }).catch(err => {
            log('⚠️ Échec refresh config: ' + err.message);
            throw err;
        });
    }

    function collectTexts(limit) {
        const texts = [];
        const seen = new Set();
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
        let node;

        while ((node = walker.nextNode()) && texts.length < limit) {
            const text = node.textContent.trim();
            if (text.length < 2 || text.length > 200) continue;
            if (/^\d+$/.test(text)) continue;
            if (/^\$?[\d,.]+$/.test(text)) continue;
            if (seen.has(text)) continue;

            const parent = node.parentElement;
            if (!parent) continue;
            if (parent.closest('script, style, noscript, iframe, [data-no-translate]')) continue;

            seen.add(text);
            texts.push(text);
        }
        return texts;
    }

    function translateBatch(strings, target) {
        const cache = DICT[target] || {};
        const missing = [];
        const results = {};

        strings.forEach(str => {
            if (cache[str]) results[str] = cache[str];
            else missing.push(str);
        });

        if (missing.length === 0) {
            return Promise.resolve({ translations: results, saved: 0, fallback: false });
        }

        return ajaxPost('ctq_translate', {
            target: target,
            strings: JSON.stringify(missing)
        }).then(res => {
            if (!res.success) {
                log('⚠️ Erreur serveur');
                return { translations: results, saved: 0, fallback: true };
            }

            const data = res.data;
            if (data.debug && data.debug.step === 'NONCE_FAILED') {
                log('⚠️ Nonce expiré, tentative de refresh...');
                return refreshConfig().then(() => {
                    return translateBatch(strings, target);
                });
            }

            if (data.translations) {
                Object.assign(results, data.translations);
                if (data.saved > 0) {
                    Object.assign(cache, data.translations);
                }
            }

            return { 
                translations: results, 
                saved: data.saved || 0, 
                fallback: data.fallback || false 
            };
        }).catch(err => {
            log('⚠️ Erreur réseau: ' + err.message);
            return { translations: results, saved: 0, fallback: true };
        });
    }

    function translatePage(target) {
        if (isTranslating) return Promise.resolve();
        isTranslating = true;

        document.dispatchEvent(new CustomEvent('ctq:translating', { detail: { lang: target } }));

        const texts = collectTexts(PAGE_LIMIT);
        log('⚡ ' + Object.keys(DICT[target] || {}).length + ' cache · 📊 ' + texts.length + ' collectés');

        const batches = [];
        for (let i = 0; i < texts.length; i += BATCH_SIZE) {
            batches.push(texts.slice(i, i + BATCH_SIZE));
        }

        let totalSaved = 0;

        function nextBatch(index) {
            if (index >= batches.length) {
                isTranslating = false;
                log('✅ Terminé · 💾 ' + totalSaved + ' nouveaux en cache.');
                document.dispatchEvent(new CustomEvent('ctq:translated', { detail: { lang: target, saved: totalSaved } }));
                return Promise.resolve();
            }

            log('📡 Lot ' + (index + 1) + '/' + batches.length + ' (' + batches[index].length + ' textes)');

            return translateBatch(batches[index], target).then(result => {
                if (result.saved > 0) totalSaved += result.saved;

                applyTranslations(result.translations, target);

                return new Promise(resolve => setTimeout(resolve, 300)).then(() => nextBatch(index + 1));
            });
        }

        return nextBatch(0);
    }

    function applyTranslations(translations, target) {
        const cache = DICT[target] || {};
        Object.assign(cache, translations);

        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null, false);
        let node;

        while ((node = walker.nextNode())) {
            const text = node.textContent.trim();
            if (translations[text]) {
                node.textContent = node.textContent.replace(text, translations[text]);
            }
        }

        document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach(el => {
            const ph = el.getAttribute('placeholder');
            if (ph && translations[ph]) {
                el.setAttribute('placeholder', translations[ph]);
            }
        });

        document.querySelectorAll('[title]').forEach(el => {
            const title = el.getAttribute('title');
            if (title && translations[title]) {
                el.setAttribute('title', translations[title]);
            }
        });
    }

    function initObserver(target) {
        if (observer) observer.disconnect();

        observer = new MutationObserver(mutations => {
            let hasNewText = false;
            mutations.forEach(m => {
                m.addedNodes.forEach(node => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        if (node.matches('script, style, noscript, iframe')) return;
                        hasNewText = true;
                    }
                });
            });

            if (hasNewText) {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    if (!isTranslating) {
                        const texts = collectTexts(50);
                        const missing = texts.filter(t => !(DICT[target] || {})[t]);
                        if (missing.length > 0) {
                            translateBatch(missing, target).then(r => {
                                if (r.translations) applyTranslations(r.translations, target);
                            });
                        }
                    }
                }, 1000);
            }
        });

        observer.observe(document.body, { childList: true, subtree: true });
    }

    function init() {
        const prefLang = localStorage.getItem('courtiq_pref_lang') || 'fr';

        log('Moteur chargé · Langue : ' + prefLang);

        if (prefLang !== 'fr') {
            refreshConfig().then(() => {
                return translatePage(prefLang);
            }).then(() => {
                initObserver(prefLang);
            }).catch(err => {
                log('❌ Erreur init: ' + err.message);
                isTranslating = false;
            });
        }
    }

    window.ctqEngine = {
        translate: function(target) {
            if (target === 'fr') {
                localStorage.setItem('courtiq_pref_lang', 'fr');
                window.location.reload();
                return;
            }
            localStorage.setItem('courtiq_pref_lang', target);
            refreshConfig().then(() => {
                return translatePage(target);
            }).then(() => {
                initObserver(target);
            }).catch(err => {
                log('❌ Erreur traduction: ' + err.message);
                isTranslating = false;
            });
        },
        refresh: refreshConfig,
        getDict: () => DICT,
        getVersion: () => VERSION
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
