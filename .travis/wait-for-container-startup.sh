#!/usr/bin/env bash

echo "Waiting for app container to start up..."
while [ ! -f ./.ready ]
do
  sleep 2
done
echo "App container is ready."
