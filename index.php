<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$asin = $_GET["asin"];

echo <<<HTML
<form action="." method="GET">
<img src="ISBN-10_finder.png" style="float:right" />

Enter the book's ASIN or ISBN-10 number (under "Product Details"):<br/>
<input type="text" name="asin"/><br/>
<input type="submit"/>
</form>
HTML;

// First step: Get session cookies.
$ch = curl_init('http://www.amazon.com/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
// get headers too with this line
curl_setopt($ch, CURLOPT_HEADER, 1);
$result = curl_exec($ch);
preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
$cookies = array();
foreach($matches[1] as $item) {
    parse_str($item, $cookie);
    $cookies = array_merge($cookies, $cookie);
}

$sessionId = $cookies['session-id'];
$sessionIdTime = $cookies['session-id-time'];

// Now get the Kindle preview for the specified ASIN.
if (isset($asin)) {
  $fields = array(
    'method' => 'getKindleSample',
    'asin' => $asin
  );
  //url-ify the data for the POST
  foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
  $fields_string = rtrim($fields_string, '&');

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'http://www.amazon.com/gp/search-inside/service-data');
  curl_setopt($ch, CURLOPT_POST, count($fields));
  curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
  curl_setopt($ch, CURLOPT_COOKIE, 'session-id=$sessionId; session-id-time=$sessionIdTime');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $jsonstrpage = curl_exec($ch);
  
  curl_close($ch);
  
  $jsonobjpage = json_decode($jsonstrpage);
  
  $kindlesample = $jsonobjpage->sample;

  $doc = new DOMDocument();
  $doc->loadHTML($kindlesample);
  
  // Filter images and hyperlinks (often the table of contents).
  $xpath = new DOMXPath($doc);
  $xpquery = '//img | //a';
  $imgnodes = $xpath->query($xpquery);
  
  foreach ($imgnodes as $imgnode) {
    $imgnode->parentNode->removeChild($imgnode);
  }
  
  // Strip all styles.
  $xpath = new DOMXPath($doc);
  $xpquery = '//*[@style]';
  $stylenodes = $xpath->query($xpquery);
  
  foreach ($stylenodes as $stylenode) {
    $stylenode->removeAttribute('style');
  }
  
  // Count words.
  $innerText = '';
  $xpath = new DOMXPath($doc);
  $xpquery = '//text()';
  $textnodes = $xpath->query($xpquery);
  
  foreach ($textnodes as $textnode) {
    $innerText .= $textnode->wholeText;
  }
  
  $wordcount = str_word_count($innerText);
  $pagecount = round($wordcount / 250);
}

echo <<<HTML
<div style="clear:both">&nbsp;</div>
<div style="font-style:italic">
  <textarea>$kindlesample</textarea><br/>
  Showing Kindle snapshot of: $asin (wordcount: $wordcount, approximately $pagecount pages)
</div>
<div>
HTML;

if (isset($doc)) {
  echo $doc->saveHTML();
}

echo "</div>";
