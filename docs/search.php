<?php
ini_set('display_errors', 0); // 本番環境ではエラーを表示しない
error_reporting(E_ALL);

// 実行時間制限を設定
set_time_limit(30); // 30秒

/**
 * テキスト内の検索語をハイライト表示する
 * @param string $text ハイライト対象のテキスト
 * @param string $searchTerm 検索語
 * @return string ハイライト適用済みのテキスト
 */
function highlightText($text, $searchTerm) {
    // 検索語と対象テキストをエスケープ
    $escaped_text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $escaped_search = htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8');
    
    if (empty($escaped_search)) {
        return $escaped_text;
    }
    
    return preg_replace(
        '/(' . preg_quote($escaped_search, '/') . ')/iu',
        '<mark>$1</mark>',
        $escaped_text
    );
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
 * @return array|string 検索結果または エラーメッセージ
 */
function searchInFiles($searchTerm, $classFilter = '') {
    try {
        // 検索語のバリデーション
        if (empty($searchTerm) || mb_strlen($searchTerm) < 1) {
            return "検索語は1文字以上入力してください。";
        }
        
        // メモリ制限を設定
        ini_set('memory_limit', '256M');
        
        $baseDirectory = './volumes/';
        $results = [];
        $fileCount = 0;
        $maxFiles = 1000; // 最大検索ファイル数
        
        // ディレクトリが存在し、読み取り可能かチェック
        if (!is_dir($baseDirectory) || !is_readable($baseDirectory)) {
            return "検索ディレクトリにアクセスできません。";
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
            
            if (strpos($realpath, $baseRealpath) !== 0) {
                continue; // ベースディレクトリ外のファイルはスキップ
            }
            
            $fileCount++;
            
            // ファイルサイズのチェック
            if ($file->getSize() > 5 * 1024 * 1024) { // 5MB以上はスキップ
                continue;
            }
            
            $content = file_get_contents($file->getPathname());
            $text = getFilteredContent($content, $classFilter);
            
            if (mb_stripos($text, $searchTerm) !== false) {
                // 検索語を含む部分の前後のテキストを抽出
                $position = mb_stripos($text, $searchTerm);
                $start = max(0, $position - 100);
                $length = mb_strlen($searchTerm) + 200;
                $excerpt = mb_substr($text, $start, $length);
                
                // 抜粋テキストをハイライト
                $highlightedExcerpt = highlightText($excerpt, $searchTerm);
                
                $relativePath = str_replace($baseRealpath, '', $realpath);
                $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
                
                $results[] = [
                    'file' => $relativePath,
                    'excerpt' => $highlightedExcerpt,
                    'class' => $classFilter
                ];
                
                // 結果の最大数を制限
                if (count($results) >= 100) {
                    break;
                }
            }
        }
        
        return $results;
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        return "検索中にエラーが発生しました。";
    }
}

// CSRFトークンの生成と検証
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// 利用可能なクラスリスト
$availableClasses = ['all', 'original', 'romanized', 'yosano', 'shibuya', 'seiden', 'annotation'];

// スタイルの追加
echo '<style>
    .search-form { margin: 20px 0; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
    .search-input { padding: 8px; width: 300px; border: 1px solid #ced4da; border-radius: 4px; }
    .search-button { padding: 8px 15px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
    .search-button:hover { background-color: #0069d9; }
    .class-select { padding: 8px; border: 1px solid #ced4da; border-radius: 4px; margin: 0 10px; }
    .result-item { margin: 20px 0; padding: 15px; border-bottom: 1px solid #eee; background-color: #fff; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    mark { background-color: #ffeb3b; padding: 2px; }
    .search-info { background-color: #e9ecef; padding: 10px; border-radius: 4px; margin-bottom: 10px; }
    .error-message { color: #dc3545; padding: 10px; background-color: #f8d7da; border-radius: 4px; }
</style>';

// 検索リクエストの処理
$searchTerm = '';
$selectedClass = 'all';
$showForm = true;
$errorMessage = '';

// POSTリクエストの検証（CSRFトークン）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "無効なリクエストです。";
    } else {
        $searchTerm = isset($_POST['search']) ? trim($_POST['search']) : '';
        $selectedClass = isset($_POST['class']) && in_array($_POST['class'], $availableClasses) ? $_POST['class'] : 'all';
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $selectedClass = isset($_GET['class']) && in_array($_GET['class'], $availableClasses) ? $_GET['class'] : 'all';
}

// エラーメッセージの表示
if (!empty($errorMessage)) {
    echo '<div class="error-message">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
}

// 検索フォームの表示
echo '<form method="POST" class="search-form">
    <input type="hidden" name="csrf_token" value="' . $csrf_token . '">
    <input type="text" name="search" value="' . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . '" class="search-input" placeholder="検索語を入力（2文字以上）" required minlength="2">
    <select name="class" class="class-select">';

foreach ($availableClasses as $class) {
    $selected = ($class === $selectedClass) ? 'selected' : '';
    $classDisplay = ($class === 'all') ? 'All' : $class;
    echo "<option value=\"" . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($classDisplay, ENT_QUOTES, 'UTF-8') . "</option>";
}

echo '</select>
    <input type="submit" value="検索" class="search-button">
</form>';

// 検索実行
if (!empty($searchTerm)) {
    $classFilter = ($selectedClass === 'all') ? '' : $selectedClass;
    $results = searchInFiles($searchTerm, $classFilter);
    
    if (is_array($results)) {
        if (empty($results)) {
            echo "<p class='search-info'>「" . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . 
                 "」の検索結果が見つかりませんでした。" . 
                 ($selectedClass !== 'all' ? "（クラス: " . htmlspecialchars($selectedClass, ENT_QUOTES, 'UTF-8') . "）" : "") . "</p>";
        } else {
            echo "<p class='search-info'>「" . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . 
                 "」の検索結果: " . count($results) . "件" . 
                 ($selectedClass !== 'all' ? "（クラス: " . htmlspecialchars($selectedClass, ENT_QUOTES, 'UTF-8') . "）" : "") . "</p>";
            
            foreach ($results as $result) {
                echo '<div class="result-item">';
                echo "<h3><a href='" . htmlspecialchars($result['file'], ENT_QUOTES, 'UTF-8') . "'>" . 
                     htmlspecialchars($result['file'], ENT_QUOTES, 'UTF-8') . "</a></h3>";
                if (!empty($result['class'])) {
                    echo "<p><strong>クラス:</strong> " . htmlspecialchars($result['class'], ENT_QUOTES, 'UTF-8') . "</p>";
                }
                echo "<p>" . $result['excerpt'] . "</p>";
                echo '</div>';
            }
        }
    } else {
        // エラーメッセージ
        echo "<p class='error-message'>" . htmlspecialchars($results, ENT_QUOTES, 'UTF-8') . "</p>";
    }
}
?>