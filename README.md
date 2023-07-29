![version](https://img.shields.io/github/v/release/flydev-fr/blackhole)
![last commit](https://img.shields.io/github/last-commit/flydev-fr/blackhole/master)
![security workflow](https://github.com/flydev-fr/blackhole/actions/workflows/security.yml/badge.svg?branch=master)

# Introduction

Originaly developped by [Jeff Starr](https://github.com/JeffStarr), [Blackhole](https://perishablepress.com/blackhole-bad-bots/) is a security plugin which trap bad bots, crawlers and spiders in a virtual black hole.

Once the bots (or any virtual user!) visit the black hole page, they are blocked and denied access for your entire site.

This helps to keep nonsense spammers, scrapers, scanners, and other malicious hacking tools away from your site, so you can save precious server resources and bandwith for your good visitors.


# How It Works

You add a rule to your **robots.txt** that instructs bots to stay away. Good bots will obey the rule, but bad bots will ignore it and follow the link... right into the black hole trap. Once trapped, bad bots are blocked and denied access to your entire site.

The main benefits of Blackhole include:

- Stops leeches, scanners, and spammers
- Saves server resources for humans and good bots
- Improves traffic quality and overall site security


Bots have one chance to obey your site’s robots.txt rules. Failure to comply results in immediate banishment.

# Features

- Disable Blackhole for logged in users
- Optionally redirect all logged-in users
- Send alert email message
- Customize email message
- Choose a custom warning message for bad bots
- Show a WHOIS Lookup informations
- Choose a custom blocked message for bad bots
- Choose a custom HTTP Status Code for blocked bots
- Choose which bots are whitelisted or not

# Usage

1. [Install the module](https://modules.processwire.com/install-uninstall/)
2. Create a new page and assign to this page the template "**blackhole**"
3. Create a new template file "**blackhole.php**" and call the module 

 `$modules->get('Blackhole')->blackhole();`

4. Add the rule to your **robots.txt**
5. Call the module from `home.php` template

 `$modules->get('Blackhole')->blackhole();`

Bye bye bad bots!

### Downloads
- [From the modules directory](https://processwire.com/modules/blackhole/)

Enjoy :neckbeard:

<!-- readme: contributors -start -->
<!-- readme: contributors -end -->