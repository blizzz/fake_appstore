<?php

namespace OCA\FakeAppstore\Service;

use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use CzProject\GitPhp\GitRepository;
use OC\App\AppStore\Bundles\Bundle;
use OC\App\AppStore\Fetcher\AppFetcher;
use OCA\FakeAppstore\AppInfo\Application;
use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\DB\Exception;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\ITempManager;
use OCP\Migration\IOutput;
use Psr\Log\LoggerInterface;

class Installer extends \OC\Installer {
	protected array $storeConfig;

	protected array $rollback;

	public function __construct(
		protected IAppManager $appManager,
		protected Git $git,
		protected IDBConnection $db,
		AppFetcher $appFetcher,
		IClientService $clientService,
		ITempManager $tempManager,
		protected LoggerInterface $logger,
		protected IConfig $config,
		bool $isCLI,
	) {
		parent::__construct($appFetcher, $clientService, $tempManager, $logger, $config, $isCLI);
	}

	public function __destruct() {
		if (!isset($this->rollback)) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->delete('migrations')
			->where($qb->expr()->eq('app', $qb->createParameter('appId')))
			->andWhere($qb->expr()->eq('version', $qb->createParameter('version')));
		foreach ($this->rollback as $appId => $app) {
			try {
				foreach ($app['forgetMigrations'] as $migration) {
					$qb->setParameters([
						'appId' => $appId,
						'version' => $migration,
					]);
					$qb->executeStatement();
				}
				$app['repo']->checkout($app['ref']);
			} catch (GitException $e) {
				$this->logger->debug('Could not rollback {appId} to {ref}', [
					'app' => Application::ID,
					'appId' => $appId,
					'ref' => $app['ref'],
					'exception' => $e,
				]);
			}
		}
	}

	protected function getStoreConfig(): array {
		if (!isset($this->storeConfig)) {
			$this->storeConfig = require_once __DIR__ . '/../../config/config.php';
		}
		return $this->storeConfig;
	}

	protected function getCurrentRef(GitRepository $repo): string {
		$currentRef = $repo->getCurrentBranchName();
		if (str_starts_with($currentRef, '(HEAD detached at ')) {
			$currentRef = \substr($currentRef, strlen('(HEAD detached at '), -1);
		}
		return $currentRef;
	}

	/**
	 * @throws Exception
	 */
	protected function dropMigrations(string $appId, array $migrations): void {
		static $qb;

		if (!isset($qb)) {
			$qb = $this->db->getQueryBuilder();
			$qb->delete('migrations')
				->where($qb->expr()->eq('app', $qb->createParameter('appId')))
				->andWhere($qb->expr()->eq('version', $qb->createParameter('version')));
		}

		foreach ($migrations as $migration) {
			$qb->setParameters([
				'appId' => $appId,
				'version' => $migration,
			]);
			$qb->executeStatement();
		}
	}

	protected function hackUpdateFromVersionIfNecessary(string $appId, string $oldVersion, string $targetVersion): void {
		if (version_compare($oldVersion, $targetVersion, '<')) {
			return;
		}
		$interimVersionBits = array_reverse(explode('.', $targetVersion));
		foreach ($interimVersionBits as $i => $part) {
			if (is_numeric($part) && $part !== '0') {
				$interimVersionBits[$i] = intval($part) - 1;
				break;
			}
		}
		$interimVersion = implode('.', array_reverse($interimVersionBits));
		$this->config->setAppValue($appId, 'installed_version', $interimVersion);
		$this->appManager->getAppVersion($appId, false);
	}

	public function installApp(string $appId, bool $forceEnable = false): string {
		throw new \Exception('Not implemented');
	}

	/**
	 * @throws AppPathNotFoundException
	 * @throws GitException
	 * @throws \Exception
	 */
	public function updateAppstoreApp(string $appId, bool $allowUnstable = false): bool {
		if ($this->isUpdateAvailable($appId)) {
			try {
				$this->appManager->loadApp($appId);	// force error case with old classes
				$this->downloadApp($appId, $allowUnstable);
			} catch (\Throwable) {
				return false;
			}
			\OC_App::updateApp($appId);
			return true;
		}

		return false;
	}

	public function downloadApp(string $appId, bool $allowUnstable = false): void {
		$oldVersion = $this->appManager->getAppVersion($appId);
		$this->hackUpdateFromVersionIfNecessary($appId, $oldVersion, $this->getStoreConfig()[$appId]['version']);

		$appPath = $this->appManager->getAppPath($appId);
		$repo = $this->git->open($appPath);
		$currentRef = $this->getCurrentRef($repo);
		$repo->checkout($this->storeConfig[$appId]['git-ref']);
		if ($this->storeConfig[$appId]['rollback'] ?? false) {
			$this->rollback[$appId] = [
				'repo' => $repo,
				'ref' => $currentRef,
			];
		}

		if ($this->storeConfig[$appId]['dropMigrations'] ?? false) {
			$this->dropMigrations($appId, $this->storeConfig[$appId]['dropMigrations']);
		}


	}

	public function isUpdateAvailable($appId, $allowUnstable = false): string|false {
		// always offer an update when config is set
		return $this->getStoreConfig()[$appId]['version'] ?? false;
	}

	public function isDownloaded(string $name): bool {
		return parent::isDownloaded($name);
	}

	public function removeApp(string $appId): bool {
		throw new \Exception('Not implemented');
	}

	public function installAppBundle(Bundle $bundle): void {
		throw new \Exception('Not implemented');
	}

	public static function installShippedApps(bool $softErrors = false, ?IOutput $output = null): array {
		throw new \Exception('Not implemented');
	}

	public static function installShippedApp(string $app, ?IOutput $output = null): string|false {
		throw new \Exception('Not implemented');
	}
}
