#!/bin/bash

# List of strings
strings=(
#"amcorp-at"
#"amcorp-ch"
#"amcorp-es"
#"amcorp-eu"
#"amcorp-fi"
#"amcorp-fr"
#"amcorp-it"
#"amcorp-nl"
#"amcorp-no"
#"amcorp-pt"
#"amcorp-se"
#"amcorp-uk"
)

# Loop through each string
for str in "${strings[@]}"; do
    # Inject the string into a command
    echo "Processing: $str"

    terminus remote:drush $str.live user:create pantheonps -- --mail="mike.walker@pantheon.io" --password="psmigrations"
    terminus remote:drush $str.live user:role:add "administrator" pantheonps
    sleep 1
done



