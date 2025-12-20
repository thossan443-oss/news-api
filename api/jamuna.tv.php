<?php
include 'helper.php';

function newsID($link){
	$parts = explode('/', $link);
	return intval(end($parts));
}

function recentNewsLinks(){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://jamuna.tv/archive');
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
	
	$html = str_get_html($content);
	$linkOverlay = $html->find('a.linkOverlay');
	if(!$linkOverlay){
		return false;
	}
	
	$links = [];
	foreach($linkOverlay as $link){
		$href = $link->getAttribute('href') ?? null;
		if($href){
			$links[] = $href;
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
			$news = ['id' => newsID($key), 'url' => '$key'];
			$content = curl_multi_getcontent($handle);
			if(!empty($content)){
				$html = str_get_html($content);
				$desktopDetailHeadline = $html->find('.desktopDetailHeadline', 0);
				$desktopDetailPhoto = $html->find('.desktopDetailPhoto', 0);
				$desktopDetailBody = $html->find('.desktopDetailBody', 0);
				$desktopDetailReporter = $html->find('.desktopDetailReporter', 0);
				$desktopDetailPTime = $html->find('.desktopDetailPTime', 0);
				
				if(!$desktopDetailHeadline){
					$desktopDetailHeadline = $html->find('title', 0);
				}
				
				if($desktopDetailHeadline && $desktopDetailBody){
					$imageFound = false;
					$news['image'] = $news['time'] = $news['reporter'] = null;
					$news['title'] = html_entity_decode(trim($desktopDetailHeadline->plaintext));
					$news['body'] = html_entity_decode(trim($desktopDetailBody->plaintext));
					
					if($desktopDetailPhoto){
						$desktopDetailImg = $desktopDetailPhoto->find('img', 0);
						if($desktopDetailImg){
							$src = $desktopDetailImg->getAttribute('src') ?? false;
							if($src){
								$imageFound = true;
								$news['image'] = $src;
							}
						}
					}
					
					if($imageFound === false){
						$desktopDetailImg = $html->find('[property="og:image"]', 0);
						if($desktopDetailImg){
							$src = $desktopDetailImg->getAttribute('content') ?? false;
							if($src){
								$news['image'] = $src;
							}
						}
					}
					
					if($desktopDetailReporter){
						$news['reporter'] = html_entity_decode(trim($desktopDetailReporter->plaintext));
					}
					
					if($desktopDetailPTime){
						$news['time'] = html_entity_decode(trim($desktopDetailPTime->plaintext));
					}
					
					$output[] = $news;
				}
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

header("Content-Type: application/json; charset=utf-8");
$update = $_GET['update'] ?? $_POST['update'] ?? 0;
echo json_encode(recentNews(intval($update)));
