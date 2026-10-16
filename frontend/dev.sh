#!/usr/bin/env bash
cd "$(dirname "$0")"
exec ./node_modules/.bin/vite --host 127.0.0.1 --port 5175
