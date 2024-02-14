<?php

namespace OCA\FakeAppstore\AppInfo;

use OC\Installer as OriginalInstaller;
use OCA\FakeAppstore\Service\Installer;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use Psr\Container\ContainerInterface;

require_once __DIR__ . '/../../vendor/autoload.php';

class Application extends App implements IBootstrap {
	public const ID = 'fake_appstore';

	public function __construct() {
		parent::__construct(self::ID);
	}

	/**
	 * @inheritDoc
	 */
	public function register(IRegistrationContext $context): void {
		\OC::$server->registerService(OriginalInstaller::class, function (ContainerInterface $c) {
			return $c->get(Installer::class);
		});
	}

	/**
	 * @inheritDoc
	 */
	public function boot(IBootContext $context): void {

	}
}
