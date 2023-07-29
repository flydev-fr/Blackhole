<?php namespace ProcessWire;

$info = array(
	'title' => 'Blackhole',
	'summary' => 'Trap bad bots, crawlers and spiders in a virtual black hole.',
	'version' => json_decode(file_get_contents(__DIR__ . "/package.json"))->version,
	'author' => 'flydev',
	'icon' => 'grav',
	'href' => 'https://github.com/flydev-fr/Blackhole',
);