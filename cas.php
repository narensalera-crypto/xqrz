<?php
/**
 * ============================================================
 *  PAP TT3 BWANG - Enhanced Version (FIXED v4)
 *  PAP TT3 Cyber Assistant | Blue Team Utility
 * ============================================================
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(300);

// ─── KONFIGURASI ────────────────────────────────────────────
$defaultDir  = __DIR__;
$maxFileSize = 5 * 1024 * 1024;

// ─── EXCLUDED FILENAMES ──────────────────────────────────────
$excludedFiles = [
    'script-loader-packages-react.php','htmlembed.php'
    // tambahkan nama file lain di sini jika perlu
];

$patterns = [
    // ── CODE EXECUTION ────────────────────────────────────
    'eval(',
    'assert(',
    'create_function(',
    'call_user_func(',
    'call_user_func_array(',
    'preg_replace.*\/e',

    // ── ENCODING / OBFUSCATION ────────────────────────────
    'base64_decode(',
    'str_rot13(',
    'gzinflate(',
    'gzuncompress(',
    'gzdecode(',
    'gzdeflate(',
    'hex2bin(',
    'convert_uudecode(',
    'convert_uuencode(',
    'str_replace.*base64',
    'base64_encode.*eval',
    'goto ',

    // ── SYSTEM COMMANDS ───────────────────────────────────
    'system(',
    'exec(',
    'shell_exec(',
    'passthru(',
    'proc_open(',
    'popen(',

    // ── FILE OPERATIONS ───────────────────────────────────
    'move_uploaded_file(',
    'file_put_contents(',
    'file_get_contents(',
    'tempnam(',
    'include.*tmp',
    'require.*http',
    'include_once.*http',

    // ── NETWORK ───────────────────────────────────────────
    'curl_exec(',
    'curl_init(',
    'fsockopen(',
    'fopen.*http',
    'copy.*http',

    // ── USER INPUT ────────────────────────────────────────
    '$_POST[',
    '$_GET[',
    '$_REQUEST[',
    '$_FILES[',
    '$_COOKIE[',
    '$_SERVER[',

    // ── FILESYSTEM MANIPULATION ───────────────────────────
    'chmod(',
    'chown(',
    'touch(',
    'rename(',
    'unlink(',
    'rmdir(',
    'mkdir(',
    'scandir(',
    'glob(',
    'opendir(',
    'readdir(',

    // ── MAGIC BYTES / DISGUISE ────────────────────────────
    '%PDF',
    'JFIF',
    'GIF89',
    'GIF87',

    // ── KNOWN WEBSHELL IOC ────────────────────────────────
    'MadExploits',
    'Gecko',
    'alfashell',
    'c99shell',
    'r57shell',
    'b374k',
    'wso shell',
    'FilesMan',
    'solevisible',
    'linuxploit',
    '403Webshell',
    'yonez',

    // ── REFLECTION / DYNAMIC ──────────────────────────────
    'ReflectionFunction',
    'ReflectionClass',
    'preg_replace_callback',
    'array_map.*base64',
    'array_filter.*eval',
    'usort.*eval',
    'ob_start',
];

$allowedExtensions = [
    'php', 'php3', 'php4', 'php5', 'php7', 'php8',
    'phtml', 'phar', 'inc', 'tpl', 'module',
];

// ─── MAGIC BYTE DETECTION ────────────────────────────────────
function hasSuspiciousMagicBytes($filePath) {
    $handle = @fopen($filePath, 'rb');
    if (!$handle) return false;
    $header = fread($handle, 16);
    fclose($handle);

    $magicMap = [
        '%PDF'              => 'Fake PDF header',
        "\xFF\xD8\xFF"      => 'Fake JPEG header',
        'GIF87a'            => 'Fake GIF87 header',
        'GIF89a'            => 'Fake GIF89 header',
        "\x89PNG\r\n\x1a\n" => 'Fake PNG header',
        'BM'                => 'Fake BMP header',
        'PK'                => 'Fake ZIP/DOCX header',
        "\x1f\x8b"          => 'Fake GZIP header',
    ];

    foreach ($magicMap as $magic => $desc) {
        if (strpos($header, $magic) === 0) {
            return $desc;
        }
    }
    return false;
}

// ─── GOTO OBFUSCATION DETECTOR ───────────────────────────────
function detectGotoObfuscation($content) {
    preg_match_all('/\bgoto\s+\w+\s*;/i', $content, $gotoMatches);
    $gotoCount = count($gotoMatches[0]);

    preg_match_all('/^[A-Za-z_]\w{3,8}:\s*$/m', $content, $labelMatches);
    $labelCount = count($labelMatches[0]);

    if ($gotoCount >= 5 && $labelCount >= 5) {
        return "goto-jump obfuscation: {$gotoCount} gotos, {$labelCount} labels";
    }
    return false;
}

// ─── HEX/OCTAL DENSITY DETECTOR ─────────────────────────────
function detectHexOctalDensity($content) {
    preg_match_all('/\\\\x[0-9a-fA-F]{2}/', $content, $hexMatches);
    preg_match_all('/\\\\[0-7]{3}/', $content, $octMatches);

    $hexCount = count($hexMatches[0]);
    $octCount = count($octMatches[0]);
    $total    = $hexCount + $octCount;

    if ($total >= 50) {
        return "Heavy hex/octal encoding: {$hexCount} hex + {$octCount} octal = {$total} total";
    }
    return false;
}

// ─── VARIABLE-AS-FUNCTION DETECTOR ───────────────────────────
function detectVariableAsFunction($content) {
    $dangerous = [
        'system','exec','shell_exec','passthru','proc_open',
        'popen','eval','assert','base64_decode','str_rot13',
        'gzinflate','file_get_contents','file_put_contents',
        'move_uploaded_file','curl_exec',
    ];

    $score = 0;
    $found = [];

    // Pattern: $var = "system"; ... $var(...)
    preg_match_all('/\$\w+\s*=\s*["\'](' . implode('|', $dangerous) . ')["\']/', $content, $m1);
    if (!empty($m1[1])) {
        $score += count($m1[1]) * 5;
        $found  = array_merge($found, $m1[1]);
    }

    // Pattern: $var("cmd") — variable used as callable
    preg_match_all('/\$[a-zA-Z_]\w*\s*\(\s*[^)]{0,100}\)/', $content, $m2);
    if (count($m2[0]) >= 3) {
        $score += count($m2[0]) * 2;
    }

    if ($score >= 10) {
        $uniq = array_unique($found);
        return "Variable-as-function (score:{$score})"
             . (!empty($uniq) ? " targets: " . implode(', ', $uniq) : '');
    }
    return false;
}

// ─── CONCAT OBFUSCATION DETECTOR ─────────────────────────────
function detectConcatObfuscation($content) {
    $score = 0;

    preg_match_all('/\$\w+\s*=\s*[\'"][a-z_]{1,8}[\'"]\s*;/i', $content, $m1);
    if (count($m1[0]) >= 3) $score += count($m1[0]);

    preg_match_all('/\$\w+\s*\.\s*\$\w+/', $content, $m2);
    if (count($m2[0]) >= 3) $score += count($m2[0]) * 2;

    preg_match_all('/\$[a-zA-Z_]\w*\s*\(/', $content, $m3);
    if (count($m3[0]) >= 2) $score += count($m3[0]) * 3;

    preg_match_all('/\\\\x[0-9a-fA-F]{2}/', $content, $m4);
    if (count($m4[0]) >= 5) $score += count($m4[0]);

    preg_match_all('/\\\\[0-7]{3}/', $content, $m5);
    if (count($m5[0]) >= 5) $score += count($m5[0]);

    return $score >= 10 ? "Obfuscation score: {$score}" : false;
}

// ─── REMOTE FETCHER DETECTOR ─────────────────────────────────
function detectRemoteFetcher($content) {
    $indicators = [
        'raw.githubusercontent.com',
        'pastebin.com/raw',
        'paste.ee/r/',
        'hastebin.com',
        'ghostbin.com',
        'transfer.sh',
        'file_get_contents.*http',
        'curl.*http.*eval',
        'tempnam.*sys_get_temp_dir',
        'include.*tempnam',
    ];

    foreach ($indicators as $ind) {
        if (strpos($ind, '.*') !== false) {
            if (@preg_match('/' . $ind . '/i', $content)) {
                return "Remote fetcher: {$ind}";
            }
        } else {
            if (stripos($content, $ind) !== false) {
                return "Remote fetcher: {$ind}";
            }
        }
    }
    return false;
}

// ─── MAIN SCAN FUNCTION ───────────────────────────────────────
function scanDirectory($dir, $patterns, $allowedExtensions, $maxFileSize, $excludedFiles) {
    if (!is_dir($dir)) {
        return ['error' => "Directory not found: {$dir}"];
    }
    if (!is_readable($dir)) {
        return ['error' => "Directory not readable: {$dir}"];
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $dir,
                RecursiveDirectoryIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
    } catch (Exception $e) {
        return ['error' => "Iterator error: " . $e->getMessage()];
    }

    $suspiciousFiles = [];

    foreach ($iterator as $file) {
        try {
            if (!$file->isFile()) continue;

            // ── EXCLUSION CHECK ───────────────────────────
            $basename = basename($file->getPathname());
            if (in_array($basename, $excludedFiles)) {
                continue; // skip excluded files
            }

            $ext = strtolower(pathinfo($file->getPathname(), PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions)) continue;

            // ── OVERSIZED FILE ────────────────────────────
            if ($file->getSize() > $maxFileSize) {
                $suspiciousFiles[$file->getPathname()]['patterns']['[OVERSIZED] File exceeds 5MB'][] = [
                    'num'  => 0,
                    'code' => '!! Oversized PHP file ('
                            . round($file->getSize() / 1024 / 1024, 2)
                            . ' MB) — likely obfuscated shell !!',
                ];
                $suspiciousFiles[$file->getPathname()]['size']  = $file->getSize();
                $suspiciousFiles[$file->getPathname()]['mtime'] = $file->getMTime();
                $suspiciousFiles[$file->getPathname()]['lines'] = 0;
                continue;
            }

            if (!$file->isReadable()) continue;

            $content = @file_get_contents($file->getPathname());
            if ($content === false) continue;

            $lines = explode("\n", $content);

            // ── MAGIC BYTE CHECK ──────────────────────────
            $magicResult = hasSuspiciousMagicBytes($file->getPathname());
            if ($magicResult) {
                $suspiciousFiles[$file->getPathname()]['patterns']['[MAGIC_BYTE] ' . $magicResult][] = [
                    'num'  => 1,
                    'code' => '!! File disguised with non-PHP magic bytes: ' . $magicResult . ' !!',
                ];
                $suspiciousFiles[$file->getPathname()]['size']  = $file->getSize();
                $suspiciousFiles[$file->getPathname()]['mtime'] = $file->getMTime();
                $suspiciousFiles[$file->getPathname()]['lines'] = count($lines);
            }

            // ── GOTO OBFUSCATION CHECK ────────────────────
            $gotoResult = detectGotoObfuscation($content);
            if ($gotoResult) {
                $suspiciousFiles[$file->getPathname()]['patterns']['[GOTO_OBFUSC] ' . $gotoResult][] = [
                    'num'  => 0,
                    'code' => '!! goto-label control flow obfuscation detected (403Webshell/yonez style) !!',
                ];
                $suspiciousFiles[$file->getPathname()]['size']  = $file->getSize();
                $suspiciousFiles[$file->getPathname()]['mtime'] = $file->getMTime();
                $suspiciousFiles[$file->getPathname()]['lines'] = count($lines);
            }

            // ── HEX/OCTAL DENSITY CHECK ───────────────────
            $hexResult = detectHexOctalDensity($content);
            if ($hexResult) {
                $suspiciousFiles[$file->getPathname()]['patterns']['[HEX_OCTAL] ' . $hexResult][] = [
                    'num'  => 0,
                    'code' => '!! Heavy hex/octal string encoding detected !!',
                ];
                $suspiciousFiles[$file->getPathname()]['size']  = $file->getSize();
                $suspiciousFiles[$file->getPathname()]['mtime'] = $file->getMTime();
                $suspiciousFiles[$file->getPathname()]['lines'] = count($lines);
            }

            // ── VARIABLE-AS-FUNCTION CHECK ────────────────
            $varFuncResult = detectVariableAsFunction($content);
            if ($varFuncResult) {
                $suspiciousFiles[$file->getPathname()]['patterns']['[VAR_FUNC] ' . $varFuncResult][] = [
                    'num'  => 0,
                    'code' => '!! Variable used as function call (dynamic execution) !!',
                ];
                $suspiciousFiles[$file->getPathname()]['size']  = $file->getSize();
                $suspiciousFiles[$file->getPathname()]['mtime'] = $file->getMTime();
                $suspiciousFiles[$file->getPathname()]['lines'] = count($lines);
            }

            // ── CONCAT OBFUSCATION CHECK ──────────────────
            $concatResult = detectConcatObfuscation($content);
            if ($concatResult) {
                $suspiciousFiles[$file->getPathname()]['patterns']['[OBFUSCATION] ' . $concatResult][] = [
                    'num'  => 0,
                    'code' => '!! String concatenation / encoding obfuscation detected !!',
                ];
                $suspiciousFiles[$file->getPathname()]['size']  = $file->getSize();
                $suspiciousFiles[$file->getPathname()]['mtime'] = $file->getMTime();
                $suspiciousFiles[$file->getPathname()]['lines'] = count($lines);
            }

            // ── REMOTE FETCHER CHECK ──────────────────────
            $remoteResult = detectRemoteFetcher($content);
            if ($remoteResult) {
                $suspiciousFiles[$file->getPathname()]['patterns']['[REMOTE_FETCH] ' . $remoteResult][] = [
                    'num'  => 0,
                    'code' => '!! Remote payload fetcher detected: ' . $remoteResult . ' !!',
                ];
                $suspiciousFiles[$file->getPathname()]['size']  = $file->getSize();
                $suspiciousFiles[$file->getPathname()]['mtime'] = $file->getMTime();
                $suspiciousFiles[$file->getPathname()]['lines'] = count($lines);
            }

            // ── PATTERN MATCHING ──────────────────────────
            foreach ($patterns as $pattern) {
                $isRegex = (strpos($pattern, '.*') !== false);

                if ($isRegex) {
                    $safePattern = str_replace('/', '\\/', $pattern);
                    $matched = @preg_match('/' . $safePattern . '/i', $content);
                    if ($matched === false) continue;
                } else {
                    $matched = (stripos($content, $pattern) !== false);
                }

                if ($matched) {
                    if (!isset($suspiciousFiles[$file->getPathname()])) {
                        $suspiciousFiles[$file->getPathname()]['size']  = $file->getSize();
                        $suspiciousFiles[$file->getPathname()]['mtime'] = $file->getMTime();
                        $suspiciousFiles[$file->getPathname()]['lines'] = count($lines);
                    }

                    $suspiciousFiles[$file->getPathname()]['patterns'][$pattern] = [];

                    foreach ($lines as $lineNum => $line) {
                        if ($isRegex) {
                            $safePattern = str_replace('/', '\\/', $pattern);
                            $lineMatched = @preg_match('/' . $safePattern . '/i', $line);
                            if ($lineMatched === false) continue;
                        } else {
                            $lineMatched = (stripos($line, $pattern) !== false);
                        }

                        if ($lineMatched) {
                            $suspiciousFiles[$file->getPathname()]['patterns'][$pattern][] = [
                                'num'  => $lineNum + 1,
                                'code' => $line,
                            ];
                        }
                    }
                }
            }

            unset($content, $lines);

        } catch (Exception $e) {
            continue;
        }
    }

    return $suspiciousFiles;
}

// ─── SOURCE CODE RENDERER ────────────────────────────────────
function renderSource($filePath, $highlightPatterns) {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return '<p style="color:red">Cannot read file.</p>';
    }

    $content = @file_get_contents($filePath);
    if ($content === false) {
        return '<p style="color:red">Cannot read file.</p>';
    }

    $lines       = explode("\n", $content);
    $highlighted = [];

    foreach ($lines as $i => $line) {
        $lineNum     = $i + 1;
        $isHit       = false;
        $escapedLine = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');

        foreach ($highlightPatterns as $pat) {
            if (strpos($pat, '[') === 0) continue;
            $isRegex = (strpos($pat, '.*') !== false);

            if ($isRegex) {
                $safePat = str_replace('/', '\\/', $pat);
                $matched = @preg_match('/' . $safePat . '/i', $line);
                if ($matched === false) continue;
            } else {
                $matched = (stripos($line, $pat) !== false);
            }

            if ($matched) {
                $isHit = true;
                if (!$isRegex) {
                    $escapedPat  = htmlspecialchars($pat, ENT_QUOTES, 'UTF-8');
                    $escapedLine = str_ireplace(
                        $escapedPat,
                        '<mark style="background:#ff4444;color:#fff;'
                        . 'padding:0 2px;border-radius:2px;">'
                        . $escapedPat . '</mark>',
                        $escapedLine
                    );
                }
            }
        }

        $rowStyle = $isHit
            ? 'background:#3a1a1a;border-left:3px solid #ff4444;'
            : 'background:transparent;border-left:3px solid transparent;';

        $highlighted[] = sprintf(
            '<tr style="%s">
                <td style="color:#666;text-align:right;padding:1px 10px;'
                . 'user-select:none;min-width:45px;font-size:12px;">%d</td>
                <td style="padding:1px 10px;white-space:pre;font-size:13px;">%s</td>
            </tr>',
            $rowStyle,
            $lineNum,
            $escapedLine
        );
    }

    return implode('', $highlighted);
}

// ─── EXECUTE SCAN ────────────────────────────────────────────
$results   = [];
$scanError = null;
$scanTime  = 0;

$scanDir  = isset($_POST['scanDir']) ? trim($_POST['scanDir']) : $defaultDir;
$viewFile = isset($_GET['viewFile']) ? trim($_GET['viewFile']) : null;
$doScan   = isset($_POST['doScan']);

if ($doScan) {
    $start    = microtime(true);
    $results  = scanDirectory(
        $scanDir, $patterns, $allowedExtensions, $maxFileSize, $excludedFiles
    );
    $scanTime = round(microtime(true) - $start, 2);

    if (isset($results['error'])) {
        $scanError = $results['error'];
        $results   = [];
    }
}

// Validate viewFile
$viewPatterns = [];
if ($viewFile) {
    $realView    = realpath($viewFile);
    $realScanDir = realpath($scanDir);
    if (
        $realView === false ||
        !file_exists($realView) ||
        ($realScanDir && strpos($realView, $realScanDir) !== 0)
    ) {
        $viewFile = null;
    } else {
        if (isset($results[$viewFile]['patterns'])) {
            $viewPatterns = array_keys($results[$viewFile]['patterns']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>🔍 PAP TT3 BWANG</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    background: #0d0d0d;
    color: #c9d1d9;
    font-family: 'Segoe UI', Consolas, monospace;
    min-height: 100vh;
  }
  .topbar {
    background: linear-gradient(90deg, #161b22, #1f2937);
    border-bottom: 1px solid #30363d;
    padding: 14px 28px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .topbar h1 { font-size: 18px; color: #58a6ff; }
  .topbar .badge {
    background: #21262d;
    border: 1px solid #30363d;
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 11px;
    color: #8b949e;
  }
  .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
  .scan-form {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 10px;
    padding: 20px 24px;
    margin-bottom: 24px;
  }
  .scan-form h2 {
    font-size: 14px;
    color: #8b949e;
    margin-bottom: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .form-row { display: flex; gap: 10px; align-items: stretch; flex-wrap: wrap; }
  .form-row input[type="text"] {
    flex: 1;
    min-width: 280px;
    background: #0d1117;
    border: 1px solid #30363d;
    border-radius: 6px;
    color: #c9d1d9;
    padding: 10px 14px;
    font-size: 14px;
    font-family: Consolas, monospace;
    outline: none;
    transition: border-color .2s;
  }
  .form-row input[type="text"]:focus { border-color: #58a6ff; }
  .btn {
    background: #238636;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 10px 22px;
    font-size: 14px;
    cursor: pointer;
    font-weight: 600;
    transition: background .2s;
    white-space: nowrap;
  }
  .btn:hover { background: #2ea043; }
  .current-path { margin-top: 10px; font-size: 12px; color: #8b949e; }
  .current-path span { color: #58a6ff; word-break: break-all; }
  .ext-info { margin-top: 8px; font-size: 11px; color: #484f58; }
  .ext-info span {
    display: inline-block;
    background: #21262d;
    border: 1px solid #30363d;
    border-radius: 3px;
    padding: 1px 6px;
    margin: 2px;
    font-family: Consolas, monospace;
    color: #8b949e;
  }
  .excl-info { margin-top: 6px; font-size: 11px; color: #484f58; }
  .excl-info span {
    display: inline-block;
    background: #1a2a1a;
    border: 1px solid #3fb950;
    border-radius: 3px;
    padding: 1px 6px;
    margin: 2px;
    font-family: Consolas, monospace;
    color: #3fb950;
  }
  .stats-bar { display: flex; gap: 14px; margin-bottom: 20px; flex-wrap: wrap; }
  .stat-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 8px;
    padding: 12px 20px;
    flex: 1;
    min-width: 140px;
  }
  .stat-card .val { font-size: 26px; font-weight: 700; color: #f0883e; }
  .stat-card .lbl { font-size: 12px; color: #8b949e; margin-top: 2px; }
  .stat-card.ok .val   { color: #3fb950; }
  .stat-card.info .val { color: #58a6ff; }
  .result-section h2 {
    font-size: 14px;
    color: #8b949e;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
  }
  .file-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 8px;
    margin-bottom: 14px;
    overflow: hidden;
  }
  .file-card-header {
    background: #1c2128;
    padding: 10px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    border-bottom: 1px solid #30363d;
  }
  .file-path {
    font-family: Consolas, monospace;
    font-size: 13px;
    color: #ffa657;
    word-break: break-all;
  }
  .file-meta {
    font-size: 11px;
    color: #8b949e;
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
  }
  .pattern-list { padding: 12px 16px; }
  .pattern-item { margin-bottom: 10px; }
  .pattern-badge {
    display: inline-block;
    background: #3a1a1a;
    border: 1px solid #f85149;
    color: #f85149;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 11px;
    font-family: Consolas, monospace;
    margin-bottom: 6px;
  }
  .pattern-badge.special  { background:#1a1a3a; border-color:#a371f7; color:#a371f7; }
  .pattern-badge.remote   { background:#1a2a1a; border-color:#3fb950; color:#3fb950; }
  .pattern-badge.magic    { background:#2a1a00; border-color:#f0883e; color:#f0883e; }
  .pattern-badge.goto_ob  { background:#2a002a; border-color:#ff79c6; color:#ff79c6; }
  .pattern-badge.hexoct   { background:#002a2a; border-color:#8be9fd; color:#8be9fd; }
  .pattern-badge.varfunc  { background:#2a2a00; border-color:#f1fa8c; color:#f1fa8c; }
  .match-lines { padding-left: 12px; }
  .match-line {
    font-family: Consolas, monospace;
    font-size: 12px;
    color: #8b949e;
    padding: 2px 0;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }
  .match-line .ln   { color: #58a6ff; min-width: 65px; }
  .match-line .code { color: #c9d1d9; word-break: break-all; }
  .view-btn {
    display: inline-block;
    margin-top: 8px;
    background: #1f2937;
    border: 1px solid #30363d;
    color: #58a6ff;
    border-radius: 5px;
    padding: 5px 14px;
    font-size: 12px;
    text-decoration: none;
    transition: background .2s;
  }
  .view-btn:hover { background: #30363d; }
  .source-viewer {
    background: #0d1117;
    border: 1px solid #30363d;
    border-radius: 10px;
    margin-bottom: 28px;
    overflow: hidden;
  }
  .source-header {
    background: #161b22;
    border-bottom: 1px solid #30363d;
    padding: 12px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
  }
  .source-header .title {
    color: #ffa657;
    font-size: 13px;
    font-family: Consolas, monospace;
    word-break: break-all;
  }
  .source-header a {
    color: #8b949e;
    font-size: 12px;
    text-decoration: none;
    background: #21262d;
    border: 1px solid #30363d;
    border-radius: 4px;
    padding: 4px 10px;
    white-space: nowrap;
  }
  .source-header a:hover { color: #c9d1d9; }
  .source-table {
    width: 100%;
    border-collapse: collapse;
    font-family: Consolas, 'Courier New', monospace;
    overflow-x: auto;
    display: block;
  }
  .source-table td { vertical-align: top; }
  .alert {
    padding: 12px 18px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-size: 14px;
    line-height: 1.5;
  }
  .alert.danger  { background:#3a1a1a; border:1px solid #f85149; color:#f85149; }
  .alert.success { background:#0d2818; border:1px solid #3fb950; color:#3fb950; }
  .alert.info    { background:#0c1929; border:1px solid #58a6ff; color:#58a6ff; }
  .empty { text-align:center; padding:60px 20px; color:#8b949e; }
  .empty .icon { font-size:52px; margin-bottom:14px; }
  .empty strong { color:#c9d1d9; }
  ::-webkit-scrollbar       { width:6px; height:6px; }
  ::-webkit-scrollbar-track { background:#0d1117; }
  ::-webkit-scrollbar-thumb { background:#30363d; border-radius:3px; }
  ::-webkit-scrollbar-thumb:hover { background:#484f58; }
</style>
</head>
<body>

<div class="topbar">
  <h1>🔍 PAP TT3 BWANG</h1>
  <span class="badge">Blue Team Utility</span>
  <span class="badge">PAP TT3 Cyber Assistant</span>
  <span class="badge">Fixed v4</span>
</div>

<div class="container">

  <!-- SCAN FORM -->
  <div class="scan-form">
    <h2>⚙ Scan Configuration</h2>
    <form method="POST" action="">
      <div class="form-row">
        <input
          type="text"
          name="scanDir"
          value="<?= htmlspecialchars($scanDir) ?>"
          placeholder="e.g: /var/www/html"
        >
        <button type="submit" name="doScan" class="btn">▶ Start Scan</button>
      </div>
      <div class="current-path">
        📁 Script location: <span><?= htmlspecialchars(__DIR__) ?></span>
        &nbsp;|&nbsp;
        📄 Script file: <span><?= htmlspecialchars(__FILE__) ?></span>
      </div>
      <div class="ext-info">
        🔎 Scanning extensions:
        <?php foreach ($allowedExtensions as $ext): ?>
          <span>.<?= htmlspecialchars($ext) ?></span>
        <?php endforeach; ?>
      </div>
      <div class="excl-info">
        ✅ Excluded files:
        <?php foreach ($excludedFiles as $ef): ?>
          <span><?= htmlspecialchars($ef) ?></span>
        <?php endforeach; ?>
      </div>
    </form>
  </div>

  <?php if ($scanError): ?>
    <div class="alert danger">❌ Error: <?= htmlspecialchars($scanError) ?></div>
  <?php endif; ?>

  <!-- SOURCE VIEWER -->
  <?php if ($viewFile && file_exists($viewFile)): ?>
    <?php
      $srcRows    = renderSource($viewFile, $viewPatterns ?: $patterns);
      $totalLines = count(file($viewFile));
    ?>
    <div class="source-viewer">
      <div class="source-header">
        <span class="title">📄 <?= htmlspecialchars($viewFile) ?></span>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <span style="font-size:11px;color:#8b949e;"><?= $totalLines ?> lines</span>
          <a href="?">✕ Close Viewer</a>
        </div>
      </div>
      <div style="overflow-x:auto;">
        <table class="source-table">
          <tbody><?= $srcRows ?></tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <!-- SCAN RESULTS -->
  <?php if ($doScan && !$scanError): ?>

    <?php
      $totalSuspicious  = count($results);
      $totalPatternHits = 0;
      foreach ($results as $f) {
          $totalPatternHits += count($f['patterns']);
      }
    ?>

    <div class="stats-bar">
      <div class="stat-card <?= $totalSuspicious === 0 ? 'ok' : '' ?>">
        <div class="val"><?= $totalSuspicious ?></div>
        <div class="lbl">Suspicious Files</div>
      </div>
      <div class="stat-card">
        <div class="val"><?= $totalPatternHits ?></div>
        <div class="lbl">Pattern Hits</div>
      </div>
      <div class="stat-card info">
        <div class="val"><?= $scanTime ?>s</div>
        <div class="lbl">Scan Duration</div>
      </div>
      <div class="stat-card info">
        <div class="val" style="font-size:16px;word-break:break-all;">
          <?= htmlspecialchars(basename($scanDir)) ?>
        </div>
        <div class="lbl">Scanned Directory</div>
      </div>
    </div>

    <div class="result-section">
      <h2>📋 Scan Results — <?= htmlspecialchars($scanDir) ?></h2>

      <?php if (empty($results)): ?>
        <div class="alert success">
          ✅ No suspicious files found. Directory looks clean.
        </div>
      <?php else: ?>
        <div class="alert danger">
          ⚠️ Found <strong><?= $totalSuspicious ?></strong> suspicious file(s)
          with <strong><?= $totalPatternHits ?></strong> pattern hit(s).
        </div>

        <?php foreach ($results as $filePath => $info): ?>
          <?php
            $sizeKB    = round($info['size'] / 1024, 1);
            $mtime     = date('Y-m-d H:i:s', $info['mtime']);
            $lineCount = $info['lines'];
            $viewUrl   = '?viewFile=' . urlencode($filePath)
                       . '&scanDir='  . urlencode($scanDir);
          ?>
          <div class="file-card" id="file-<?= md5($filePath) ?>">
            <div class="file-card-header">
              <div class="file-path">
                📄 <?= htmlspecialchars($filePath) ?>
              </div>
              <div class="file-meta">
                <span>📦 <?= $sizeKB ?> KB</span>
                <span>📝 <?= $lineCount ?> lines</span>
                <span>🕒 <?= $mtime ?></span>
                <span>🚨 <?= count($info['patterns']) ?> pattern(s)</span>
              </div>
            </div>

            <div class="pattern-list">
              <?php foreach ($info['patterns'] as $pat => $hits): ?>
                <?php
                  $bc = 'pattern-badge';
                  if      (strpos($pat, '[MAGIC_BYTE]')  === 0) $bc .= ' magic';
                  elseif  (strpos($pat, '[OBFUSCATION]') === 0) $bc .= ' special';
                  elseif  (strpos($pat, '[REMOTE_FETCH]')=== 0) $bc .= ' remote';
                  elseif  (strpos($pat, '[OVERSIZED]')   === 0) $bc .= ' magic';
                  elseif  (strpos($pat, '[GOTO_OBFUSC]') === 0) $bc .= ' goto_ob';
                  elseif  (strpos($pat, '[HEX_OCTAL]')   === 0) $bc .= ' hexoct';
                  elseif  (strpos($pat, '[VAR_FUNC]')    === 0) $bc .= ' varfunc';
                ?>
                <div class="pattern-item">
                  <span class="<?= $bc ?>">
                    <?= htmlspecialchars($pat) ?>
                  </span>
                  <div class="match-lines">
                    <?php foreach (array_slice($hits, 0, 5) as $hit): ?>
                      <div class="match-line">
                        <?php if ($hit['num'] > 0): ?>
                          <span class="ln">Line <?= $hit['num'] ?>:</span>
                        <?php endif; ?>
                        <span class="code">
                          <?= htmlspecialchars(
                              substr(trim($hit['code']), 0, 250)
                          ) ?>
                        </span>
                      </div>
                    <?php endforeach; ?>
                    <?php if (count($hits) > 5): ?>
                      <div class="match-line"
                           style="color:#555;font-style:italic;">
                        ... and <?= count($hits) - 5 ?> more occurrences
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>

              <?php if ($lineCount > 0): ?>
                <a href="<?= htmlspecialchars($viewUrl) ?>"
                   class="view-btn">
                  📖 View Full Source
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>

      <?php endif; ?>
    </div>

  <?php elseif (!$doScan): ?>
    <div class="empty">
      <div class="icon">🔍</div>
      <div>Enter a directory path and click
        <strong>Start Scan</strong> to begin.</div>
      <div style="margin-top:10px;font-size:12px;color:#444;">
        Default: <?= htmlspecialchars($defaultDir) ?>
      </div>
    </div>
  <?php endif; ?>

</div>
</body>
</html>
