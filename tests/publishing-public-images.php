<?php
declare(strict_types=1);
// Explicit public-site smoke test: HEAD requests only; never publishes or sends private tokens.
if(PHP_SAPI!=='cli' || !in_array('--public-smoke',$argv,true)) exit("Use --public-smoke to run public image checks.\n");
require_once dirname(__DIR__).'/includes/publishing.php';
$urls=[
    'https://www.travelandtourworld.com/newsletter/ttw-header-new.jpg',
    'https://www.travelandtourworld.com/newsletter/2025/Feb/ttw-app-banner.jpg',
    'https://www.travelandtourworld.com/newsletter/2024/Mar/ttw-social-icon.png',
    'https://newsletter.travelandtourworld.com/New/image.php?path=uploads%2F2026%2FNov%2FHeader_10E.jpg&kind=header',
];
foreach($urls as $url) publish_probe_image($url);
echo 'PASS: '.count($urls)." public image probes with the TTW User-Agent; no publication attempted\n";
