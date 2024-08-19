#!/bin/bash

# Usage: ./ucf-sso.sh [site1 site2 site3 ...]

ALL_SITES=(
	# ucf-cah
	# ucf-creol
	# ucf-cugs
	ucf-ucfit
	# ucf-sdes
	# ucf-bs
	# ucf-dtl
	# ucf-grit
	# ucf-osi
	# ucf-rchm
	# ucf-ucn
)

SITES=("${@:-${ALL_SITES[@]}}")

function notice {
	echo -e "\033[1;32m$1\033[0m"
}

function error {
	echo -e "\033[1;31m$1\033[0m"
}

for SITE in "${SITES[@]}"; do

	# wait if all 8 workers are busy
	if test "$(jobs | wc -l)" -ge 8; then
		notice "Waiting for a worker to free up..."
		wait
	fi

	# create a worker sub-process
	{
		notice "Setting up SSO on $SITE..."

		SITE_DIR=~/Migrations/ucf/"$SITE"

		# Clone the repo if not already cloned
		if [ ! -d "$SITE_DIR" ]; then
			notice "Cloning $SITE locally..."
			eval "$(terminus connection:info "$SITE.dev" --field=git_command)"
		fi

		cd "$SITE_DIR" || exit 1

		# URL of the file on the web
		url="https://gist.githubusercontent.com/ryanshoover/5f04d0ee87bae738429b7bfdf942fd0b/raw/70e299555553bc1bcbb910af19109167b1af5069/update_sso_settings.php"

		# Local file to compare
		filename="private/scripts/update_sso_settings.php"

		# Fetch the web content
		web_content=$(curl -s "$url")

		# Read the local file content
		local_content=$(cat "$filename")

		# Compare the two contents
		if [ "$web_content" != "$local_content" ]; then
			notice "Deploying updated SSO settings script to $SITE..."

			if [[ -n $(terminus env:diffstat "$SITE.dev" --format=string) ]]; then
				error "The $SITE.dev environment has uncommitted changes. Skipping..."
				exit 1
			fi

			# Put dev into git mode
			terminus connection:set "$SITE.dev" git

			# Pull latest changes
			git pull

			# Replace update_sso_settings.php with new gist content
			curl "$url" >"$filename"

			git commit -a -m 'Replace update_sso_settings.php with full settings script'
			git push

			notice "Deploying $SITE test..."
			terminus env:deploy "$SITE.test" --note="Update onelogin options script"

			notice "Deploying $SITE test..."
			terminus env:deploy "$SITE.live" --note="Update onelogin options script"
		fi

		for ENV in dev test live; do
			notice "Running options import on $SITE.$ENV..."
			terminus wp "$SITE.$ENV" -- plugin activate onelogin-saml-sso UCF-Onelogin-Extension --network
			# Run the update settings script in the background

			# SSO settings update command
			command=(terminus wp "$SITE.$ENV" -- eval-file private/scripts/update_sso_settings.php)

			# Run the command and retry if it fails
			if ! "${command[@]}"; then
				"${command[@]}"
			fi

			notice "SSO settings updated on $SITE.$ENV"
		done
	} &
done
wait