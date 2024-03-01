## Pantheon TMC Migration scripts [WORK IN PROGRESS]

All scripts assume you're running from the base install directory for wordpress (i.e. the directory that contains index.php, wp-contents, wp-admin, etc).

### Available scripts:

  * `scan-wp-contents` - Scans the wp-contents/plugins and wp-contents/themes for problematic installed plugins/themes as noted in https://docs.pantheon.io/plugins-known-issues
  * `wordfence-local-setup` - Run commands required to set up WordFence in local git repository (e.g. set up synlinks, etc.)
  * `wordfence-dev-setup` - [WIP] Configure dev environment to support WordFence private files.
