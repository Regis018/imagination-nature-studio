#!/usr/bin/env bash
# ─────────────────────────────────────────────────────
#  Imagination Nature Studio — Local Dev Server Launcher
# ─────────────────────────────────────────────────────
# Main site  → http://localhost:8080
# Shop       → http://localhost:8081
# Stop both  →  Ctrl+C  (or kill the PIDs shown below)
# ─────────────────────────────────────────────────────

set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
PHP=/opt/homebrew/bin/php

echo ""
echo "╔══════════════════════════════════════════════════╗"
echo "║   Imagination Nature Studio — Dev Servers         ║"
echo "╠══════════════════════════════════════════════════╣"
echo "║  Main site  →  http://localhost:8080              ║"
echo "║  Shop       →  http://localhost:8081              ║"
echo "║  Press Ctrl+C to stop both servers                ║"
echo "╚══════════════════════════════════════════════════╝"
echo ""

# Start main site server
"$PHP" -S localhost:8080 \
    -t "$DIR/imaginationnaturestudio.com/public_html" \
    "$DIR/imaginationnaturestudio.com/router.php" \
    > /tmp/dev-main.log 2>&1 &
MAIN_PID=$!
echo "✓ Main site started  (PID $MAIN_PID)  → http://localhost:8080"

# Start shop server
"$PHP" -S localhost:8081 \
    -t "$DIR/shop.imaginationnaturestudio.com/public_html" \
    "$DIR/shop.imaginationnaturestudio.com/router.php" \
    > /tmp/dev-shop.log 2>&1 &
SHOP_PID=$!
echo "✓ Shop started       (PID $SHOP_PID)  → http://localhost:8081"
echo ""
echo "Logs: /tmp/dev-main.log  |  /tmp/dev-shop.log"
echo ""

# Clean up on Ctrl+C
trap 'echo ""; echo "Stopping servers…"; kill $MAIN_PID $SHOP_PID 2>/dev/null; echo "Done."; exit 0' INT TERM

# Wait for both
wait $MAIN_PID $SHOP_PID
