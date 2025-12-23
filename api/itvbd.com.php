<?php
include 'helper.php';
date_default_timezone_set('Asia/Dhaka');

function newsID($link){
	$parts = explode('/', $link);
	return intval($parts[count($parts) - 2]);
}

function recentNewsLinks(){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://www.itvbd.com/api/theme_engine/get_ajax_contents?widget=725&start=0&count=50&page_id=0&subpage_id=0&author=0&tags=&archive_time='.date('Y-m-d').'&filter=');
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'user-agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'
	]);
	$content = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	
	if($status !== 200 || empty($content)){
		return false;
	}
	
	$json = json_decode($content, true);
	if($json === null || !isset($json['total'], $json['html']) || $json['total'] === 0){
		return false;
	}
	
	$html = str_get_html($json['html']);
	$linkOverlay = $html->find('a.link_overlay');
	if(!$linkOverlay){
		return false;
	}
	
	$links = [];
	foreach($linkOverlay as $link){
		$href = $link->getAttribute('href') ?? null;
		if($href){
			$links[] = 'https:'.$href;
		}
	}
	
	if(empty($links)){
		return false;
	}
	
	return array_reverse($links);
}

function latestNews($latest){
	$output = [];
	$handles = [];
	$completed = 0;
	$total = count($latest);
	$multiHandle = curl_multi_init();
	while($completed < $total){
		while(count($handles) < 25 && ($completed + count($handles)) < $total){
			$index = $completed + count($handles);
			$handle = curl_init($latest[$index]);

			curl_setopt_array($handle, [
				CURLOPT_TIMEOUT => 15,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_ENCODING => 'gzip',
				CURLOPT_HTTPHEADER => ['user-agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36']
			]);

			curl_multi_add_handle($multiHandle, $handle);
			$handles[$latest[$index]] = $handle;
		}

		do{
			$status = curl_multi_exec($multiHandle, $active);
			if($active){
				curl_multi_select($multiHandle);
			}
		}while($active && $status == CURLM_OK);

		foreach($handles as $key => $handle){
			$news = ['id' => newsID($key), 'url' => $key];
			$content = curl_multi_getcontent($handle);
			if(!empty($content)){
				$newsData = [];
				$html = str_get_html($content);
				$img = $html->find('#adf-overlay', 0);
				$time = $html->find('.published_time', 0);
				$subtitle = $html->find('.content_highlights', 0);
				$author = $html->find('div[itemprop="author"]', 0);
				$headline = $html->find('h1[itemprop="headline"]', 0);
				$body = $html->find('div[itemprop="articleBody"]', 0);
				
				foreach($html->find('script[type="application/ld+json"]') as $script){
					$json = trim($script->innertext);
					if ($json === '') continue;

					$data = json_decode($json, true);
					if (!is_array($data)) continue;

					if(isset($data['headline'], $data['description'], $data['datePublished'])){
						$newsData = $data;
						break;
					}
				}
				
				if(($headline || isset($newsData['headline'])) && $body){
					$news['reporter'] = $newsData['author']['name'] ?? ($author ? html_entity_decode(trim($author->plaintext)) : '');
					$news['time'] = isset($newsData['datePublished']) ? date("l, F j, Y, h:i:s A", strtotime($newsData['datePublished'])) : ($time ? html_entity_decode(trim($time->plaintext)) : '');
					$news['image'] = $newsData['image']['url'] ?? ($img ? 'https:'.trim($img->getAttribute('src')) : '');
					$news['title'] = $newsData['headline'] ?? html_entity_decode(trim($headline->plaintext));
					$news['subtitle'] = $newsData['description'] ?? ($subtitle ? html_entity_decode(trim($subtitle->plaintext)) : '');
					$news['body'] = html_entity_decode(trim($body->plaintext));
					if(mb_strpos($news['body'], 'আরও ভিডিও দেখতে ইনডিপেনডেন্ট টেলিভিশনের ইউটিউব চ্যানেলের লিংকটি ক্লিক করুন') === false){
						$output[] = $news;
					}
				}
				
				$html->clear();
				unset($html);
			}
			curl_multi_remove_handle($multiHandle, $handle);
			curl_close($handle);
			unset($handles[$key]);
			$completed++;
		}
	}
	
	curl_multi_close($multiHandle);
	
	if(empty($output)){
		return ['success' => false, 'messsage' => 'Failed to fetch latest news.'];
	}
	
	return ['success' => true, 'messsage' => 'Latest news successfully fetched.', 'news' => $output];
}


function recentNews($i = 0){
	$res = ['success' => false];
	$links = recentNewsLinks();
	
	if($links === false){
		$res['messsage'] = 'Failed to fetch recent news links.';
		return $res;
	}

	$latest = []; 
	foreach($links as $link){
		$no = newsID($link);
		if($no > $i){
			$latest[] = $link;
		}
	}
	
	if(empty($latest)){
		$res['messsage'] = 'No latest news found.';
		return $res;
	}
	
	return latestNews($latest);
}

$update = $_GET['update'] ?? $_POST['update'] ?? 0;
header("Content-Type: application/json; charset=utf-8");
echo json_encode(recentNews(intval($update)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);