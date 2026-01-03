<?php
include 'helper.php';
date_default_timezone_set('Asia/Dhaka');

function newsID($link){
	$parts = explode('/', $link);
	return intval(end($parts));
}

function recentNewsLinks(){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://www.atnnewstv.com/assets/news/LastTop20.json?_='.time());
	curl_setopt($ch, CURLOPT_POST, true);
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
	if($json === null || !isset($json['posts'])){
		return false;
	}
	
	$links = [];
	foreach($json['posts'] as $data){
		$id = $data['post_id'] ?? false;
		
		if($id){
			$links[] = 'https://www.atnnewstv.com/details/'.$id;
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
				$html = str_get_html($content);
				$title = $html->find('.main-title', 0);
				$body = $html->find('.text-justify', 0);
				$category = $html->find('#breadcrumb .active', 0);
				$img = $html->find('[property="og:image"]', 0);
				if(preg_match('/"datePublished": "(.*?)"/', $content, $time) && $title && $body && $category && $img){
					foreach($body->find('div, script, legend') as $el){
						$el->remove();
					}
					
					$news = ['id' => newsID($key), 'url' => $key];
					$news['category'] = trim(html_entity_decode(trim($category->plaintext)));
					$news['reporter'] = '';
					$news['time'] = date("l, j F Y, h:i:s A", strtotime($time[1]));
					$news['image'] = $img->getAttribute('content');
					$news['title'] = trim(html_entity_decode(trim($title->plaintext)));
					$news['body'] = trim(str_replace("\r\n", "\n", html_entity_decode(trim($body->plaintext))));
					$bodyParts = explode('রিপোর্ট : ', $news['body']);
					
					if(isset($bodyParts[1]) && !empty($bodyParts[1])){
						$news['body'] = trim($bodyParts[0]);
						$news['reporter'] = trim($bodyParts[1]);
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

$update = $_GET['update'] ?? $_POST['update'] ?? 0;
header("Content-Type: application/json; charset=utf-8");
echo json_encode(recentNews(intval($update)), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);