<?php declare(strict_types=1);

namespace Lavrenov\ExchangeAPI\Base;

abstract class Singleton
{
	private static $_instances = [];

	public function init(): void
	{

	}

	final private function __construct()
	{
	}

	final private function __clone()
	{
	}

	final private function __wakeup()
	{
	}

	final public static function getInstance()
	{
		self::$_instances[static::class] = self::$_instances[static::class] ?? new static();
		self::$_instances[static::class]->init();

		return self::$_instances[static::class];
	}
}