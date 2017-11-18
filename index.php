<?php

require_once( __DIR__.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'autoload.php');

use Phediverse\MastodonRest\Auth\AuthClient as AuthClient;
use Phediverse\MastodonRest\Auth\AppRegisterClient as AppRegisterClient;
use Phediverse\MastodonRest\Auth\Scope as Scope;
use Phediverse\MastodonRest\Resource\Application as Application;
use Phediverse\MastodonRest\Client as Client;

$profileName = $argc > 1 ? $argv[1] : die('No configuration specified');

const APP_NAME = 'PhpRssToBotStateless';


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
			APP_NAME, 
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


function createToot($rssItemArray){
	return
		implode(
				"\n\n",
				[
					$rssItemArray['title'],
					substr(
						$rssItemArray['description'], 
						0, 
						500 - strlen($rssItemArray['title']) + 30
					), //I believe links are 20
					$rssItemArray['link']
				]
			);
}

function postToot($tootString, $guzzle, $accessToken){
	//$body = \GuzzleHttp\Psr7\stream_for('hello!');
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
$date = count($statuses) ? new DateTime($statuses[0]['created_at']) : new DateTime();
if(count($statuses)){
	//get all rss items since most recent post
	
	//read xmls
	foreach($config->feeds as $feed){

		$rss = Feed::loadRss($feed);

		foreach ($rss->item as $item) {
			$itemDate = new DateTime($item->timestamp);
			if($itemDate > $date){
				var_dump($item);
			}
			/*
			echo 'Title: ', $item->title;
			echo 'Link: ', $item->link;
			echo 'Timestamp: ', $item->timestamp;
			echo 'Description ', $item->description;
			echo 'HTML encoded content: ', $item->{'content:encoded'};
			//*/
		}
	}
}else{
	//get the most recent post
	//read xmls
	foreach($config->feeds as $feed){

		$rss = Feed::loadRss($feed)->toArray();
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
		$status = createToot($the_item);
		postToot($status, $guzzle, $profileCredentials->accessToken);
	}
}