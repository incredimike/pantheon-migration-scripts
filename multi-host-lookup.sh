#!/bin/bash

# List of strings
strings=(
live-amcorp-main.pantheonsite.io
live-amcorp-at.pantheonsite.io
live-amcorp-ch.pantheonsite.io
live-amcorp-es.pantheonsite.io
live-amcorp-eu.pantheonsite.io
live-amcorp-fi.pantheonsite.io
live-amcorp-fr.pantheonsite.io
live-amcorp-it.pantheonsite.io
live-amcorp-nl.pantheonsite.io
live-amcorp-no.pantheonsite.io
live-amcorp-pt.pantheonsite.io
live-amcorp-se.pantheonsite.io
live-amcorp-uk.pantheonsite.io
)

# Loop through each string
for DOMAIN in "${strings[@]}"; do
    # Inject the string into a command

    IP=`dig $DOMAIN A +short +time=5`
    echo $DOMAIN $IP
    sleep 1
done



