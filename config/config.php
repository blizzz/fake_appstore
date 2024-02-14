<?php

return [
	'tables' => [
		// version to be returned by isUpdateAvailable()
		'version' => '0.7.0.beta.1',
		// git reference to be checked out during updateAppstoreApp()
		'git-ref' => 'v0.7.0-beta.1',
		// migrations entries to be deleted from oc_migrations during updateAppstoreApp()
		'dropMigrations' => [
			'000700Date20230916000000',
			'000700Date20240213123743',
		],
		// if eventually the previous git checked should be restored again
		'rollback' => false,
	]
];
