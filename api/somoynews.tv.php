<?php
include 'helper.php';
date_default_timezone_set('Asia/Dhaka');

function plainBody($news){
	$html = str_get_html($news);

	foreach($html->find('p') as $p){
		$text = trim($p->plaintext);
		if(mb_strpos($text, 'আরও পড়ুন') !== false || mb_strpos($text, '&nbsp;') !== false){
			$p->remove();
		}
	}

	return str_replace("\r\n", "", html_entity_decode(trim($html->plaintext)));
}

function plainTags($tags){
	if(empty($tags)){
		return '';
	}
	
	$tagNames = array_column($tags, 'name');
	return implode(', ', $tagNames);
}

function recentNews($i = 0){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, 'https://www.somoynews.tv/api/graphql');
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	curl_setopt($ch, CURLOPT_POSTFIELDS, '{"operationName": "getMostReadNews","variables": {"page": 1},"query": "query getMostReadNews($page: Int){news(count: 20, page: $page){total news {id slug title subtitle sideHead coverImage publishedAt readingDuration videoLink category {name} __typename description tags {name}} __typename}}"}');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
		'content-type:application/json',
		'user-agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36'
	]);
	$content = curl_exec($ch);
	$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	
	$res = ['success' => false];
	if($status !== 200 || empty($content)){
		$res['message'] = 'Failed to fetch latest news.';
		return $res;
	}
	
	$newses = json_decode($content, true);
	if($newses === null || !isset($newses['data']['news']['news'])){
		$res['message'] = 'Failed to fetch latest news data.';
		return $res;
	}
	
	$latest = [];
	foreach($newses['data']['news']['news'] as $news){
		if(isset($news['publishedAt'], $news['title'], $news['coverImage'], $news['description'])){
			$timestamp = substr($news['publishedAt'], 0, 10);
			if($timestamp <= $i){
				continue;
			}
			$latest[] = [
				'id' => intval($timestamp),
				'uid' => $news['id'] ?? '',
				'category' => $news['category']['name'] ?? '',
				'time' => date("l, F j, Y, h:i:s A", $timestamp),
				'url' => 'https://www.somoynews.tv/news/'.date('Y-m-d', $timestamp).'/'.($news['slug'] ?? ''),
				'image' => $news['coverImage'],
				'title' => $news['title'],
				'subtitle' => $news['subtitle'] ?? '',
				'body' => plainBody($news['description']),
				'tags' => plainTags($news['tags'] ?? [])
			];
		}
	}
	
	if(empty($latest)){
		$res['messsage'] = 'No latest news found.';
		return $res;
	}
	
	return ['success' => true, 'messsage' => 'Latest news successfully fetched.', 'news' => array_reverse($latest)];
}

header("Content-Type: application/json; charset=utf-8");
$update = $_GET['update'] ?? $_POST['update'] ?? 0;
echo json_encode(recentNews(intval($update)));