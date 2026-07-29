#!/bin/bash
set -e

# Use PORT env var (Fly.io sets this) or default to 8080
PORT="${PORT:-8080}"

# Update Apache to listen on the assigned port
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
