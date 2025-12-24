<?php
include 'helper.php';
date_default_timezone_set('Asia/Dhaka');

function plainBody($news){
	$html = str_get_html($news);

	foreach($html->find('p, ul') as $el){
		$text = trim($el->innertext);
		if(mb_strpos($text, '/article/') !== false || mb_strpos($text, 'আরও পড়ুন') !== false || mb_strpos($text, 'সর্বশেষ খবর পেতে গুগল প্লে স্টোর এবং অ্যাপল অ্যাপ স্টোর') !== false){
			$el->remove();
		}
	}
	
	$text = html_entity_decode(trim($html->plaintext));
	$html->clear();
	unset($html);

	return str_replace("\r\n", "", $text);
}

function newsID($link){
	$parts = explode('/', $link);
	return intval(end($parts));
}

function newsCategory($link){
	$parts = explode('/', $link);
	return $parts[count($parts) - 2];
}

function recentNewsLinks(){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://backoffice.channel24bd.tv/api/v2/archive');
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	curl_setopt($ch, CURLOPT_POSTFIELDS, '{"start_date":"","end_date":"","category_name":0,"limit":50}');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'content-type: application/json',
		'user-agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'
	]);
	$content = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	
	if($status !== 200 || empty($content)){
		return false;
	}
	
	$json = json_decode($content, true);
	if($json === null || !isset($json['archive_data'])){
		return false;
	}
	
	$links = [];
	foreach($json['archive_data'] as $data){
		$Slug = $data['Slug'] ?? null;
		$VideoID = $data['VideoID'] ?? null;
		$URLAlies = $data['URLAlies'] ?? null;
		$ContentID = $data['ContentID'] ?? null;
		$VideoPath = $data['VideoPath'] ?? null;
		$VideoType = $data['VideoType'] ?? null;
		
		if($Slug && $URLAlies && $ContentID && $VideoID === null && $VideoPath === null && $VideoType === null){
			$links[] = 'https://backoffice.channel24bd.tv/api/v2/content-details/'.$Slug.'/'.$ContentID;
		}
	}
	
	if(empty($links)){
		return [];
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
			
			$content = curl_multi_getcontent($handle);
			if(!empty($content)){
				$news = json_decode(str_replace(['\u00a0', '&nbsp;'], '', $content), true);
				if($news !== null && isset($news['contentDetails'][0])){
					$category = newsCategory($key);
					$id = $news['contentDetails'][0]['ContentID'] ?? newsID($key); 
					$uri = $news['contentDetails'][0]['URLAlies'] ?? ''; 
					$time = $news['contentDetails'][0]['create_date'] ?? ''; 
					$title = $news['contentDetails'][0]['ContentHeading'] ?? ''; 
					$subtitle = $news['contentDetails'][0]['ContentBrief'] ?? ''; 
					$body = $news['contentDetails'][0]['ContentDetails'] ?? ''; 
					$tags = $news['contentDetails'][0]['Keywords'] ?? ''; 
					$img = isset($news['contentDetails'][0]['ImageBgPath']) ? 'https://backoffice.channel24bd.tv/media/imgAll/'.$news['contentDetails'][0]['ImageBgPath'] : ''; 
					$reporter = $news['writerInfo']['WriterName'] ?? $news['contentDetails'][0]['WriterName'] ?? ''; 
					
					if($uri && $time && $title && $body){
						$output[] = [
							'id' => intval($id),
							'category' => $category,
							'reporter' => trim($reporter),
							'time' => date("l, j F Y, h:i:s A", strtotime($time)),
							'url' => 'https://www.channel24bd.tv/'.$category.'/article/'.$id.'/'.urlencode($uri),
							'image' => $img,
							'title' => trim($title),
							'subtitle' => trim($subtitle),
							'body' => plainBody($body),
							'tags' => trim($tags)
						];	
					}
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

$update = $_GET['update'] ?? $_POST['update'] ?? 0;
header("Content-Type: application/json; charset=utf-8");
echo json_encode(recentNews(intval($update)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);