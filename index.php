<?php

require_once( __DIR__.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');

use Phediverse\MastodonRest\Auth\AuthClient as AuthClient;
use Phediverse\MastodonRest\Auth\AppRegisterClient as AppRegisterClient;
use Phediverse\MastodonRest\Auth\Scope as Scope;
use Phediverse\MastodonRest\Resource\Application as Application;
use Phediverse\MastodonRest\Client as Client;

$profileName = $argc > 1 ? $argv[1] : die('No configuration specified');

$appConfig = 
	json_decode(
		file_get_contents(
			__DIR__.DIRECTORY_SEPARATOR
				.'configurations'.DIRECTORY_SEPARATOR
				."APP.json"
		),
		FALSE
	);


$config = 
	json_decode(
		file_get_contents(
			__DIR__.DIRECTORY_SEPARATOR
				.'configurations'.DIRECTORY_SEPARATOR
				."$profileName.json"
		),
		FALSE
	);

//Get App Credentials
$appCredentialsPath =
	__DIR__.DIRECTORY_SEPARATOR
		.'.credentials'.DIRECTORY_SEPARATOR
		."_APP.json";

if(!file_exists($appCredentialsPath)){
	$registerClient = AppRegisterClient::forInstance($config->instance);
	$app = 
		$registerClient->createApp(
			$appConfig->name, 
			Application::REDIRECT_NONE, 
			[Scope::READ, Scope::WRITE]
		);
	file_put_contents($appCredentialsPath, json_encode($app));
}


//Get User Credentials
$profileCredentialsPath =
	__DIR__.DIRECTORY_SEPARATOR
		.'.credentials'.DIRECTORY_SEPARATOR
		."$profileName.json";

if(!file_exists($profileCredentialsPath)){
	$app = Application::fromJsonConfig(file_get_contents($appCredentialsPath));
	$authClient = AuthClient::forApplication($app);

	$accessToken = $authClient->login($config->email, $config->password);
	
	file_put_contents($profileCredentialsPath, json_encode(['accessToken' => $accessToken]));
}

$profileCredentials = json_decode(file_get_contents($profileCredentialsPath), FALSE);

$client = Client::build($config->instance, $profileCredentials->accessToken);
$account = $client->getAccount();

//Extend Libraries to get statuses

//Get most recent status
$guzzle = 
	new \GuzzleHttp\Client([
		'base_uri' => 'https://' . $config->instance . '/api/v1/',
	]);
$response = 
	$guzzle->request(
		'GET',
		'accounts/' . $account->getId() . '/statuses?limit=1',
		[
			'headers' => [ 
				'Authorization' => 'Bearer ' . $profileCredentials->accessToken 
			]
		]
	);


function createToot($rssItemArray, $hashtags = []){
	$formattedHashtags = 
		implode(
			' ',
			array_map(
				function($var){return '#'.$var;},
				$hashtags
			)
		);
	
	$offset = strlen($rssItemArray['title']) + strlen($formattedHashtags) + 30;//I believe links are 20 chars
	$descriptionStriped = html_entity_decode(strip_tags($rssItemArray['description']));
	$description = 
		strlen($descriptionStriped) > 500 - $offset
			? substr($descriptionStriped, 0, 500 - $offset - 3).'...'
			: $descriptionStriped;
	return
		implode(
				"\n\n",
				[
					$rssItemArray['title'],
					$description,
					$formattedHashtags,
					$rssItemArray['link']
				]
			);
}

function postToot($tootString, $guzzle, $accessToken){
	$guzzle->request(
		'POST',
		'statuses',
		[
			'headers' => [ 
				'Authorization' => 'Bearer ' . $accessToken 
			],
			'form_params' => [
				'status' => $tootString,
                'visibility' => 'public',
			]
		]
	);
}

$statuses = json_decode($response->getBody()->getContents(), true);
//Get most recent post date
if(count($statuses)){
	//get all rss items since most recent post
	$date = new DateTime($statuses[0]['created_at']);
	//read xmls
	foreach($config->feeds as $feed){
		$loader = 'load'.ucfirst(strtolower($feed->type));
		$rss = Feed::$loader($feed->url)->toArray();
		foreach ($rss['item'] as $item) {
			$itemDate = new DateTime();
			$itemDate->setTimestamp((int)$item['timestamp']);
			if($itemDate > $date){
				$status = createToot($item, array_merge($config->hashtags, $feed->hashtags));
				postToot($status, $guzzle, $profileCredentials->accessToken);
			}
		}
	}
}else{
	//get the most recent post
	//read xmls
	foreach($config->feeds as $feed){
		$loader = 'load'.ucfirst(strtolower($feed->type));
		$rss = Feed::$loader($feed->url)->toArray();
		$the_item = reset($rss['item']);
		foreach ($rss['item'] as $item) {
			$itemDate = new DateTime();
			$itemDate->setTimestamp((int)$item['timestamp']);
			
			$the_itemDate = new DateTime();
			$itemDate->setTimestamp((int)$the_item['timestamp']);
			
			if($itemDate > $the_itemDate){
				$the_item = $item;
			}
		}
		$status = createToot($the_item, array_merge($config->hashtags, $feed->hashtags));
		postToot($status, $guzzle, $profileCredentials->accessToken);
	}
}