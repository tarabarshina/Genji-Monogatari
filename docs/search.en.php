<?php
ini_set('display_errors', 1); // Enable error display during debugging
error_reporting(E_ALL);

// CSRF token generation and validation
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

/**
 * Highlight search terms in text
 * @param string $text Text to highlight
 * @param string $searchTerm Search term
 * @return string Text with highlights applied
 */
function highlightText($text, $searchTerm) {
    // Properly escape the text and search term
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
 * Count occurrences of search term in text
 * @param string $text Text to search in
 * @param string $searchTerm Search term
 * @return int Number of occurrences
 */
function countOccurrences($text, $searchTerm) {
    if (empty($searchTerm) || empty($text)) {
        return 0;
    }
    
    // Case-insensitive matching
    return preg_match_all('/' . preg_quote($searchTerm, '/') . '/iu', $text);
}

/**
 * Get filtered content from HTML
 * @param string $html HTML content
 * @param string $classFilter Class filter
 * @return string Filtered text
 */
function getFilteredContent($html, $classFilter = '') {
    // Patterns to exclude
    $excludePatterns = [
        '/<nav class="md-nav.*?<\/nav>/s',  // md navigation
        '/<span class="md-ellipsis".*?<\/span>/s',  // md ellipsis
        '/<li class="md-nav".*?<\/li>/s',  // md nav items
        '/<h1.*?<\/h1>/s',                   // h1 tags
        '/<h2.*?<\/h2>/s',                   // h2 tags
        '/<h3.*?<\/h3>/s',                   // h3 tags
        '/<header.*?<\/header>/s',           // header
        '/<footer.*?<\/footer>/s',           // footer
        '/class="md-header".*?<\/div>/s',    // md header
        '/class="md-footer".*?<\/div>/s'     // md footer
    ];
    
    // Apply exclusion patterns
    foreach ($excludePatterns as $pattern) {
        $html = preg_replace($pattern, '', $html);
    }
    
    // If class filter is specified, extract only elements with that class
    if (!empty($classFilter)) {
        $dom = new DOMDocument();
        // Use libxml internal errors
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Find elements with the specified class (with XSS protection)
        $safeClassFilter = htmlspecialchars($classFilter, ENT_QUOTES, 'UTF-8');
        $elements = $xpath->query("//*[contains(@class, '$safeClassFilter')]");
        
        $filteredContent = '';
        foreach ($elements as $element) {
            $filteredContent .= $dom->saveHTML($element);
        }
        
        return strip_tags($filteredContent);
    }
    
    // If no filter, return all content
    return strip_tags($html);
}

/**
 * Search for term in files
 * @param string $searchTerm Search term
 * @param string $classFilter Class filter
 * @return array|string Search results or error message
 */
function searchInFiles($searchTerm, $classFilter = '') {
    try {
        // Validate search term
        if (empty($searchTerm)) {
            return "Please enter a search term.";
        }
        
        // Set memory limit
        ini_set('memory_limit', '256M');
        
        $baseDirectory = './volumes/';
        $results = [];
        $fileCount = 0;
        $maxFiles = 1000; // Maximum files to search
        $totalOccurrences = 0; // Total occurrences count
        
        // Check if directory exists and is readable
        if (!is_dir($baseDirectory) || !is_readable($baseDirectory)) {
            return "Cannot access search directory.";
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDirectory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            // Check maximum file count
            if ($fileCount >= $maxFiles) {
                break;
            }
            
            // Validate file
            if (!$file->isFile() || $file->getExtension() !== 'html' || !is_readable($file->getPathname())) {
                continue;
            }
            
            // Directory traversal protection
            $realpath = realpath($file->getPathname());
            $baseRealpath = realpath($baseDirectory);
            
            if ($baseRealpath === false || strpos($realpath, $baseRealpath) !== 0) {
                continue; // Skip files outside base directory
            }
            
            $fileCount++;
            
            // Check file size
            if ($file->getSize() > 5 * 1024 * 1024) { // Skip files larger than 5MB
                continue;
            }
            
            $content = file_get_contents($file->getPathname());
            if ($content === false) {
                continue; // Skip if file reading fails
            }
            
            $text = getFilteredContent($content, $classFilter);
            
            // Count occurrences
            $occurrences = countOccurrences($text, $searchTerm);
            $totalOccurrences += $occurrences;
            
            if ($occurrences > 0) {
                // Extract text surrounding the search term
                $position = mb_stripos($text, $searchTerm);
                $start = max(0, $position - 100);
                $length = mb_strlen($searchTerm) + 200;
                $excerpt = mb_substr($text, $start, $length);
                
                // Highlight the excerpt
                $highlightedExcerpt = highlightText($excerpt, $searchTerm);
                
                // Generate relative path
                if ($baseRealpath && $realpath) {
                    $relativePath = str_replace($baseRealpath, '', $realpath);
                    $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
                    
                    $results[] = [
                        'file' => $relativePath,
                        'excerpt' => $highlightedExcerpt,
                        'class' => $classFilter,
                        'occurrences' => $occurrences
                    ];
                    
                    // Limit maximum results
                    if (count($results) >= 100) {
                        break;
                    }
                }
            }
        }
        
        // Sort by occurrence count (descending)
        usort($results, function($a, $b) {
            return $b['occurrences'] - $a['occurrences'];
        });
        
        return [
            'results' => $results,
            'totalOccurrences' => $totalOccurrences
        ];
    } catch (Exception $e) {
        error_log("Search error: " . $e->getMessage());
        return "An error occurred during search: " . $e->getMessage();
    }
}

// Available class list
$availableClasses = ['all', 'original', 'romanized', 'yosano', 'shibuya', 'seiden', 'annotation'];

// Class names display mapping
$classDisplayNames = [
    'all' => 'All',
    'original' => 'Original text',
    'romanized' => 'Romanized text',
    'yosano' => 'Yosano Translation',
    'shibuya' => 'Shibuya Translation',
    'seiden' => 'Seidensticker Translation',
    'annotation' => 'Annotations'
];

// Process search request
$searchTerm = '';
$selectedClass = 'all';
$showForm = true;
$errorMessage = '';

// HTML title variable
$pageTitle = 'The Tale of Genji - Search';

// Validate POST request (CSRF token)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid request.";
    } else {
        $searchTerm = isset($_POST['search']) ? trim($_POST['search']) : '';
        $selectedClass = isset($_POST['class']) && in_array($_POST['class'], $availableClasses) ? $_POST['class'] : 'all';
        if (!empty($searchTerm)) {
            $pageTitle = 'Search results for "' . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . '" - The Tale of Genji';
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $selectedClass = isset($_GET['class']) && in_array($_GET['class'], $availableClasses) ? $_GET['class'] : 'all';
    if (!empty($searchTerm)) {
        $pageTitle = 'Search results for "' . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . '" - The Tale of Genji';
    }
}

// Start HTML document and set title
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $pageTitle . '</title>
    <style>
    body { 
        font-family: "Hiragino Sans", Arial, Helvetica, sans-serif; 
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
    </style>
</head>
<body>
    <div class="header">
        <h1>The Tale of Genji - Search</h1>
    </div>';

// Display error message if any
if (!empty($errorMessage)) {
    echo '<div class="error-message">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
}

// Display search form
echo '<form method="POST" class="search-form">
    <input type="hidden" name="csrf_token" value="' . $csrf_token . '">
    <input type="text" name="search" value="' . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . '" class="search-input" placeholder="Enter search term" required>
    <select name="class" class="class-select">';

foreach ($availableClasses as $class) {
    $selected = ($class === $selectedClass) ? 'selected' : '';
    $classDisplay = isset($classDisplayNames[$class]) ? $classDisplayNames[$class] : $class;
    echo "<option value=\"" . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . "\" $selected>" . htmlspecialchars($classDisplay, ENT_QUOTES, 'UTF-8') . "</option>";
}

echo '</select>
    <input type="submit" value="Search" class="search-button">
</form>';

// Execute search
if (!empty($searchTerm)) {
    $classFilter = ($selectedClass === 'all') ? '' : $selectedClass;
    $searchResult = searchInFiles($searchTerm, $classFilter);
    
    if (is_array($searchResult) && isset($searchResult['results'])) {
        $results = $searchResult['results'];
        $totalOccurrences = $searchResult['totalOccurrences'];
        
        if (empty($results)) {
            echo "<p class='search-info'>No results found for \"" . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . 
                 "\"" . 
                 ($selectedClass !== 'all' ? " in " . htmlspecialchars($classDisplayNames[$selectedClass], ENT_QUOTES, 'UTF-8') : "") . ".</p>";
        } else {
            echo "<p class='search-info'>Found " . count($results) . " files with <span class='total-occurrences'>" . $totalOccurrences . "</span> total matches for \"" . 
                 htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . "\"" . 
                 ($selectedClass !== 'all' ? " in " . htmlspecialchars($classDisplayNames[$selectedClass], ENT_QUOTES, 'UTF-8') : "") . ".</p>";
            
            foreach ($results as $result) {
                echo '<div class="result-item">';
                echo "<h3><a href='/volumes/" . htmlspecialchars($result['file'], ENT_QUOTES, 'UTF-8') . "'>" . 
                     htmlspecialchars($result['file'], ENT_QUOTES, 'UTF-8') . "</a>";
                
                // Display occurrence count
                echo "<span class='occurrence-count' title='Occurrences'>" . $result['occurrences'] . "</span>";
                
                echo "</h3>";
                
                echo "<p>" . $result['excerpt'] . "</p>";
                echo '</div>';
            }
        }
    } else {
        // Error message
        echo "<p class='error-message'>" . htmlspecialchars($searchResult, ENT_QUOTES, 'UTF-8') . "</p>";
    }
}

// Close HTML document
echo '</body>
</html>';