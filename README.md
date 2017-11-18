# PHP Feed-to-Bot Tool for Mastodon

A tool for creating bots for Mastodon which read RSS/Atom feeds.  

Features:
- Multiple bots from one script
- Multiple feeds per bot
- Supports (valid) RSS and Atom
- Stateless: in the sense that it does not maintain a cache of which RSS posts have been converted to toots.

## Setup

You will need a [cert file](https://stackoverflow.com/a/21114601/2329474) to run Guzzle.

### Load Dependencies
Dependencies are loaded via [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)

    composer install

It uses the following dependencies:
- [phediverse/mastodon-rest](https://github.com/phediverse/mastodon-rest)
- [dg/rss-php](https://github.com/dg/rss-php)
- [guzzlehttp/guzzle](http://docs.guzzlephp.org/en/stable/#)

### Application

This code takes care of running the 3rd party application which handles the bots.

You will need to copy `/configurations/APP.example.json` to `/configurations/APP.json`.  

In `/configurations/APP.json` change the `name` property to a custom name.

### Bot Configuration

You will first need to manually make the account on the Mastodon instance of your choice with an email, password and username.

The file `/configurations/example.json` is an example of a bot configuration.  Create a new [JSON](https://www.json.org/) in the `/configurations/` folder and name it for your bot.

```javascript
{
	"instance" : "social.targaryen.house", //like 'mastodon.social'

	"username" : "my_username",            //username for your bot's account, not display name
                                             //not used at this point

	"email" : "myemail+botname@mail.com",  //email for your bot's login

	"password" : "realpassword",           //password for your bot's login

	"hashtags" : ["all"],                  //array of strings that you want as 
                                             //hashtags (#) in each toot
                                             //do not add '#'

	"feeds" : [{                           //array of feeds for this bot

		"type" : "rss",                    //type of feed, rss or atom

		"url" : "http://www.website.com/rss/one.xml",
                                           //url to feed

		"hashtags" : ["cats"]              //hashtags for every toot from this feed
	},{
		"type" : "rss",
		"url" : "http://www.website.com/rss/two.xml",
		"hashtags" : ["music"]
	},{
		"type" : "atom",
		"url" : "http://www.website.com/atom/three.xml",
		"hashtags" : ["mood"]
	}]
}
```

Create a separate configuration file for each bot you wish to run.

### Use

#### Updates

If your configuration file is called my_rss.json, then the RSS items can be posted/updated with:

    php /path/to/php-mastodon-rss-to-bot-stateless/index.php my_rss

Please note the lack of '.json' in the parameter.

To keep the application 'stateless' (see top), the tool works based off of timestamps.  The tool will check the timestamp of the latest toot and get all Feed items after that timestamp and posts them.

Obviously, this has the potential to not be as accurate as a bot with a cache, but I'm too lazy to build one.  If your feed has items added every minute (hopefully unlikely), then this tool may miss an item or two along the way.

If the bot is new and has no posts, it will only post the most recent feed item, not the entire feed.

#### Scheduling

In order to have a bot that updates regularly, you will need to schedule the above PHP call with a [Cron](https://en.wikipedia.org/wiki/Cron) task or a [Scheduled Task](https://en.wikipedia.org/wiki/Windows_Task_Scheduler) depending on your system.

You will need to schedule separate tasks for each bot/config.

#### Additional

If you need to disable the tool for a while (system maintenance, etc), but you don't want the bot to flood Mastodon with a bunch of belated posts when it starts up again, you can just make a post directly to the bot via the Mastodon interface and then start the scheduled task again.  The new post will make sure the most recent timestamp in the Toot timeline is up to date.

## TODO

- Create config class to validate configurations
- Add utility class and PHPUnit for long term stability
- Get toot length by instance
- Replace strlen with more reliable function
- Extend to any iterable, fetchable, timestamped resource