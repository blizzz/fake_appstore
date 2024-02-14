# Very brief intro

This app replaces the internall Installer so app updates can be executed for testing more easily on the dev setup, and via git.

Check the config/config.php for how to configure the available apps.

On the configured app directories, the web user needs to have write access. Linux ACLs are a nice thing to have such permissions for both the regular user account as well as the user the webserver is running as.

In a nutshell:

- the app with the specified version is announced as configured
- upon updating, the app is loaded initially (to provoke autoloading issues on web, at least that was the idea), the installed version is adjusted if necessary, and the target git reference is being checked out. The repo is expected to be clean. 
- Before running the upgrade procedure specified migrations are being removed from the stored list, so they may run again. If database changes are necessary, they have to be done manually so far.

Setup:

- clone this app into an app dir and run `composer install`
- enable this app: `occ app:enable fake_appstore`

Example procedure:

- adjust fake_appstore/config/config.php to your needs
- manually check out the version of the targeted app to be updated from
- if necessary, modify database bits
- run update via occ or web interface
