<?php
ini_set('display_errors', 1); // デバッグ中はエラー表示を有効に
error_reporting(E_ALL);

// 言語の検出（URLパスで判定）
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isEnglish = (strpos($requestUri, '/en/') !== false);

// 言語ごとのメッセージ定義
$messages = [
    'ja' => [
        'page_title' => '源氏物語 全文テキスト検索',
        'search_results_title' => '「%s」の検索結果 - 源氏物語',
        'header_title' => '源氏物語 全文テキスト検索',
        'placeholder' => '検索語を入力',
        'search_button' => '検索',
        'no_results' => '「%s」の検索結果が見つかりませんでした。',
        'in_class' => 'テキスト: %s',
        'search_results_count' => '「%s」の検索結果: %d ファイル、合計 <span class="total-occurrences">%d</span> 箇所で一致',
        'occurrence_title' => '出現回数',
        'error_empty_search' => '検索語を入力してください。',
        'error_dir_access' => '検索ディレクトリにアクセスできません。',
        'error_search' => '検索中にエラーが発生しました: %s',
        'invalid_request' => '無効なリクエストです。'
    ],
    'en' => [
        'page_title' => 'The Tale of Genji - Full Text Search',
        'search_results_title' => 'Search results for "%s" - The Tale of Genji',
        'header_title' => 'The Tale of Genji - Full Text Search',
        'placeholder' => 'Enter search term',
        'search_button' => 'Search',
        'no_results' => 'No results found for "%s".',
        'in_class' => 'in %s',
        'search_results_count' => 'Found %d files with <span class="total-occurrences">%d</span> total matches for "%s"',
        'occurrence_title' => 'Occurrences',
        'error_empty_search' => 'Please enter a search term.',
        'error_dir_access' => 'Cannot access search directory.',
        'error_search' => 'An error occurred during search: %s',
        'invalid_request' => 'Invalid request.'
    ]
];

// 現在の言語コードと言語メッセージを設定
$langCode = $isEnglish ? 'en' : 'ja';
$lang = $messages[$langCode];

// 利用可能なクラスリスト
$availableClasses = ['all', 'original', 'romanized', 'yosano', 'shibuya', 'seiden', 'annotation'];

// クラス名の表示名
$classDisplayNames = [
    'ja' => [
        'all' => '全て',
        'original' => '原文',
        'romanized' => 'ローマ字',
        'yosano' => '与謝野訳',
        'shibuya' => '渋谷訳',
        'seiden' => 'サイデンステッカー訳',
        'annotation' => '注釈'
    ],
    'en' => [
        'all' => 'All',
        'original' => 'Original',
        'romanized' => 'Romanized',
        'yosano' => 'Yosano Translation',
        'shibuya' => 'Shibuya Translation',
        'seiden' => 'Seidensticker Translation',
        'annotation' => 'Annotations'
    ]
];

// 言語切り替えリンク生成
function getLanguageSwitchUrl($targetLang) {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    
    if ($targetLang === 'en') {
        // 日本語から英語へ
        if (strpos($requestUri, '/en/') === false) {
            // /en/ を追加
            return preg_replace('#^(/[^/]*)#', '/en$1', $requestUri);
        }
    } else {
        // 英語から日本語へ
        return str_replace('/en/', '/', $requestUri);
    }
    
    return $requestUri; // 変更なし
}

// CSRFトークンの生成と検証
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/**
 * テキスト内の検索語をハイライト表示する
 * @param string $text ハイライト対象のテキスト
 * @param string $searchTerm 検索語
 * @return string ハイライト適用済みのテキスト
 */
function highlightText($text, $searchTerm) {
    // 検索語と対象テキストをしっかりとエスケープ
    $escaped_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $escaped_search = preg_quote($searchTerm, '/');
    
    if (empty($searchTerm)) {
        return $escaped_text;
    }
    
    return preg_replace(
        '/(' . $escaped_search . ')/iu',
        '<mark>$1</mark>',
        $escaped_text
    );
}

/**
 * テキスト内の検索語の出現回数をカウントする
 * @param string $text 検索対象のテキスト
 * @param string $searchTerm 検索語
 * @return int 出現回数
 */
function countOccurrences($text, $searchTerm) {
    if (empty($searchTerm) || empty($text)) {
        return 0;
    }
    
    // 大文字小文字を区別せずにマッチング
    return preg_match_all('/' . preg_quote($searchTerm, '/') . '/iu', $text);
}

/**
 * HTMLからフィルタリングされたコンテンツを取得する
 * @param string $html HTMLコンテンツ
 * @param string $classFilter クラスフィルター
 * @return string フィルタリングされたテキスト
 */
function getFilteredContent($html, $classFilter = '') {
    // 除外する要素のパターン
    $excludePatterns = [
        '/<nav class="md-nav.*?<\/nav>/s',  // mdナビゲーション
        '/<span class="md-ellipsis".*?<\/span>/s',  // mdナビゲーション
        '/<li class="md-nav".*?<\/li>/s',  // mdナビゲーション
        '/<h1.*?<\/h1>/s',                   // h1タグ
        '/<h2.*?<\/h2>/s',                   // h2タグ
        '/<h3.*?<\/h3>/s',                   // h3タグ
        '/<header.*?<\/header>/s',           // ヘッダー
        '/<footer.*?<\/footer>/s',           // フッター
        '/class="md-header".*?<\/div>/s',    // mdヘッダー
        '/class="md-footer".*?<\/div>/s'     // mdフッター
    ];
    
    // 除外パターンを適用
    foreach ($excludePatterns as $pattern) {
        $html = preg_replace($pattern, '', $html);
    }
    
    // classフィルターが指定されている場合、そのクラスを持つ要素のみ抽出
    if (!empty($classFilter)) {
        $dom = new DOMDocument();
        // 文字コードエラーを抑制せず、libxml内部エラーを使用
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // 指定されたクラスを持つ要素を検索（XSS対策としてクラス名をエスケープ）
        $safeClassFilter = htmlspecialchars($classFilter, ENT_QUOTES, 'UTF-8');
        $elements = $xpath->query("//*[contains(@class, '$safeClassFilter')]");
        
        $filteredContent = '';
        foreach ($elements as $element) {
            $filteredContent .= $dom->saveHTML($element);
        }
        
        return strip_tags($filteredContent);
    }
    
    // フィルターなしの場合は全体を返す
    return strip_tags($html);
}

/**
 * ファイル内で検索語を検索する
 * @param string $searchTerm 検索語
 * @param string $classFilter クラスフィルター
 * @param array $messages 言語メッセージ
 * @return array|string 検索結果または エラーメッセージ
 */
function searchInFiles($searchTerm, $classFilter = '', $messages = []) {
    try {
        // 検索語のバリデーション
        if (empty($searchTerm)) {
            return $messages['error_empty_search'];
        }
        
        // メモリ制限を設定
        ini_set('memory_limit', '256M');
        
        $baseDirectory = './volumes/';
        $results = [];
        $fileCount = 0;
        $maxFiles = 1000; // 最大検索ファイル数
        $totalOccurrences = 0; // 総出現回数
        
        // ディレクトリが存在し、読み取り可能かチェック
        if (!is_dir($baseDirectory) || !is_readable($baseDirectory)) {
            return $messages['error_dir_access'];
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            // 最大ファイル数のチェック
            if ($fileCount >= $maxFiles) {
                break;
            }
            
            // ファイルのバリデーション
            if (!$file->isFile() || $file->getExtension() !== 'html' || !is_readable($file->getPathname())) {
                continue;
            }
            
            // ディレクトリトラバーサル対策
            $realpath = realpath($file->getPathname());
            $baseRealpath = realpath($baseDirectory);
            
            if ($baseRealpath === false || strpos($realpath, $baseRealpath) !== 0) {
                continue; // ベースディレクトリ外のファイルはスキップ
            }
            
            $fileCount++;
            
            // ファイルサイズのチェック
            if ($file->getSize() > 5 * 1024 * 1024) { // 5MB以上はスキップ
                continue;
            }
            
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue; // ファイル読み込みに失敗した場合はスキップ
            }
            
            $text = getFilteredContent($content, $classFilter);
            
            // 出現回数をカウント
            $occurrences = countOccurrences($text, $searchTerm);
            $totalOccurrences += $occurrences;
            
            if ($occurrences > 0) {
                // 検索語を含む部分の前後のテキストを抽出
                $position = mb_stripos($text, $searchTerm);
                $start = max(0, $position - 100);
                $length = mb_strlen($searchTerm) + 200;
                $excerpt = mb_substr($text, $start, $length);
                
                // 抜粋テキストをハイライト
                $highlightedExcerpt = highlightText($excerpt, $searchTerm);
                
                // 相対パスの生成
                if ($baseRealpath && $realpath) {
                    $relativePath = str_replace($baseRealpath, '', $realpath);
                    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
                    
                    // ファイル名をディレクトリパスから分離
                    $pathInfo = pathinfo($relativePath);
                    $directoryPath = $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] : '';
                    
                    $results[] = [
                        'file' => $relativePath,
                        'directory' => $directoryPath,
                        'excerpt' => $highlightedExcerpt,
                        'class' => $classFilter,
                        'occurrences' => $occurrences
                    ];
                    
                    // 結果の最大数を制限
                    if (count($results) >= 100) {
                        break;
                    }
                }
            }
        }
        
        // 出現回数でソート（降順）
        usort($results, function($a, $b) {
            return $b['occurrences'] - $a['occurrences'];
        });
        
        return [
            'results' => $results,
            'totalOccurrences' => $totalOccurrences
        ];
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        return sprintf($messages['error_search'], $e->getMessage());
    }
}

// 検索リクエストの処理
$searchTerm = '';
$selectedClass = 'all';
$showForm = true;
$errorMessage = '';

// HTMLタイトル用の変数
$pageTitle = $lang['page_title'];

// POSTリクエストの検証（CSRFトークン）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = $lang['invalid_request'];
    } else {
        $searchTerm = isset($_POST['search']) ? trim($_POST['search']) : '';
        $selectedClass = isset($_POST['class']) && in_array($_POST['class'], $availableClasses) ? $_POST['class'] : 'all';
        if (!empty($searchTerm)) {
            $pageTitle = sprintf($lang['search_results_title'], htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'));
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $selectedClass = isset($_GET['class']) && in_array($_GET['class'], $availableClasses) ? $_GET['class'] : 'all';
    if (!empty($searchTerm)) {
        $pageTitle = sprintf($lang['search_results_title'], htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'));
    }
}

// 言語切り替えリンク
$switchLangUrl = getLanguageSwitchUrl($isEnglish ? 'ja' : 'en');
$switchLangText = $isEnglish ? '日本語' : 'English';

// HTMLドキュメント開始とタイトル設定
echo '<!DOCTYPE html>
<html lang="' . $langCode . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $pageTitle . '</title>
    <style>
    body { 
        font-family: "Hiragino Sans", "Hiragino Kaku Gothic ProN", Meiryo, Arial, Helvetica, sans-serif; 
        line-height: 1.6; 
        max-width: 75rem; 
        margin: 0 auto; 
        padding: 1.25rem; 
    }
    .search-form { 
        margin: 1.25rem 0; 
        padding: 1rem; 
        background-color: #f8f9fa; 
        border-radius: 0.3125rem; 
    }
    .search-input { 
        padding: 0.5rem; 
        width: 18.75rem; 
        border: 0.0625rem solid #ced4da; 
        border-radius: 0.25rem; 
    }
    .search-button { 
        padding: 0.5rem 1rem; 
        background-color: #007bff; 
        color: white; 
        border: none; 
        border-radius: 0.25rem; 
        cursor: pointer; 
    }
    .search-button:hover { 
        background-color: #0069d9; 
    }
    .class-select { 
        padding: 0.5rem; 
        border: 0.0625rem solid #ced4da; 
        border-radius: 0.25rem; 
        margin: 0 0.625rem; 
    }
    .result-item { 
        margin: 1.25rem 0; 
        padding: 1rem; 
        border-bottom: 0.0625rem solid #eee; 
        background-color: #fff; 
        border-radius: 0.3125rem; 
        box-shadow: 0 0.0625rem 0.1875rem rgba(0,0,0,0.1); 
    }
    mark { 
        background-color: #ffeb3b; 
        padding: 0.125rem; 
    }
    .search-info { 
        background-color: #e9ecef; 
        padding: 0.625rem; 
        border-radius: 0.25rem; 
        margin-bottom: 0.625rem; 
    }
    .error-message { 
        color: #dc3545; 
        padding: 0.625rem; 
        background-color: #f8d7da; 
        border-radius: 0.25rem; 
    }
    h1 { 
        color: #333; 
        margin-top: 0; 
    }
    .header { 
        margin-bottom: 1.25rem; 
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .occurrence-count { 
        display: inline-block; 
        background-color: #007bff; 
        color: white; 
        border-radius: 50%; 
        width: 1.5rem; 
        height: 1.5rem; 
        text-align: center; 
        line-height: 1.5rem; 
        margin-left: 0.625rem;
        font-size: 0.75rem;
    }
    .total-occurrences {
        font-weight: bold;
        color: #007bff;
    }
    .lang-switch {
        font-size: 0.875rem;
        text-decoration: none;
        color: #007bff;
        padding: 0.375rem 0.75rem;
        border: 0.0625rem solid #007bff;
        border-radius: 0.25rem;
        transition: all 0.3s;
    }
    .lang-switch:hover {
        background-color: #007bff;
        color: white;
    }
    </style>
</head>
<body>
<a href="https://www.genji-monogatari.com">源氏物語の世界 令和再編集版 HOME - The Tale of Genji Reiwa Edition</a>
    <div class="header">
        <h1>' . $lang['header_title'] . '</h1>
        <a href="' . htmlspecialchars($switchLangUrl, ENT_QUOTES, 'UTF-8') . '" class="lang-switch">' . $switchLangText . '</a>
    </div>';

// エラーメッセージの表示
if (!empty($errorMessage)) {
    echo '<div class="error-message">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
}

// 検索フォームの表示
echo '<form method="POST" class="search-form">
    <input type="hidden" name="csrf_token" value="' . $csrf_token . '">
    <input type="text" name="search" value="' . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . '" class="search-input" placeholder="' . $lang['placeholder'] . '" required>
    <select name="class" class="class-select">';

foreach ($availableClasses as $class) {
    $selected = ($class === $selectedClass) ? 'selected' : '';
    $classDisplay = isset($classDisplayNames[$langCode][$class]) ? $classDisplayNames[$langCode][$class] : $class;
    echo "<option value=\"" . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($classDisplay, ENT_QUOTES, 'UTF-8') . "</option>";
}

echo '</select>
    <input type="submit" value="' . $lang['search_button'] . '" class="search-button">
</form>';

// 検索実行
if (!empty($searchTerm)) {
    $classFilter = ($selectedClass === 'all') ? '' : $selectedClass;
    $searchResult = searchInFiles($searchTerm, $classFilter, $lang);
    
    if (is_array($searchResult) && isset($searchResult['results'])) {
        $results = $searchResult['results'];
        $totalOccurrences = $searchResult['totalOccurrences'];
        
        if (empty($results)) {
            echo "<p class='search-info'>" . sprintf(
                $lang['no_results'], 
                htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8')
            );
            
            if ($selectedClass !== 'all') {
                echo " " . sprintf(
                    $lang['in_class'], 
                    htmlspecialchars($classDisplayNames[$langCode][$selectedClass], ENT_QUOTES, 'UTF-8')
                );
            }
            
            echo "</p>";
        } else {
            if ($langCode === 'ja') {
                // 日本語表示の場合
                echo "<p class='search-info'>" . sprintf(
                    $lang['search_results_count'], 
                    htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'),
                    count($results),
                    $totalOccurrences
                );
            } else {
                // 英語表示の場合
                echo "<p class='search-info'>" . sprintf(
                    $lang['search_results_count'], 
                    count($results),
                    $totalOccurrences,
                    htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8')
                );
            }
            
            if ($selectedClass !== 'all') {
                echo " " . sprintf(
                    $lang['in_class'], 
                    htmlspecialchars($classDisplayNames[$langCode][$selectedClass], ENT_QUOTES, 'UTF-8')
                );
            }
            
            echo "</p>";
            
            foreach ($results as $result) {
                echo '<div class="result-item">';
                echo "<h3><a href='/volumes/" . htmlspecialchars($result['file'], ENT_QUOTES, 'UTF-8') . "'>" . 
                     htmlspecialchars($result['directory'], ENT_QUOTES, 'UTF-8') . "</a>";
                
                // 出現回数を表示
                echo "<span class='occurrence-count' title='" . $lang['occurrence_title'] . "'>" . $result['occurrences'] . "</span>";
                
                echo "</h3>";
                
                echo "<p>" . $result['excerpt'] . "</p>";
                echo '</div>';
            }
        }
    } else {
        // エラーメッセージ
        echo "<p class='error-message'>" . htmlspecialchars($searchResult, ENT_QUOTES, 'UTF-8') . "</p>";
    }
}

// HTMLドキュメントを閉じる
echo '</body>
</html>';