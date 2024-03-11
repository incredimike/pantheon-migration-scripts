## Pantheon TMC Migration scripts [WORK IN PROGRESS]

All scripts assume you're running from the base install directory for wordpress (i.e. the directory that contains index.php, wp-contents, wp-admin, etc).

### Available scripts:

  * `check-wp-config` - [WIP] Checks the wp-config.php file passed into the script to output important settings to be migrated (e.g. table_prefix, etc)
  * `lookup-canonical-domains` - Scans a list of domains to see if they resolve, redirect the user or are the canonical domain for the website.
  * `scan-dir-file-count` - Scans a directory & sub-directories to check for files over 250MB or directories that contain over 10,000 files.
  * `scan-plugins-fs-write` - [WIP] Scans a directory for PHP files which contain write functions (e.g. fwritre, file_put_contents, etc). Useful for finding plugins which might try to write to locations outside the uploads directory.
  * `scan-wp-contents` - Scans the wp-contents/plugins and wp-contents/themes for problematic installed plugins/themes as noted in https://docs.pantheon.io/plugins-known-issues
  * `wordfence-local-setup` - Run commands required to set up WordFence in local git repository (e.g. set up synlinks, etc.)
  * `wordfence-dev-setup` - [WIP] Configure dev environment to support WordFence private files.
