<?php
require dirname(__DIR__).'/vendor/autoload.php';
use RetroWEB\Rendering\Extraction\ArticleCardOrder;
$order=new ArticleCardOrder();
$card='<article><a href="/story"><img src="/photo.jpg" alt="Photo"></a><div><a href="/story"><header><span class="primary-category">Science</span><h2>Story title</h2></header><p>Summary retained.</p></a></div><time>Today</time></article>';
$input='<html><body><main>'.$card.$card.'</main></body></html>';$out=$order->apply($input);
if(!(strpos($out,'Story title')<strpos($out,'Science')&&strpos($out,'Science')<strpos($out,'<img')&&strpos($out,'<img')<strpos($out,'Summary retained.'))||substr_count($out,'src="/photo.jpg"')!==2||substr_count($out,'Today')!==2)throw new RuntimeException('Generic card ordering loses content or order');
if($order->apply($card)!==$card)throw new RuntimeException('Lone article changed');
$checkDom=new DOMDocument();@$checkDom->loadHTML($out);$checkXp=new DOMXPath($checkDom);if($checkXp->query('//article/h2/small')->length!==2||$checkXp->query('//article/p/a/img')->length!==2)throw new RuntimeException('Category must stay with heading and images need separate paragraph spacing');
$captioned=str_replace('<img src="/photo.jpg" alt="Photo">','<figure><img src="/photo.jpg" alt="Photo"><figcaption>Caption</figcaption></figure>',$input);if($order->apply($captioned)!==$captioned)throw new RuntimeException('Captioned article changed');
$plain='<article><h1>Long story</h1><p>Text</p><img src="/a.jpg"></article>';if($order->apply($plain)!==$plain)throw new RuntimeException('Full article changed');
echo "Article card order checks passed.\n";
if(isset($argv[1])){$page=$order->apply(file_get_contents($argv[1]));$dom=new DOMDocument();@$dom->loadHTML($page);$xp=new DOMXPath($dom);$card=$xp->query('//article')->item(0);$nodes=$xp->query('.//h2|.//img|.//*[contains(@class,"primary-tag")]|.//*[contains(@class,"excerpt")]',$card);foreach($nodes as$node)echo $node->nodeName.' '.substr(trim($node->textContent),0,60)."\n";}
