document.addEventListener('DOMContentLoaded', function() {
    // 現在の言語を検出
    function detectLanguage() {
        // URLパスに '/en/' が含まれている場合は英語
        return window.location.pathname.includes('/en/') ? 'en' : 'ja';
    }
    
    const currentLanguage = detectLanguage();
    
    // index.mdページでは何もしない
    if (isIndexPage()) {
        console.log('Index page detected, toggle controls not loaded');
        return;
    }
    
    // index.mdかどうかを判定する関数
    function isIndexPage() {
        // URLパスで判定
        const path = window.location.pathname;
        if (path === '/' || path.endsWith('/index.html') || path.endsWith('/index') || path.endsWith('/index.en.md' ||
            path.endsWith('/en') )) {
            return true;
        }
        
        // ページタイトルで判定（よくある「ホーム」や「Home」などのタイトル）
        const title = document.title.toLowerCase();
        if (title === 'home' || title === 'ホーム' || title === 'トップページ' ||  title === 'TOP PAGE' || 
            title === 'index' || title === '索引') {
            return true;
        }
        
        return false;
    }
    
    // グローバル変数 - コントロールを追加したかどうかの状態を保持
    let controlsAdded = false;
    
    // まず最初に元のチェックボックスを非表示にする
    function hideOriginalControls() {
        const originalControls = document.querySelector('.checkboxes');
        if (originalControls) {
            originalControls.style.display = 'none';
        }
    }
    
    // コントロールの初期セットアップ
    function setupControls() {
        console.log('Setting up controls');
        hideOriginalControls();
        addToggleControlsToSidebar();
        setupMutationObserver();
        setupScrollListener();
        
        // ハッシュ変更リスナーを追加
        window.addEventListener('hashchange', function() {
            console.log('Hash changed, re-adding controls');
            setTimeout(function() {
                addToggleControlsToSidebar();
            }, 300);
        });
    }
    
    // スクロールイベントを監視
    function setupScrollListener() {
        window.addEventListener('scroll', function() {
            // スクロール中にコントロールが消えたかチェック
            const existingControls = document.querySelector('.fixed-checkboxes');
            if (!existingControls && controlsAdded) {
                console.log('Controls disappeared during scroll, re-adding');
                addToggleControlsToSidebar();
            }
        }, { passive: true });
    }
    
    // DOM変更を監視するMutationObserverをセットアップ
    function setupMutationObserver() {
        //監視対象はbody全体
        const targetNode = document.body;
        
        // オブザーバーのオプション
        const config = { childList: true, subtree: true };
        
        // DOM変更時の処理
        const callback = function(mutationsList, observer) {
            const existingControls = document.querySelector('.fixed-checkboxes');
            const sidebar = document.querySelector('.md-sidebar--secondary') || 
                           document.querySelector('.toc-wrapper') ||
                           document.querySelector('.table-of-contents');
                           
            // サイドバーが存在し、コントロールが消えてしまった場合は再追加
            if (sidebar && !existingControls && controlsAdded) {
                console.log('Controls removed from DOM, re-adding');
                setTimeout(function() {
                    addToggleControlsToSidebar();
                }, 100);
            }
        };
        
        // オブザーバーを作成して監視開始
        const observer = new MutationObserver(callback);
        observer.observe(targetNode, config);
    }
    
    // 右サイドバーにトグルボタンを追加する
    function addToggleControlsToSidebar() {
        // まず既存のコントロールをチェック
        if (document.querySelector('.fixed-checkboxes')) {
            return; // 既に存在する場合は何もしない
        }
        
        hideOriginalControls(); // 元のコントロールを確実に非表示
        
        // まず右サイドバーを探す - Material for MkDocsでは通常これらのセレクタを使用
        const rightSidebar = document.querySelector('.md-sidebar--secondary .md-sidebar__scrollwrap');
        
        // もし右サイドバーが見つからない場合、他の一般的なセレクタを試す
        if (!rightSidebar) {
            console.log('Could not find the right sidebar. Trying alternative selectors...');
            const alternatives = [
                '.md-sidebar.md-sidebar--secondary .md-sidebar__scrollwrap',
                '[data-md-component="toc"]',
                '.toc-wrapper',
                '.table-of-contents',
                '.md-sidebar--secondary',
                '.md-sidebar--secondary .md-nav',
                '.md-sidebar--secondary nav'
            ];
            
            for (const selector of alternatives) {
                const element = document.querySelector(selector);
                if (element) {
                    console.log(`Found alternative sidebar with selector: ${selector}`);
                    addToggleControls(element, true);
                    controlsAdded = true;
                    return;
                }
            }
            
            // 最終手段として、ページの最上部に追加
            console.log('Could not find any sidebar. Adding controls to the top of the page.');
            const content = document.querySelector('.md-content') || 
                           document.querySelector('main') ||
                           document.body;
            addToggleControls(content, false);
            controlsAdded = true;
            return;
        }
        
        // 右サイドバーが見つかった場合
        addToggleControls(rightSidebar, true);
        controlsAdded = true;
    }
    
    // 既存のトグルコントローラを削除する関数
    function removeExistingToggleControls() {
        const existingControls = document.querySelectorAll('.fixed-checkboxes');
        existingControls.forEach(control => {
            control.remove();
        });
    }
    
    // 言語に応じたHTMLを返す関数
    function getLocalizedHTML() {
        if (currentLanguage === 'en') {
            return `
                <div class="checkbox-title">Display Options</div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showOriginal" checked>
                    <label for="showOriginal">Original Text</label>
                </div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showRomanized" checked>
                    <label for="showRomanized">Romanized Text</label>
                </div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showShibuya" checked>
                    <label for="showShibuya">Shibuya Translation</label>
                </div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showYosano" checked>
                    <label for="showYosano">Yosano Translation</label>
                </div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showSeiden" checked>
                    <label for="showSeiden">Seidensticker Translation</label>
                </div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showAnnotations" checked>
                    <label for="showAnnotations">Annotations</label>
                </div>
            `;
        } else {
            return `
                <div class="checkbox-title">表示切替</div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showOriginal" checked>
                    <label for="showOriginal">原文</label>
                </div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showRomanized" checked>
                    <label for="showRomanized">ローマ字表記</label>
                </div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showShibuya" checked>
                    <label for="showShibuya">渋谷栄一訳</label>
                </div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showYosano" checked>
                    <label for="showYosano">与謝野晶子訳</label>
                </div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showSeiden" checked>
                    <label for="showSeiden">サイデンステッカー訳</label>
                </div>
                <div class="checkbox-container">
                    <input type="checkbox" id="showAnnotations" checked>
                    <label for="showAnnotations">注釈文</label>
                </div>
            `;
        }
    }
    
    // トグルコントロールを追加する関数
    function addToggleControls(container, prepend = false) {
        // 既存のコントロールを削除して重複を防ぐ
        removeExistingToggleControls();
        
        // チェックボックスコンテナを作成
        const checkboxesDiv = document.createElement('div');
        checkboxesDiv.className = 'fixed-checkboxes';
        checkboxesDiv.setAttribute('data-testid', 'toggle-controls'); // テスト用のIDを追加
        
        // 言語に応じたHTMLを設定
        checkboxesDiv.innerHTML = getLocalizedHTML();
        
        // サイドバーの最初に追加するか最後に追加するか
        if (prepend && container.firstChild) {
            container.insertBefore(checkboxesDiv, container.firstChild);
        } else {
            container.appendChild(checkboxesDiv);
        }
        
        // イベントリスナーを追加
        addCheckboxEventListeners();
        
        // 初期表示状態を設定
        loadPreferences();
        updateVisibility();
    }
    
    // チェックボックスにイベントリスナーを追加
    function addCheckboxEventListeners() {
        const originalCheckbox = document.getElementById('showOriginal');
        const romanizedCheckbox = document.getElementById('showRomanized');
        const shibuyaCheckbox = document.getElementById('showShibuya');
        const yosanoCheckbox = document.getElementById('showYosano');
        const seidenCheckbox = document.getElementById('showSeiden');
        const annotationsCheckbox = document.getElementById('showAnnotations');
        
        // Set up event listeners for checkboxes
        if (originalCheckbox) {
            originalCheckbox.addEventListener('change', updateVisibility);
        }
        
        if (romanizedCheckbox) {
            romanizedCheckbox.addEventListener('change', updateVisibility);
        }
        
        if (shibuyaCheckbox) {
            shibuyaCheckbox.addEventListener('change', updateVisibility);
        }
        
        if (yosanoCheckbox) {
            yosanoCheckbox.addEventListener('change', updateVisibility);
        }
        
        if (seidenCheckbox) {
            seidenCheckbox.addEventListener('change', updateVisibility);
        }
        
        if (annotationsCheckbox) {
            annotationsCheckbox.addEventListener('change', updateVisibility);
        }
    }
    
    // テキスト表示状態を更新
    function updateVisibility() {
        const originalCheckbox = document.getElementById('showOriginal');
        const romanizedCheckbox = document.getElementById('showRomanized');
        const shibuyaCheckbox = document.getElementById('showShibuya');
        const yosanoCheckbox = document.getElementById('showYosano');
        const seidenCheckbox = document.getElementById('showSeiden');
        const annotationsCheckbox = document.getElementById('showAnnotations');
        
        if (!originalCheckbox) return; // チェックボックスが見つからない場合は何もしない
        
        // Toggle original text
        const originalTexts = document.querySelectorAll('.original');
        originalTexts.forEach(function(text) {
            text.style.display = originalCheckbox.checked ? 'block' : 'none';
        });
        
        // Toggle romanized text
        const romanizedTexts = document.querySelectorAll('.romanized');
        romanizedTexts.forEach(function(text) {
            text.style.display = romanizedCheckbox.checked ? 'block' : 'none';
        });
        
        // Toggle Shibuya translation
        const shibuyaTexts = document.querySelectorAll('.shibuya');
        shibuyaTexts.forEach(function(text) {
            text.style.display = shibuyaCheckbox.checked ? 'block' : 'none';
        });
        
        // Toggle Yosano translation
        const yosanoTexts = document.querySelectorAll('.yosano');
        yosanoTexts.forEach(function(text) {
            text.style.display = yosanoCheckbox.checked ? 'block' : 'none';
        });
        
        // Toggle Seidensticker translation
        const seidenTexts = document.querySelectorAll('.seiden');
        seidenTexts.forEach(function(text) {
            text.style.display = seidenCheckbox.checked ? 'block' : 'none';
        });        
        
        // Toggle annotations if the checkbox exists
        if (annotationsCheckbox) {
            const annotationDivs = document.querySelectorAll('.annotations');
            annotationDivs.forEach(function(div) {
                div.style.display = annotationsCheckbox.checked ? 'block' : 'none';
            });
        }
        
        // Save preferences
        savePreferences();
    }
    
    // Save preferences to localStorage
    function savePreferences() {
        const originalCheckbox = document.getElementById('showOriginal');
        const romanizedCheckbox = document.getElementById('showRomanized');
        const shibuyaCheckbox = document.getElementById('showShibuya');
        const yosanoCheckbox = document.getElementById('showYosano');
        const seidenCheckbox = document.getElementById('showSeiden');
        const annotationsCheckbox = document.getElementById('showAnnotations');
        
        if (originalCheckbox) {
            localStorage.setItem('showOriginal', originalCheckbox.checked);
        }
        if (romanizedCheckbox) {
            localStorage.setItem('showRomanized', romanizedCheckbox.checked);
        }
        if (shibuyaCheckbox) {
            localStorage.setItem('showShibuya', shibuyaCheckbox.checked);
        }
        if (yosanoCheckbox) {
            localStorage.setItem('showYosano', yosanoCheckbox.checked);
        }
        if (seidenCheckbox) {
            localStorage.setItem('showSeiden', seidenCheckbox.checked);
        }
        if (annotationsCheckbox) {
            localStorage.setItem('showAnnotations', annotationsCheckbox.checked);
        }
    }
    
    // Load preferences from localStorage
    function loadPreferences() {
        const originalCheckbox = document.getElementById('showOriginal');
        const romanizedCheckbox = document.getElementById('showRomanized');
        const shibuyaCheckbox = document.getElementById('showShibuya');
        const yosanoCheckbox = document.getElementById('showYosano');
        const seidenCheckbox = document.getElementById('showSeiden');
        const annotationsCheckbox = document.getElementById('showAnnotations');
        
        if (originalCheckbox && localStorage.getItem('showOriginal') !== null) {
            originalCheckbox.checked = localStorage.getItem('showOriginal') === 'true';
        }
        if (romanizedCheckbox && localStorage.getItem('showRomanized') !== null) {
            romanizedCheckbox.checked = localStorage.getItem('showRomanized') === 'true';
        }
        if (shibuyaCheckbox && localStorage.getItem('showShibuya') !== null) {
            shibuyaCheckbox.checked = localStorage.getItem('showShibuya') === 'true';
        }
        if (yosanoCheckbox && localStorage.getItem('showYosano') !== null) {
            yosanoCheckbox.checked = localStorage.getItem('showYosano') === 'true';
        }
        if (seidenCheckbox && localStorage.getItem('showSeiden') !== null) {
            seidenCheckbox.checked = localStorage.getItem('showSeiden') === 'true';
        }
        if (annotationsCheckbox && localStorage.getItem('showAnnotations') !== null) {
            annotationsCheckbox.checked = localStorage.getItem('showAnnotations') === 'true';
        }
    }
    
    // メイン処理を実行
    setupControls();
    
    // ページの完全読み込み後に再度確認
    window.addEventListener('load', function() {
        setTimeout(function() {
            if (!document.querySelector('.fixed-checkboxes')) {
                console.log('Controls not found after page load, re-adding');
                addToggleControlsToSidebar();
            }
        }, 500);
    });
});